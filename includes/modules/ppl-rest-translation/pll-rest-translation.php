<?php
/**
 * Plugin Name: Polylang REST API Enhancer
 * Description: Adiciona o campo `pll_post_translations` às respostas da API REST para posts e páginas e permite sua atualização via API.
 * Version: 1.1
 * Author: Yan Fernandes
 */

// Impede acesso direto ao arquivo.
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
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

    // Adiciona rota para listar idiomas disponíveis
    register_rest_route('pll/v1', '/languages', array(
        'methods' => 'GET',
        'callback' => function () {
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
        },
        'permission_callback' => function () {
            return true;
        }
    ));

    // Adiciona rota para listar posts sem tradução completa
    register_rest_route('pll/v1', '/untranslated-posts', array(
        'methods' => 'GET',
        'callback' => function (WP_REST_Request $request) {
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
        },
        'permission_callback' => function () {
            return true;
        }
    ));

    // Rota para obter ID da categoria traduzida
    register_rest_route('pll/v1', '/category-translation', array(
        'methods' => 'GET',
        'callback' => function (WP_REST_Request $request) {
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
        },
        'permission_callback' => function () {
            return true;
        },
        'args' => array(
            'category_id' => array(
                'required' => true,
                'type' => 'integer',
                'description' => 'ID da categoria original'
            ),
            'lang' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Código do idioma desejado (ex: en, es, fr)'
            )
        )
    ));
});