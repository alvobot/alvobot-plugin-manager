<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_Rest_Api_Service')) {
    return;
}

/**
 * Service para gerenciar todas as APIs REST do módulo Multi Languages
 * Consolidação de todas as rotas REST em uma classe especializada
 */
class AlvoBotPro_Rest_Api_Service {
    
    private $namespace = 'alvobot-pro/v1';
    private $translation_service;
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializa hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Define o service de tradução
     */
    public function set_translation_service($translation_service) {
        $this->translation_service = $translation_service;
    }
    
    /**
     * Registra todas as rotas da API REST
     */
    public function register_rest_routes() {
        // Rota para listar idiomas disponíveis
        register_rest_route($this->namespace, '/languages', [
            'methods' => 'GET',
            'callback' => array($this, 'get_languages'),
            'permission_callback' => '__return_true',
        ]);
        
        // Rota para obter URL de um post em um idioma específico
        register_rest_route($this->namespace, '/language-url', [
            'methods' => 'GET',
            'callback' => array($this, 'get_language_url'),
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'language_code' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // Rotas para tradução de posts
        register_rest_route($this->namespace, '/translate', [
            [
                'methods' => 'POST',
                'callback' => array($this, 'create_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_translation_args(),
            ],
            [
                'methods' => 'PUT',
                'callback' => array($this, 'update_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_translation_args(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                    'language_code' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ]
                ]
            ]
        ]);
        
        // Rota para verificar existência de tradução
        register_rest_route($this->namespace, '/translations/check', [
            'methods' => 'GET',
            'callback' => array($this, 'check_translation_existence'),
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'language_code' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // Rota para estatísticas de tradução
        register_rest_route($this->namespace, '/translation-stats', [
            'methods' => 'GET',
            'callback' => array($this, 'get_translation_stats'),
            'permission_callback' => '__return_true',
        ]);
        
        // Rota para alterar idioma de um post existente
        register_rest_route($this->namespace, '/change-post-language', [
            'methods' => 'PUT',
            'callback' => array($this, 'change_post_language'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => [
                'post_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'language_code' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'update_translations' => [
                    'required' => false,
                    'default' => true,
                    'validate_callback' => function($param) {
                        return is_bool($param);
                    }
                ]
            ]
        ]);
        
        // Rota para listar todas as traduções
        register_rest_route($this->namespace, '/translations', [
            'methods' => 'GET',
            'callback' => array($this, 'get_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param <= 100;
                    }
                ],
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return post_type_exists($param);
                    }
                ]
            ]
        ]);
        
        // Rota para listar posts sem tradução
        register_rest_route($this->namespace, '/translations/missing', [
            'methods' => 'GET',
            'callback' => array($this, 'get_missing_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param <= 100;
                    }
                ],
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return post_type_exists($param);
                    }
                ],
                'language_code' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // Rotas para categorias
        register_rest_route($this->namespace, '/translate/category', [
            [
                'methods' => 'POST',
                'callback' => array($this, 'create_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_category_translation_args(),
            ],
            [
                'methods' => 'PUT',
                'callback' => array($this, 'update_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_category_translation_args(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_category_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => [
                    'category_id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                    'language_code' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ]
                ]
            ]
        ]);
        
        // Rota para listar traduções de categorias
        register_rest_route($this->namespace, '/translations/categories', [
            'methods' => 'GET',
            'callback' => array($this, 'get_category_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param <= 100;
                    }
                ]
            ]
        ]);
        
        // Rota para listar slugs
        register_rest_route($this->namespace, '/slugs', [
            'methods' => 'GET',
            'callback' => array($this, 'get_slugs'),
            'permission_callback' => '__return_true',
            'args' => [
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return post_type_exists($param);
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param <= 100;
                    }
                ]
            ]
        ]);
        
        // Rotas para traduções de slug
        register_rest_route($this->namespace, '/translate/slug', [
            [
                'methods' => 'POST',
                'callback' => array($this, 'create_slug_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_slug_translation_args(),
            ],
            [
                'methods' => 'PUT',
                'callback' => array($this, 'update_slug_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_slug_translation_args(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_slug_translation'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                    'language_code' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && !empty($param);
                        }
                    ]
                ]
            ]
        ]);
        
        // Rota para listar taxonomias
        register_rest_route($this->namespace, '/taxonomies', [
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomies'),
            'permission_callback' => '__return_true',
        ]);
        
        // Rota para listar termos de taxonomia
        register_rest_route($this->namespace, '/taxonomy/terms', [
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomy_terms'),
            'permission_callback' => '__return_true',
            'args' => [
                'taxonomy' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return taxonomy_exists($param);
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param <= 100;
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param);
                    }
                ]
            ]
        ]);
        
        // Rota para termos não traduzidos
        register_rest_route($this->namespace, '/taxonomy/untranslated', [
            'methods' => 'GET',
            'callback' => array($this, 'get_untranslated_terms'),
            'permission_callback' => '__return_true',
            'args' => [
                'taxonomy' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return taxonomy_exists($param);
                    }
                ],
                'language_code' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // Rota para sincronizar traduções
        register_rest_route($this->namespace, '/sync-translations', [
            'methods' => 'POST',
            'callback' => array($this, 'sync_translations'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => [
                'translations' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Callback para a rota de idiomas disponíveis
     */
    public function get_languages() {
        if (!function_exists('PLL') || !PLL()->model) {
            return new WP_Error('pll_not_active', __('Polylang não está ativo.', 'alvobot-pro'), array('status' => 500));
        }

        $pll_languages = PLL()->model->get_languages_list();
        $response_data = array();

        if (empty($pll_languages)) {
            return new WP_REST_Response($response_data, 200);
        }

        foreach ($pll_languages as $language) {
            $response_data[] = array(
                'code'        => $language->slug,
                'name'        => $language->name,
                'native_name' => isset($language->native_name) ? $language->native_name : $language->name,
                'locale'      => $language->locale,
                'flag'        => isset($language->flag_url) ? $language->flag_url : (isset($language->flag) ? $language->flag : ''),
                'is_rtl'      => isset($language->is_rtl) ? (bool) $language->is_rtl : false,
                'is_default'  => isset($language->is_default) ? (bool) $language->is_default : false,
            );
        }
        return new WP_REST_Response($response_data, 200);
    }
    
    /**
     * Obtém a URL de um post em um idioma específico
     */
    public function get_language_url($request) {
        if (!function_exists('pll_get_post') || !function_exists('get_permalink')) {
            return new WP_Error('polylang_not_active', __('O plugin Polylang não está ativo.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Obtém o ID do post traduzido
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error('translation_not_found', __('Tradução não encontrada para este idioma.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Obtém a URL do post traduzido
        $url = get_permalink($translated_post_id);
        
        return new WP_REST_Response([
            'post_id' => $translated_post_id,
            'url' => $url
        ]);
    }
    
    /**
     * Cria uma nova tradução para um post existente
     */
    public function create_translation($request) {
        if (!$this->translation_service) {
            return new WP_Error('service_not_available', __('Serviço de tradução não disponível.', 'alvobot-pro'), ['status' => 500]);
        }
        
        return $this->translation_service->create_translation($request);
    }
    
    /**
     * Verifica se existe uma tradução para um post em um determinado idioma
     */
    public function check_translation_existence($request) {
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error('polylang_not_active', __('O plugin Polylang não está ativo.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_post_translations($post_id);
        $exists = isset($translations[$language_code]);
        
        return new WP_REST_Response([
            'exists' => $exists,
            'post_id' => $exists ? $translations[$language_code] : null
        ]);
    }
    
    /**
     * Obtém estatísticas de tradução para o site
     */
    public function get_translation_stats() {
        if (!function_exists('pll_languages_list')) {
            return new WP_Error('polylang_not_active', __('O plugin Polylang não está ativo.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $languages = pll_languages_list();
        $default_language = pll_default_language();
        
        $post_types = ['post', 'page'];
        $taxonomies = ['category', 'post_tag'];
        
        $stats = [
            'languages' => count($languages),
            'default_language' => $default_language,
            'post_types' => [],
            'taxonomies' => []
        ];
        
        // Estatísticas para tipos de post
        foreach ($post_types as $post_type) {
            $total_posts = wp_count_posts($post_type)->publish;
            $translated_posts = [];
            
            foreach ($languages as $language) {
                $args = [
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'lang' => $language
                ];
                
                $query = new WP_Query($args);
                $translated_posts[$language] = $query->found_posts;
            }
            
            $stats['post_types'][$post_type] = [
                'total' => $total_posts,
                'by_language' => $translated_posts
            ];
        }
        
        return new WP_REST_Response($stats);
    }
    
    /**
     * Altera o idioma de um post existente sem criar nova tradução
     */
    public function change_post_language($request) {
        if (!function_exists('pll_set_post_language')) {
            return new WP_Error('polylang_not_active', __('O plugin Polylang não está ativo.', 'alvobot-pro'), ['status' => 400]);
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $update_translations = isset($params['update_translations']) ? (bool) $params['update_translations'] : true;
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Verifica se o usuário pode editar o post
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', __('Você não tem permissão para editar este post.', 'alvobot-pro'), ['status' => 403]);
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error('language_not_found', __('Idioma não encontrado.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Obtém o idioma atual do post
        $current_language = pll_get_post_language($post_id);
        
        if ($current_language === $language_code) {
            return new WP_Error('same_language', __('O post já está neste idioma.', 'alvobot-pro'), ['status' => 400]);
        }
        
        // Obtém traduções existentes antes da mudança
        $existing_translations = pll_get_post_translations($post_id);
        
        // Verifica se já existe um post neste idioma
        if (isset($existing_translations[$language_code])) {
            return new WP_Error(
                'translation_exists', 
                __('Já existe uma tradução deste post para o idioma selecionado.', 'alvobot-pro'), 
                [
                    'status' => 400,
                    'existing_post_id' => $existing_translations[$language_code]
                ]
            );
        }
        
        // Altera o idioma do post
        pll_set_post_language($post_id, $language_code);
        
        // Se update_translations for true, atualiza o grupo de traduções
        if ($update_translations && !empty($existing_translations)) {
            // Remove o post do grupo de traduções anterior
            unset($existing_translations[$current_language]);
            
            // Adiciona o post ao grupo com o novo idioma
            $existing_translations[$language_code] = $post_id;
            
            // Atualiza as associações de tradução
            pll_save_post_translations($existing_translations);
        }
        
        // Log da operação
        AlvoBotPro::debug_log('multi-languages', sprintf(
            'Idioma do post #%d alterado de %s para %s',
            $post_id,
            $current_language,
            $language_code
        ));
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'previous_language' => $current_language,
            'new_language' => $language_code,
            'translations_updated' => $update_translations,
            'message' => sprintf(
                __('Idioma do post alterado de %s para %s com sucesso.', 'alvobot-pro'),
                $current_language,
                $language_code
            )
        ], 200);
    }
    
    /**
     * Verifica permissões do usuário
     */
    public function permissions_check() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Argumentos comuns para tradução de posts
     */
    private function get_translation_args() {
        return [
            'post_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
            'language_code' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && strlen($param) === 2;
                },
            ],
            'title' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param);
                },
            ],
            'content' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param);
                },
            ],
        ];
    }
    
    /**
     * Atualiza uma tradução existente
     */
    public function update_translation($request) {
        if (!$this->translation_service) {
            return new WP_Error('service_not_available', __('Serviço de tradução não disponível.', 'alvobot-pro'), ['status' => 500]);
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se já existe uma tradução
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error('translation_not_found', __('Tradução não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Atualiza o post traduzido
        $update_data = [
            'ID' => $translated_post_id,
            'post_title' => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content']),
        ];
        
        if (isset($params['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
        }
        
        if (isset($params['slug'])) {
            $update_data['post_name'] = sanitize_title($params['slug']);
        }
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'message' => __('Tradução atualizada com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Exclui uma tradução
     */
    public function delete_translation($request) {
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Obtém o ID do post traduzido
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error('translation_not_found', __('Tradução não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Verifica permissões
        if (!current_user_can('delete_post', $translated_post_id)) {
            return new WP_Error('forbidden', __('Você não tem permissão para excluir esta tradução.', 'alvobot-pro'), ['status' => 403]);
        }
        
        // Exclui o post
        $result = wp_delete_post($translated_post_id, false); // Move para lixeira
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Falha ao excluir a tradução.', 'alvobot-pro'), ['status' => 500]);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Tradução excluída com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Lista todas as traduções com paginação
     */
    public function get_translations($request) {
        $params = $request->get_params();
        $page = intval($params['page']);
        $per_page = intval($params['per_page']);
        $post_type = sanitize_text_field($params['post_type']);
        
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => [
                [
                    'key' => '_language',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        $posts = [];
        
        foreach ($query->posts as $post) {
            $translations = pll_get_post_translations($post->ID);
            $posts[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'language' => pll_get_post_language($post->ID),
                'translations' => $translations
            ];
        }
        
        return new WP_REST_Response([
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page
        ], 200);
    }
    
    /**
     * Lista posts sem tradução em um idioma específico
     */
    public function get_missing_translations($request) {
        $params = $request->get_params();
        $page = intval($params['page']);
        $per_page = intval($params['per_page']);
        $post_type = sanitize_text_field($params['post_type']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Primeiro, pega todos os posts do tipo especificado
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $all_posts = get_posts($args);
        $missing_posts = [];
        
        foreach ($all_posts as $post_id) {
            $translations = pll_get_post_translations($post_id);
            if (!isset($translations[$language_code])) {
                $missing_posts[] = $post_id;
            }
        }
        
        // Pagina os resultados
        $total = count($missing_posts);
        $offset = ($page - 1) * $per_page;
        $paged_posts = array_slice($missing_posts, $offset, $per_page);
        
        $posts = [];
        foreach ($paged_posts as $post_id) {
            $post = get_post($post_id);
            $posts[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'language' => pll_get_post_language($post->ID),
                'translations' => pll_get_post_translations($post->ID)
            ];
        }
        
        return new WP_REST_Response([
            'posts' => $posts,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ], 200);
    }
    
    /**
     * Argumentos para tradução de categorias
     */
    private function get_category_translation_args() {
        return [
            'category_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'language_code' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ],
            'name' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param);
                }
            ],
            'description' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_string($param);
                }
            ],
            'slug' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_string($param);
                }
            ]
        ];
    }
    
    /**
     * Cria tradução de categoria
     */
    public function create_category_translation($request) {
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se a categoria existe
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return new WP_Error('category_not_found', __('Categoria não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Verifica se já existe tradução
        $existing_translation = pll_get_term($category_id, $language_code);
        if ($existing_translation) {
            return new WP_Error('translation_exists', __('Já existe uma tradução desta categoria.', 'alvobot-pro'), ['status' => 400]);
        }
        
        // Cria a nova categoria
        $new_category = wp_insert_term(
            sanitize_text_field($params['name']),
            'category',
            [
                'description' => isset($params['description']) ? sanitize_textarea_field($params['description']) : '',
                'slug' => isset($params['slug']) ? sanitize_title($params['slug']) : ''
            ]
        );
        
        if (is_wp_error($new_category)) {
            return $new_category;
        }
        
        // Define o idioma da nova categoria
        pll_set_term_language($new_category['term_id'], $language_code);
        
        // Associa as traduções
        $translations = pll_get_term_translations($category_id);
        $translations[$language_code] = $new_category['term_id'];
        pll_save_term_translations($translations);
        
        return new WP_REST_Response([
            'success' => true,
            'category_id' => $new_category['term_id'],
            'message' => __('Tradução de categoria criada com sucesso.', 'alvobot-pro')
        ], 201);
    }
    
    /**
     * Atualiza tradução de categoria
     */
    public function update_category_translation($request) {
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Obtém a tradução existente
        $translated_category_id = pll_get_term($category_id, $language_code);
        
        if (!$translated_category_id) {
            return new WP_Error('translation_not_found', __('Tradução de categoria não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Atualiza a categoria
        $update_data = [
            'name' => sanitize_text_field($params['name'])
        ];
        
        if (isset($params['description'])) {
            $update_data['description'] = sanitize_textarea_field($params['description']);
        }
        
        if (isset($params['slug'])) {
            $update_data['slug'] = sanitize_title($params['slug']);
        }
        
        $result = wp_update_term($translated_category_id, 'category', $update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'category_id' => $translated_category_id,
            'message' => __('Tradução de categoria atualizada com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Exclui tradução de categoria
     */
    public function delete_category_translation($request) {
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Obtém a tradução
        $translated_category_id = pll_get_term($category_id, $language_code);
        
        if (!$translated_category_id) {
            return new WP_Error('translation_not_found', __('Tradução de categoria não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Exclui a categoria
        $result = wp_delete_term($translated_category_id, 'category');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Tradução de categoria excluída com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Lista traduções de categorias
     */
    public function get_category_translations($request) {
        $params = $request->get_params();
        $page = intval($params['page']);
        $per_page = intval($params['per_page']);
        
        $args = [
            'taxonomy' => 'category',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];
        
        $categories = get_terms($args);
        $total = wp_count_terms('category', ['hide_empty' => false]);
        
        $data = [];
        foreach ($categories as $category) {
            $translations = pll_get_term_translations($category->term_id);
            $data[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'language' => pll_get_term_language($category->term_id),
                'translations' => $translations
            ];
        }
        
        return new WP_REST_Response([
            'categories' => $data,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ], 200);
    }
    
    /**
     * Lista slugs
     */
    public function get_slugs($request) {
        $params = $request->get_params();
        $post_type = sanitize_text_field($params['post_type']);
        $page = intval($params['page']);
        $per_page = intval($params['per_page']);
        
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'fields' => 'ids'
        ];
        
        $query = new WP_Query($args);
        $slugs = [];
        
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            $translations = pll_get_post_translations($post_id);
            $translated_slugs = [];
            
            foreach ($translations as $lang => $trans_id) {
                $trans_post = get_post($trans_id);
                $translated_slugs[$lang] = $trans_post->post_name;
            }
            
            $slugs[] = [
                'post_id' => $post_id,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'language' => pll_get_post_language($post_id),
                'translated_slugs' => $translated_slugs
            ];
        }
        
        return new WP_REST_Response([
            'slugs' => $slugs,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page
        ], 200);
    }
    
    /**
     * Argumentos para tradução de slug
     */
    private function get_slug_translation_args() {
        return [
            'post_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'language_code' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ],
            'slug' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param);
                }
            ]
        ];
    }
    
    /**
     * Cria tradução de slug
     */
    public function create_slug_translation($request) {
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $slug = sanitize_title($params['slug']);
        
        // Obtém o post traduzido
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error('translation_not_found', __('Tradução não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Atualiza o slug
        $result = wp_update_post([
            'ID' => $translated_post_id,
            'post_name' => $slug
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'slug' => $slug,
            'message' => __('Slug traduzido com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Atualiza tradução de slug
     */
    public function update_slug_translation($request) {
        // Mesmo comportamento que create_slug_translation
        return $this->create_slug_translation($request);
    }
    
    /**
     * Exclui tradução de slug (restaura para o padrão)
     */
    public function delete_slug_translation($request) {
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Obtém o post traduzido
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error('translation_not_found', __('Tradução não encontrada.', 'alvobot-pro'), ['status' => 404]);
        }
        
        // Gera um slug baseado no título
        $post = get_post($translated_post_id);
        $default_slug = sanitize_title($post->post_title);
        
        // Atualiza para o slug padrão
        $result = wp_update_post([
            'ID' => $translated_post_id,
            'post_name' => $default_slug
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'slug' => $default_slug,
            'message' => __('Slug restaurado para o padrão.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Lista taxonomias disponíveis
     */
    public function get_taxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $data = [];
        
        foreach ($taxonomies as $taxonomy) {
            if (pll_is_translated_taxonomy($taxonomy->name)) {
                $data[] = [
                    'name' => $taxonomy->name,
                    'label' => $taxonomy->label,
                    'singular_label' => $taxonomy->labels->singular_name,
                    'hierarchical' => $taxonomy->hierarchical
                ];
            }
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Lista termos de uma taxonomia
     */
    public function get_taxonomy_terms($request) {
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $page = intval($params['page']);
        $per_page = intval($params['per_page']);
        $hide_empty = (bool) $params['hide_empty'];
        
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $hide_empty,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];
        
        $terms = get_terms($args);
        $total = wp_count_terms($taxonomy, ['hide_empty' => $hide_empty]);
        
        $data = [];
        foreach ($terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            $data[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'language' => pll_get_term_language($term->term_id),
                'translations' => $translations
            ];
        }
        
        return new WP_REST_Response([
            'terms' => $data,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ], 200);
    }
    
    /**
     * Lista termos sem tradução completa
     */
    public function get_untranslated_terms($request) {
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $language_code = sanitize_text_field($params['language_code']);
        
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ]);
        
        $untranslated = [];
        foreach ($terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            if (!isset($translations[$language_code])) {
                $untranslated[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'language' => pll_get_term_language($term->term_id),
                    'translations' => $translations
                ];
            }
        }
        
        return new WP_REST_Response($untranslated, 200);
    }
    
    /**
     * Sincroniza traduções
     */
    public function sync_translations($request) {
        $params = $request->get_params();
        $translations = $params['translations'];
        
        if (empty($translations) || count($translations) < 2) {
            return new WP_Error('invalid_translations', __('São necessárias pelo menos 2 traduções para sincronizar.', 'alvobot-pro'), ['status' => 400]);
        }
        
        // Verifica se todos os posts existem
        foreach ($translations as $lang => $post_id) {
            if (!get_post($post_id)) {
                return new WP_Error('post_not_found', sprintf(__('Post #%d não encontrado.', 'alvobot-pro'), $post_id), ['status' => 404]);
            }
        }
        
        // Salva as associações de tradução
        pll_save_post_translations($translations);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Traduções sincronizadas com sucesso.', 'alvobot-pro'),
            'translations' => $translations
        ], 200);
    }
}