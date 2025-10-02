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

        // Multi-language integration
        $this->init_multilanguage_support();

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

    /**
     * Initialize multi-language support
     */
    private function init_multilanguage_support() {
        // Hook para adicionar botão de traduzir shortcodes CTA nos posts
        add_action('admin_footer', array($this, 'add_cta_translation_modal'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_translation_assets'));

        // AJAX handlers para tradução de shortcodes
        add_action('wp_ajax_alvobot_translate_cta_shortcode', array($this, 'translate_cta_shortcode'));

        AlvoBotPro::debug_log($this->module_slug, 'Multi-language support initialized');
    }

    /**
     * Enqueue assets for translation functionality
     */
    public function enqueue_translation_assets($hook) {
        // Só carrega nas páginas de edição de posts/páginas
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // Verifica se o Polylang está ativo
        if (!function_exists('PLL') || !PLL()->model) {
            return;
        }

        wp_enqueue_script(
            'alvobot-cta-translation',
            plugin_dir_url(__FILE__) . 'assets/js/cta-translation.js',
            array('jquery'),
            ALVOBOT_PRO_VERSION,
            true
        );

        wp_localize_script('alvobot-cta-translation', 'alvobotCTATranslation', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alvobot_cta_translation_nonce'),
            'languages' => $this->get_available_languages(),
            'strings' => array(
                'translate_cta' => __('Traduzir CTA Cards', 'alvobot-pro'),
                'select_languages' => __('Selecione os idiomas de destino:', 'alvobot-pro'),
                'translating' => __('Traduzindo...', 'alvobot-pro'),
                'success' => __('CTA Cards traduzidos com sucesso!', 'alvobot-pro'),
                'error' => __('Erro ao traduzir CTA Cards.', 'alvobot-pro'),
                'no_cta_found' => __('Nenhum shortcode [cta_card] encontrado neste post.', 'alvobot-pro')
            )
        ));
    }

    /**
     * Add translation modal to admin footer
     */
    public function add_cta_translation_modal() {
        global $typenow;

        // Só adiciona em posts/páginas
        if (!in_array($typenow, array('post', 'page'))) {
            return;
        }

        // Verifica se o Polylang está ativo
        if (!function_exists('PLL') || !PLL()->model) {
            return;
        }

        ?>
        <div id="alvobot-cta-translation-modal" style="display: none;">
            <div class="alvobot-modal-content">
                <div class="alvobot-modal-header">
                    <h3><?php _e('Traduzir CTA Cards', 'alvobot-pro'); ?></h3>
                    <button type="button" class="alvobot-modal-close">&times;</button>
                </div>
                <div class="alvobot-modal-body">
                    <p><?php _e('Este post contém shortcodes [cta_card]. Selecione os idiomas para os quais deseja traduzir os textos dos CTA Cards:', 'alvobot-pro'); ?></p>
                    <div class="alvobot-languages-selection">
                        <?php foreach ($this->get_available_languages() as $lang_code => $lang_name): ?>
                            <label>
                                <input type="checkbox" name="target_languages[]" value="<?php echo esc_attr($lang_code); ?>">
                                <?php echo esc_html($lang_name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="alvobot-translation-options">
                        <label>
                            <input type="checkbox" name="replace_original" value="1">
                            <?php _e('Substituir shortcodes originais pelos traduzidos', 'alvobot-pro'); ?>
                        </label>
                    </div>
                </div>
                <div class="alvobot-modal-footer">
                    <button type="button" class="button button-secondary alvobot-modal-cancel"><?php _e('Cancelar', 'alvobot-pro'); ?></button>
                    <button type="button" class="button button-primary alvobot-translate-cta-submit"><?php _e('Traduzir', 'alvobot-pro'); ?></button>
                </div>
            </div>
        </div>

        <style>
        #alvobot-cta-translation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .alvobot-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .alvobot-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alvobot-modal-header h3 {
            margin: 0;
        }
        .alvobot-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }
        .alvobot-modal-body {
            padding: 20px;
        }
        .alvobot-languages-selection {
            margin: 15px 0;
        }
        .alvobot-languages-selection label {
            display: block;
            margin: 8px 0;
        }
        .alvobot-translation-options {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .alvobot-modal-footer {
            padding: 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        .alvobot-modal-footer .button {
            margin-left: 10px;
        }
        </style>
        <?php
    }

    /**
     * Get available languages from Polylang
     */
    private function get_available_languages() {
        if (!function_exists('PLL') || !PLL()->model) {
            return array();
        }

        $languages = array();
        $pll_languages = PLL()->model->get_languages_list();

        foreach ($pll_languages as $lang) {
            $languages[$lang->slug] = $lang->name;
        }

        return $languages;
    }

    /**
     * AJAX handler for translating CTA shortcodes
     */
    public function translate_cta_shortcode() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'alvobot_cta_translation_nonce')) {
            wp_send_json_error(__('Acesso negado.', 'alvobot-pro'));
        }

        // Verify permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permissões insuficientes.', 'alvobot-pro'));
        }

        $post_id = intval($_POST['post_id']);
        $target_languages = array_map('sanitize_text_field', $_POST['target_languages']);
        $replace_original = isset($_POST['replace_original']) && $_POST['replace_original'];

        if (empty($post_id) || empty($target_languages)) {
            wp_send_json_error(__('Parâmetros inválidos.', 'alvobot-pro'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post não encontrado.', 'alvobot-pro'));
        }

        AlvoBotPro::debug_log($this->module_slug, 'Translating CTA shortcodes for post ' . $post_id . ' to languages: ' . implode(', ', $target_languages));

        // Busca por shortcodes [cta_card] no conteúdo
        $content = $post->post_content;
        $pattern = '/\[cta_card([^\]]*)\]/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            wp_send_json_error(__('Nenhum shortcode [cta_card] encontrado neste post.', 'alvobot-pro'));
        }

        // Verifica se o módulo multi-languages está ativo
        if (!class_exists('AlvoBotPro_MultiLanguages') || !class_exists('AlvoBotPro_Translation_Service')) {
            wp_send_json_error(__('Módulo Multi-Languages não está ativo.', 'alvobot-pro'));
        }

        $translated_shortcodes = array();
        $total_shortcodes = count($matches);

        foreach ($matches as $match) {
            $full_shortcode = $match[0];
            $attributes = $match[1];

            // Parse attributes
            $atts = $this->parse_shortcode_attributes($attributes);

            // Campos que precisam ser traduzidos
            $translatable_fields = array('title', 'subtitle', 'description', 'button', 'tag', 'button2', 'button3', 'pulse_text');

            foreach ($target_languages as $lang_code) {
                $translated_atts = $atts;

                foreach ($translatable_fields as $field) {
                    if (!empty($atts[$field])) {
                        // Aqui vamos usar o serviço de tradução do multi-languages
                        $translated_text = $this->translate_text($atts[$field], $lang_code);
                        if ($translated_text) {
                            $translated_atts[$field] = $translated_text;
                        }
                    }
                }

                // Reconstroi o shortcode traduzido
                $translated_shortcode = $this->build_shortcode($translated_atts);
                $translated_shortcodes[$lang_code][] = $translated_shortcode;
            }
        }

        // Se replace_original está marcado, substitui os shortcodes no post
        if ($replace_original && !empty($translated_shortcodes)) {
            $updated_content = $content;
            $offset = 0;

            foreach ($matches as $index => $match) {
                $original_shortcode = $match[0];
                $pos = strpos($updated_content, $original_shortcode, $offset);

                if ($pos !== false) {
                    // Substitui pelo shortcode traduzido (usa o primeiro idioma como padrão)
                    $first_lang = array_keys($translated_shortcodes)[0];
                    $replacement = $translated_shortcodes[$first_lang][$index];

                    $updated_content = substr_replace($updated_content, $replacement, $pos, strlen($original_shortcode));
                    $offset = $pos + strlen($replacement);
                }
            }

            // Atualiza o post
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $updated_content
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d CTA Cards traduzidos para %d idiomas.', 'alvobot-pro'), $total_shortcodes, count($target_languages)),
            'translated_shortcodes' => $translated_shortcodes,
            'total_shortcodes' => $total_shortcodes,
            'languages' => $target_languages
        ));
    }

    /**
     * Parse shortcode attributes from string
     */
    private function parse_shortcode_attributes($attr_string) {
        $attributes = array();

        // Remove espaços no início e fim
        $attr_string = trim($attr_string);

        // Pattern para capturar atributos name="value" ou name='value'
        $pattern = '/(\w+)=(?:"([^"]*)"|\'([^\']*)\')*/';
        preg_match_all($pattern, $attr_string, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = isset($match[2]) ? $match[2] : (isset($match[3]) ? $match[3] : '');
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    /**
     * Build shortcode from attributes array
     */
    private function build_shortcode($attributes) {
        $shortcode = '[cta_card';

        foreach ($attributes as $key => $value) {
            if (!empty($value)) {
                $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }

        $shortcode .= ']';
        return $shortcode;
    }

    /**
     * Translate text using the multi-languages service
     */
    private function translate_text($text, $target_language) {
        // Aqui vamos integrar com o serviço de tradução do módulo multi-languages
        // Por enquanto, retorna uma versão mockada

        // Verifica se há uma instância do serviço de tradução
        if (class_exists('AlvoBotPro_MultiLanguages')) {
            $multi_lang_instance = AlvoBotPro_MultiLanguages::get_instance();

            // Se o serviço estiver disponível, usa ele
            // Caso contrário, retorna uma tradução simples com prefixo
            if (method_exists($multi_lang_instance, 'translate_text_simple')) {
                return $multi_lang_instance->translate_text_simple($text, $target_language);
            }
        }

        // Fallback: adiciona prefixo do idioma
        return '[' . strtoupper($target_language) . '] ' . $text;
    }
}