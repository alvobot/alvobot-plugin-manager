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
        // Carrega a classe base dos módulos
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-module-base.php';
        
        // Carrega os módulos
        $module_files = array(
            'logo_generator' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/class-logo-generator.php',
            'author_box' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/class-author-box.php',
            'plugin-manager' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/plugin-manager/class-plugin-manager.php',
            'pre-article' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pre-article/pre-article.php',
            'essential_pages' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/essential-pages/class-essential-pages.php',
            'multi-languages' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/multi-languages/class-multi-languages.php',
            'temporary-login' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/temporary-login/class-temporary-login.php'
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Alvobot Pro: Iniciando init_modules');
        }

        // Carrega módulos ativos das opções com valores padrão
        $default_modules = array(
            'logo_generator' => true,
            'author_box' => true,
            'plugin-manager' => true,
            'pre-article' => true,
            'essential_pages' => true,
            'multi-languages' => true,
            'temporary-login' => true
        );

        // Obtém os módulos ativos do banco de dados
        $saved_modules = get_option('alvobot_pro_active_modules');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Alvobot Pro: Módulos salvos: ' . json_encode($saved_modules));
        }
        
        // Se não existir no banco, cria com os valores padrão
        if (false === $saved_modules) {
            update_option('alvobot_pro_active_modules', $default_modules);
            $this->active_modules = $default_modules;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Criando módulos padrão');
            }
        } else {
            // Mescla os módulos salvos com os padrões para garantir que todos os módulos existam
            $this->active_modules = wp_parse_args($saved_modules, $default_modules);
            // Garante que o plugin_manager esteja sempre ativo
            $this->active_modules['plugin-manager'] = true;
            // Atualiza a opção no banco de dados
            update_option('alvobot_pro_active_modules', $this->active_modules);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Módulos ativos atualizados: ' . json_encode($this->active_modules));
            }
        }

        // Mapeia os módulos para suas classes
        $module_classes = array(
            'logo_generator' => 'AlvoBotPro_LogoGenerator',
            'author_box' => 'AlvoBotPro_AuthorBox',
            'plugin-manager' => 'AlvoBotPro_PluginManager',
            'pre-article' => 'Alvobot_Pre_Article',
            'essential_pages' => 'AlvoBotPro_EssentialPages',
            'multi-languages' => 'AlvoBotPro_MultiLanguages',
            'temporary-login' => 'AlvoBotPro_TemporaryLogin'
        );

        // Instancia apenas os módulos ativos
        foreach ($module_classes as $module_id => $class_name) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Alvobot Pro: Verificando módulo {$module_id} ({$class_name})");
            }
            if (
                isset($this->active_modules[$module_id]) && 
                $this->active_modules[$module_id] && 
                class_exists($class_name)
            ) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Alvobot Pro: Instanciando módulo {$module_id}");
                }
                $this->modules[$module_id] = new $class_name();
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Alvobot Pro: Módulo {$module_id} não ativo ou classe não existe");
                    if (!class_exists($class_name)) {
                        error_log("Alvobot Pro: Classe {$class_name} não existe");
                    }
                }
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Alvobot Pro: Iniciando add_admin_menu');
        }

        // Adiciona menu principal
        add_menu_page(
            'Configurações - AlvoBot Pro',
            'Configurações',
            'manage_options',
            'alvobot-pro',
            array($this, 'render_dashboard_page'),
            ALVOBOT_PRO_PLUGIN_URL . 'assets/images/icon-alvobot-app.svg',
            2
        );

        // 1. Criador de Logos - Ferramenta principal e visual
        if (isset($this->modules['logo_generator'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Logo Generator');
            }
            add_submenu_page(
                'alvobot-pro',
                'Criador de Logos',
                'Criador de Logos',
                'manage_options',
                'alvobot-pro-logo',
                array($this->modules['logo_generator'], 'render_settings_page')
            );
        }

        // 2. Caixa de Autor - Funcionalidade de conteúdo
        if (isset($this->modules['author_box'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Author Box');
            }
            add_submenu_page(
                'alvobot-pro',
                'Caixa de Autor',
                'Caixa de Autor',
                'manage_options',
                'alvobot-pro-author-box',
                array($this->modules['author_box'], 'render_settings_page')
            );
        }

        // 3. Pré-Artigos - Funcionalidade de conteúdo
        if (isset($this->modules['pre-article'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Pre Article');
            }
            add_submenu_page(
                'alvobot-pro',
                'Páginas de Pré-Artigo',
                'Pré-Artigos',
                'manage_options',
                'alvobot-pro-pre-article',
                array($this->modules['pre-article'], 'render_settings_page')
            );
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Módulo Pre Article não está ativo ou não existe');
                error_log('Alvobot Pro: Módulos ativos: ' . json_encode($this->modules));
            }
        }

        // 4. Páginas Essenciais - Configuração básica do site
        if (isset($this->modules['essential_pages'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Essential Pages');
            }
            add_submenu_page(
                'alvobot-pro',
                'Páginas Essenciais',
                'Páginas Essenciais',
                'manage_options',
                'alvobot-pro-essential-pages',
                array($this->modules['essential_pages'], 'render_settings_page')
            );
        }

        // 5. Multilíngue - Funcionalidade avançada
        if (isset($this->modules['multi-languages'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Multi Languages');
            }
            add_submenu_page(
                'alvobot-pro',
                'Gerenciamento Multilíngue',
                'Multilíngue',
                'manage_options',
                'alvobot-pro-multi-languages',
                array($this->modules['multi-languages'], 'render_settings_page')
            );
        }

        // 6. Login Temporário - Ferramenta de suporte/segurança
        if (isset($this->modules['temporary-login'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alvobot Pro: Adicionando submenu Temporary Login');
            }
            add_submenu_page(
                'alvobot-pro',
                'Login Temporário',
                'Login Temporário',
                'manage_options',
                'alvobot-pro-temporary-login',
                array($this->modules['temporary-login'], 'render_settings_page')
            );
        }

        // 7. Configurações - Por último, configurações gerais (remove submenu duplicado)
        // O menu principal já aponta para render_dashboard_page
    }

    public function render_dashboard_page() {
        // Processa ações do Plugin Manager na página de configurações
        if (isset($_POST['action']) && isset($this->modules['plugin-manager'])) {
            if ($_POST['action'] === 'activate_plugin_manager') {
                check_admin_referer('activate_plugin_manager');
                $this->modules['plugin-manager']->activate();
            } elseif ($_POST['action'] === 'retry_registration') {
                check_admin_referer('retry_registration');
                $alvobot_user = get_user_by('login', 'alvobot');
                if ($alvobot_user) {
                    $app_password = $this->modules['plugin-manager']->generate_alvobot_app_password($alvobot_user);
                    if ($app_password) {
                        $result = $this->modules['plugin-manager']->register_site($app_password);
                        if ($result) {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success"><p>Registro refeito com sucesso!</p></div>';
                            });
                        } else {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-error"><p>Erro ao refazer o registro. Verifique os logs para mais detalhes.</p></div>';
                            });
                        }
                    }
                }
            }
        }

        // Obtém o estado atual dos módulos
        $active_modules = $this->get_active_modules();
        
        // Obtém o usuário e token do AlvoBot
        $alvobot_user = get_user_by('login', 'alvobot');
        $site_token = get_option('grp_site_token');
        
        // Inclui o template
        include ALVOBOT_PRO_PLUGIN_DIR . 'assets/templates/dashboard.php';
    }

    public function enqueue_admin_assets($hook) {
        // Sempre carrega os estilos do menu em todas as páginas do admin
        wp_enqueue_style(
            'alvobot-pro-menu-styles',
            ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            $this->version
        );

        // Verifica se estamos em uma página do plugin para carregar assets específicos
        if (strpos($hook, 'alvobot-pro') !== false) {
            wp_enqueue_script(
                'alvobot-pro-token-visibility',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/js/token-visibility.js',
                array('jquery'),
                $this->version,
                true
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
                'plugin-manager' => true,
                'pre-article' => true,
                'essential_pages' => true,
                'multi-languages' => true,
                'temporary-login' => true
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
