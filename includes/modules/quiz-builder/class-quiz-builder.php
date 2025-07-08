<?php
/**
 * AlvoBot Quiz Builder Module
 *
 * @package AlvoBotPro
 * @subpackage Modules/QuizBuilder
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quiz Builder Module Class
 */
class AlvoBotPro_QuizBuilder {
    
    /**
     * Module slug
     *
     * @var string
     */
    private $module_slug = 'quiz-builder';
    
    /**
     * Quiz instance
     *
     * @var object
     */
    private $quiz_instance;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the module
     */
    private function init() {
        AlvoBotPro::debug_log('quiz-builder', 'Initializing Quiz Builder module');
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize quiz system
        $this->init_quiz_system();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        
        // Module activation check
        add_action('admin_init', array($this, 'check_module_activation'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        AlvoBotPro::debug_log('quiz-builder', 'Quiz Builder module initialized');
    }
    
    /**
     * Load module dependencies
     */
    private function load_dependencies() {
        $module_path = plugin_dir_path(__FILE__);
        
        // Load main quiz class
        if (file_exists($module_path . 'alvobot-quiz.php')) {
            require_once $module_path . 'alvobot-quiz.php';
        }
        
        // Load includes
        $includes = array(
            'includes/class-alvobot-quiz-main.php',
            'includes/class-assets.php',
            'includes/class-shortcode.php',
            'includes/class-content-handler.php',
            'admin/class-admin.php',
            'admin/class-ajax.php'
        );
        
        foreach ($includes as $file) {
            $file_path = $module_path . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize quiz system
     */
    private function init_quiz_system() {
        // Initialize main quiz instance
        if (class_exists('Alvobot_Quiz_Main')) {
            $this->quiz_instance = Alvobot_Quiz_Main::get_instance();
            
            // Override some settings to integrate with AlvoBot Pro
            add_filter('alvobot_quiz_settings', array($this, 'modify_quiz_settings'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Module is already active if this method is called
        
        add_submenu_page(
            'alvobot-pro',
            __('Quiz Builder', 'alvobot-pro'),
            __('Quiz Builder', 'alvobot-pro'),
            'manage_options',
            'alvobot-quiz-builder',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'alvobot-pro'));
        }
        
        // Use the existing admin class if available
        if (class_exists('Alvobot_Quiz_Admin') && method_exists('Alvobot_Quiz_Admin', 'get_instance')) {
            $admin = Alvobot_Quiz_Admin::get_instance();
            if (method_exists($admin, 'admin_page')) {
                // Wrap the admin page in AlvoBot Pro styles
                ?>
                <div class="alvobot-admin-wrap">
                    <div class="alvobot-admin-container">
                        <?php $admin->admin_page(); ?>
                    </div>
                </div>
                <?php
                return;
            }
        }
        
        // Fallback to template
        $template = plugin_dir_path(__FILE__) . 'templates/admin-page.php';
        if (file_exists($template)) {
            include $template;
        }
    }
    
    /**
     * Check module activation
     */
    public function check_module_activation() {
        // Module is already active if this method is called
        
        // Check if rewrite rules need to be flushed
        $version = get_option('alvobot_quiz_version');
        if ($version !== ALVOBOT_PRO_VERSION) {
            flush_rewrite_rules();
            update_option('alvobot_quiz_version', ALVOBOT_PRO_VERSION);
        }
    }
    
    /**
     * Modify quiz settings for integration
     *
     * @param array $settings Current settings
     * @return array Modified settings
     */
    public function modify_quiz_settings($settings) {
        // Use AlvoBot Pro text domain
        $settings['text_domain'] = 'alvobot-pro';
        
        // Use AlvoBot Pro capability
        $settings['capability'] = 'manage_options';
        
        // Integrate with AlvoBot Pro logging
        $settings['debug'] = defined('WP_DEBUG') && WP_DEBUG;
        
        return $settings;
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on quiz builder pages
        if (strpos($hook, 'alvobot-quiz-builder') === false && strpos($hook, 'alvobot-quiz') === false) {
            return;
        }
        
        // Load AlvoBot Pro unified styles
        wp_enqueue_style(
            'alvobot-pro-styles',
            ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            ALVOBOT_PRO_VERSION
        );
        
        // Load quiz specific styles if they exist
        $quiz_css = plugin_dir_path(__FILE__) . 'assets/css/admin.css';
        if (file_exists($quiz_css)) {
            wp_enqueue_style(
                'alvobot-quiz-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin.css',
                array('alvobot-pro-styles'),
                ALVOBOT_PRO_VERSION
            );
        }
        
        // Load quiz builder JavaScript
        $quiz_js = plugin_dir_path(__FILE__) . 'assets/js/quiz-builder.js';
        if (file_exists($quiz_js)) {
            // Sortable.js for drag and drop
            wp_enqueue_script(
                'sortable-js',
                'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
                array(),
                '1.15.0',
                true
            );
            
            wp_enqueue_script(
                'alvobot-quiz-builder',
                plugin_dir_url(__FILE__) . 'assets/js/quiz-builder.js',
                array('jquery', 'sortable-js'),
                ALVOBOT_PRO_VERSION,
                true
            );
            
            // Localize script with AJAX data
            wp_localize_script('alvobot-quiz-builder', 'alvobot_quiz_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alvobot_quiz_nonce'),
                'quiz_url' => ALVOBOT_QUIZ_URL
            ));
        }
    }
    
    /**
     * Get module info
     *
     * @return array
     */
    public static function get_module_info() {
        return array(
            'name'        => __('Quiz Builder', 'alvobot-pro'),
            'description' => __('Create interactive quizzes with AI-powered questions and detailed analytics.', 'alvobot-pro'),
            'version'     => '1.0.0',
            'author'      => 'AlvoBot',
            'icon'        => 'dashicons-forms',
            'category'    => 'engagement'
        );
    }
}