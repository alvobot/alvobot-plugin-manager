<?php
/**
 * AlvoBot Pro Multi-Languages Utility API Class
 * 
 * Provides utility functions and endpoints for the Multi-Languages module.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AlvoBotPro_MultiLanguages_Util_API
 * Utility API handler for Multi-Languages module.
 */
class AlvoBotPro_MultiLanguages_Util_API extends AlvoBotPro_MultiLanguages_Base_API {

    /**
     * Constructor.
     * Registers utility REST API endpoints.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Register endpoint for retrieving language information
        register_rest_route(
            $this->namespace . '/multi-languages',
            '/languages',
            array(
                'methods'  => 'GET',
                'callback' => array($this, 'get_languages'),
                'permission_callback' => array($this, 'get_permissions_check'),
            )
        );

        // Register endpoint for plugin status check
        register_rest_route(
            $this->namespace . '/multi-languages',
            '/status',
            array(
                'methods'  => 'GET',
                'callback' => array($this, 'get_status'),
                'permission_callback' => array($this, 'get_permissions_check'),
            )
        );
    }

    /**
     * Check if the user has permissions to access these endpoints.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool
     */
    public function get_permissions_check($request) {
        // Only allow authenticated users with manage_options capability
        return current_user_can('manage_options');
    }

    /**
     * Get available languages from Polylang or WPML.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_languages($request) {
        $languages = array();

        // Check for Polylang
        if (function_exists('pll_languages_list')) {
            $polylang_languages = pll_languages_list(array('fields' => 'slug'));
            
            foreach ($polylang_languages as $lang) {
                $languages[] = array(
                    'code' => $lang,
                    'name' => $this->get_language_name($lang),
                    'source' => 'polylang'
                );
            }
        } 
        // Check for WPML
        elseif (defined('ICL_SITEPRESS_VERSION')) {
            global $sitepress;
            
            if ($sitepress) {
                $wpml_languages = $sitepress->get_active_languages();
                
                foreach ($wpml_languages as $lang_code => $lang_data) {
                    $languages[] = array(
                        'code' => $lang_code,
                        'name' => $lang_data['display_name'],
                        'source' => 'wpml'
                    );
                }
            }
        }

        if (empty($languages)) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'No multilingual plugin detected or no languages configured.'
                ),
                200
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'languages' => $languages
            ),
            200
        );
    }

    /**
     * Get plugin status information.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response Response object.
     */
    public function get_status($request) {
        $status = array(
            'active' => true,
            'version' => defined('ALVOBOT_PRO_VERSION') ? ALVOBOT_PRO_VERSION : '1.0.0',
            'multilingual_plugin' => $this->detect_multilingual_plugin(),
        );

        return new WP_REST_Response($status, 200);
    }

    /**
     * Helper to detect which multilingual plugin is active.
     *
     * @return string Name of the detected plugin or 'none'.
     */
    private function detect_multilingual_plugin() {
        if (function_exists('pll_languages_list')) {
            return 'polylang';
        } elseif (defined('ICL_SITEPRESS_VERSION')) {
            return 'wpml';
        }
        return 'none';
    }

    /**
     * Get language name for a given language code.
     *
     * @param string $code Language code.
     * @return string Language name.
     */
    private function get_language_name($code) {
        // Basic mapping for common languages
        $languages = array(
            'en' => 'English',
            'pt' => 'Português',
            'pt_BR' => 'Português do Brasil',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ru' => 'Russian',
        );

        return isset($languages[$code]) ? $languages[$code] : $code;
    }
}
