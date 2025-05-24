<?php
/**
 * AlvoBot Pro Multi-Languages General Taxonomy API Class
 *
 * Handles REST API endpoints for general taxonomy information and term listings.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AlvoBotPro_MultiLanguages_Taxonomy_API
 * Manages API endpoints for general taxonomy operations related to Polylang.
 */
class AlvoBotPro_MultiLanguages_Taxonomy_API extends AlvoBotPro_MultiLanguages_Base_API {

    /**
     * Constructor.
     * Registers REST API routes for taxonomy operations.
     */
    public function __construct() {
        parent::__construct();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes for taxonomy information.
     */
    public function register_rest_routes() {
        // Rotas para gerenciamento de taxonomias
        register_rest_route($this->namespace, '/taxonomies', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_taxonomies'),
            'permission_callback' => array($this, 'public_permissions_check'),
        ]);
        
        register_rest_route($this->namespace, '/taxonomy/terms', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_taxonomy_terms'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => array_merge(
                parent::get_pagination_args(10, 'Number of terms to return per page.'),
                [
                    'taxonomy' => ['required' => true, 'validate_callback' => 'taxonomy_exists', 'description' => 'The taxonomy slug.'],
                ]
            ),
        ]);
        
        register_rest_route($this->namespace, '/taxonomy/untranslated', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_untranslated_terms'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => array_merge(
                parent::get_pagination_args(10, 'Number of terms to return per page.'),
                [
                    'taxonomy' => ['required' => true, 'validate_callback' => 'taxonomy_exists', 'description' => 'The taxonomy slug.'],
                ]
            ),
        ]);
    }

    // --- Callback Implementations (Moved from AlvoBotPro_MultiLanguages) ---

    /**
     * Retrieves a list of all public, Polylang-translatable taxonomies.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function get_taxonomies($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $result = [];
        
        foreach ($taxonomies as $taxonomy) {
            if (pll_is_translated_taxonomy($taxonomy->name)) {
                $result[] = [
                    'name' => $taxonomy->name,
                    'label' => $taxonomy->label,
                    'description' => $taxonomy->description,
                    'hierarchical' => $taxonomy->hierarchical,
                    'rest_base' => $taxonomy->rest_base
                ];
            }
        }
        return new WP_REST_Response($result);
    }
    
    /**
     * Retrieves a paginated list of terms for a given taxonomy, along with their translations.
     *
     * @param WP_REST_Request $request The REST API request. Requires 'taxonomy' parameter.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function get_taxonomy_terms($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);

        if (!pll_is_translated_taxonomy($taxonomy)) {
            return new WP_Error('taxonomy_not_translatable', __('Esta taxonomia não é traduzível pelo Polylang.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $args = ['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty, 'number' => $per_page, 'offset' => ($page - 1) * $per_page];
        $terms = get_terms($args);
        if (is_wp_error($terms)) return $terms;
        
        $result = [];
        foreach ($terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            $translated_terms_data = [];
            foreach ($translations as $lang => $term_id) {
                $translated_term = get_term($term_id, $taxonomy);
                if ($translated_term && !is_wp_error($translated_term)) {
                    $translated_terms_data[$lang] = ['id' => $term_id, 'name' => $translated_term->name, 'slug' => $translated_term->slug, 'description' => $translated_term->description, 'count' => $translated_term->count];
                }
            }
            $result[] = ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'count' => $term->count, 'language' => pll_get_term_language($term->term_id, 'slug'), 'translations' => $translated_terms_data];
        }
        
        $total_terms = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty]);
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $total_terms);
        $response->header('X-WP-TotalPages', ceil($total_terms / $per_page));
        return $response;
    }
    
    /**
     * Retrieves a paginated list of terms within a taxonomy that are missing translations
     * in one or more languages.
     *
     * @param WP_REST_Request $request The REST API request. Requires 'taxonomy' parameter.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function get_untranslated_terms($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);

        if (!pll_is_translated_taxonomy($taxonomy)) {
            return new WP_Error('taxonomy_not_translatable', __('Esta taxonomia não é traduzível pelo Polylang.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $languages = pll_languages_list();
        if (empty($languages)) return new WP_REST_Response([]);
        
        $all_terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($all_terms)) return $all_terms;
        
        $untranslated_terms = [];
        foreach ($all_terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            $missing_langs = array_diff($languages, array_keys($translations));
            if (!empty($missing_langs)) {
                $untranslated_terms[] = ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'count' => $term->count, 'current_language' => pll_get_term_language($term->term_id, 'slug'), 'missing_translations' => array_values($missing_langs)];
            }
        }
        
        $total_items = count($untranslated_terms);
        $paginated_items = array_slice($untranslated_terms, ($page - 1) * $per_page, $per_page);
        
        $response = new WP_REST_Response($paginated_items);
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', ceil($total_items / $per_page));
        return $response;
    }
}
