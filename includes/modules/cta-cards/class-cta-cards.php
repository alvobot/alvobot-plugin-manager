<?php
/**
 * AlvoBot CTA Cards Module
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CTA Cards Module Class
 */
class AlvoBotPro_CTACards {
    
    /**
     * Module slug
     *
     * @var string
     */
    private $module_slug = 'cta-cards';
    
    /**
     * Shortcode handler instance
     *
     * @var object
     */
    private $shortcode_handler;
    
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
        AlvoBotPro::debug_log($this->module_slug, 'Initializing CTA Cards module');
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize shortcode handler
        $this->init_shortcode();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers for preview
        add_action('wp_ajax_alvobot_render_cta_preview', array($this, 'render_cta_preview'));
        
        AlvoBotPro::debug_log($this->module_slug, 'CTA Cards module initialized');
    }
    
    /**
     * Load module dependencies
     */
    private function load_dependencies() {
        $module_path = plugin_dir_path(__FILE__);
        
        // Load shortcode handler
        if (file_exists($module_path . 'includes/class-shortcode.php')) {
            require_once $module_path . 'includes/class-shortcode.php';
        }
    }
    
    /**
     * Initialize shortcode system
     */
    private function init_shortcode() {
        if (class_exists('AlvoBotPro_CTACards_Shortcode')) {
            $this->shortcode_handler = new AlvoBotPro_CTACards_Shortcode();
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'alvobot-pro',
            __('CTA Cards', 'alvobot-pro'),
            __('CTA Cards', 'alvobot-pro'),
            'manage_options',
            'alvobot-cta-cards',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'alvobot-pro'));
        }
        
        // Include admin template
        $template = plugin_dir_path(__FILE__) . 'templates/admin-page.php';
        if (file_exists($template)) {
            include $template;
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load if page has our shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cta_card')) {
            return;
        }
        
        wp_enqueue_style(
            'alvobot-cta-cards',
            plugin_dir_url(__FILE__) . 'assets/css/cta-cards.css',
            array(),
            ALVOBOT_PRO_VERSION
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if (strpos($hook, 'alvobot-cta-cards') === false) {
            return;
        }
        
        // Load AlvoBot Pro unified styles
        wp_enqueue_style(
            'alvobot-pro-styles',
            ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            ALVOBOT_PRO_VERSION
        );
        
        // Load frontend CSS for accurate previews
        wp_enqueue_style(
            'alvobot-cta-cards-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/cta-cards.css',
            array(),
            ALVOBOT_PRO_VERSION
        );
        
        // Force load styles in head to ensure preview works
        add_action('admin_head', function() {
            echo '<link rel="stylesheet" type="text/css" href="' . plugin_dir_url(__FILE__) . 'assets/css/cta-cards.css?v=' . ALVOBOT_PRO_VERSION . '" />';
        });
        
        // Load new builder JS
        $builder_js = plugin_dir_path(__FILE__) . 'assets/js/cta-builder.js';
        if (file_exists($builder_js)) {
            wp_enqueue_script(
                'alvobot-cta-builder',
                plugin_dir_url(__FILE__) . 'assets/js/cta-builder.js',
                array('jquery'),
                ALVOBOT_PRO_VERSION,
                true
            );
            
            // Add color picker
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            
            // Localize script
            wp_localize_script('alvobot-cta-builder', 'alvobotCTACards', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alvobot_cta_cards_nonce'),
                'templates' => $this->get_available_templates(),
                'copy' => __('Copiar', 'alvobot-pro'),
                'copied' => __('Copiado!', 'alvobot-pro')
            ));
        }
        
        // Also load the old admin helper for examples page
        $admin_js = plugin_dir_path(__FILE__) . 'assets/js/admin-helper.js';
        if (file_exists($admin_js)) {
            wp_enqueue_script(
                'alvobot-cta-cards-admin',
                plugin_dir_url(__FILE__) . 'assets/js/admin-helper.js',
                array('jquery'),
                ALVOBOT_PRO_VERSION,
                true
            );
        }
    }
    
    /**
     * Get available CTA templates
     *
     * @return array
     */
    private function get_available_templates() {
        return array(
            'vertical' => array(
                'name' => __('Vertical Centered', 'alvobot-pro'),
                'description' => __('Layout vertical com conteúdo centralizado', 'alvobot-pro'),
                'params' => array('title', 'subtitle', 'description', 'button', 'url', 'image')
            ),
            'horizontal' => array(
                'name' => __('Horizontal com Imagem', 'alvobot-pro'),
                'description' => __('Imagem à esquerda, conteúdo à direita', 'alvobot-pro'),
                'params' => array('title', 'description', 'image', 'button', 'url')
            ),
            'minimal' => array(
                'name' => __('Minimalista', 'alvobot-pro'),
                'description' => __('Design limpo com título, tag e botão', 'alvobot-pro'),
                'params' => array('title', 'tag', 'button', 'url')
            ),
            'banner' => array(
                'name' => __('Banner Destacado', 'alvobot-pro'),
                'description' => __('Banner largo com imagem de fundo', 'alvobot-pro'),
                'params' => array('title', 'description', 'background', 'button', 'url')
            ),
            'simple' => array(
                'name' => __('Card Simples', 'alvobot-pro'),
                'description' => __('Apenas ícone, título e link', 'alvobot-pro'),
                'params' => array('title', 'icon', 'url')
            ),
            'pulse' => array(
                'name' => __('Pulse Animado', 'alvobot-pro'),
                'description' => __('Template com animações pulsantes e indicador ao vivo', 'alvobot-pro'),
                'params' => array('title', 'subtitle', 'description', 'button', 'url', 'icon', 'pulse_text', 'pulse_color')
            ),
            'multi-button' => array(
                'name' => __('Múltiplos Botões', 'alvobot-pro'),
                'description' => __('Template com até 3 botões de ação diferentes', 'alvobot-pro'),
                'params' => array('title', 'subtitle', 'description', 'button', 'url', 'button2', 'url2', 'button3', 'url3', 'image')
            ),
            'led-border' => array(
                'name' => __('LED Border', 'alvobot-pro'),
                'description' => __('Template com efeito LED girando na borda do botão', 'alvobot-pro'),
                'params' => array('title', 'subtitle', 'description', 'button', 'url', 'icon', 'led_colors', 'led_speed', 'image')
            )
        );
    }
    
    /**
     * AJAX handler for rendering CTA preview
     */
    public function render_cta_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'alvobot_cta_cards_nonce')) {
            wp_send_json_error(__('Acesso negado.', 'alvobot-pro'));
        }
        
        // Get shortcode from request
        $shortcode = sanitize_text_field($_POST['shortcode']);
        
        if (empty($shortcode)) {
            wp_send_json_error(__('Shortcode inválido.', 'alvobot-pro'));
        }
        
        AlvoBotPro::debug_log($this->module_slug, 'Rendering preview for shortcode: ' . $shortcode);
        
        // Ensure shortcode handler is loaded
        if (!$this->shortcode_handler) {
            $this->init_shortcode();
        }
        
        // Render shortcode
        $output = do_shortcode($shortcode);
        
        AlvoBotPro::debug_log($this->module_slug, 'Preview output length: ' . strlen($output));
        
        if (empty($output) || $output === $shortcode) {
            wp_send_json_error(__('Erro ao renderizar shortcode. Verifique se o módulo está ativo.', 'alvobot-pro'));
        }
        
        wp_send_json_success($output);
    }
    
    /**
     * Get module info
     *
     * @return array
     */
    public static function get_module_info() {
        return array(
            'name'        => __('CTA Cards', 'alvobot-pro'),
            'description' => __('Crie cards de CTA (Call-to-Action) personalizados para seus artigos.', 'alvobot-pro'),
            'version'     => '1.0.0',
            'author'      => 'AlvoBot',
            'icon'        => 'dashicons-index-card',
            'category'    => 'content'
        );
    }
}