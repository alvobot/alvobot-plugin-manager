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
            'methods' => 'POST',
            'callback' => array($this, 'create_translation'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => $this->get_translation_args(),
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
}