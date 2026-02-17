<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_Rest_Api_Service' ) ) {
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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'rest_api_init', array( $this, 'add_language_field_to_posts' ) );
	}

	/**
	 * Define o service de tradução
	 */
	public function set_translation_service( $translation_service ) {
		$this->translation_service = $translation_service;
	}

	/**
	 * Registra todas as rotas da API REST
	 */
	public function register_rest_routes() {
		$auth_permission_callback = array( $this, 'permissions_check' );

		// Rota para listar idiomas disponíveis
		register_rest_route(
			$this->namespace,
			'/languages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_languages' ),
				'permission_callback' => $auth_permission_callback,
			)
		);

		// Rota para obter URL de um post em um idioma específico
			register_rest_route(
				$this->namespace,
				'/language-url',
				array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_language_url' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'post_id'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'language_code' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
						),
					),
				)
			);

			// Rotas canônicas para fila de tradução.
			register_rest_route(
				$this->namespace,
				'/queue/status',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_queue_status_endpoint' ),
					'permission_callback' => $auth_permission_callback,
				)
			);

			register_rest_route(
				$this->namespace,
				'/queue/add',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_to_queue_endpoint' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => array(
						'post_id'             => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'target_lang'         => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'target_langs'        => array(
							'required' => false,
						),
						'options'             => array(
							'required' => false,
						),
						'priority'            => array(
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
						'process_immediately' => array(
							'required' => false,
						),
					),
				)
			);

			// Rotas para tradução de posts
			register_rest_route(
				$this->namespace,
				'/translate',
				array(
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'create_translation' ),
						'permission_callback' => $auth_permission_callback,
						'args'                => $this->get_translation_args(),
					),
					array(
						'methods'             => 'PUT',
						'callback'            => array( $this, 'update_translation' ),
						'permission_callback' => $auth_permission_callback,
						'args'                => $this->get_translation_args(),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( $this, 'delete_translation' ),
						'permission_callback' => $auth_permission_callback,
						'args'                => array(
							'post_id'       => array(
								'required'          => true,
								'validate_callback' => function ( $param ) {
									return is_numeric( $param );
								},
							),
							'language_code' => array(
								'required'          => true,
								'validate_callback' => function ( $param ) {
									return is_string( $param ) && ! empty( $param );
								},
							),
						),
					),
				)
			);

		// Rota para verificar existência de tradução
		register_rest_route(
			$this->namespace,
			'/translations/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_translation_existence' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'post_id'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'language_code' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		// Rota para estatísticas de tradução
		register_rest_route(
			$this->namespace,
			'/translation-stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_translation_stats' ),
				'permission_callback' => $auth_permission_callback,
			)
		);

		// Rota para alterar idioma de um post existente
		register_rest_route(
			$this->namespace,
			'/change-post-language',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'change_post_language' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'post_id'             => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'language_code'       => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
					'update_translations' => array(
						'required'          => false,
						'default'           => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param );
						},
					),
				),
			)
		);

		// Rota para listar todas as traduções
		register_rest_route(
			$this->namespace,
			'/translations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_translations' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'page'      => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page'  => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
					'post_type' => array(
						'default'           => 'post',
						'validate_callback' => function ( $param ) {
							return post_type_exists( $param );
						},
					),
				),
			)
		);

		// Rota para listar posts sem tradução
		register_rest_route(
			$this->namespace,
			'/translations/missing',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_missing_translations' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'page'          => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page'      => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
					'post_type'     => array(
						'default'           => 'post',
						'validate_callback' => function ( $param ) {
							return post_type_exists( $param );
						},
					),
					'language_code' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		// Rotas para categorias
		register_rest_route(
			$this->namespace,
			'/translate/category',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_category_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => $this->get_category_translation_args(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_category_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => $this->get_category_translation_args(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_category_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => array(
						'category_id'   => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
						'language_code' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && ! empty( $param );
							},
						),
					),
				),
			)
		);

		// Rota para listar traduções de categorias
		register_rest_route(
			$this->namespace,
			'/translations/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_category_translations' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page' => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
				),
			)
		);

		// Rota para listar slugs
		register_rest_route(
			$this->namespace,
			'/slugs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_slugs' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'post_type' => array(
						'default'           => 'post',
						'validate_callback' => function ( $param ) {
							return post_type_exists( $param );
						},
					),
					'page'      => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page'  => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
				),
			)
		);

		// Rotas para traduções de slug
		register_rest_route(
			$this->namespace,
			'/translate/slug',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_slug_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => $this->get_slug_translation_args(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_slug_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => $this->get_slug_translation_args(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_slug_translation' ),
					'permission_callback' => $auth_permission_callback,
					'args'                => array(
						'post_id'       => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
						'language_code' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && ! empty( $param );
							},
						),
					),
				),
			)
		);

		// Rota para listar taxonomias
		register_rest_route(
			$this->namespace,
			'/taxonomies',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_taxonomies' ),
				'permission_callback' => $auth_permission_callback,
			)
		);

		// Rota para listar termos de taxonomia
		register_rest_route(
			$this->namespace,
			'/taxonomy/terms',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_taxonomy_terms' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'taxonomy'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return taxonomy_exists( $param );
						},
					),
					'page'       => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page'   => array(
						'default'           => 10,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param <= 100;
						},
					),
					'hide_empty' => array(
						'default'           => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param );
						},
					),
				),
			)
		);

		// Rota para termos não traduzidos
		register_rest_route(
			$this->namespace,
			'/taxonomy/untranslated',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_untranslated_terms' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'taxonomy'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return taxonomy_exists( $param );
						},
					),
					'language_code' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		// Rota para sincronizar traduções
		register_rest_route(
			$this->namespace,
			'/sync-translations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sync_translations' ),
				'permission_callback' => $auth_permission_callback,
				'args'                => array(
					'translations' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Callback para a rota de idiomas disponíveis
	 */
	public function get_languages() {
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return new WP_Error( 'pll_not_active', __( 'Polylang não está ativo.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		$pll_languages = PLL()->model->get_languages_list();
		$response_data = array();

		if ( empty( $pll_languages ) ) {
			return new WP_REST_Response( $response_data, 200 );
		}

		foreach ( $pll_languages as $language ) {
			$response_data[] = array(
				'code'        => $language->slug,
				'name'        => $language->name,
				'native_name' => isset( $language->native_name ) ? $language->native_name : $language->name,
				'locale'      => $language->locale,
				'flag'        => isset( $language->flag_url ) ? $language->flag_url : ( isset( $language->flag ) ? $language->flag : '' ),
				'is_rtl'      => isset( $language->is_rtl ) ? (bool) $language->is_rtl : false,
				'is_default'  => isset( $language->is_default ) ? (bool) $language->is_default : false,
			);
		}
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Obtém a URL de um post em um idioma específico
	 */
	public function get_language_url( $request ) {
		if ( ! function_exists( 'pll_get_post' ) || ! function_exists( 'get_permalink' ) ) {
			return new WP_Error( 'polylang_not_active', __( 'O plugin Polylang não está ativo.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Verifica se o post existe
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Verifica se o idioma existe
		if ( ! PLL()->model->get_language( $language_code ) ) {
			return new WP_Error( 'language_not_found', __( 'Idioma não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Obtém o ID do post traduzido
		$translated_post_id = pll_get_post( $post_id, $language_code );

		if ( ! $translated_post_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução não encontrada para este idioma.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Obtém a URL do post traduzido
		$url = get_permalink( $translated_post_id );

		return new WP_REST_Response(
			array(
				'post_id' => $translated_post_id,
				'url'     => $url,
			)
		);
	}

	/**
	 * Cria uma nova tradução para um post existente
	 */
	public function create_translation( $request ) {
		if ( ! $this->translation_service ) {
			return new WP_Error( 'service_not_available', __( 'Serviço de tradução não disponível.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		return $this->translation_service->create_translation( $request );
	}

	/**
	 * Verifica se existe uma tradução para um post em um determinado idioma
	 */
	public function check_translation_existence( $request ) {
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return new WP_Error( 'polylang_not_active', __( 'O plugin Polylang não está ativo.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Verifica se o post existe
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Verifica se o idioma existe
		if ( ! PLL()->model->get_language( $language_code ) ) {
			return new WP_Error( 'language_not_found', __( 'Idioma não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Obtém as traduções existentes
		$translations = pll_get_post_translations( $post_id );
		$exists       = isset( $translations[ $language_code ] );

		return new WP_REST_Response(
			array(
				'exists'  => $exists,
				'post_id' => $exists ? $translations[ $language_code ] : null,
			)
		);
	}

	/**
	 * Obtém estatísticas de tradução para o site
	 */
	public function get_translation_stats() {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			return new WP_Error( 'polylang_not_active', __( 'O plugin Polylang não está ativo.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$languages        = pll_languages_list();
		$default_language = pll_default_language();

		$post_types = array( 'post', 'page' );
		$taxonomies = array( 'category', 'post_tag' );

		$stats = array(
			'languages'        => count( $languages ),
			'default_language' => $default_language,
			'post_types'       => array(),
			'taxonomies'       => array(),
		);

		// Estatísticas para tipos de post
		foreach ( $post_types as $post_type ) {
			$total_posts      = wp_count_posts( $post_type )->publish;
			$translated_posts = array();

			foreach ( $languages as $language ) {
				$args = array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'lang'           => $language,
				);

				$query                         = new WP_Query( $args );
				$translated_posts[ $language ] = $query->found_posts;
			}

			$stats['post_types'][ $post_type ] = array(
				'total'       => $total_posts,
				'by_language' => $translated_posts,
			);
		}

		return new WP_REST_Response( $stats );
	}

	/**
	 * Altera o idioma de um post existente sem criar nova tradução
	 */
	public function change_post_language( $request ) {
		if ( ! function_exists( 'pll_set_post_language' ) ) {
			return new WP_Error( 'polylang_not_active', __( 'O plugin Polylang não está ativo.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$params              = $request->get_params();
		$post_id             = intval( $params['post_id'] );
		$language_code       = sanitize_text_field( $params['language_code'] );
		$update_translations = isset( $params['update_translations'] ) ? (bool) $params['update_translations'] : true;

		// Verifica se o post existe
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Verifica se o usuário pode editar o post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', __( 'Você não tem permissão para editar este post.', 'alvobot-pro' ), array( 'status' => 403 ) );
		}

		// Verifica se o idioma existe
		if ( ! PLL()->model->get_language( $language_code ) ) {
			return new WP_Error( 'language_not_found', __( 'Idioma não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Obtém o idioma atual do post
		$current_language = pll_get_post_language( $post_id );

		// Se já está no idioma correto, retorna sucesso
		if ( $current_language === $language_code ) {
			return new WP_REST_Response(
				array(
					'success'              => true,
					'post_id'              => $post_id,
					'previous_language'    => $current_language,
					'new_language'         => $language_code,
					'translations_updated' => false,
					'message'              => __( 'O post já estava neste idioma.', 'alvobot-pro' ),
					'no_change_needed'     => true,
				),
				200
			);
		}

		// Obtém traduções existentes antes da mudança
		$existing_translations = pll_get_post_translations( $post_id );

		// Verifica se já existe um post neste idioma
		if ( isset( $existing_translations[ $language_code ] ) ) {
			return new WP_Error(
				'translation_exists',
				__( 'Já existe uma tradução deste post para o idioma selecionado.', 'alvobot-pro' ),
				array(
					'status'           => 400,
					'existing_post_id' => $existing_translations[ $language_code ],
				)
			);
		}

		// Altera o idioma do post
		pll_set_post_language( $post_id, $language_code );

		// Se update_translations for true, atualiza o grupo de traduções
		if ( $update_translations && ! empty( $existing_translations ) ) {
			// Remove o post do grupo de traduções anterior
			unset( $existing_translations[ $current_language ] );

			// Adiciona o post ao grupo com o novo idioma
			$existing_translations[ $language_code ] = $post_id;

			// Atualiza as associações de tradução
			pll_save_post_translations( $existing_translations );
		}

		// Log da operação
		AlvoBotPro::debug_log(
			'multi-languages',
			sprintf(
				'Idioma do post #%d alterado de %s para %s',
				$post_id,
				$current_language,
				$language_code
			)
		);

		return new WP_REST_Response(
			array(
				'success'              => true,
				'post_id'              => $post_id,
				'previous_language'    => $current_language,
				'new_language'         => $language_code,
				'translations_updated' => $update_translations,
				'message'              => sprintf(
					__( 'Idioma do post alterado de %1$s para %2$s com sucesso.', 'alvobot-pro' ),
					$current_language,
					$language_code
				),
			),
			200
			);
	}

	/**
	 * Endpoint canônico para status da fila de tradução.
	 */
	public function get_queue_status_endpoint( $_request ) {
		$translation_queue = $this->get_translation_queue_instance();
		if ( ! $translation_queue ) {
			return new WP_Error( 'queue_unavailable', __( 'Sistema de fila não disponível.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( $translation_queue->get_queue_status(), 200 );
	}

	/**
	 * Endpoint canônico para adicionar item à fila de tradução.
	 */
	public function add_to_queue_endpoint( $request ) {
		$translation_queue = $this->get_translation_queue_instance();
		if ( ! $translation_queue ) {
			return new WP_Error( 'queue_unavailable', __( 'Sistema de fila não disponível.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post não encontrado.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden_post', __( 'Você não tem permissão para editar este post.', 'alvobot-pro' ), array( 'status' => 403 ) );
		}

		$target_langs = $this->parse_target_languages_from_request( $request );
		if ( is_wp_error( $target_langs ) ) {
			return $target_langs;
		}
		$source_lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id, 'slug' ) : '';
		if ( ! empty( $source_lang ) ) {
			$source_lang  = sanitize_text_field( strtolower( (string) $source_lang ) );
			$target_langs = array_values(
				array_filter(
					$target_langs,
					function ( $lang ) use ( $source_lang ) {
						return $lang !== $source_lang;
					}
				)
			);
		}
		if ( empty( $target_langs ) ) {
			return new WP_Error( 'invalid_target_langs', __( 'Nenhum idioma de destino válido após filtrar o idioma de origem.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$options = $this->parse_options_from_request( $request );

		$priority = $request->get_param( 'priority' );
		$priority = is_numeric( $priority ) ? (int) $priority : 10;

		$queue_id = $translation_queue->add_to_queue( $post_id, $target_langs, $options, $priority );
		if ( ! $queue_id ) {
			return new WP_Error( 'queue_add_failed', __( 'Falha ao adicionar item na fila.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		$process_immediately_param = $request->get_param( 'process_immediately' );
		$process_immediately_opt   = isset( $options['process_immediately'] ) ? $options['process_immediately'] : false;
		$process_immediately       = filter_var( $process_immediately_param, FILTER_VALIDATE_BOOLEAN ) || filter_var( $process_immediately_opt, FILTER_VALIDATE_BOOLEAN );

		$processed = false;
		if ( $process_immediately && method_exists( $translation_queue, 'process_specific_item' ) ) {
			$processed = (bool) $translation_queue->process_specific_item( $queue_id );
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'queue_id'  => $queue_id,
				'processed' => $processed,
				'message'   => $processed ? __( 'Item adicionado e processado.', 'alvobot-pro' ) : __( 'Item adicionado à fila.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Obtém a instância de fila de tradução a partir do serviço principal.
	 */
	private function get_translation_queue_instance() {
		if ( ! $this->translation_service || ! method_exists( $this->translation_service, 'get_translation_queue' ) ) {
			return null;
		}

		return $this->translation_service->get_translation_queue();
	}

	/**
	 * Extrai e valida idiomas alvo da requisição de fila.
	 *
	 * @return array|WP_Error
	 */
	private function parse_target_languages_from_request( $request ) {
		$target_langs = $request->get_param( 'target_langs' );
		if ( is_string( $target_langs ) ) {
			$decoded = json_decode( $target_langs, true );
			if ( is_array( $decoded ) ) {
				$target_langs = $decoded;
			} else {
				$target_langs = array_map( 'trim', explode( ',', $target_langs ) );
			}
		}

		if ( ! is_array( $target_langs ) ) {
			$target_langs = array();
		}

		$single_target_lang = sanitize_text_field( (string) $request->get_param( 'target_lang' ) );
		if ( ! empty( $single_target_lang ) ) {
			$target_langs[] = $single_target_lang;
		}

		$target_langs = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $lang ) {
							$lang = sanitize_text_field( (string) $lang );
							return strtolower( trim( $lang ) );
						},
						$target_langs
					)
				)
			)
		);

		if ( empty( $target_langs ) ) {
			return new WP_Error( 'invalid_target_langs', __( 'Informe target_lang ou target_langs.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		$available_slugs = $this->get_available_language_slugs();
		if ( ! empty( $available_slugs ) ) {
			$invalid_langs = array_values( array_diff( $target_langs, $available_slugs ) );
			if ( ! empty( $invalid_langs ) ) {
				return new WP_Error(
					'invalid_target_langs',
					sprintf( __( 'Idiomas inválidos: %s', 'alvobot-pro' ), implode( ', ', $invalid_langs ) ),
					array( 'status' => 400 )
				);
			}
		}

		return $target_langs;
	}

	/**
	 * Extrai opções da requisição de fila.
	 */
	private function parse_options_from_request( $request ) {
		$options = $request->get_param( 'options' );
		if ( is_string( $options ) ) {
			$decoded_options = json_decode( $options, true );
			if ( is_array( $decoded_options ) ) {
				$options = $decoded_options;
			}
		}

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Retorna slugs de idiomas válidos configurados no Polylang.
	 *
	 * @return array<int, string>
	 */
	private function get_available_language_slugs() {
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return array();
		}

		$languages = PLL()->model->get_languages_list();
		return array_values(
			array_filter(
				array_map(
					function ( $language ) {
						return isset( $language->slug ) ? sanitize_text_field( strtolower( (string) $language->slug ) ) : '';
					},
					$languages
				)
			)
		);
	}

	/**
	 * Verifica permissões do usuário - aceita Basic Auth
	 */
	public function permissions_check() {
		// Se o usuário já está logado
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		// Verifica autenticação Basic
		if ( ! isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return new WP_Error( 'missing_auth', __( 'Autorização necessária.', 'alvobot-pro' ), array( 'status' => 401 ) );
		}

		$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );

		// Verifica se é Basic Auth
		if ( ! preg_match( '/^Basic\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error( 'invalid_auth', __( 'Tipo de autorização inválido.', 'alvobot-pro' ), array( 'status' => 401 ) );
		}

		$credentials = base64_decode( $matches[1] );
		if ( ! $credentials ) {
			return new WP_Error( 'invalid_credentials', __( 'Credenciais inválidas.', 'alvobot-pro' ), array( 'status' => 401 ) );
		}

		list($username, $password) = explode( ':', $credentials, 2 );

		// Autentica o usuário
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			// Fallback: verifica se é um token de aplicação válido
			$user = $this->authenticate_with_application_token( $username, $password );
			if ( is_wp_error( $user ) ) {
				return new WP_Error( 'auth_failed', __( 'Falha na autenticação.', 'alvobot-pro' ), array( 'status' => 401 ) );
			}
		}

		// Verifica se o usuário pode editar posts
		if ( ! user_can( $user, 'edit_posts' ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'Permissões insuficientes.', 'alvobot-pro' ), array( 'status' => 403 ) );
		}

		// Define o usuário atual para o contexto da requisição
		wp_set_current_user( $user->ID );

		return true;
	}

	/**
	 * Autentica usando token de aplicação personalizado
	 */
	private function authenticate_with_application_token( $username, $token ) {
		// Busca o usuário pelo nome
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado.', 'alvobot-pro' ) );
		}

		// Verifica se existe um token personalizado salvo
		$saved_token = get_user_meta( $user->ID, 'alvobot_api_token', true );

		// Se não existe token salvo, cria um baseado no hash da senha + salt
		if ( empty( $saved_token ) ) {
			// Gera um token baseado na senha hash do usuário
			$user_data     = get_userdata( $user->ID );
			$password_hash = $user_data->user_pass;
			$saved_token   = substr( wp_hash( $password_hash . 'alvobot_salt' ), 0, 32 );

			// Salva o token para uso futuro
			update_user_meta( $user->ID, 'alvobot_api_token', $saved_token );
		}

		// Verifica se o token fornecido corresponde
		if ( $token === $saved_token ) {
			return $user;
		}

			return new WP_Error( 'invalid_token', __( 'Token inválido.', 'alvobot-pro' ) );
	}

	/**
	 * Argumentos comuns para tradução de posts
	 */
	private function get_translation_args() {
		return array(
			'post_id'       => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
			'language_code' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param ) && strlen( $param ) === 2;
				},
			),
			'title'         => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
			'content'       => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
		);
	}

	/**
	 * Atualiza uma tradução existente
	 */
	public function update_translation( $request ) {
		if ( ! $this->translation_service ) {
			return new WP_Error( 'service_not_available', __( 'Serviço de tradução não disponível.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Verifica se já existe uma tradução
		$translated_post_id = pll_get_post( $post_id, $language_code );

		if ( ! $translated_post_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Atualiza o post traduzido
		$update_data = array(
			'ID'           => $translated_post_id,
			'post_title'   => sanitize_text_field( $params['title'] ),
			'post_content' => wp_kses_post( $params['content'] ),
		);

		if ( isset( $params['excerpt'] ) ) {
			$update_data['post_excerpt'] = sanitize_textarea_field( $params['excerpt'] );
		}

		if ( isset( $params['slug'] ) ) {
			$update_data['post_name'] = sanitize_title( $params['slug'] );
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'post_id' => $translated_post_id,
				'message' => __( 'Tradução atualizada com sucesso.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Exclui uma tradução
	 */
	public function delete_translation( $request ) {
		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Obtém o ID do post traduzido
		$translated_post_id = pll_get_post( $post_id, $language_code );

		if ( ! $translated_post_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Verifica permissões
		if ( ! current_user_can( 'delete_post', $translated_post_id ) ) {
			return new WP_Error( 'forbidden', __( 'Você não tem permissão para excluir esta tradução.', 'alvobot-pro' ), array( 'status' => 403 ) );
		}

		// Exclui o post
		$result = wp_delete_post( $translated_post_id, false ); // Move para lixeira

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Falha ao excluir a tradução.', 'alvobot-pro' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Tradução excluída com sucesso.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Lista todas as traduções com paginação
	 */
	public function get_translations( $request ) {
		$params    = $request->get_params();
		$page      = intval( $params['page'] );
		$per_page  = intval( $params['per_page'] );
		$post_type = sanitize_text_field( $params['post_type'] );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array(
				array(
					'key'     => '_language',
					'compare' => 'EXISTS',
				),
			),
		);

		$query = new WP_Query( $args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$translations = pll_get_post_translations( $post->ID );
			$posts[]      = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'slug'         => $post->post_name,
				'language'     => pll_get_post_language( $post->ID ),
				'translations' => $translations,
			);
		}

		return new WP_REST_Response(
			array(
				'posts'        => $posts,
				'total'        => $query->found_posts,
				'pages'        => $query->max_num_pages,
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * Lista posts sem tradução em um idioma específico
	 */
	public function get_missing_translations( $request ) {
		$params        = $request->get_params();
		$page          = intval( $params['page'] );
		$per_page      = intval( $params['per_page'] );
		$post_type     = sanitize_text_field( $params['post_type'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Primeiro, pega todos os posts do tipo especificado
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$all_posts     = get_posts( $args );
		$missing_posts = array();

		foreach ( $all_posts as $post_id ) {
			$translations = pll_get_post_translations( $post_id );
			if ( ! isset( $translations[ $language_code ] ) ) {
				$missing_posts[] = $post_id;
			}
		}

		// Pagina os resultados
		$total       = count( $missing_posts );
		$offset      = ( $page - 1 ) * $per_page;
		$paged_posts = array_slice( $missing_posts, $offset, $per_page );

		$posts = array();
		foreach ( $paged_posts as $post_id ) {
			$post    = get_post( $post_id );
			$posts[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'slug'         => $post->post_name,
				'language'     => pll_get_post_language( $post->ID ),
				'translations' => pll_get_post_translations( $post->ID ),
			);
		}

		return new WP_REST_Response(
			array(
				'posts'        => $posts,
				'total'        => $total,
				'pages'        => ceil( $total / $per_page ),
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * Argumentos para tradução de categorias
	 */
	private function get_category_translation_args() {
		return array(
			'category_id'   => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
			'language_code' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param ) && ! empty( $param );
				},
			),
			'name'          => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
			'description'   => array(
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
			'slug'          => array(
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
		);
	}

	/**
	 * Cria tradução de categoria
	 */
	public function create_category_translation( $request ) {
		$params        = $request->get_params();
		$category_id   = intval( $params['category_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Verifica se a categoria existe
		$category = get_term( $category_id, 'category' );
		if ( ! $category || is_wp_error( $category ) ) {
			return new WP_Error( 'category_not_found', __( 'Categoria não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Verifica se já existe tradução
		$existing_translation = pll_get_term( $category_id, $language_code );
		if ( $existing_translation ) {
			return new WP_Error( 'translation_exists', __( 'Já existe uma tradução desta categoria.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		// Cria a nova categoria
		$new_category = wp_insert_term(
			sanitize_text_field( $params['name'] ),
			'category',
			array(
				'description' => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
				'slug'        => isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '',
			)
		);

		if ( is_wp_error( $new_category ) ) {
			return $new_category;
		}

		// Define o idioma da nova categoria
		pll_set_term_language( $new_category['term_id'], $language_code );

		// Associa as traduções
		$translations                   = pll_get_term_translations( $category_id );
		$translations[ $language_code ] = $new_category['term_id'];
		pll_save_term_translations( $translations );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'category_id' => $new_category['term_id'],
				'message'     => __( 'Tradução de categoria criada com sucesso.', 'alvobot-pro' ),
			),
			201
		);
	}

	/**
	 * Atualiza tradução de categoria
	 */
	public function update_category_translation( $request ) {
		$params        = $request->get_params();
		$category_id   = intval( $params['category_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Obtém a tradução existente
		$translated_category_id = pll_get_term( $category_id, $language_code );

		if ( ! $translated_category_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução de categoria não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Atualiza a categoria
		$update_data = array(
			'name' => sanitize_text_field( $params['name'] ),
		);

		if ( isset( $params['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $params['description'] );
		}

		if ( isset( $params['slug'] ) ) {
			$update_data['slug'] = sanitize_title( $params['slug'] );
		}

		$result = wp_update_term( $translated_category_id, 'category', $update_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'category_id' => $translated_category_id,
				'message'     => __( 'Tradução de categoria atualizada com sucesso.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Exclui tradução de categoria
	 */
	public function delete_category_translation( $request ) {
		$params        = $request->get_params();
		$category_id   = intval( $params['category_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Obtém a tradução
		$translated_category_id = pll_get_term( $category_id, $language_code );

		if ( ! $translated_category_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução de categoria não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Exclui a categoria
		$result = wp_delete_term( $translated_category_id, 'category' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Tradução de categoria excluída com sucesso.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Lista traduções de categorias
	 */
	public function get_category_translations( $request ) {
		$params   = $request->get_params();
		$page     = intval( $params['page'] );
		$per_page = intval( $params['per_page'] );

		$args = array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
		);

		$categories = get_terms( $args );
		$total      = wp_count_terms( 'category', array( 'hide_empty' => false ) );

		$data = array();
		foreach ( $categories as $category ) {
			$translations = pll_get_term_translations( $category->term_id );
			$data[]       = array(
				'id'           => $category->term_id,
				'name'         => $category->name,
				'slug'         => $category->slug,
				'language'     => pll_get_term_language( $category->term_id ),
				'translations' => $translations,
			);
		}

		return new WP_REST_Response(
			array(
				'categories'   => $data,
				'total'        => $total,
				'pages'        => ceil( $total / $per_page ),
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * Lista slugs
	 */
	public function get_slugs( $request ) {
		$params    = $request->get_params();
		$post_type = sanitize_text_field( $params['post_type'] );
		$page      = intval( $params['page'] );
		$per_page  = intval( $params['per_page'] );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		$slugs = array();

		foreach ( $query->posts as $post_id ) {
			$post             = get_post( $post_id );
			$translations     = pll_get_post_translations( $post_id );
			$translated_slugs = array();

			foreach ( $translations as $lang => $trans_id ) {
				$trans_post                = get_post( $trans_id );
				$translated_slugs[ $lang ] = $trans_post->post_name;
			}

			$slugs[] = array(
				'post_id'          => $post_id,
				'title'            => $post->post_title,
				'slug'             => $post->post_name,
				'language'         => pll_get_post_language( $post_id ),
				'translated_slugs' => $translated_slugs,
			);
		}

		return new WP_REST_Response(
			array(
				'slugs'        => $slugs,
				'total'        => $query->found_posts,
				'pages'        => $query->max_num_pages,
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * Argumentos para tradução de slug
	 */
	private function get_slug_translation_args() {
		return array(
			'post_id'       => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
			'language_code' => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param ) && ! empty( $param );
				},
			),
			'slug'          => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			),
		);
	}

	/**
	 * Cria tradução de slug
	 */
	public function create_slug_translation( $request ) {
		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );
		$slug          = sanitize_title( $params['slug'] );

		// Obtém o post traduzido
		$translated_post_id = pll_get_post( $post_id, $language_code );

		if ( ! $translated_post_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Atualiza o slug
		$result = wp_update_post(
			array(
				'ID'        => $translated_post_id,
				'post_name' => $slug,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'post_id' => $translated_post_id,
				'slug'    => $slug,
				'message' => __( 'Slug traduzido com sucesso.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Atualiza tradução de slug
	 */
	public function update_slug_translation( $request ) {
		// Mesmo comportamento que create_slug_translation
		return $this->create_slug_translation( $request );
	}

	/**
	 * Exclui tradução de slug (restaura para o padrão)
	 */
	public function delete_slug_translation( $request ) {
		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Obtém o post traduzido
		$translated_post_id = pll_get_post( $post_id, $language_code );

		if ( ! $translated_post_id ) {
			return new WP_Error( 'translation_not_found', __( 'Tradução não encontrada.', 'alvobot-pro' ), array( 'status' => 404 ) );
		}

		// Gera um slug baseado no título
		$post         = get_post( $translated_post_id );
		$default_slug = sanitize_title( $post->post_title );

		// Atualiza para o slug padrão
		$result = wp_update_post(
			array(
				'ID'        => $translated_post_id,
				'post_name' => $default_slug,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'post_id' => $translated_post_id,
				'slug'    => $default_slug,
				'message' => __( 'Slug restaurado para o padrão.', 'alvobot-pro' ),
			),
			200
		);
	}

	/**
	 * Lista taxonomias disponíveis
	 */
	public function get_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$data       = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( pll_is_translated_taxonomy( $taxonomy->name ) ) {
				$data[] = array(
					'name'           => $taxonomy->name,
					'label'          => $taxonomy->label,
					'singular_label' => $taxonomy->labels->singular_name,
					'hierarchical'   => $taxonomy->hierarchical,
				);
			}
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Lista termos de uma taxonomia
	 */
	public function get_taxonomy_terms( $request ) {
		$params     = $request->get_params();
		$taxonomy   = sanitize_text_field( $params['taxonomy'] );
		$page       = intval( $params['page'] );
		$per_page   = intval( $params['per_page'] );
		$hide_empty = (bool) $params['hide_empty'];

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $hide_empty,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
		);

		$terms = get_terms( $args );
		$total = wp_count_terms( $taxonomy, array( 'hide_empty' => $hide_empty ) );

		$data = array();
		foreach ( $terms as $term ) {
			$translations = pll_get_term_translations( $term->term_id );
			$data[]       = array(
				'id'           => $term->term_id,
				'name'         => $term->name,
				'slug'         => $term->slug,
				'language'     => pll_get_term_language( $term->term_id ),
				'translations' => $translations,
			);
		}

		return new WP_REST_Response(
			array(
				'terms'        => $data,
				'total'        => $total,
				'pages'        => ceil( $total / $per_page ),
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * Lista termos sem tradução completa
	 */
	public function get_untranslated_terms( $request ) {
		$params        = $request->get_params();
		$taxonomy      = sanitize_text_field( $params['taxonomy'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		$untranslated = array();
		foreach ( $terms as $term ) {
			$translations = pll_get_term_translations( $term->term_id );
			if ( ! isset( $translations[ $language_code ] ) ) {
				$untranslated[] = array(
					'id'           => $term->term_id,
					'name'         => $term->name,
					'slug'         => $term->slug,
					'language'     => pll_get_term_language( $term->term_id ),
					'translations' => $translations,
				);
			}
		}

		return new WP_REST_Response( $untranslated, 200 );
	}

	/**
	 * Sincroniza traduções
	 */
	public function sync_translations( $request ) {
		$params       = $request->get_params();
		$translations = $params['translations'];

		if ( empty( $translations ) || count( $translations ) < 2 ) {
			return new WP_Error( 'invalid_translations', __( 'São necessárias pelo menos 2 traduções para sincronizar.', 'alvobot-pro' ), array( 'status' => 400 ) );
		}

		// Verifica se todos os posts existem
		foreach ( $translations as $lang => $post_id ) {
			if ( ! get_post( $post_id ) ) {
				return new WP_Error( 'post_not_found', sprintf( __( 'Post #%d não encontrado.', 'alvobot-pro' ), $post_id ), array( 'status' => 404 ) );
			}
		}

		// Salva as associações de tradução
		pll_save_post_translations( $translations );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'Traduções sincronizadas com sucesso.', 'alvobot-pro' ),
				'translations' => $translations,
			),
			200
		);
	}

	/**
	 * Adiciona campo 'language' aos posts na API REST
	 */
	public function add_language_field_to_posts() {
		register_rest_field(
			array( 'post', 'page' ),
			'language',
			array(
				'get_callback'    => function ( $object ) {
					if ( function_exists( 'pll_get_post_language' ) ) {
						$language_code = pll_get_post_language( $object['id'], 'slug' );
						return $language_code ? $language_code : null;
					}
					return null;
				},
				'update_callback' => null,
				'schema'          => array(
					'description' => __( 'Código do idioma do post (Polylang)', 'alvobot-pro' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}
}
