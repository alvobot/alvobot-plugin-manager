<?php

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_MultiLanguages {
    private $namespace = 'alvobot-pro/v1';
    
    /** @var int Limite máximo de logs armazenados */
    private const MAX_LOGS = 100;

    /** @var int Tamanho máximo de conteúdo em bytes (100 MB) */
    private const MAX_CONTENT_SIZE = 100000000;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Adiciona o campo `pll_post_translations` às respostas da API REST para posts e páginas
        add_action('rest_api_init', array($this, 'register_post_translations_field'));
        
        // Removido para evitar duplicação de menu
        // add_action('admin_menu', array($this, 'register_admin_pages'));
    }

    public function init() {
        // Inicialização adicional se necessário
    }

    public function activate() {
        // Ações de ativação se necessário
    }

    public function deactivate() {
        // Ações de desativação se necessário
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Ativar exibição de erros para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Multi-Languages: Iniciando render_settings_page');
        }

        try {
            // Sempre inclui o template - a verificação do Polylang é feita dentro do template
            include_once plugin_dir_path(__FILE__) . 'templates/multi-languages-settings.php';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Multi-Languages: Template carregado com sucesso');
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Multi-Languages Error: ' . $e->getMessage());
            }
            
            // Exibir mensagem de erro para o admin
            echo '<div class="wrap alvobot-admin-page">';  
            echo '<h1>AlvoBot Multi Languages</h1>';
            echo '<div class="notice notice-error"><p>Erro ao carregar a página de configurações. Por favor, verifique os logs para mais detalhes.</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Registra as rotas da API REST.
     */
    public function register_rest_routes() {
        $this->namespace = 'alvobot-pro/v1';
        
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
        
        // Rota para alterar o idioma de um post existente
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
                        return is_bool($param) || in_array($param, ['true', 'false', '0', '1'], true);
                    }
                ]
            ]
        ]);
        
        // Rotas para tradução de posts
        register_rest_route($this->namespace, '/translate', [
            'methods' => 'POST',
            'callback' => array($this, 'create_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_translation_args(),
        ]);
        
        register_rest_route($this->namespace, '/translate', [
            'methods' => 'PUT',
            'callback' => array($this, 'update_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_translation_args(),
        ]);
        
        register_rest_route($this->namespace, '/translate', [
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
        ]);
        
        // Rota para listar traduções de posts
        register_rest_route($this->namespace, '/translations', [
            'methods' => 'GET',
            'callback' => array($this, 'get_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || in_array($param, ['true', 'false', '1', '0'], true);
                    }
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
        
        // Rota para listar posts sem traduções
        register_rest_route($this->namespace, '/translations/missing', [
            'methods' => 'GET',
            'callback' => array($this, 'get_posts_missing_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || in_array($param, ['true', 'false', '1', '0'], true);
                    }
                ]
            ]
        ]);
        
        // Rotas para tradução de categorias
        register_rest_route($this->namespace, '/translate/category', [
            'methods' => 'POST',
            'callback' => array($this, 'create_category_translation'),
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
                ],
                'name' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ],
                'description' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ],
                'slug' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/translate/category', [
            'methods' => 'PUT',
            'callback' => array($this, 'update_category_translation'),
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
                ],
                'name' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ],
                'description' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ],
                'slug' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/translate/category', [
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
        ]);
        
        // Rota para listar traduções de categorias
        register_rest_route($this->namespace, '/translations/categories', [
            'methods' => 'GET',
            'callback' => array($this, 'get_categories_translations'),
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || in_array($param, ['true', 'false', '1', '0'], true);
                    }
                ]
            ]
        ]);
        
        // Rotas para gerenciamento de slugs
        register_rest_route($this->namespace, '/slugs', [
            'methods' => 'GET',
            'callback' => array($this, 'get_all_slugs'),
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'post_type' => [
                    'default' => 'post',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || in_array($param, ['true', 'false', '1', '0'], true);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [
            'methods' => 'POST',
            'callback' => array($this, 'create_slug_translation'),
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
                'slug' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [
            'methods' => 'PUT',
            'callback' => array($this, 'update_slug_translation'),
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
                'slug' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/translate/slug', [
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
        ]);
        
        // Rotas para gerenciamento de taxonomias
        register_rest_route($this->namespace, '/taxonomies', [
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomies'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route($this->namespace, '/taxonomy/terms', [
            'methods' => 'GET',
            'callback' => array($this, 'get_taxonomy_terms'),
            'permission_callback' => '__return_true',
            'args' => [
                'taxonomy' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'hide_empty' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || in_array($param, ['true', 'false', '0', '1'], true);
                    }
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/taxonomy/untranslated', [
            'methods' => 'GET',
            'callback' => array($this, 'get_untranslated_terms'),
            'permission_callback' => '__return_true',
            'args' => [
                'taxonomy' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
        
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
        
        register_rest_route($this->namespace, '/translation-stats', [
            'methods' => 'GET',
            'callback' => array($this, 'get_translation_stats'),
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Callback para a rota de idiomas disponíveis
     */
    public function get_languages() {
        if (!function_exists('pll_languages_list')) {
            return new WP_Error('pll_not_active', 'Polylang não está ativo', array('status' => 404));
        }

        $languages = PLL()->model->get_languages_list();
        $response = array();

        foreach ($languages as $lang) {
            $response[] = array(
                'code' => $lang->slug,
                'name' => $lang->name,
                'locale' => $lang->locale,
                'is_default' => (bool) $lang->is_default,
                'term_id' => $lang->term_id,
                'count' => $lang->count,
                'active' => $lang->active,
                'flag_url' => $lang->flag_url,
                'home_url' => $lang->home_url,
                'search_url' => $lang->search_url,
                'w3c' => $lang->w3c,
                'facebook' => $lang->facebook
            );
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Callback para a rota de posts sem tradução completa
     */
    public function get_untranslated_posts(WP_REST_Request $request) {
        if (!function_exists('pll_languages_list') || !function_exists('pll_get_post_translations')) {
            return new WP_Error('pll_not_active', 'Polylang não está ativo', array('status' => 404));
        }

        $languages = pll_languages_list();
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        $untranslated_posts = array();

        foreach ($posts as $post) {
            $translations = pll_get_post_translations($post->ID);
            $missing_languages = array_diff($languages, array_keys($translations));

            if (!empty($missing_languages)) {
                $post_language = pll_get_post_language($post->ID);
                $untranslated_posts[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'current_language' => $post_language,
                    'missing_translations' => array_values($missing_languages),
                    'permalink' => get_permalink($post->ID)
                );
            }
        }

        return new WP_REST_Response($untranslated_posts, 200);
    }

    /**
     * Callback para a rota de tradução de categoria
     */
    public function get_category_translation(WP_REST_Request $request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error('pll_not_active', 'Polylang não está ativo', array('status' => 404));
        }

        // Obtém os parâmetros da requisição
        $category_id = $request->get_param('category_id');
        $language = $request->get_param('lang');

        // Valida os parâmetros
        if (!$category_id || !$language) {
            return new WP_Error(
                'missing_params',
                'Parâmetros obrigatórios: category_id e lang',
                array('status' => 400)
            );
        }

        // Verifica se a categoria existe
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return new WP_Error(
                'invalid_category',
                'Categoria não encontrada',
                array('status' => 404)
            );
        }

        // Obtém as traduções da categoria
        $translations = pll_get_term_translations($category_id);
        
        // Verifica se existe tradução para o idioma solicitado
        if (!isset($translations[$language])) {
            return new WP_Error(
                'translation_not_found',
                'Tradução não encontrada para o idioma especificado',
                array('status' => 404)
            );
        }

        // Obtém os detalhes da categoria traduzida
        $translated_category = get_term($translations[$language], 'category');
        
        return new WP_REST_Response(array(
            'original_category' => array(
                'id' => (int)$category_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'language' => pll_get_term_language($category_id)
            ),
            'translated_category' => array(
                'id' => (int)$translations[$language],
                'name' => $translated_category->name,
                'slug' => $translated_category->slug,
                'language' => $language
            )
        ), 200);
    }

    /**
     * Registra logs com limite de tamanho.
     */
    private function log_action(string $action, string $status, string $message, array $details = []) {
        $logs = $this->get_logs();
        
        // Adiciona novo log
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'details' => $details
        ];
        
        // Limita o número de logs
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }
        
        update_option('alvobot_multi_languages_logs', $logs);
    }
    
    /**
     * Obtém logs armazenados.
     */
    private function get_logs() {
        $logs = get_option('alvobot_multi_languages_logs', []);
        return is_array($logs) ? $logs : [];
    }
    
    /**
     * Argumentos de paginação reutilizáveis.
     */
    private function get_pagination_args(int $default_per_page, string $per_page_desc): array {
        return [
            'per_page' => [
                'default' => $default_per_page,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                },
                'description' => $per_page_desc,
            ],
            'page' => [
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
                'description' => 'Current page of the collection.',
            ],
            'hide_empty' => [
                'default' => false,
                'validate_callback' => function($param) {
                    return is_bool($param) || in_array($param, ['true', 'false', '1', '0'], true);
                },
                'description' => 'Whether to hide empty categories or posts.',
            ],
        ];
    }

    /**
     * Verifica permissões do usuário.
     */
    public function permissions_check() {
        return current_user_can('edit_posts');
    }

    /**
     * Argumentos comuns para tradução de posts.
     */
    private function get_translation_args() {
        return array_merge(
            $this->get_basic_translation_args(),
            [
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
                'excerpt' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
                'slug' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
                'date' => [
                    'validate_callback' => function($param) {
                        return strtotime($param) !== false;
                    },
                ],
                'categories' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
                'featured_media' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'meta_input' => [
                    'validate_callback' => function($param) {
                        return is_array($param);
                    },
                ],
            ]
        );
    }

    /**
     * Argumentos básicos para tradução (post_id e language_code).
     */
    private function get_basic_translation_args() {
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
        ];
    }

    /**
     * Argumentos para tradução de categorias.
     */
    private function get_category_translation_args() {
        return array_merge(
            $this->get_basic_category_args(),
            [
                'name' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
                'description' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
                'slug' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
            ]
        );
    }

    /**
     * Argumentos básicos para categorias (category_id e language_code).
     */
    private function get_basic_category_args() {
        return [
            'category_id' => [
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
        ];
    }

    /**
     * Argumentos para tradução de slugs.
     */
    private function get_slug_translation_args() {
        return array_merge(
            $this->get_basic_translation_args(),
            [
                'slug' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                ],
            ]
        );
    }

    /**
     * Cria uma nova tradução para um post existente.
     */
    public function create_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations') || !function_exists('pll_set_post_language')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }

        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post original não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se já existe uma tradução para este idioma
        $translations = pll_get_post_translations($post_id);
        if (isset($translations[$language_code])) {
            return new WP_Error(
                'translation_exists',
                __('Já existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Prepara os dados do post
        $post_data = [
            'post_title' => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content']),
            'post_status' => $post->post_status,
            'post_type' => $post->post_type,
            'post_author' => get_current_user_id(),
        ];
        
        // Adiciona campos opcionais
        if (isset($params['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
        }
        
        if (isset($params['slug'])) {
            $post_data['post_name'] = sanitize_title($params['slug']);
        }
        
        if (isset($params['date'])) {
            $post_data['post_date'] = sanitize_text_field($params['date']);
            $post_data['post_date_gmt'] = get_gmt_from_date($params['date']);
        }
        
        // Cria o post traduzido
        $translated_post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($translated_post_id)) {
            $this->log_action('create_translation', 'error', 'Erro ao criar tradução', [
                'post_id' => $post_id,
                'language_code' => $language_code,
                'error' => $translated_post_id->get_error_message()
            ]);
            return $translated_post_id;
        }
        
        // Define o idioma do post traduzido
        pll_set_post_language($translated_post_id, $language_code);
        
        // Atualiza as traduções
        $translations[$language_code] = $translated_post_id;
        pll_save_post_translations($translations);
        
        // Atualiza categorias se fornecidas
        if (isset($params['categories']) && is_array($params['categories'])) {
            $translated_categories = [];
            
            foreach ($params['categories'] as $category_id) {
                // Tenta obter a tradução da categoria no idioma de destino
                $translated_category = $this->get_translated_term($category_id, 'category', $language_code);
                if ($translated_category) {
                    $translated_categories[] = $translated_category;
                } else {
                    $translated_categories[] = $category_id;
                }
            }
            
            wp_set_post_terms($translated_post_id, $translated_categories, 'category');
        }
        
        // Atualiza imagem destacada se fornecida
        if (isset($params['featured_media']) && $params['featured_media']) {
            set_post_thumbnail($translated_post_id, intval($params['featured_media']));
        }
        
        // Atualiza meta dados se fornecidos
        if (isset($params['meta_input']) && is_array($params['meta_input'])) {
            foreach ($params['meta_input'] as $meta_key => $meta_value) {
                update_post_meta($translated_post_id, sanitize_key($meta_key), $meta_value);
            }
        }
        
        $this->log_action('create_translation', 'success', 'Tradução criada com sucesso', [
            'post_id' => $post_id,
            'language_code' => $language_code,
            'translated_post_id' => $translated_post_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'message' => __('Tradução criada com sucesso.', 'alvobot-pro')
        ], 201);
    }
    
    /**
     * Atualiza uma tradução existente.
     */
    public function update_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post original não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_post_translations($post_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_post_id = $translations[$language_code];
        
        // Prepara os dados do post
        $post_data = [
            'ID' => $translated_post_id,
            'post_title' => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content']),
        ];
        
        // Adiciona campos opcionais
        if (isset($params['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
        }
        
        if (isset($params['slug'])) {
            $post_data['post_name'] = sanitize_title($params['slug']);
        }
        
        if (isset($params['date'])) {
            $post_data['post_date'] = sanitize_text_field($params['date']);
            $post_data['post_date_gmt'] = get_gmt_from_date($params['date']);
        }
        
        // Atualiza o post traduzido
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            $this->log_action('update_translation', 'error', 'Erro ao atualizar tradução', [
                'post_id' => $post_id,
                'language_code' => $language_code,
                'error' => $result->get_error_message()
            ]);
            return $result;
        }
        
        // Atualiza categorias se fornecidas
        if (isset($params['categories']) && is_array($params['categories'])) {
            $translated_categories = [];
            
            foreach ($params['categories'] as $category_id) {
                // Tenta obter a tradução da categoria no idioma de destino
                $translated_category = $this->get_translated_term($category_id, 'category', $language_code);
                if ($translated_category) {
                    $translated_categories[] = $translated_category;
                } else {
                    $translated_categories[] = $category_id;
                }
            }
            
            wp_set_post_terms($translated_post_id, $translated_categories, 'category');
        }
        
        // Atualiza imagem destacada se fornecida
        if (isset($params['featured_media'])) {
            if ($params['featured_media']) {
                set_post_thumbnail($translated_post_id, intval($params['featured_media']));
            } else {
                delete_post_thumbnail($translated_post_id);
            }
        }
        
        // Atualiza meta dados se fornecidos
        if (isset($params['meta_input']) && is_array($params['meta_input'])) {
            foreach ($params['meta_input'] as $meta_key => $meta_value) {
                update_post_meta($translated_post_id, sanitize_key($meta_key), $meta_value);
            }
        }
        
        $this->log_action('update_translation', 'success', 'Tradução atualizada com sucesso', [
            'post_id' => $post_id,
            'language_code' => $language_code,
            'translated_post_id' => $translated_post_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'message' => __('Tradução atualizada com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Exclui uma tradução existente.
     */
    public function delete_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post original não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_post_translations($post_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_post_id = $translations[$language_code];
        
        // Não permite excluir o post original
        if ($translated_post_id == $post_id) {
            return new WP_Error(
                'cannot_delete_original',
                __('Não é possível excluir o post original.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Remove a tradução do grupo de traduções
        unset($translations[$language_code]);
        pll_save_post_translations($translations);
        
        // Exclui o post traduzido
        $result = wp_delete_post($translated_post_id, true);
        
        if (!$result) {
            $this->log_action('delete_translation', 'error', 'Erro ao excluir tradução', [
                'post_id' => $post_id,
                'language_code' => $language_code,
                'translated_post_id' => $translated_post_id
            ]);
            
            return new WP_Error(
                'delete_failed',
                __('Falha ao excluir a tradução.', 'alvobot-pro'),
                ['status' => 500]
            );
        }
        
        $this->log_action('delete_translation', 'success', 'Tradução excluída com sucesso', [
            'post_id' => $post_id,
            'language_code' => $language_code,
            'translated_post_id' => $translated_post_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Tradução excluída com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Obtém o ID de um termo traduzido.
     */
    private function get_translated_term($term_id, $taxonomy, $language_code) {
        if (!function_exists('pll_get_term')) {
            return $term_id;
        }
        
        $translated_term_id = pll_get_term($term_id, $language_code);
        return $translated_term_id ? $translated_term_id : $term_id;
    }

    /**
     * Cria uma nova tradução para uma categoria existente.
     */
    public function create_category_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations') || !function_exists('pll_set_term_language')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }

        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se a categoria existe
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return new WP_Error(
                'category_not_found',
                __('Categoria original não encontrada.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se já existe uma tradução para este idioma
        $translations = pll_get_term_translations($category_id);
        if (isset($translations[$language_code])) {
            return new WP_Error(
                'translation_exists',
                __('Já existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Prepara os dados da categoria
        $term_data = [
            'name' => sanitize_text_field($params['name']),
            'description' => isset($params['description']) ? wp_kses_post($params['description']) : '',
            'slug' => isset($params['slug']) ? sanitize_title($params['slug']) : '',
            'parent' => 0 // Por padrão, cria no nível raiz
        ];
        
        // Se a categoria original tiver um pai, tenta encontrar a tradução do pai
        if ($category->parent > 0) {
            $parent_translations = pll_get_term_translations($category->parent);
            if (isset($parent_translations[$language_code])) {
                $term_data['parent'] = $parent_translations[$language_code];
            }
        }
        
        // Cria a categoria traduzida
        $result = wp_insert_term(
            $term_data['name'],
            'category',
            [
                'description' => $term_data['description'],
                'slug' => $term_data['slug'],
                'parent' => $term_data['parent']
            ]
        );
        
        if (is_wp_error($result)) {
            $this->log_action('create_category_translation', 'error', 'Erro ao criar tradução de categoria', [
                'category_id' => $category_id,
                'language_code' => $language_code,
                'error' => $result->get_error_message()
            ]);
            return $result;
        }
        
        $translated_category_id = $result['term_id'];
        
        // Define o idioma da categoria traduzida
        pll_set_term_language($translated_category_id, $language_code);
        
        // Atualiza as traduções
        $translations[$language_code] = $translated_category_id;
        pll_save_term_translations($translations);
        
        $this->log_action('create_category_translation', 'success', 'Tradução de categoria criada com sucesso', [
            'category_id' => $category_id,
            'language_code' => $language_code,
            'translated_category_id' => $translated_category_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'category_id' => $translated_category_id,
            'message' => __('Tradução de categoria criada com sucesso.', 'alvobot-pro')
        ], 201);
    }
    
    /**
     * Atualiza uma tradução existente de categoria.
     */
    public function update_category_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se a categoria existe
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return new WP_Error(
                'category_not_found',
                __('Categoria original não encontrada.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_term_translations($category_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_category_id = $translations[$language_code];
        
        // Prepara os dados da categoria
        $term_data = [
            'name' => sanitize_text_field($params['name'])
        ];
        
        if (isset($params['description'])) {
            $term_data['description'] = wp_kses_post($params['description']);
        }
        
        if (isset($params['slug'])) {
            $term_data['slug'] = sanitize_title($params['slug']);
        }
        
        // Atualiza a categoria traduzida
        $result = wp_update_term($translated_category_id, 'category', $term_data);
        
        if (is_wp_error($result)) {
            $this->log_action('update_category_translation', 'error', 'Erro ao atualizar tradução de categoria', [
                'category_id' => $category_id,
                'language_code' => $language_code,
                'error' => $result->get_error_message()
            ]);
            return $result;
        }
        
        $this->log_action('update_category_translation', 'success', 'Tradução de categoria atualizada com sucesso', [
            'category_id' => $category_id,
            'language_code' => $language_code,
            'translated_category_id' => $translated_category_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'category_id' => $translated_category_id,
            'message' => __('Tradução de categoria atualizada com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Exclui uma tradução existente de categoria.
     */
    public function delete_category_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $category_id = intval($params['category_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se a categoria existe
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return new WP_Error(
                'category_not_found',
                __('Categoria original não encontrada.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_term_translations($category_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_category_id = $translations[$language_code];
        
        // Não permite excluir a categoria original
        if ($translated_category_id == $category_id) {
            return new WP_Error(
                'cannot_delete_original',
                __('Não é possível excluir a categoria original.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Remove a tradução do grupo de traduções
        unset($translations[$language_code]);
        pll_save_term_translations($translations);
        
        // Exclui a categoria traduzida
        $result = wp_delete_term($translated_category_id, 'category');
        
        if (is_wp_error($result) || $result === false) {
            $this->log_action('delete_category_translation', 'error', 'Erro ao excluir tradução de categoria', [
                'category_id' => $category_id,
                'language_code' => $language_code,
                'translated_category_id' => $translated_category_id
            ]);
            
            return new WP_Error(
                'delete_failed',
                __('Falha ao excluir a tradução de categoria.', 'alvobot-pro'),
                ['status' => 500]
            );
        }
        
        $this->log_action('delete_category_translation', 'success', 'Tradução de categoria excluída com sucesso', [
            'category_id' => $category_id,
            'language_code' => $language_code,
            'translated_category_id' => $translated_category_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Tradução de categoria excluída com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Obtém todas as traduções de categorias com paginação.
     */
    public function get_categories_translations($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        // Obtém todas as categorias
        $args = [
            'taxonomy' => 'category',
            'hide_empty' => $hide_empty,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];
        
        $categories = get_terms($args);
        
        if (is_wp_error($categories)) {
            return $categories;
        }
        
        $result = [];
        
        foreach ($categories as $category) {
            $translations = pll_get_term_translations($category->term_id);
            $translated_categories = [];
            
            foreach ($translations as $lang => $term_id) {
                $term = get_term($term_id, 'category');
                if ($term && !is_wp_error($term)) {
                    $translated_categories[$lang] = [
                        'id' => $term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'description' => $term->description,
                        'count' => $term->count
                    ];
                }
            }
            
            // Se hide_empty for true, pula categorias que não têm traduções
            if ($hide_empty && count($translated_categories) <= 1) {
                continue;
            }
            
            $result[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'translations' => $translated_categories
            ];
        }
        
        // Obtém o total de categorias para paginação
        $total_categories = wp_count_terms('category', ['hide_empty' => $hide_empty]);
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $total_categories);
        $response->header('X-WP-TotalPages', ceil($total_categories / $per_page));
        
        return $response;
    }
    
    /**
     * Verifica se existe uma tradução para um post em um determinado idioma.
     */
    public function check_translation_existence($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
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
     * Lista posts sem traduções com paginação.
     */
    public function get_posts_missing_translations($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations') || !function_exists('pll_languages_list')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        // Obtém todos os idiomas configurados
        $languages = pll_languages_list();
        
        if (empty($languages)) {
            return new WP_REST_Response([]);
        }
        
        // Obtém posts do tipo especificado
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];
        
        $query = new WP_Query($args);
        $posts = $query->posts;
        
        $result = [];
        
        foreach ($posts as $post) {
            $translations = pll_get_post_translations($post->ID);
            $missing_languages = array_diff($languages, array_keys($translations));
            
            if (!empty($missing_languages)) {
                $post_language = pll_get_post_language($post->ID);
                
                $result[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'language' => $post_language,
                    'translations' => $translations,
                    'missing_translations' => array_values($missing_languages)
                ];
            }
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    /**
     * Obtém todos os slugs de um tipo de post com paginação.
     */
    public function get_all_slugs($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_language')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        // Obtém posts do tipo especificado
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];
        
        $query = new WP_Query($args);
        $posts = $query->posts;
        
        $result = [];
        
        foreach ($posts as $post) {
            $language = pll_get_post_language($post->ID);
            
            if (!$language) {
                continue;
            }
            
            $translations = pll_get_post_translations($post->ID);
            $slugs = [];
            
            foreach ($translations as $lang => $post_id) {
                $translated_post = get_post($post_id);
                if ($translated_post) {
                    $slugs[$lang] = $translated_post->post_name;
                }
            }
            
            // Se hide_empty for true, pula posts que não têm slug
            if ($hide_empty && empty($slugs)) {
                continue;
            }
            
            $result[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'language' => $language,
                'translated_slugs' => $slugs
            ];
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    /**
     * Cria uma tradução de slug para um post.
     */
    public function create_slug_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $slug = sanitize_title($params['slug']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_post_translations($post_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_post_id = $translations[$language_code];
        
        // Verifica se o slug já está em uso
        $slug_exists = get_page_by_path($slug, OBJECT, $post->post_type);
        if ($slug_exists && $slug_exists->ID != $translated_post_id) {
            return new WP_Error(
                'slug_exists',
                __('Este slug já está em uso por outro post.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Atualiza o slug do post traduzido
        $result = wp_update_post([
            'ID' => $translated_post_id,
            'post_name' => $slug
        ], true);
        
        if (is_wp_error($result)) {
            $this->log_action('create_slug_translation', 'error', 'Erro ao criar tradução de slug', [
                'post_id' => $post_id,
                'language_code' => $language_code,
                'slug' => $slug,
                'error' => $result->get_error_message()
            ]);
            return $result;
        }
        
        $this->log_action('create_slug_translation', 'success', 'Tradução de slug criada com sucesso', [
            'post_id' => $post_id,
            'language_code' => $language_code,
            'slug' => $slug
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'slug' => $slug,
            'message' => __('Tradução de slug criada com sucesso.', 'alvobot-pro')
        ], 200);
    }
    
    /**
     * Atualiza uma tradução existente de slug.
     */
    public function update_slug_translation($request) {
        // Este método é idêntico ao create_slug_translation, pois a operação é a mesma
        return $this->create_slug_translation($request);
    }
    
    /**
     * Exclui uma tradução existente de slug (redefine para o slug padrão).
     */
    public function delete_slug_translation($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém as traduções existentes
        $translations = pll_get_post_translations($post_id);
        
        // Verifica se existe uma tradução para este idioma
        if (!isset($translations[$language_code])) {
            return new WP_Error(
                'translation_not_found',
                __('Não existe uma tradução para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        $translated_post_id = $translations[$language_code];
        $translated_post = get_post($translated_post_id);
        
        // Gera um novo slug baseado no título
        $new_slug = sanitize_title($translated_post->post_title);
        
        // Atualiza o slug do post traduzido
        $result = wp_update_post([
            'ID' => $translated_post_id,
            'post_name' => $new_slug
        ], true);
        
        if (is_wp_error($result)) {
            $this->log_action('delete_slug_translation', 'error', 'Erro ao redefinir slug', [
                'post_id' => $post_id,
                'language_code' => $language_code,
                'error' => $result->get_error_message()
            ]);
            return $result;
        }
        
        $this->log_action('delete_slug_translation', 'success', 'Slug redefinido com sucesso', [
            'post_id' => $post_id,
            'language_code' => $language_code,
            'new_slug' => $new_slug
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $translated_post_id,
            'new_slug' => $new_slug,
            'message' => __('Slug redefinido com sucesso.', 'alvobot-pro')
        ], 200);
    }

    /**
     * Obtém a URL de um post em um idioma específico.
     */
    public function get_language_url($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post') || !function_exists('get_permalink')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém o ID do post traduzido
        $translated_post_id = pll_get_post($post_id, $language_code);
        
        if (!$translated_post_id) {
            return new WP_Error(
                'translation_not_found',
                __('Tradução não encontrada para este idioma.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Obtém a URL do post traduzido
        $url = get_permalink($translated_post_id);
        
        return new WP_REST_Response([
            'post_id' => $translated_post_id,
            'url' => $url
        ]);
    }
    
    /**
     * Obtém todas as traduções de posts com paginação.
     */
    public function get_translations($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $post_type = sanitize_text_field($params['post_type']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        // Obtém posts do tipo especificado
        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];
        
        $query = new WP_Query($args);
        $posts = $query->posts;
        
        $result = [];
        
        foreach ($posts as $post) {
            $translations = pll_get_post_translations($post->ID);
            $translated_posts = [];
            
            foreach ($translations as $lang => $post_id) {
                $translated_post = get_post($post_id);
                if ($translated_post) {
                    $translated_posts[$lang] = [
                        'id' => $post_id,
                        'title' => $translated_post->post_title,
                        'slug' => $translated_post->post_name,
                        'excerpt' => $translated_post->post_excerpt,
                        'date' => $translated_post->post_date
                    ];
                }
            }
            
            // Se hide_empty for true, pula posts que não têm traduções
            if ($hide_empty && count($translated_posts) <= 1) {
                continue;
            }
            
            $post_language = pll_get_post_language($post->ID);
            
            $result[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'language' => $post_language,
                'translations' => $translated_posts
            ];
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    /**
     * Obtém a lista de idiomas disponíveis.
     */
    
    /**
     * Obtém todas as taxonomias disponíveis para tradução.
     */
    public function get_taxonomies($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_languages_list')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Obtém todas as taxonomias públicas
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $result = [];
        
        foreach ($taxonomies as $taxonomy) {
            // Verifica se a taxonomia é traduzível pelo Polylang
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
     * Obtém todos os termos de uma taxonomia com suas traduções.
     */
    public function get_taxonomy_terms($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        $hide_empty = filter_var($params['hide_empty'], FILTER_VALIDATE_BOOLEAN);
        
        // Verifica se a taxonomia existe
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error(
                'taxonomy_not_found',
                __('Taxonomia não encontrada.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se a taxonomia é traduzível
        if (!pll_is_translated_taxonomy($taxonomy)) {
            return new WP_Error(
                'taxonomy_not_translatable',
                __('Esta taxonomia não é traduzível pelo Polylang.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Obtém os termos da taxonomia
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $hide_empty,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];
        
        $terms = get_terms($args);
        
        if (is_wp_error($terms)) {
            return $terms;
        }
        
        $result = [];
        
        foreach ($terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            $translated_terms = [];
            
            foreach ($translations as $lang => $term_id) {
                $translated_term = get_term($term_id, $taxonomy);
                if (!is_wp_error($translated_term)) {
                    $translated_terms[$lang] = [
                        'id' => $term_id,
                        'name' => $translated_term->name,
                        'slug' => $translated_term->slug,
                        'description' => $translated_term->description,
                        'count' => $translated_term->count
                    ];
                }
            }
            
            // Se hide_empty for true, pula termos que não têm traduções
            if ($hide_empty && count($translated_terms) <= 1) {
                continue;
            }
            
            $term_language = pll_get_term_language($term->term_id);
            
            $result[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $term->count,
                'language' => $term_language,
                'translations' => $translated_terms
            ];
        }
        
        $response = new WP_REST_Response($result);
        $response->header('X-WP-Total', wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty]));
        $response->header('X-WP-TotalPages', ceil(wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => $hide_empty]) / $per_page));
        
        return $response;
    }
    
    /**
     * Obtém termos sem traduções completas.
     */
    public function get_untranslated_terms($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_get_term_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $taxonomy = sanitize_text_field($params['taxonomy']);
        $per_page = intval($params['per_page']);
        $page = intval($params['page']);
        
        // Verifica se a taxonomia existe
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error(
                'taxonomy_not_found',
                __('Taxonomia não encontrada.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se a taxonomia é traduzível
        if (!pll_is_translated_taxonomy($taxonomy)) {
            return new WP_Error(
                'taxonomy_not_translatable',
                __('Esta taxonomia não é traduzível pelo Polylang.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Obtém todos os idiomas disponíveis
        $languages = pll_languages_list();
        
        // Obtém os termos da taxonomia
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ];
        
        $terms = get_terms($args);
        
        if (is_wp_error($terms)) {
            return $terms;
        }
        
        $untranslated_terms = [];
        
        foreach ($terms as $term) {
            $translations = pll_get_term_translations($term->term_id);
            $missing_languages = array_diff($languages, array_keys($translations));
            
            if (!empty($missing_languages)) {
                $term_language = pll_get_term_language($term->term_id);
                
                $untranslated_terms[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'count' => $term->count,
                    'current_language' => $term_language,
                    'missing_translations' => array_values($missing_languages)
                ];
            }
        }
        
        // Aplicar paginação manualmente
        $total = count($untranslated_terms);
        $max_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        
        $paginated_terms = array_slice($untranslated_terms, $offset, $per_page);
        
        $response = new WP_REST_Response($paginated_terms);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', $max_pages);
        
        return $response;
    }
    
    /**
     * Sincroniza as traduções de um post com o Polylang.
     */
    public function sync_translations($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_save_post_translations')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $translations = $params['translations'];
        
        if (!is_array($translations) || empty($translations)) {
            return new WP_Error(
                'invalid_translations',
                __('O parâmetro translations deve ser um array não vazio.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        // Verifica se todos os posts existem
        foreach ($translations as $lang => $post_id) {
            if (!get_post($post_id)) {
                return new WP_Error(
                    'post_not_found',
                    sprintf(__('Post com ID %d não encontrado.', 'alvobot-pro'), $post_id),
                    ['status' => 404]
                );
            }
        }
        
        // Salva as traduções
        pll_save_post_translations($translations);
        
        $this->log_action(
            'sync_translations',
            'success',
            'Traduções sincronizadas com sucesso',
            ['translations' => $translations]
        );
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Traduções sincronizadas com sucesso.', 'alvobot-pro'),
            'translations' => $translations
        ]);
    }
    
    /**
     * Obtém estatísticas de tradução para o site.
     */
    public function get_translation_stats() {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_languages_list')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
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
        
        // Estatísticas para taxonomias
        foreach ($taxonomies as $taxonomy) {
            if (pll_is_translated_taxonomy($taxonomy)) {
                $total_terms = wp_count_terms(['taxonomy' => $taxonomy]);
                $translated_terms = [];
                
                foreach ($languages as $language) {
                    $args = [
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false,
                        'lang' => $language
                    ];
                    
                    $terms = get_terms($args);
                    $translated_terms[$language] = is_array($terms) ? count($terms) : 0;
                }
                
                $stats['taxonomies'][$taxonomy] = [
                    'total' => $total_terms,
                    'by_language' => $translated_terms
                ];
            }
        }
        
        return new WP_REST_Response($stats);
    }
    
    /**
     * Altera o idioma de um post existente.
     */
    public function change_post_language($request) {
        // Verifica se o Polylang está ativo
        if (!function_exists('pll_set_post_language')) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo.', 'alvobot-pro'),
                ['status' => 400]
            );
        }
        
        $params = $request->get_params();
        $post_id = intval($params['post_id']);
        $language_code = sanitize_text_field($params['language_code']);
        $update_translations = filter_var($params['update_translations'], FILTER_VALIDATE_BOOLEAN);
        
        // Verifica se o post existe
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                __('Post não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Verifica se o idioma existe
        if (!PLL()->model->get_language($language_code)) {
            return new WP_Error(
                'language_not_found',
                __('Idioma não encontrado.', 'alvobot-pro'),
                ['status' => 404]
            );
        }
        
        // Altera o idioma do post
        pll_set_post_language($post_id, $language_code);
        
        // Atualiza as traduções se necessário
        if ($update_translations) {
            $translations = pll_get_post_translations($post_id);
            $translations[$language_code] = $post_id;
            pll_save_post_translations($translations);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'language_code' => $language_code,
            'message' => __('Idioma do post alterado com sucesso.', 'alvobot-pro')
        ]);
    }
    
    /**
     * Registra o campo pll_post_translations para posts e páginas na API REST
     */
    public function register_post_translations_field() {
        // Adiciona o campo `pll_post_translations` para posts e páginas.
        register_rest_field(
            ['post', 'page'], // Tipos de post onde o campo será adicionado.
            'pll_post_translations',
            [
                'get_callback' => function ($object) {
                    // Certifica-se de que a função do Polylang está disponível.
                    if (function_exists('pll_get_post_translations')) {
                        return pll_get_post_translations($object['id']);
                    }
                    return [];
                },
                'update_callback' => function ($value, $post) {
                    // Verifica se o Polylang está ativo e se as funções necessárias existem.
                    if (function_exists('pll_save_post_translations') && function_exists('pll_set_post_language')) {
                        // Obtém o idioma do post atual.
                        $current_lang = pll_get_post_language($post->ID);
                        
                        // Define o idioma do post atual, se não estiver definido.
                        if (!$current_lang && isset($value['lang'])) {
                            pll_set_post_language($post->ID, $value['lang']);
                        }

                        // Prepara as traduções para salvar.
                        $translations = [];
                        foreach ($value as $lang => $translated_post_id) {
                            // Verifica se o ID do post traduzido é válido.
                            if (get_post($translated_post_id)) {
                                $translations[$lang] = (int) $translated_post_id;
                            }
                        }

                        // Adiciona o próprio post às traduções, se o idioma estiver definido.
                        $post_lang = pll_get_post_language($post->ID);
                        if ($post_lang) {
                            $translations[$post_lang] = $post->ID;
                        }

                        // Salva as traduções.
                        pll_save_post_translations($translations);
                    }
                },
                'schema' => [
                    'description' => __('Polylang translations for the post.'),
                    'type'        => 'object',
                ],
            ]
        );
    }
    
    /**
     * Registra as páginas de administração do módulo.
     */
    public function register_admin_pages() {
        add_submenu_page(
            'alvobot-pro',
            __('Multi Languages', 'alvobot-pro'),
            __('Multi Languages', 'alvobot-pro'),
            'manage_options',
            'alvobot-pro-multi-languages',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Renderiza a página de documentação da API.
     */
    public function render_api_docs_page() {
        // Caminho absoluto para o arquivo de template
        $template_path = plugin_dir_path(__FILE__) . 'templates/multi-languages-api-docs.php';
        
        // Verificar se o arquivo existe
        if (file_exists($template_path)) {
            include_once $template_path;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('API Documentation', 'alvobot-pro') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Template file not found:', 'alvobot-pro') . ' ' . esc_html($template_path) . '</p></div>';
            echo '</div>';
        }
    }
}
