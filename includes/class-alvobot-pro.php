<?php
/**
 * Main class for the AlvoBot Pro plugin.
 *
 * This class handles the initialization of the plugin, loading of dependencies,
 * module management, admin menu creation, asset enqueueing, and core functionalities
 * like the onboarding process and global settings.
 *
 * @package AlvoBotPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class AlvoBotPro.
 */
class AlvoBotPro {
    /**
     * Plugin version.
     * @var string
     */
    private $version;

    /**
     * Array of instantiated module objects.
     * @var array
     */
    private $modules = [];

    /**
     * Array of active module slugs and their status (true/false).
     * @var array
     */
    private $active_modules = [];

    /**
     * Constructor.
     * Sets up the plugin version, loads dependencies, initializes modules,
     * and hooks into WordPress actions.
     */
    public function __construct() {
        $this->version = ALVOBOT_PRO_VERSION;
        $this->load_dependencies();
        $this->init_modules();
        add_action('admin_init', array($this, 'handle_onboarding_redirect')); // Corrected hook name
    }

    /**
     * Loads required dependencies, including module files and AJAX handler.
     * @access private
     */
    private function load_dependencies() {
        // Carrega os módulos
        $module_files = array(
            'logo-generator' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/logo-generator.php',
            'author-box' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/class-author-box.php',
            'plugin-manager' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/plugin-manager/class-plugin-manager.php',
            'pre-article' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pre-article/pre-article.php',
            'essential-pages' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/essential-pages/class-essential-pages.php',
            'multi-languages' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/multi-languages/class-multi-languages.php',
            'temporary-login' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/temporary-login/class-temporary-login.php',
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
        add_action('admin_menu', array($this, 'register_onboarding_page')); // Renamed for clarity
        
        // Adiciona scripts e estilos
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Registra configurações globais como 'alvobot_company_info'
        add_action('admin_init', array($this, 'register_global_settings'));
    }

    /**
     * Registers global plugin settings, such as company information.
     * Uses the WordPress Settings API.
     * @since 2.2.0
     */
    public function register_global_settings() {
        register_setting(
            'alvobot_pro_company_info_group', // Option group
            'alvobot_company_info',          // Option name
            array($this, 'sanitize_company_info') // Sanitization callback
        );
    }

    /**
     * Sanitizes the company information settings array.
     * Callback for `register_setting`.
     *
     * @since 2.2.0
     * @param array $input The array of company information submitted by the user.
     * @return array Sanitized array of company information.
     */
    public function sanitize_company_info($input) {
        $sanitized_input = array();
        $company_info_fields = $this->get_company_info_fields_definition();

        foreach ($company_info_fields as $key => $field_def) {
            if (isset($input[$key])) {
                $value = $input[$key];
                switch ($field_def['type']) {
                    case 'email':
                        $sanitized_input[$key] = sanitize_email($value);
                        break;
                    case 'url':
                        $sanitized_input[$key] = esc_url_raw($value);
                        break;
                    case 'textarea':
                        $sanitized_input[$key] = sanitize_textarea_field($value);
                        break;
                    case 'text':
                    default:
                        $sanitized_input[$key] = sanitize_text_field($value);
                        break;
                }
            }
        }
        
        // Handle social media as an array
        if (isset($input['social_media']) && is_array($input['social_media'])) {
            $sanitized_input['social_media'] = array();
            foreach ($input['social_media'] as $social_key => $social_url) {
                $sanitized_input['social_media'][$social_key] = esc_url_raw($social_url);
            }
        }
        
        // Handle legal info as an array
         if (isset($input['legal_info']) && is_array($input['legal_info'])) {
            $sanitized_input['legal_info'] = array();
            foreach ($input['legal_info'] as $legal_key => $legal_value) {
                $sanitized_input['legal_info'][$legal_key] = sanitize_text_field($legal_value);
            }
        }

        return $sanitized_input;
    }
    
    /**
     * Defines the structure and labels for company information fields.
     * Used for rendering the form and for sanitization.
     * 
     * @since 2.2.0
     * @return array An array defining the company information fields.
     */
    public function get_company_info_fields_definition() {
        return array(
            'name' => ['label' => __('Nome da Empresa', 'alvobot-pro'), 'type' => 'text'],
            'legal_name' => ['label' => __('Razão Social', 'alvobot-pro'), 'type' => 'text'],
            'document' => ['label' => __('CNPJ/Documento Fiscal', 'alvobot-pro'), 'type' => 'text'],
            'state_document' => ['label' => __('Inscrição Estadual', 'alvobot-pro'), 'type' => 'text'],
            'address' => ['label' => __('Endereço (Rua, Número, Complemento)', 'alvobot-pro'), 'type' => 'text'],
            'neighborhood' => ['label' => __('Bairro', 'alvobot-pro'), 'type' => 'text'],
            'city' => ['label' => __('Cidade', 'alvobot-pro'), 'type' => 'text'],
            'state' => ['label' => __('Estado (UF)', 'alvobot-pro'), 'type' => 'text'],
            'zip' => ['label' => __('CEP', 'alvobot-pro'), 'type' => 'text'],
            'country' => ['label' => __('País', 'alvobot-pro'), 'type' => 'text'],
            'phone' => ['label' => __('Telefone Principal', 'alvobot-pro'), 'type' => 'text'],
            'whatsapp' => ['label' => __('WhatsApp', 'alvobot-pro'), 'type' => 'text'],
            'email' => ['label' => __('E-mail de Contato Principal', 'alvobot-pro'), 'type' => 'email'],
            'support_email' => ['label' => __('E-mail de Suporte', 'alvobot-pro'), 'type' => 'email'],
            'sales_email' => ['label' => __('E-mail de Vendas', 'alvobot-pro'), 'type' => 'email'],
            'working_hours' => ['label' => __('Horário de Funcionamento', 'alvobot-pro'), 'type' => 'text'],
            'working_hours_extended' => ['label' => __('Horário de Funcionamento (Detalhado)', 'alvobot-pro'), 'type' => 'text'],
            'support_hours' => ['label' => __('Horário de Suporte', 'alvobot-pro'), 'type' => 'text'],
            'emergency_phone' => ['label' => __('Telefone de Emergência', 'alvobot-pro'), 'type' => 'text'],
            // Social media and legal_info are handled separately in sanitize_company_info due to their nested array structure
        );
    }


    /**
     * Handles redirection to the onboarding wizard upon plugin activation.
     * Checks for a transient set during activation and redirects if found.
     * 
     * @since 2.2.0
     */
    public function handle_onboarding_redirect() { // Renamed from check_onboarding_status
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for the activation transient
        if (get_transient('alvobot_pro_activation_redirect')) {
            delete_transient('alvobot_pro_activation_redirect');
            // Ensure this is not an AJAX request from the onboarding itself or other essential operations
            if (wp_doing_ajax() || (isset($_GET['page']) && $_GET['page'] === 'alvobot-pro-onboarding')) {
                return;
            }
            wp_redirect(admin_url('admin.php?page=alvobot-pro-onboarding'));
            exit;
        }
        
        // Optional: Force redirect if onboarding is not complete and user is on other AlvoBot pages
        // For now, the primary trigger is the post-activation transient.
        // $onboarding_complete = get_option('alvobot_pro_onboarding_complete', false);
        // $current_screen = get_current_screen();
        // if (!$onboarding_complete && isset($current_screen->id) &&
        //     strpos($current_screen->id, 'alvobot-pro') !== false &&
        //     $current_screen->id !== 'admin_page_alvobot-pro-onboarding') {
        //     // Consider if there are pages the user MUST be ableto access, like main dashboard even if onboarding not done.
        //     // if ($current_screen->id !== 'toplevel_page_alvobot-pro') { // Example: allow dashboard
        //     //    wp_redirect(admin_url('admin.php?page=alvobot-pro-onboarding'));
        //     //    exit;
        //     // }
        // }
    }
    
    /**
     * Registers the hidden admin page for the onboarding wizard.
     * 
     * @since 2.2.0
     */
    public function register_onboarding_page() { // Renamed from add_onboarding_page_menu
        // This page will not be visible in the menu. It's accessed via direct navigation/redirect.
        add_submenu_page(
            null, // No parent menu slug, makes it hidden
            __('Bem-vindo ao AlvoBot Pro', 'alvobot-pro'), // Page title
            __('Onboarding', 'alvobot-pro'), // Menu title (not visible)
            'manage_options', // Capability
            'alvobot-pro-onboarding', // Page slug
            array($this, 'render_onboarding_page') // Callback function
        );
    }

    /**
     * Renders the onboarding wizard page by including the template file.
     * Passes necessary data to the template.
     * 
     * @since 2.2.0
     */
    public function render_onboarding_page() {
        // Data for onboarding wizard
        $onboarding_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alvobot_pro_onboarding_nonce'),
            'steps' => array(
                array(
                    'id' => 'welcome',
                    'title' => __('Bem-vindo ao AlvoBot Pro!', 'alvobot-pro'),
                ),
                array(
                    'id' => 'core_setup',
                    'title' => __('Configuração Essencial', 'alvobot-pro'),
                ),
                // Future steps will be added here
            )
        );
        include ALVOBOT_PRO_PLUGIN_DIR . 'assets/templates/onboarding-wizard.php';
    }


    /**
     * Initializes available modules based on saved options.
     * Ensures 'plugin-manager' is always active. Instantiates active modules.
     * @access private
     * @since 1.0.0
     */
    private function init_modules() {
        error_log('AlvoBot Pro: Iniciando init_modules'); // Keep for debugging if needed

        // Define default active states for all modules known to the core plugin
        $default_modules = array(
            'logo-generator' => true,
            'author-box' => true,
            'plugin-manager' => true,
            'pre-article' => true,
            'essential-pages' => true,
            'multi-languages' => true,
            'temporary-login' => true,
        );

        // Obtém os módulos ativos do banco de dados
        $saved_modules = get_option('alvobot_pro_active_modules');
        error_log('Alvobot Pro: Módulos salvos: ' . json_encode($saved_modules));
        
        // Se não existir no banco, cria com os valores padrão
        if (false === $saved_modules) {
            update_option('alvobot_pro_active_modules', $default_modules);
            $this->active_modules = $default_modules;
            error_log('Alvobot Pro: Criando módulos padrão');
        } else {
            // Mescla os módulos salvos com os padrões para garantir que todos os módulos existam
            $this->active_modules = wp_parse_args($saved_modules, $default_modules);
            // Garante que o plugin_manager esteja sempre ativo
            $this->active_modules['plugin-manager'] = true;
            // Atualiza a opção no banco de dados
            update_option('alvobot_pro_active_modules', $this->active_modules);
            error_log('Alvobot Pro: Módulos ativos atualizados: ' . json_encode($this->active_modules));
        }

        // Mapeia os módulos para suas classes
        $module_classes = array(
            'logo-generator' => 'AlvoBotPro_LogoGenerator',
            'author-box' => 'AlvoBotPro_AuthorBox',
            'plugin-manager' => 'AlvoBotPro_PluginManager',
            'pre-article' => 'Alvobot_Pre_Article',
            'essential-pages' => 'AlvoBotPro_EssentialPages',
            'multi-languages' => 'AlvoBotPro_MultiLanguages',
            'temporary-login' => 'AlvoBotPro_TemporaryLogin',
        );

        // Instancia apenas os módulos ativos
        foreach ($module_classes as $module_id => $class_name) {
            error_log("Alvobot Pro: Verificando módulo {$module_id} ({$class_name})");
            if (
                isset($this->active_modules[$module_id]) && 
                $this->active_modules[$module_id] && 
                class_exists($class_name)
            ) {
                error_log("Alvobot Pro: Instanciando módulo {$module_id}");
                $this->modules[$module_id] = new $class_name();
            } else {
                error_log("Alvobot Pro: Módulo {$module_id} não ativo ou classe não existe");
                if (!class_exists($class_name)) {
                    error_log("Alvobot Pro: Classe {$class_name} não existe");
                }
            }
        }
    }

    /**
     * Obtém o estado atual dos módulos
     */
    /**
     * Retrieves the array of active modules and their status.
     * 
     * @since 2.1.0
     * @return array Associative array of module slugs and their boolean active status.
     */
    public function get_active_modules() {
        return $this->active_modules;
    }

    /**
     * Adds the main AlvoBot Pro admin menu and submenus for active modules.
     * 
     * @since 1.0.0
     */
    public function add_admin_menu() {
        error_log('Alvobot Pro: Iniciando add_admin_menu');

        // Adiciona menu principal
        add_menu_page(
            'AlvoBot Pro',
            'AlvoBot Pro',
            'manage_options',
            'alvobot-pro',
            array($this, 'render_dashboard_page'),
            ALVOBOT_PRO_PLUGIN_URL . 'assets/images/icon-alvobot-app.svg',
            2
        );

        // Adiciona o submenu "Configurações" para a página principal
        add_submenu_page(
            'alvobot-pro',
            'Configurações',
            'Configurações',
            'manage_options',
            'alvobot-pro',
            array($this, 'render_dashboard_page')
        );

        // Adiciona submenus apenas para módulos ativos
        if (isset($this->modules['logo-generator'])) {
            error_log('Alvobot Pro: Adicionando submenu Logo Generator');
            add_submenu_page(
                'alvobot-pro',
                'Gerador de Logo',
                'Gerador de Logo',
                'manage_options',
                'alvobot-pro-logo',
                array($this->modules['logo-generator'], 'render_settings_page')
            );
        }

        if (isset($this->modules['author-box'])) {
            error_log('Alvobot Pro: Adicionando submenu Author Box');
            add_submenu_page(
                'alvobot-pro',
                'Caixa de Autor',
                'Caixa de Autor',
                'manage_options',
                'alvobot-pro-author',
                array($this->modules['author-box'], 'render_settings_page')
            );
        }

        if (isset($this->modules['pre-article'])) {
            error_log('Alvobot Pro: Adicionando submenu Pre Article');
            add_submenu_page(
                'alvobot-pro',
                'Pré-Artigo',
                'Pré-Artigo',
                'manage_options',
                'alvobot-pro-pre-article',
                array($this->modules['pre-article'], 'render_settings_page')
            );
        } else {
            error_log('Alvobot Pro: Módulo Pre Article não está ativo ou não existe');
            error_log('Alvobot Pro: Módulos ativos: ' . json_encode($this->modules));
        }

        if (isset($this->modules['essential-pages'])) {
            error_log('Alvobot Pro: Adicionando submenu Essential Pages');
            add_submenu_page(
                'alvobot-pro',
                'Páginas Essenciais',
                'Páginas Essenciais',
                'manage_options',
                'alvobot-pro-essential-pages',
                array($this->modules['essential-pages'], 'render_settings_page')
            );
        }

        if (isset($this->modules['multi-languages'])) {
            error_log('Alvobot Pro: Adicionando submenu Multi Idiomas');
            add_submenu_page(
                'alvobot-pro',
                __('Multi Idiomas', 'alvobot-pro'),
                __('Multi Idiomas', 'alvobot-pro'),
                'manage_options',
                'alvobot-pro-multi-languages',
                array($this->modules['multi-languages'], 'render_settings_page')
            );
        }

        if (isset($this->modules['plugin-manager'])) {
            error_log('Alvobot Pro: Adicionando submenu Plugin Manager');
            add_submenu_page(
                'alvobot-pro',
                'Plugins',
                'Plugins',
                'manage_options',
                'alvobot-pro-plugins',
                array($this->modules['plugin-manager'], 'render_settings_page')
            );
        }

        if (isset($this->modules['temporary-login'])) {
            error_log('AlvoBot Pro: Adicionando submenu Acesso Temporário'); // Optional: for debugging
            add_submenu_page(
                'alvobot-pro',
                __('Acesso Temporário', 'alvobot-pro'), // Page Title
                __('Acesso Temporário', 'alvobot-pro'), // Menu Title
                'manage_options',
                'alvobot-pro-temporary-login', // Menu Slug
                array($this->modules['temporary-login'], 'render_admin_page') // Assumes 'render_admin_page' method exists in 'AlvoBotPro_TemporaryLogin'
            );
        }

    }

    /**
     * Retrieves information about all available modules.
     * 
     * Provides names, descriptions, settings page slugs, and core status for each module.
     * This method is central to how the dashboard and other parts of the plugin
     * understand available modules.
     * 
     * @since 2.2.0
     * @return array An associative array where keys are module slugs and values are arrays of module information.
     */
    public function get_module_info() {
        // Centralized module information
        return array(
            'plugin-manager' => array( // Kebab-case slug
                'name' => __('Gerenciador de Plugins', 'alvobot-pro'),
                'description' => __('Gerencia plugins remotamente e lida com a conexão central.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-plugins',
                'is_core' => true // Always active
            ),
            'author-box' => array(
                'name' => __('Caixa de Autor', 'alvobot-pro'),
                'description' => __('Exibe uma caixa com informações do autor abaixo dos posts.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-author',
                'is_core' => false
            ),
            'logo-generator' => array(
                'name' => __('Gerador de Logo', 'alvobot-pro'),
                'description' => __('Crie logos e favicons personalizados para o seu site.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-logo',
                'is_core' => false
            ),
            'pre-article' => array(
                'name' => __('Página de Pré-Artigo', 'alvobot-pro'),
                'description' => __('Gere páginas de pré-artigo para seus posts existentes.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-pre-article',
                'is_core' => false
            ),
            'essential-pages' => array(
                'name' => __('Páginas Essenciais', 'alvobot-pro'),
                'description' => __('Crie e gerencie páginas essenciais como Termos de Uso e Política de Privacidade.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-essential-pages',
                'is_core' => false
            ),
            'multi-languages' => array(
                'name' => __('Multi Idiomas', 'alvobot-pro'),
                'description' => __('Gerencia traduções de conteúdo (requer Polylang).', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-multi-languages',
                'is_core' => false
            ),
            'temporary-login' => array(
                'name' => __('Acesso Temporário', 'alvobot-pro'),
                'description' => __('Crie links de login seguros e temporários para o site.', 'alvobot-pro'),
                'settings_slug' => 'alvobot-pro-temporary-login',
                'is_core' => false // Set to true if it should be a non-deactivatable core module
            ),
            // Future "Temporary Login" module can be added here
        );
    }

    /**
     * Renders the main AlvoBot Pro dashboard page.
     * 
     * Gathers system information, module statuses, and company information,
     * then includes the dashboard template file.
     * 
     * @since 1.0.0
     */
    public function render_dashboard_page() {
        $system_info = array(
            'plugin_version' => $this->version,
            'site_token' => get_option('grp_site_token'),
            'alvobot_user' => null,
            'alvobot_user_is_admin' => false,
            'active_module_count' => 0,
            'total_module_count' => 0,
            'company_info' => get_option('alvobot_company_info', array()), // Add this
        );

        $alvobot_user_obj = get_user_by('login', 'alvobot');
        if ($alvobot_user_obj) {
            $system_info['alvobot_user'] = $alvobot_user_obj;
            $system_info['alvobot_user_is_admin'] = in_array('administrator', $alvobot_user_obj->roles);
        }

        $all_modules_info = $this->get_module_info();
        $active_modules_keys = $this->get_active_modules();
        
        $system_info['total_module_count'] = count($all_modules_info);
        // Count active modules, ensuring plugin-manager is always counted if present in $all_modules_info
        $current_active_count = 0;
        foreach ($active_modules_keys as $key => $is_active) {
            if ($is_active) {
                $current_active_count++;
            }
        }
        // Ensure plugin-manager is counted as active if it exists in definition
        if (isset($all_modules_info['plugin-manager']) && (!isset($active_modules_keys['plugin-manager']) || !$active_modules_keys['plugin-manager'])) {
             // This case should ideally not happen if plugin-manager is always forced active in init_modules
        } else if (!isset($all_modules_info['plugin-manager'])) {
            // if plugin-manager is not defined in get_module_info, count based on active_modules_keys
        }

        $system_info['active_module_count'] = $current_active_count;
        if (isset($all_modules_info['plugin-manager']) && $active_modules_keys['plugin-manager'] == false){
            // if plugin manager is defined but somehow set to false in active_modules_keys, it still counts as 1.
            // but init_modules() should force it to true. So this path is unlikely.
            // for safety, let's ensure it's counted if defined.
            // $system_info['active_module_count'] = max(1, $current_active_count);
        }


        // This part is complex due to plugin-manager always being active.
        // Let's count active modules from $this->active_modules which is already correctly set by init_modules()
        $actually_active_count = 0;
        foreach($this->active_modules as $slug => $is_active){
            if($is_active && isset($all_modules_info[$slug])) { // check if module is defined in get_module_info
                 $actually_active_count++;
            }
        }
        $system_info['active_module_count'] = $actually_active_count;


        // For passing to the template, we need the full info array merged with active status
        $modules_for_template = array();
        foreach ($all_modules_info as $slug => $info) {
            $modules_for_template[$slug] = $info;
            $modules_for_template[$slug]['active'] = isset($this->active_modules[$slug]) ? $this->active_modules[$slug] : false;
            // Plugin Manager is always active
            if ($slug === 'plugin-manager') {
                $modules_for_template[$slug]['active'] = true;
            }
        }
        
        // Inclui o template
        include ALVOBOT_PRO_PLUGIN_DIR . 'assets/templates/dashboard.php';
    }

    /**
     * Enqueues admin-specific CSS and JavaScript files.
     * 
     * Loads general admin styles and scripts for AlvoBot Pro pages,
     * and specific assets for the onboarding wizard page.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        // Enqueue assets for regular admin pages
        if (strpos($hook, 'alvobot-pro') !== false && $hook !== 'admin_page_alvobot-pro-onboarding') { // Exclude onboarding page from general admin assets
            wp_enqueue_style(
                'alvobot-pro-admin',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/css/alvobot-pro-admin.css',
                array(),
                $this->version
            );

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

        // Enqueue assets for onboarding wizard page
        if ($hook === 'admin_page_alvobot-pro-onboarding') {
             wp_enqueue_style(
                'alvobot-pro-onboarding',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/css/alvobot-pro-onboarding.css',
                array('wp-admin', 'buttons'), // Ensure WordPress admin styles and buttons are loaded
                $this->version
            );
            wp_enqueue_script(
                'alvobot-pro-onboarding',
                ALVOBOT_PRO_PLUGIN_URL . 'assets/js/alvobot-pro-onboarding.js',
                array('jquery'),
                $this->version,
                true
            );
             wp_localize_script('alvobot-pro-onboarding', 'alvobotOnboarding', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alvobot_pro_onboarding_nonce')
                // Any other data needed by the onboarding script
            ));
        }
    }

    /**
     * Handles plugin activation.
     * 
     * Sets default options for active modules, initializes onboarding status,
     * sets default company information if not already present, activates the Plugin Manager module,
     * and stores the plugin version. Sets a transient to trigger onboarding redirect.
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Initialize onboarding status if not already set
        if (get_option('alvobot_pro_onboarding_complete') === false) {
            update_option('alvobot_pro_onboarding_complete', false); // Explicitly false, not just non-existent
        }
        
        // Garante que as opções padrão existam
        if (!get_option('alvobot_pro_active_modules')) {
            update_option('alvobot_pro_active_modules', array(
                'logo-generator' => true,
                'author-box' => true,
                'plugin-manager' => true,
                'pre-article' => true,
                'essential-pages' => true,
                'multi-languages' => true,
                'temporary-login' => true,
            ));
        }

        // Ativa cada módulo
        // This loop might not run correctly on first activation as $this->modules is populated by init_modules,
        // which might run after activate hook for some modules.
        // Better to call module activation directly if needed or ensure init_modules runs before this.
        // For now, focusing on onboarding flag.
        // foreach ($this->modules as $module_slug => $module_instance) {
        //     if (method_exists($module_instance, 'activate')) {
        //         $module_instance->activate();
        //     }
        // }
        
        // Ensure Plugin Manager activation logic runs as it's critical (user, token)
        if (class_exists('AlvoBotPro_PluginManager')) {
            $pm_module = new AlvoBotPro_PluginManager();
            if (method_exists($pm_module, 'activate')) {
                 $pm_module->activate(); // This creates user, token, etc.
            }
        }


        // Atualiza a versão do plugin
        update_option('alvobot_pro_version', $this->version);

        // Set a transient to trigger the redirect to onboarding after activation
        set_transient('alvobot_pro_activation_redirect', true, 30);
    }
    
    /**
     * Handles plugin deactivation.
     * 
     * Currently, this method is placeholder and does not perform specific actions
     * beyond what individual modules might do if they had deactivation methods.
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Desativa cada módulo
        // foreach ($this->modules as $module_slug => $module_instance) {
        //     if (method_exists($module_instance, 'deactivate')) {
        //         $module_instance->deactivate();
        //     }
        // }
    }
}
