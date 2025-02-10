<?php
/**
 * Classe principal do plugin AlvoBot Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro {
    private $version;
    private $modules = [];
    private $active_modules = [];

    public function __construct() {
        $this->version = ALVOBOT_PRO_VERSION;
        $this->load_dependencies();
        $this->init_modules();
    }

    private function load_dependencies() {
        // Carrega os módulos
        $module_files = array(
            'logo-generator' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/logo-generator.php',
            'author-box' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/class-author-box.php',
            'plugin-manager' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/plugin-manager/class-plugin-manager.php',
            'pre-article' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/alvobot-pre-article/alvobot-pre-article.php'
        );

        foreach ($module_files as $module => $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        // Inicializa o AJAX handler
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-ajax.php';
        new AlvoBotPro_Ajax();
    }

    public function init() {
        // Adiciona menus administrativos
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Adiciona scripts e estilos
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    private function init_modules() {
        // Carrega módulos ativos das opções com valores padrão
        $default_modules = array(
            'logo_generator' => true,
            'author_box' => true,
            'plugin_manager' => true,
            'pre_article' => true
        );

        // Obtém os módulos ativos do banco de dados
        $saved_modules = get_option('alvobot_pro_active_modules');
        
        // Se não existir no banco, cria com os valores padrão
        if (false === $saved_modules) {
            update_option('alvobot_pro_active_modules', $default_modules);
            $this->active_modules = $default_modules;
        } else {
            // Mescla os módulos salvos com os padrões para garantir que todos os módulos existam
            $this->active_modules = wp_parse_args($saved_modules, $default_modules);
            // Garante que o plugin_manager esteja sempre ativo
            $this->active_modules['plugin_manager'] = true;
            // Atualiza a opção no banco de dados
            update_option('alvobot_pro_active_modules', $this->active_modules);
        }

        // Mapeamento de módulos para suas classes
        $module_classes = array(
            'logo_generator' => 'AlvoBotPro_LogoGenerator',
            'author_box' => 'AlvoBotPro_AuthorBox',
            'plugin_manager' => 'AlvoBotPro_PluginManager',
            'pre_article' => 'AlvoBotPro_PreArticle'
        );

        // Instancia apenas os módulos ativos
        foreach ($module_classes as $module_id => $class_name) {
            if (
                isset($this->active_modules[$module_id]) && 
                $this->active_modules[$module_id] && 
                class_exists($class_name)
            ) {
                $this->modules[$module_id] = new $class_name();
            }
        }
    }

    /**
     * Obtém o estado atual dos módulos
     */
    public function get_active_modules() {
        return $this->active_modules;
    }

    public function add_admin_menu() {
        // Adiciona menu principal
        add_menu_page(
            'AlvoBot Pro',
            'AlvoBot Pro',
            'manage_options',
            'alvobot-pro',
            array($this, 'render_dashboard_page'),
            'dashicons-superhero',
            30
        );

        // Adiciona submenus apenas para módulos ativos
        if (isset($this->modules['logo_generator'])) {
            add_submenu_page(
                'alvobot-pro',
                'Gerador de Logo',
                'Gerador de Logo',
                'manage_options',
                'alvobot-pro-logo',
                array($this->modules['logo_generator'], 'render_settings_page')
            );
        }

        if (isset($this->modules['author_box'])) {
            add_submenu_page(
                'alvobot-pro',
                'Author Box',
                'Author Box',
                'manage_options',
                'alvobot-pro-author',
                array($this->modules['author_box'], 'render_settings_page')
            );
        }

        if (isset($this->modules['pre_article'])) {
            add_submenu_page(
                'alvobot-pro',
                'Pre Article',
                'Pre Article',
                'manage_options',
                'alvobot-pro-pre-article',
                array($this->modules['pre_article'], 'render_settings_page')
            );
        }

        if (isset($this->modules['plugin_manager'])) {
            add_submenu_page(
                'alvobot-pro',
                'Plugin Manager',
                'Plugin Manager',
                'manage_options',
                'alvobot-pro-plugins',
                array($this->modules['plugin_manager'], 'render_settings_page')
            );
        }
    }

    public function render_dashboard_page() {
        // Obtém o estado atual dos módulos
        $active_modules = $this->get_active_modules();
        
        // Inclui o template
        include ALVOBOT_PRO_PLUGIN_DIR . 'assets/templates/dashboard.php';
    }

    public function enqueue_admin_assets($hook) {
        // Verifica se estamos em uma página do plugin
        if (strpos($hook, 'alvobot-pro') !== false) {
            wp_enqueue_style(
                'alvobot-pro-admin',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/css/alvobot-pro-admin.css',
                array(),
                $this->version
            );

            wp_enqueue_script(
                'alvobot-pro-admin',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/js/alvobot-pro-admin.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('alvobot-pro-admin', 'alvobotPro', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alvobot_pro_nonce')
            ));
        }
    }

    /**
     * Método chamado quando o plugin é ativado
     */
    public function activate() {
        // Garante que as opções padrão existam
        if (!get_option('alvobot_pro_active_modules')) {
            update_option('alvobot_pro_active_modules', array(
                'logo_generator' => true,
                'author_box' => true,
                'plugin_manager' => true,
                'pre_article' => true
            ));
        }

        // Ativa cada módulo
        foreach ($this->modules as $module) {
            if (method_exists($module, 'activate')) {
                $module->activate();
            }
        }

        // Atualiza a versão do plugin
        update_option('alvobot_pro_version', $this->version);
    }

    /**
     * Método chamado quando o plugin é desativado
     */
    public function deactivate() {
        // Desativa cada módulo
        foreach ($this->modules as $module) {
            if (method_exists($module, 'deactivate')) {
                $module->deactivate();
            }
        }
    }
}
