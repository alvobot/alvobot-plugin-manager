<?php
/**
 * AlvoBot Pro Multi-Languages Category API Class
 *
 * Handles REST API endpoints related to category translations.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AlvoBotPro_MultiLanguages_Category_API
 * Manages API endpoints for category translations.
 */
class AlvoBotPro_MultiLanguages_Category_API extends AlvoBotPro_MultiLanguages_Base_API {

    /**
     * Constructor.
     * Registers REST API routes for category translations.
     */
    public function __construct() {
        parent::__construct();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes for category translations.
     */
    public function register_rest_routes() {
        // Rotas para tradução de categorias
        register_rest_route($this->namespace, '/translate/category', [
            [
                'methods' => WP_REST_Server::CREATABLE, // POST
                'callback' => array($this, 'create_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_category_translation_args(true), // is_creating = true
            ],
            [
                'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH
                'callback' => array($this, 'update_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_category_translation_args(false), // is_creating = false
            ],
            [
                'methods' => WP_REST_Server::DELETABLE, // DELETE
                'callback' => array($this, 'delete_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_basic_category_args(),
            ]
        ]);
        
        // Rota para listar traduções de categorias
        register_rest_route($this->namespace, '/translations/categories', [
            'methods' => WP_REST_Server::READABLE, // GET
            'callback' => array($this, 'get_categories_translations'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => parent::get_pagination_args(10, 'Number of categories to return per page.'),
        ]);
    }

    // --- Argument Helper Methods ---

    /**
     * Gets basic arguments for category translation endpoints (category_id, language_code).
     *
     * @return array Array of argument definitions.
     */
    protected function get_basic_category_args() {
        return [
            'category_id' => ['required' => true, 'validate_callback' => 'is_numeric', 'description' => 'ID of the original category.'],
            'language_code' => ['required' => true, 'validate_callback' => 'is_string', 'description' => 'Two-letter language code for the translation.'],
        ];
    }

    /**
     * Gets arguments for creating or updating a category translation.
     *
     * @param bool $is_creating True if for creation (name is required), false for update.
     * @return array Array of argument definitions.
     */
    protected function get_category_translation_args($is_creating = false) {
        $base_args = $this->get_basic_category_args();
        $content_args = [
            'name' => ['required' => $is_creating, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'description' => 'Name of the translated category.'],
            'description' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'description' => 'Description of the translated category.'],
            'slug' => ['type' => 'string', 'sanitize_callback' => 'sanitize_title', 'description' => 'Slug for the translated category.'],
            // 'parent' could be added here if needed for creation/update
        ];
        return array_merge($base_args, $content_args);
    }


    // --- Callback Implementations (Moved from AlvoBotPro_MultiLanguages) ---
    
    /**
     * Creates a new translation for a category.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function create_category_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) return new WP_Error('category_not_found', __('Categoria original não encontrada.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translations = pll_get_term_translations($category_id);
        if (isset($translations[$language_code])) return new WP_Error('translation_exists', __('Já existe uma tradução para este idioma.', 'alvobot-pro'), ['status' => 400]);
        
        $term_data = ['name' => $params['name']];
        if (isset($params['description'])) $term_data['description'] = $params['description'];
        if (isset($params['slug'])) $term_data['slug'] = $params['slug'];
        
        if ($category->parent > 0) {
            $parent_translation_id = pll_get_term($category->parent, $language_code);
            if ($parent_translation_id) $term_data['parent'] = $parent_translation_id;
        }
        
        $result = wp_insert_term($term_data['name'], 'category', $term_data);
        if (is_wp_error($result)) {
            $this->log_action('create_category_translation', 'error', 'Erro ao criar tradução de categoria', ['cat_id' => $category_id, 'lang' => $language_code, 'error' => $result->get_error_message()]);
            return $result;
        }
        
        $translated_category_id = $result['term_id'];
        pll_set_term_language($translated_category_id, $language_code);
        $translations[$language_code] = $translated_category_id;
        pll_save_term_translations($translations);
        
        $this->log_action('create_category_translation', 'success', 'Tradução de categoria criada', ['cat_id' => $category_id, 'lang' => $language_code, 'new_id' => $translated_category_id]);
        return new WP_REST_Response(['success' => true, 'category_id' => $translated_category_id, 'message' => __('Tradução de categoria criada com sucesso.', 'alvobot-pro')], 201);
    }
    
    /**
     * Updates an existing category translation.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function update_category_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $category_id = intval($params['category_id']); // ID of the original category
        $language_code = sanitize_text_field($params['language_code']);

        $translations = pll_get_term_translations($category_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_category_id = $translations[$language_code];
        $term_data = [];
        if (isset($params['name'])) $term_data['name'] = $params['name'];
        if (isset($params['description'])) $term_data['description'] = $params['description'];
        if (isset($params['slug'])) $term_data['slug'] = $params['slug'];
        // Parent update could be added if needed

        if (empty($term_data)) return new WP_Error('no_data_to_update', __('Nenhum dado fornecido para atualização.', 'alvobot-pro'), ['status' => 400]);

        $result = wp_update_term($translated_category_id, 'category', $term_data);
        if (is_wp_error($result)) {
            $this->log_action('update_category_translation', 'error', 'Erro ao atualizar tradução de categoria', ['cat_id' => $translated_category_id, 'lang' => $language_code, 'error' => $result->get_error_message()]);
            return $result;
        }
        
        $this->log_action('update_category_translation', 'success', 'Tradução de categoria atualizada', ['cat_id' => $translated_category_id, 'lang' => $language_code]);
        return new WP_REST_Response(['success' => true, 'category_id' => $translated_category_id, 'message' => __('Tradução de categoria atualizada com sucesso.', 'alvobot-pro')]);
    }
    
    /**
     * Deletes a category translation.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function delete_category_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $category_id = intval($params['category_id']); // ID of any category in the translation group
        $language_code = sanitize_text_field($params['language_code']);

        $translations = pll_get_term_translations($category_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_category_id_to_delete = $translations[$language_code];

        // Prevent deleting the category if it's the only one in the group or the 'original' request ID
        if (count($translations) === 1 && $translations[$language_code] === $category_id) {
             return new WP_Error('cannot_delete_last_category', __('Não é possível excluir a única versão da categoria ou a categoria original desta forma.', 'alvobot-pro'), ['status' => 400]);
        }
         if ($category_id === $translated_category_id_to_delete && count($translations) > 1) {
             return new WP_Error('cannot_delete_original_category_if_others_exist', __('Esta é a categoria de referência para outras traduções. Exclua as outras traduções primeiro.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $result = wp_delete_term($translated_category_id_to_delete, 'category');
        if (is_wp_error($result) || !$result) {
            $this->log_action('delete_category_translation', 'error', 'Erro ao excluir tradução de categoria', ['cat_id' => $category_id, 'lang' => $language_code, 'deleted_id' => $translated_category_id_to_delete]);
            return new WP_Error('delete_failed', __('Falha ao excluir a tradução de categoria.', 'alvobot-pro'), ['status' => 500]);
        }
        
        // Polylang should clean up its data upon term deletion.
        // If manual update of pll_save_term_translations is needed:
        // unset($translations[$language_code]);
        // pll_save_term_translations($translations);

        $this->log_action('delete_category_translation', 'success', 'Tradução de categoria excluída', ['cat_id' => $category_id, 'lang' => $language_code, 'deleted_id' => $translated_category_id_to_delete]);
        return new WP_REST_Response(['success' => true, 'message' => __('Tradução de categoria excluída com sucesso.', 'alvobot-pro')]);
    }
    
    /**
     * Retrieves a paginated list of categories and their translations.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response|WP_Error The REST API response or error object.
     */
    public function get_categories_translations($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        $args = ['taxonomy' => 'category', 'hide_empty' => $hide_empty, 'number' => $per_page, 'offset' => ($page - 1) * $per_page];
        $categories = get_terms($args);
        if (is_wp_error($categories)) return $categories;
        
        $result = [];
        foreach ($categories as $category) {
            $translations = pll_get_term_translations($category->term_id);
            $translated_categories_data = [];
            foreach ($translations as $lang => $term_id) {
                $term = get_term($term_id, 'category');
                if ($term && !is_wp_error($term)) {
                    $translated_categories_data[$lang] = ['id' => $term_id, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'count' => $term->count];
                }
            }
            $result[] = ['id' => $category->term_id, 'name' => $category->name, 'slug' => $category->slug, 'description' => $category->description, 'count' => $category->count, 'language' => pll_get_term_language($category->term_id, 'slug'), 'translations' => $translated_categories_data];
        }
        
        $total_categories = wp_count_terms('category', ['hide_empty' => $hide_empty]);
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $total_categories);
        $response->header('X-WP-TotalPages', ceil($total_categories / $per_page));
        return $response;
    }
}
