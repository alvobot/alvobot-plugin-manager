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
            'temporary-login' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/temporary-login/class-temporary-login.php',
            'quiz-builder' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/quiz-builder/class-quiz-builder.php',
            'cta-cards' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/cta-cards/class-cta-cards.php'
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
        self::debug_log('plugin-manager', 'Iniciando init_modules');

        // Carrega módulos ativos das opções com valores padrão
        $default_modules = array(
            'logo_generator' => true,
            'author_box' => true,
            'plugin-manager' => true,
            'pre-article' => true,
            'essential_pages' => true,
            'multi-languages' => true,
            'temporary-login' => true,
            'quiz-builder' => true,
            'cta-cards' => true
        );

        // Obtém os módulos ativos do banco de dados
        $saved_modules = get_option('alvobot_pro_active_modules');
        self::debug_log('plugin-manager', 'Módulos salvos: ' . json_encode($saved_modules));
        
        // Se não existir no banco, cria com os valores padrão
        if (false === $saved_modules) {
            update_option('alvobot_pro_active_modules', $default_modules);
            $this->active_modules = $default_modules;
            self::debug_log('plugin-manager', 'Criando módulos padrão');
        } else {
            // Mescla os módulos salvos com os padrões para garantir que todos os módulos existam
            $this->active_modules = wp_parse_args($saved_modules, $default_modules);
            // Garante que o plugin_manager esteja sempre ativo
            $this->active_modules['plugin-manager'] = true;
            // Atualiza a opção no banco de dados
            update_option('alvobot_pro_active_modules', $this->active_modules);
            self::debug_log('plugin-manager', 'Módulos ativos atualizados: ' . json_encode($this->active_modules));
        }

        // Mapeia os módulos para suas classes
        $module_classes = array(
            'logo_generator' => 'AlvoBotPro_LogoGenerator',
            'author_box' => 'AlvoBotPro_AuthorBox',
            'plugin-manager' => 'AlvoBotPro_PluginManager',
            'pre-article' => 'Alvobot_Pre_Article',
            'essential_pages' => 'AlvoBotPro_EssentialPages',
            'multi-languages' => 'AlvoBotPro_MultiLanguages',
            'temporary-login' => 'AlvoBotPro_TemporaryLogin',
            'quiz-builder' => 'AlvoBotPro_QuizBuilder',
            'cta-cards' => 'AlvoBotPro_CTACards'
        );

        // Instancia apenas os módulos ativos
        foreach ($module_classes as $module_id => $class_name) {
            self::debug_log('plugin-manager', "Verificando módulo {$module_id} ({$class_name})");
            if (
                isset($this->active_modules[$module_id]) && 
                $this->active_modules[$module_id] && 
                class_exists($class_name)
            ) {
                self::debug_log('plugin-manager', "Instanciando módulo {$module_id}");
                if ($class_name === 'AlvoBotPro_MultiLanguages') {
                    $this->modules[$module_id] = AlvoBotPro_MultiLanguages::get_instance();
                } else {
                    $this->modules[$module_id] = new $class_name();
                }
            } else {
                self::debug_log('plugin-manager', "Módulo {$module_id} não ativo ou classe não existe");
                if (!class_exists($class_name)) {
                    self::debug_log('plugin-manager', "Classe {$class_name} não existe");
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

    /**
     * Salva as configurações de debug dos módulos
     */
    private function save_debug_settings() {
        $debug_modules = isset($_POST['debug_modules']) ? $_POST['debug_modules'] : array();
        
        // Converte para formato booleano
        $debug_settings = array();
        $module_ids = array('logo_generator', 'author_box', 'pre-article', 'essential_pages', 'multi-languages', 'temporary-login', 'plugin-manager', 'quiz-builder', 'cta-cards');
        
        foreach ($module_ids as $module_id) {
            $debug_settings[$module_id] = isset($debug_modules[$module_id]) && $debug_modules[$module_id] == '1';
        }
        
        update_option('alvobot_pro_debug_modules', $debug_settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Configurações de debug salvas com sucesso!</p></div>';
        });
    }

    /**
     * Verifica se o debug está habilitado para um módulo específico
     */
    public static function is_debug_enabled($module_id) {
        $debug_settings = get_option('alvobot_pro_debug_modules', array());
        return isset($debug_settings[$module_id]) && $debug_settings[$module_id];
    }

    /**
     * Função auxiliar para log de debug condicional
     */
    public static function debug_log($module_id, $message) {
        // Verifica se WP_DEBUG está ativo
        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        // Verifica se o debug está habilitado para este módulo específico
        if (!self::is_debug_enabled($module_id)) {
            return;
        }
        
        // Verifica se o módulo está ativo (exceto para core/plugin-manager/updater que sempre devem logar)
        if ($module_id !== 'core' && $module_id !== 'plugin-manager' && $module_id !== 'updater') {
            $active_modules = get_option('alvobot_pro_active_modules', array());
            if (!empty($active_modules) && (!isset($active_modules[$module_id]) || !$active_modules[$module_id])) {
                return;
            }
        }
        
        error_log("[AlvoBot Pro - {$module_id}] " . $message);
    }

    public function add_admin_menu() {
        self::debug_log('plugin-manager', 'Iniciando add_admin_menu');

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
            self::debug_log('plugin-manager', 'Adicionando submenu Logo Generator');
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
            self::debug_log('plugin-manager', 'Adicionando submenu Author Box');
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
            self::debug_log('plugin-manager', 'Adicionando submenu Pre Article');
            add_submenu_page(
                'alvobot-pro',
                'Páginas de Pré-Artigo',
                'Pré-Artigos',
                'manage_options',
                'alvobot-pro-pre-article',
                array($this->modules['pre-article'], 'render_settings_page')
            );
        } else {
            self::debug_log('plugin-manager', 'Módulo Pre Article não está ativo ou não existe');
            self::debug_log('plugin-manager', 'Módulos ativos: ' . json_encode(array_keys($this->modules)));
        }

        // 4. Páginas Essenciais - Configuração básica do site
        if (isset($this->modules['essential_pages'])) {
            self::debug_log('plugin-manager', 'Adicionando submenu Essential Pages');
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
            self::debug_log('plugin-manager', 'Adicionando submenu Multi Languages');
            add_submenu_page(
                'alvobot-pro',
                'Gerenciamento Multilíngue',
                'Multilíngue',
                'manage_options',
                'alvobot-pro-multi-languages',
                array($this->modules['multi-languages'], 'render_settings_page')
            );
        }

        // 6. Quiz Builder - Menu é adicionado pelo próprio módulo

        // 7. CTA Cards - Menu é adicionado pelo próprio módulo

        // 8. Configurações - Por último, configurações gerais (remove submenu duplicado)
        // O menu principal já aponta para render_dashboard_page
    }

    public function render_dashboard_page() {
        // Processa ações do Plugin Manager na página de configurações
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'activate_plugin_manager' && isset($this->modules['plugin-manager'])) {
                check_admin_referer('activate_plugin_manager');
                $this->modules['plugin-manager']->activate();
            } elseif ($_POST['action'] === 'retry_registration' && isset($this->modules['plugin-manager'])) {
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
            } elseif ($_POST['action'] === 'save_debug_settings') {
                check_admin_referer('alvobot_debug_settings');
                $this->save_debug_settings();
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
