<?php
/**
 * Main plugin class that orchestrates all functionality
 *
 * @package Alvobot_Quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin orchestrator class
 * 
 * This class is responsible for initializing all plugin components,
 * managing hooks, and coordinating between frontend and admin functionality.
 *
 * @since 1.0.0
 */
class Alvobot_Quiz_Main {
    
    /**
     * Plugin instance
     *
     * @var Alvobot_Quiz_Main
     */
    private static $instance = null;
    
    /**
     * Shortcode handler instance
     *
     * @var Alvobot_Quiz_Shortcode
     */
    private $shortcode;
    
    /**
     * Assets handler instance
     *
     * @var Alvobot_Quiz_Assets
     */
    private $assets;
    
    /**
     * Admin handler instance
     *
     * @var Alvobot_Quiz_Admin
     */
    private $admin;
    
    /**
     * Ajax handler instance
     *
     * @var Alvobot_Quiz_Ajax
     */
    private $ajax;
    
    /**
     * Submissions handler instance
     *
     * @var Alvobot_Quiz_Submissions
     */
    private $submissions;
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return Alvobot_Quiz_Main
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize assets first (shared component)
        $this->assets = new Alvobot_Quiz_Assets();
        
        // Initialize shortcode with shared assets instance
        $this->shortcode = new Alvobot_Quiz_Shortcode($this->assets);
        
        // Initialize submissions handler
        $this->submissions = new Alvobot_Quiz_Submissions();
        
        // Initialize admin components if in admin area
        if (is_admin()) {
            $this->admin = new Alvobot_Quiz_Admin();
            $this->ajax = new Alvobot_Quiz_Ajax();
        }
        
        // Setup URL rewrite functionality
        $this->setup_url_rewrite();
        
        // Module activation is handled by AlvoBot Pro main plugin
    }
    
    /**
     * Setup URL rewrite functionality (consolidated from main file)
     */
    private function setup_url_rewrite() {
        // Add query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Add rewrite rules with highest priority
        add_action('init', array($this, 'add_rewrite_rules'), 1);
        
        // Debug rewrite rules
        add_action('parse_request', array($this, 'debug_parse_request'));
        
        // Add debug for all rewrite rules
        add_action('init', array($this, 'debug_all_rewrite_rules'), 999);
    }
    
    /**
     * Add custom query variables
     */
    public function add_query_vars($vars) {
        $vars[] = 'quiz_step_suffix';
        $vars[] = 'quiz_display_step';
        return $vars;
    }
    
    /**
     * Add URL rewrite rules for quiz navigation
     */
    public function add_rewrite_rules() {
        if (method_exists('AlvoBotPro', 'debug_log')) {
            AlvoBotPro::debug_log('quiz-builder', 'Setting up Quiz Builder rewrite rules');
        }
        
        // VERY SPECIFIC RULES - Only match EXACTLY "aquiz-e" pattern
        // This ensures we don't interfere with normal WordPress URLs that end in numbers
        
        // Rule 1: Pages with aquiz-e suffix (most specific first)
        add_rewrite_rule('^([^/]+)-aquiz-e([0-9]+)/?$', 'index.php?pagename=$matches[1]&quiz_step_suffix=$matches[2]', 'top');
        // Rule 1 added
        
        // Rule 2: Posts with aquiz-e suffix 
        add_rewrite_rule('^([^/]+)-aquiz-e([0-9]+)/?$', 'index.php?name=$matches[1]&quiz_step_suffix=$matches[2]', 'top');
        // Rule 2 added
        
        // Rule 3: Category + post with aquiz-e suffix
        add_rewrite_rule('^([^/]+)/([^/]+)-aquiz-e([0-9]+)/?$', 'index.php?category_name=$matches[1]&name=$matches[2]&quiz_step_suffix=$matches[3]', 'top');
        // Rule 3 added
        
        // Rule 4: Multi-level paths with aquiz-e suffix (deeper categories)
        add_rewrite_rule('^(.+)/([^/]+)-aquiz-e([0-9]+)/?$', 'index.php?category_name=$matches[1]&name=$matches[2]&quiz_step_suffix=$matches[3]', 'top');
        // Rule 4 added
        
        // Rule 5: Fallback for complex page structures with aquiz-e suffix
        add_rewrite_rule('^(.+)-aquiz-e([0-9]+)/?$', 'index.php?pagename=$matches[1]&quiz_step_suffix=$matches[2]', 'top');
        // Rule 5 added
        
        // All rewrite rules added
        
        // IMPORTANT: These rules ONLY match URLs containing "aquiz-e" 
        // Normal WordPress URLs ending in numbers (like "page-1") are ignored
    }
    
    /**
     * Debug parse request to see what's happening with rewrite rules
     */
    public function debug_parse_request($wp) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Debug aquiz-e URLs
        if (strpos($request_uri, 'aquiz-e') !== false) {
            if (method_exists('AlvoBotPro', 'debug_log')) {
                AlvoBotPro::debug_log('quiz-builder', 'Quiz Builder request: ' . $request_uri);
            }
        }
        
        // Debug potential conflicts (URLs ending in numbers but NOT aquiz-e)
        if (preg_match('/[^\/]+-([0-9]+)\/?$/', $request_uri) && strpos($request_uri, 'aquiz-e') === false) {
            // Not a quiz URL, ignored
        }
    }
    
    /**
     * Debug all rewrite rules to see what's registered
     */
    public function debug_all_rewrite_rules() {
        global $wp_rewrite;
        // Debug rewrite rules if needed
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        // Text domain is handled by main AlvoBot Pro plugin
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Set default options if they don't exist
        if (!get_option('alvobot_quiz_options')) {
            $defaults = array(
                'default_theme' => 'default',
                'primary_color' => '#007cba',
                'text_next' => __('Próxima', 'alvobot-quiz'),
                'text_previous' => __('Anterior', 'alvobot-quiz'),
                'show_progress' => true,
                'allow_back' => true
            );
            add_option('alvobot_quiz_options', $defaults);
        }
        
        // Create submissions table
        $this->submissions = new Alvobot_Quiz_Submissions();
        // Table creation moved to activation check
        
        // Add rewrite rules first
        $this->add_rewrite_rules();
        
        // Flush rewrite rules to ensure they take effect
        // Only flush if needed
        if (get_option('alvobot_quiz_rewrite_version') !== ALVOBOT_QUIZ_VERSION) {
            flush_rewrite_rules();
            update_option('alvobot_quiz_rewrite_version', ALVOBOT_QUIZ_VERSION);
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}