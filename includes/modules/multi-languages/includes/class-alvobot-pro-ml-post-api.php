<?php
/**
 * AlvoBot Pro Multi-Languages Post API Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AlvoBotPro_MultiLanguages_Post_API extends AlvoBotPro_MultiLanguages_Base_API {

    public function __construct() {
        parent::__construct();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes() {
        // Rota para alterar o idioma de um post existente
        register_rest_route($this->namespace, '/change-post-language', [
            'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH
            'callback' => array($this, 'change_post_language'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => [
                'post_id' => ['required' => true, 'validate_callback' => 'is_numeric'],
                'language_code' => ['required' => true, 'validate_callback' => 'is_string'],
                'update_translations' => ['required' => false, 'default' => true, 'validate_callback' => 'rest_is_boolean'],
            ]
        ]);
        
        // Rotas para tradução de posts
        register_rest_route($this->namespace, '/translate', [
            'methods' => WP_REST_Server::CREATABLE, // POST
            'callback' => array($this, 'create_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_post_translation_args(true), // is_creating = true
        ]);
        
        register_rest_route($this->namespace, '/translate', [
            'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH
            'callback' => array($this, 'update_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_post_translation_args(false), // is_creating = false
        ]);
        
        register_rest_route($this->namespace, '/translate', [
            'methods' => WP_REST_Server::DELETABLE, // DELETE
            'callback' => array($this, 'delete_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_basic_post_translation_args(),
        ]);
        
        // Rota para listar traduções de posts
        register_rest_route($this->namespace, '/translations', [
            'methods' => WP_REST_Server::READABLE, // GET
            'callback' => array($this, 'get_translations'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => $this->get_pagination_args_for_posts(),
        ]);
        
        // Rota para verificar existência de tradução
        register_rest_route($this->namespace, '/translations/check', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'check_translation_existence'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => $this->get_basic_post_translation_args(),
        ]);
        
        // Rota para listar posts sem traduções
        register_rest_route($this->namespace, '/translations/missing', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_posts_missing_translations'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => $this->get_pagination_args_for_posts(),
        ]);

        // Rotas para gerenciamento de slugs de posts
        register_rest_route($this->namespace, '/slugs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_all_slugs'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => $this->get_pagination_args_for_posts(),
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [
            'methods' => WP_REST_Server::CREATABLE, // POST
            'callback' => array($this, 'create_slug_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_slug_translation_args(),
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [ // Also used for update
            'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH
            'callback' => array($this, 'update_slug_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_slug_translation_args(),
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_slug_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_basic_post_translation_args(),
        ]);

        // Rota para obter URL de um post em um idioma específico
        register_rest_route($this->namespace, '/language-url', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_language_url'),
            'permission_callback' => array($this, 'public_permissions_check'),
            'args' => [
                'post_id' => ['required' => true, 'validate_callback' => 'is_numeric'],
                'language_code' => ['required' => true, 'validate_callback' => 'is_string'],
            ]
        ]);
    }

    // --- Argument Helper Methods ---
    protected function get_pagination_args_for_posts() {
        return array_merge(
            parent::get_pagination_args(10, 'Number of posts to return per page.'),
            [
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) { return post_type_exists($param); },
                    'description' => 'Post type to retrieve.',
                ]
            ]
        );
    }

    protected function get_basic_post_translation_args() {
        return [
            'post_id' => ['required' => true, 'validate_callback' => 'is_numeric'],
            'language_code' => ['required' => true, 'validate_callback' => 'is_string'],
        ];
    }

    protected function get_post_translation_args($is_creating = false) {
        $base_args = $this->get_basic_post_translation_args();
        $content_args = [
            'title' => ['required' => $is_creating, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'content' => ['required' => $is_creating, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'excerpt' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'slug' => ['type' => 'string', 'sanitize_callback' => 'sanitize_title'],
            'date' => ['type' => 'string', 'format' => 'date-time', 'validate_callback' => function($param){ return strtotime($param) !== false; } ],
            'categories' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'featured_media' => ['type' => 'integer', 'validate_callback' => 'is_numeric'],
            'meta_input' => ['type' => 'object', 'properties' => [], 'additionalProperties' => true], // Allows any meta
        ];
        return array_merge($base_args, $content_args);
    }

    protected function get_slug_translation_args() {
        return array_merge(
            $this->get_basic_post_translation_args(),
            ['slug' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_title']]
        );
    }

    // --- Callback Implementations (Moved from AlvoBotPro_MultiLanguages) ---

    public function change_post_language($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $update_translations = filter_var($params['update_translations'], FILTER_VALIDATE_BOOLEAN);
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        pll_set_post_language($post_id, $language_code);
        
        if ($update_translations) {
            $translations = pll_get_post_translations($post_id);
            $translations[$language_code] = $post_id; // Ensure current post is in its own language slot
            pll_save_post_translations($translations);
        }
        
        $this->log_action('change_post_language', 'success', 'Idioma do post alterado', ['post_id' => $post_id, 'lang' => $language_code]);
        return new WP_REST_Response(['success' => true, 'post_id' => $post_id, 'language_code' => $language_code, 'message' => __('Idioma do post alterado com sucesso.', 'alvobot-pro')]);
    }

    public function create_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;

        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        $post = get_post($post_id);
        if (!$post) return new WP_Error('post_not_found', __('Post original não encontrado.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translations = pll_get_post_translations($post_id);
        if (isset($translations[$language_code])) return new WP_Error('translation_exists', __('Já existe uma tradução para este idioma.', 'alvobot-pro'), ['status' => 400]);
        
        $post_data = ['post_title' => $params['title'], 'post_content' => $params['content'], 'post_status' => $post->post_status, 'post_type' => $post->post_type, 'post_author' => get_current_user_id()];
        if (isset($params['excerpt'])) $post_data['post_excerpt'] = $params['excerpt'];
        if (isset($params['slug'])) $post_data['post_name'] = $params['slug'];
        if (isset($params['date'])) { $post_data['post_date'] = $params['date']; $post_data['post_date_gmt'] = get_gmt_from_date($params['date']);}
        
        $translated_post_id = wp_insert_post($post_data, true);
        if (is_wp_error($translated_post_id)) {
            $this->log_action('create_translation', 'error', 'Erro ao criar tradução', ['post_id' => $post_id, 'lang' => $language_code, 'error' => $translated_post_id->get_error_message()]);
            return $translated_post_id;
        }
        
        pll_set_post_language($translated_post_id, $language_code);
        $translations[$language_code] = $translated_post_id;
        pll_save_post_translations($translations);
        
        if (isset($params['categories']) && is_array($params['categories'])) {
            $translated_categories = [];
            foreach ($params['categories'] as $category_id) {
                $translated_cat_id = pll_get_term($category_id, $language_code);
                if ($translated_cat_id) $translated_categories[] = $translated_cat_id; else $translated_categories[] = $category_id;
            }
            wp_set_post_terms($translated_post_id, $translated_categories, 'category');
        }
        if (isset($params['featured_media']) && $params['featured_media']) set_post_thumbnail($translated_post_id, intval($params['featured_media']));
        if (isset($params['meta_input']) && is_array($params['meta_input'])) {
            foreach ($params['meta_input'] as $meta_key => $meta_value) update_post_meta($translated_post_id, sanitize_key($meta_key), $meta_value);
        }
        
        $this->log_action('create_translation', 'success', 'Tradução criada', ['post_id' => $post_id, 'lang' => $language_code, 'new_id' => $translated_post_id]);
        return new WP_REST_Response(['success' => true, 'post_id' => $translated_post_id, 'message' => __('Tradução criada com sucesso.', 'alvobot-pro')], 201);
    }

    public function update_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']); // ID of the original post, or any post in the translation group
        $language_code = sanitize_text_field($params['language_code']);

        $translations = pll_get_post_translations($post_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_post_id = $translations[$language_code];
        $post_data = ['ID' => $translated_post_id];

        if (isset($params['title'])) $post_data['post_title'] = $params['title'];
        if (isset($params['content'])) $post_data['post_content'] = $params['content'];
        if (isset($params['excerpt'])) $post_data['post_excerpt'] = $params['excerpt'];
        if (isset($params['slug'])) $post_data['post_name'] = $params['slug'];
        if (isset($params['date'])) { $post_data['post_date'] = $params['date']; $post_data['post_date_gmt'] = get_gmt_from_date($params['date']);}

        $result = wp_update_post($post_data, true);
        if (is_wp_error($result)) {
            $this->log_action('update_translation', 'error', 'Erro ao atualizar tradução', ['post_id' => $translated_post_id, 'lang' => $language_code, 'error' => $result->get_error_message()]);
            return $result;
        }

        if (isset($params['categories']) && is_array($params['categories'])) {
            $translated_categories = [];
            foreach ($params['categories'] as $category_id) {
                $translated_cat_id = pll_get_term($category_id, $language_code);
                if ($translated_cat_id) $translated_categories[] = $translated_cat_id; else $translated_categories[] = $category_id;
            }
            wp_set_post_terms($translated_post_id, $translated_categories, 'category');
        }
        if (isset($params['featured_media'])) {
            if ($params['featured_media']) set_post_thumbnail($translated_post_id, intval($params['featured_media']));
            else delete_post_thumbnail($translated_post_id);
        }
         if (isset($params['meta_input']) && is_array($params['meta_input'])) {
            foreach ($params['meta_input'] as $meta_key => $meta_value) update_post_meta($translated_post_id, sanitize_key($meta_key), $meta_value);
        }

        $this->log_action('update_translation', 'success', 'Tradução atualizada', ['post_id' => $translated_post_id, 'lang' => $language_code]);
        return new WP_REST_Response(['success' => true, 'post_id' => $translated_post_id, 'message' => __('Tradução atualizada com sucesso.', 'alvobot-pro')]);
    }

    public function delete_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']); // ID of any post in the translation group
        $language_code = sanitize_text_field($params['language_code']);

        $translations = pll_get_post_translations($post_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_post_id_to_delete = $translations[$language_code];
        
        // Prevent deleting the only remaining post in a group, or if it's the "main" post for the group
        // This logic can be complex with Polylang. For now, ensure we are not deleting the $post_id itself if it's the only one.
        if (count($translations) === 1 && isset($translations[$language_code]) && $translations[$language_code] === $post_id) {
            return new WP_Error('cannot_delete_last', __('Não é possível excluir a única versão do post ou o post original desta forma.', 'alvobot-pro'), ['status' => 400]);
        }
        // Also, Polylang might not allow deleting the post that is the reference for a language if other translations depend on it.
        // Safer to just remove the translation link and let user delete the post manually if needed.
        // However, the original code attempts wp_delete_post.
        
        // If the post to delete is the one identified by $post_id, and there are other translations, disallow.
        $is_original_request_id = ($post_id === $translated_post_id_to_delete);
        if ($is_original_request_id && count($translations) > 1) {
             return new WP_Error('cannot_delete_original_if_others_exist', __('Este é o post de referência para outras traduções. Exclua as outras traduções primeiro ou altere o idioma deste post.', 'alvobot-pro'), ['status' => 400]);
        }


        $result = wp_delete_post($translated_post_id_to_delete, true); // true = force delete
        
        if (!$result) {
            $this->log_action('delete_translation', 'error', 'Erro ao excluir tradução', ['post_id' => $post_id, 'lang' => $language_code, 'deleted_id' => $translated_post_id_to_delete]);
            return new WP_Error('delete_failed', __('Falha ao excluir a tradução.', 'alvobot-pro'), ['status' => 500]);
        }
        
        // Polylang should handle cleaning up its translation relationships upon post deletion.
        // If explicit removal from group is needed before deletion:
        // unset($translations[$language_code]);
        // pll_save_post_translations($translations);

        $this->log_action('delete_translation', 'success', 'Tradução excluída', ['post_id' => $post_id, 'lang' => $language_code, 'deleted_id' => $translated_post_id_to_delete]);
        return new WP_REST_Response(['success' => true, 'message' => __('Tradução excluída com sucesso.', 'alvobot-pro')]);
    }

    public function get_translations($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);
        // $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN); // Not directly used by WP_Query for this logic

        $args = ['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page];
        $query = new WP_Query($args);
        $posts = $query->posts;
        $result = [];

        foreach ($posts as $post_obj) {
            $translations = pll_get_post_translations($post_obj->ID);
            $translated_posts_data = [];
            foreach ($translations as $lang => $translated_id) {
                $translated_post = get_post($translated_id);
                if ($translated_post) {
                    $translated_posts_data[$lang] = ['id' => $translated_id, 'title' => $translated_post->post_title, 'slug' => $translated_post->post_name, 'excerpt' => $translated_post->post_excerpt, 'date' => $translated_post->post_date];
                }
            }
            $result[] = ['id' => $post_obj->ID, 'title' => $post_obj->post_title, 'type' => $post_obj->post_type, 'language' => pll_get_post_language($post_obj->ID, 'slug'), 'translations' => $translated_posts_data];
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        return $response;
    }

    public function check_translation_existence($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);

        if (!get_post($post_id)) return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translations = pll_get_post_translations($post_id);
        $exists = isset($translations[$language_code]);
        return new WP_REST_Response(['exists' => $exists, 'post_id' => $exists ? $translations[$language_code] : null]);
    }

    public function get_posts_missing_translations($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);

        $languages = pll_languages_list();
        if (empty($languages)) return new WP_REST_Response([]);

        // This can be very inefficient for large sites. Consider alternatives if performance is an issue.
        $all_posts_args = ['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'];
        $all_post_ids = get_posts($all_posts_args);
        
        $posts_with_missing_translations = [];
        foreach ($all_post_ids as $current_post_id) {
            $translations = pll_get_post_translations($current_post_id);
            $missing_langs = array_diff($languages, array_keys($translations));
            if (!empty($missing_langs)) {
                $post_obj = get_post($current_post_id);
                $posts_with_missing_translations[] = ['id' => $current_post_id, 'title' => $post_obj->post_title, 'type' => $post_obj->post_type, 'language' => pll_get_post_language($current_post_id, 'slug'), 'translations' => $translations, 'missing_translations' => array_values($missing_langs)];
            }
        }
        
        $total_items = count($posts_with_missing_translations);
        $paginated_items = array_slice($posts_with_missing_translations, ($page - 1) * $per_page, $per_page);
        
        $response = new WP_REST_Response($paginated_items);
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', ceil($total_items / $per_page));
        return $response;
    }
    
    public function get_all_slugs($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);

        $args = ['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page];
        $query = new WP_Query($args);
        $posts = $query->posts;
        $result = [];

        foreach ($posts as $post_obj) {
            $language = pll_get_post_language($post_obj->ID, 'slug');
            if (!$language) continue;
            
            $translations = pll_get_post_translations($post_obj->ID);
            $slugs = [];
            foreach ($translations as $lang => $translated_id) {
                $translated_post = get_post($translated_id);
                if ($translated_post) $slugs[$lang] = $translated_post->post_name;
            }
            $result[] = ['id' => $post_obj->ID, 'title' => $post_obj->post_title, 'slug' => $post_obj->post_name, 'language' => $language, 'translated_slugs' => $slugs];
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        return $response;
    }

    public function create_slug_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $slug = sanitize_title($params['slug']);

        $post = get_post($post_id);
        if (!$post) return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translations = pll_get_post_translations($post_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Não existe uma tradução para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_post_id = $translations[$language_code];
        $slug_exists = get_page_by_path($slug, OBJECT, $post->post_type);
        if ($slug_exists && $slug_exists->ID != $translated_post_id) return new WP_Error('slug_exists', __('Este slug já está em uso por outro post.', 'alvobot-pro'), ['status' => 400]);
        
        $result = wp_update_post(['ID' => $translated_post_id, 'post_name' => $slug], true);
        if (is_wp_error($result)) {
            $this->log_action('create_slug_translation', 'error', 'Erro ao criar tradução de slug', ['post_id' => $post_id, 'lang' => $language_code, 'slug' => $slug, 'error' => $result->get_error_message()]);
            return $result;
        }
        
        $this->log_action('create_slug_translation', 'success', 'Tradução de slug criada', ['post_id' => $post_id, 'lang' => $language_code, 'slug' => $slug]);
        return new WP_REST_Response(['success' => true, 'post_id' => $translated_post_id, 'slug' => $slug, 'message' => __('Tradução de slug criada com sucesso.', 'alvobot-pro')]);
    }
    
    public function update_slug_translation($request) {
        return $this->create_slug_translation($request); // Logic is identical
    }
    
    public function delete_slug_translation($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);

        $post = get_post($post_id);
        if (!$post) return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translations = pll_get_post_translations($post_id);
        if (!isset($translations[$language_code])) return new WP_Error('translation_not_found', __('Não existe uma tradução para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_post_id = $translations[$language_code];
        $translated_post = get_post($translated_post_id);
        $new_slug = sanitize_title($translated_post->post_title); // Reset to title-based slug
        
        $result = wp_update_post(['ID' => $translated_post_id, 'post_name' => $new_slug], true);
        if (is_wp_error($result)) {
            $this->log_action('delete_slug_translation', 'error', 'Erro ao redefinir slug', ['post_id' => $post_id, 'lang' => $language_code, 'error' => $result->get_error_message()]);
            return $result;
        }
        
        $this->log_action('delete_slug_translation', 'success', 'Slug redefinido', ['post_id' => $post_id, 'lang' => $language_code, 'new_slug' => $new_slug]);
        return new WP_REST_Response(['success' => true, 'post_id' => $translated_post_id, 'new_slug' => $new_slug, 'message' => __('Slug redefinido com sucesso.', 'alvobot-pro')]);
    }

    public function get_language_url($request) {
        if ($error = $this->polylang_not_active_error()) return $error;
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);

        if (!get_post($post_id)) return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        if (!PLL()->model->get_language($language_code)) return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        
        $translated_post_id = pll_get_post($post_id, $language_code);
        if (!$translated_post_id) return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        
        return new WP_REST_Response(['post_id' => $translated_post_id, 'url' => get_permalink($translated_post_id)]);
    }
}
