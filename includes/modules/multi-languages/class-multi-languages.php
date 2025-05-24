<?php
/**
 * AlvoBot Pro Multi-Languages Module - Main Class
 *
 * Handles module setup, UI elements (settings page, meta boxes),
 * and loads specialized API handler classes.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AlvoBotPro_MultiLanguages {

    /**
     * Constructor.
     * Initializes hooks for UI components and loads API handlers.
     */
    public function __construct() {
        $this->load_api_handlers();
        
        // UI related hooks
        // Admin menu is now handled centrally in AlvoBotPro class
        add_action('add_meta_boxes', array($this, 'register_change_language_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_module_assets'));
    }

    /**
     * Loads and instantiates the specialized API handler classes.
     *
     * All REST API functionalities are delegated to these classes.
     */
    private function load_api_handlers() {
        $inc_path = plugin_dir_path(__FILE__) . 'includes/';

        $api_classes_files = [
            'base'     => $inc_path . 'class-alvobot-pro-ml-base-api.php',
            'post'     => $inc_path . 'class-alvobot-pro-ml-post-api.php',
            'category' => $inc_path . 'class-alvobot-pro-ml-category-api.php',
            'taxonomy' => $inc_path . 'class-alvobot-pro-ml-taxonomy-api.php',
            'util'     => $inc_path . 'class-alvobot-pro-ml-util-api.php',
        ];

        // Load base class first
        if (file_exists($api_classes_files['base'])) {
            require_once $api_classes_files['base'];
        } else {
            error_log('AlvoBot Pro Multi-Languages: CRITICAL - Base API class file not found.');
            return;
        }
        unset($api_classes_files['base']); // Remove from loop

        // Load other API handlers
        foreach ($api_classes_files as $key => $file_path) {
            if (file_exists($file_path)) {
                require_once $file_path;
                // Construct class name, e.g., AlvoBotPro_MultiLanguages_Post_API
                $class_name = 'AlvoBotPro_MultiLanguages_' . ucfirst($key) . '_API';
                if (class_exists($class_name)) {
                    new $class_name();
                } else {
                    error_log("AlvoBot Pro Multi-Languages: API class {$class_name} not found in {$file_path}.");
                }
            } else {
                error_log("AlvoBot Pro Multi-Languages: API handler file not found: {$file_path}.");
            }
        }
    }

    /**
     * Initialize module (not used for much here as API init is separate).
     */
    public function init() {
        // Additional non-API, non-UI initialization if necessary
    }

    /**
     * Activation logic for the module (if any).
     */
    public function activate() {
        // Example: Set default options for this module if it had its own distinct settings
        // update_option('alvobot_pro_multi_languages_settings', ['some_default' => true]);
    }

    /**
     * Deactivation logic for the module (if any).
     */
    public function deactivate() {
        // Example: Clean up options or transients
        // delete_option('alvobot_pro_multi_languages_settings');
    }

    /**
     * Renders the settings page for the Multi-Languages module.
     * 
     * This page primarily checks for Polylang dependency and links to API documentation.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo '<div class="wrap alvobot-pro-wrap">';
        echo '<div class="alvobot-pro-header"><h1>' . esc_html__('AlvoBot Pro: Multi Idiomas', 'alvobot-pro') . '</h1>';
        echo '<p>' . esc_html__('Este módulo fornece funcionalidades avançadas para gerenciamento de conteúdo multilíngue através da API REST, em conjunto com o plugin Polylang.', 'alvobot-pro') . '</p></div>';


        if (!function_exists('pll_languages_list')) {
            echo '<div id="message" class="notice notice-error"><p>' . esc_html__('O plugin Polylang não está ativo. O módulo Multi Idiomas do AlvoBot Pro requer o Polylang para funcionar corretamente. Por favor, instale e ative o Polylang.', 'alvobot-pro') . '</p></div>';
        } else {
            echo '<div id="message" class="notice notice-success"><p>' . esc_html__('Plugin Polylang detectado. O módulo Multi Idiomas está pronto para uso via API REST.', 'alvobot-pro') . '</p></div>';
        }
        echo '</div>'; // .wrap
    }

    /**
     * Enqueues module-specific admin assets (CSS and JS for the meta box).
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_module_assets($hook) {
        global $post;
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post) {
            if (function_exists('pll_is_translated_post_type') && pll_is_translated_post_type($post->post_type)) {
                
                $css_path = 'includes/modules/multi-languages/assets/css/multi-languages-admin.css';
                $js_path = 'includes/modules/multi-languages/assets/js/multi-languages-admin.js';

                wp_enqueue_style(
                    'alvobot-pro-multi-languages-admin',
                    ALVOBOT_PRO_PLUGIN_URL . $css_path,
                    array(),
                    ALVOBOT_PRO_VERSION
                );

                wp_enqueue_script(
                    'alvobot-pro-multi-languages-admin',
                    ALVOBOT_PRO_PLUGIN_URL . $js_path,
                    array('jquery', 'wp-i18n'), // wp-i18n for JavaScript translations
                    ALVOBOT_PRO_VERSION,
                    true
                );

                $current_language_details = null;
                $current_lang_slug = null;
                if (function_exists('pll_get_post_language')) {
                    $current_lang_slug = pll_get_post_language($post->ID, 'slug');
                    if ($current_lang_slug && function_exists('PLL')) { // Check if PLL() function exists
                         $current_language_details = PLL()->model->get_language($current_lang_slug);
                    }
                }
                
                // Prepare namespace for REST API calls from JS
                $api_namespace = 'alvobot-pro/v1'; // Ensure this matches the one in API classes

                wp_localize_script('alvobot-pro-multi-languages-admin', 'alvobotMultiLang', array(
                    'nonce' => wp_create_nonce('alvobot_change_post_language_nonce'), // For AJAX actions if any
                    'rest_nonce' => wp_create_nonce('wp_rest'), // For REST API
                    'post_id' => $post->ID,
                    'current_language_slug' => $current_lang_slug,
                    'current_language_name' => $current_language_details ? $current_language_details->name : __('Nenhum idioma atribuído', 'alvobot-pro'),
                    'api_url_base' => esc_url_raw(rest_url($api_namespace . '/change-post-language')),
                    'text' => array(
                        'confirm_change_dissociate' => __('Você está prestes a alterar o idioma deste post e dissociá-lo de suas traduções atuais. O post se tornará independente no novo idioma. Continuar?', 'alvobot-pro'),
                        'confirm_change_associate' => __('Você está prestes a alterar o idioma principal deste post. Ele permanecerá conectado às suas traduções existentes. Continuar?', 'alvobot-pro'),
                        'changing_language' => __('Alterando idioma...', 'alvobot-pro'),
                        'language_changed_success' => __('Idioma alterado com sucesso! A página será recarregada para atualizar todas as informações.', 'alvobot-pro'),
                        'error_changing_language' => __('Erro ao alterar idioma:', 'alvobot-pro'),
                        'select_language' => __('Por favor, selecione um novo idioma.', 'alvobot-pro'),
                    )
                ));
            }
        }
    }

    /**
     * Registers the meta box for changing post language on translatable post types.
     */
    public function register_change_language_meta_box() {
        if (!function_exists('pll_is_translated_post_type') || !function_exists('pll_get_post_types')) {
            return; // Polylang not active or functions not available
        }

        $translatable_post_types = pll_get_post_types(['hide_empty' => false, 'public' => true]); 
        foreach ($translatable_post_types as $post_type_slug => $post_type_obj) {
             if (is_string($post_type_slug)) { // Polylang >3.0 returns objects, <3.0 returns slugs
                add_meta_box(
                    'alvobot_change_post_language_mb',
                    __('Idioma do Post (AlvoBot Pro)', 'alvobot-pro'),
                    array($this, 'render_change_language_meta_box'),
                    $post_type_slug,
                    'side', 
                    'low' 
                );
             } elseif (is_object($post_type_obj) && isset($post_type_obj->name)) { // For Polylang 3.0+
                 add_meta_box(
                    'alvobot_change_post_language_mb',
                    __('Idioma do Post (AlvoBot Pro)', 'alvobot-pro'),
                    array($this, 'render_change_language_meta_box'),
                    $post_type_obj->name,
                    'side', 
                    'low' 
                );
             }
        }
    }

    /**
     * Renders the content of the "Change Post Language" meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_change_language_meta_box($post) {
        if (!function_exists('pll_languages_list') || !function_exists('pll_get_post_language') || !function_exists('PLL')) {
            echo '<p>' . esc_html__('O plugin Polylang não está ativo ou funções essenciais não estão disponíveis.', 'alvobot-pro') . '</p>';
            return;
        }

        wp_nonce_field('alvobot_change_post_language_action', 'alvobot_change_post_language_nonce_field');

        $current_lang_slug = pll_get_post_language($post->ID, 'slug');
        $current_lang_obj = $current_lang_slug ? PLL()->model->get_language($current_lang_slug) : null;
        $all_languages = pll_languages_list(array('fields' => '', 'hide_empty' => false)); 

        ?>
        <div id="alvobot-change-language-meta-box-content">
            <p>
                <strong><?php esc_html_e('Idioma Atual:', 'alvobot-pro'); ?></strong>
                <span id="alvobot-current-language-display">
                    <?php if ($current_lang_obj && isset($current_lang_obj->flag_url) && isset($current_lang_obj->name) && isset($current_lang_obj->slug)) : ?>
                        <img src="<?php echo esc_url($current_lang_obj->flag_url); ?>" alt="<?php echo esc_attr($current_lang_obj->name); ?>" style="vertical-align:middle; margin-right: 5px;" />
                        <?php echo esc_html($current_lang_obj->name); ?> (<?php echo esc_html($current_lang_obj->slug); ?>)
                    <?php else : ?>
                        <?php esc_html_e('Nenhum idioma atribuído', 'alvobot-pro'); ?>
                    <?php endif; ?>
                </span>
            </p>

            <p>
                <label for="alvobot_new_language_code"><?php esc_html_e('Alterar para o Idioma:', 'alvobot-pro'); ?></label><br>
                <select name="alvobot_new_language_code" id="alvobot_new_language_code" style="width:100%;">
                    <option value=""><?php esc_html_e('-- Selecione um Idioma --', 'alvobot-pro'); ?></option>
                    <?php if (is_array($all_languages)) : ?>
                        <?php foreach ($all_languages as $language) : ?>
                            <?php if ($current_lang_slug === $language->slug) continue; // Skip current language ?>
                            <option value="<?php echo esc_attr($language->slug); ?>">
                                <?php echo esc_html($language->name); ?> (<?php echo esc_html($language->slug); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="alvobot_update_translations" id="alvobot_update_translations" value="1" checked="checked" />
                    <?php esc_html_e('Manter este post conectado às suas traduções atuais?', 'alvobot-pro'); ?>
                </label>
                <span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Se marcado, o post permanecerá no mesmo grupo de tradução, apenas o seu idioma principal será alterado. Se desmarcado, este post se tornará independente no novo idioma, e as conexões com traduções anteriores serão removidas.', 'alvobot-pro'); ?>"></span>
            </p>

            <button type="button" id="alvobot-change-language-button" class="button button-primary">
                <?php esc_html_e('Alterar Idioma do Post', 'alvobot-pro'); ?>
            </button>
            <span id="alvobot-change-language-spinner" class="spinner" style="float:none; vertical-align: middle;"></span>
            <div id="alvobot-change-language-feedback" style="margin-top:10px;"></div>
        </div>
        <?php
    }
    
    /**
     * Obtém o ID de um termo traduzido. 
     * This is a general helper that might be used by UI elements if needed.
     *
     * @param int    $term_id        Term ID.
     * @param string $taxonomy       Taxonomy slug.
     * @param string $language_code  Language code (slug).
     * @return int|null Term ID of the translation if it exists, else original term_id or null.
     */
    private function get_translated_term($term_id, $taxonomy, $language_code) {
        if (!function_exists('pll_get_term')) {
            return $term_id; // Polylang not active or function unavailable
        }
        
        $translated_term_id = pll_get_term($term_id, $language_code);
        return $translated_term_id ? (int) $translated_term_id : (int) $term_id; // Return original if no translation
    }
}
