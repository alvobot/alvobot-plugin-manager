<?php
/**
 * Admin functionality class
 *
 * @package Alvobot_Quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin interface and settings
 */
class Alvobot_Quiz_Admin {
    
    /**
     * Instance
     *
     * @var Alvobot_Quiz_Admin
     */
    private static $instance = null;
    
    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name = 'alvobot-pro';
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version = ALVOBOT_QUIZ_VERSION;
    
    /**
     * Get instance
     *
     * @return Alvobot_Quiz_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Menu is now handled by AlvoBotPro_QuizBuilder
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Quiz Builder', 'alvobot-pro'),
            __('Quiz Builder', 'alvobot-pro'),
            'manage_options',
            'alvobot-quiz',
            array($this, 'admin_page'),
            'dashicons-forms',
            30
        );
    }
    
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'builder';
        ?>
        <div class="alvobot-admin-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Crie quizzes interativos com navegação por URL única, otimizados para monetização.', 'alvobot-pro'); ?></p>
        </div>
        
        <nav class="nav-tab-wrapper">
            <a href="?page=alvobot-quiz-builder&tab=builder" 
               class="nav-tab <?php echo $active_tab == 'builder' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Gerador', 'alvobot-pro'); ?>
            </a>
            <a href="?page=alvobot-quiz-builder&tab=docs" 
               class="nav-tab <?php echo $active_tab == 'docs' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Documentação', 'alvobot-pro'); ?>
            </a>
        </nav>
        
        <div class="alvobot-card">
            <div class="alvobot-card-content">
                <?php
                switch ($active_tab) {
                    case 'builder':
                        $this->builder_tab();
                        break;
                    case 'docs':
                        $this->docs_tab();
                        break;
                    default:
                        $this->builder_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Builder tab
     */
    private function builder_tab() {
        include ALVOBOT_QUIZ_PATH . 'admin/views/view-builder.php';
    }
    
    /**
     * Documentation tab
     */
    private function docs_tab() {
        include ALVOBOT_QUIZ_PATH . 'admin/views/view-docs.php';
    }
    
}