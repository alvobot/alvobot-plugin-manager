<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_Translation_Service' ) ) {
	return;
}

/**
 * Service consolidado para toda lógica de tradução
 * Unifica Translation Engine, Translation Queue e manipulação de posts
 */
class AlvoBotPro_Translation_Service {

	private $translation_engine;
	private $translation_queue;
	private static $instance = null;

	/**
	 * Singleton pattern para evitar múltiplas instâncias
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_dependencies();
	}

	/**
	 * Inicializa dependências com instâncias únicas
	 */
	private function init_dependencies() {
		// Usa variáveis globais para garantir instância única
		global $alvobot_translation_engine, $alvobot_translation_queue;

		if ( ! $alvobot_translation_engine && class_exists( 'AlvoBotPro_Translation_Engine' ) ) {
			$alvobot_translation_engine = new AlvoBotPro_Translation_Engine();
		}
		$this->translation_engine = $alvobot_translation_engine;

		if ( ! $alvobot_translation_queue && class_exists( 'AlvoBotPro_Translation_Queue' ) ) {
			$alvobot_translation_queue = new AlvoBotPro_Translation_Queue();
		}
		$this->translation_queue = $alvobot_translation_queue;
	}

	/**
	 * Traduz um post e cria nova versão traduzida
	 */
	private function get_translation_lock_key( $post_id, $target_lang ) {
		return "alvobot_translation_lock_{$post_id}_{$target_lang}";
	}

	private function acquire_translation_lock( $post_id, $target_lang ) {
		$lock_key     = $this->get_translation_lock_key( $post_id, $target_lang );
		$lock_timeout = 300; // 5 minutes timeout

		$lock = get_transient( $lock_key );
		if ( $lock ) {
			$lock_time    = intval( $lock );
			$current_time = time();

			// Se o lock é muito antigo (mais de 10 minutos), considera órfão e remove
			if ( ( $current_time - $lock_time ) > 600 ) {
				delete_transient( $lock_key );
				AlvoBotPro::debug_log( 'multi-languages', "Lock órfão removido para post {$post_id} idioma {$target_lang}" );
			} else {
				AlvoBotPro::debug_log( 'multi-languages', "Lock ativo encontrado para post {$post_id} idioma {$target_lang} - criado há " . ( $current_time - $lock_time ) . ' segundos' );
				return false;
			}
		}

		set_transient( $lock_key, time(), $lock_timeout );
		AlvoBotPro::debug_log( 'multi-languages', "Lock adquirido para post {$post_id} idioma {$target_lang}" );
		return true;
	}

	private function release_translation_lock( $post_id, $target_lang ) {
		$lock_key = $this->get_translation_lock_key( $post_id, $target_lang );
		delete_transient( $lock_key );
		AlvoBotPro::debug_log( 'multi-languages', "Lock liberado para post {$post_id} idioma {$target_lang}" );
	}

	/**
	 * Limpa todos os locks de tradução orfãos
	 */
	public function clear_orphaned_locks() {
		global $wpdb;

		// Remove transients antigos de locks de tradução
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < %d",
				'_transient_alvobot_translation_lock_%',
				time() - 600 // 10 minutos atrás
			)
		);

		if ( $result ) {
			AlvoBotPro::debug_log( 'multi-languages', "Removidos {$result} locks órfãos de tradução" );
		}

		return $result;
	}

	public function translate_and_create_post( $post_id, $target_lang, $options = array() ) {
		// Limpa locks órfãos primeiro
		$this->clear_orphaned_locks();

		// Verifica se o Polylang está ativo
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return array(
				'success' => false,
				'error'   => 'Polylang não está ativo',
			);
		}

		$post_id     = intval( $post_id );
		$target_lang = sanitize_text_field( $target_lang );

		if ( ! $post_id || ! $target_lang ) {
			return array(
				'success' => false,
				'error'   => 'Parâmetros inválidos',
			);
		}

		// Verifica se o post existe
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return array(
				'success' => false,
				'error'   => 'Post não encontrado',
			);
		}

		// Verifica se o idioma de destino existe
		$target_language = PLL()->model->get_language( $target_lang );
		if ( ! $target_language ) {
			return array(
				'success' => false,
				'error'   => 'Idioma de destino inválido',
			);
		}

		try {
			// Verifica se a API Key está configurada
			$openai_settings = get_option( 'alvobot_openai_settings', array() );
			if ( empty( $openai_settings['api_key'] ) ) {
				return array(
					'success' => false,
					'error'   => 'API Key do OpenAI não configurada',
				);
			}

			// Detecta idioma de origem
			$source_lang = pll_get_post_language( $post_id );
			if ( ! $source_lang ) {
				$source_lang = pll_default_language();
			}

			// Verifica se já existe tradução e tenta adquirir lock
			$existing_translation = pll_get_post( $post_id, $target_lang );

			if ( $existing_translation && ! isset( $options['force_overwrite'] ) ) {
				return array(
					'success' => false,
					'error'   => 'Já existe uma tradução para este idioma',
				);
			}

			// Tenta adquirir lock de tradução
			if ( ! $this->acquire_translation_lock( $post_id, $target_lang ) ) {
				return array(
					'success' => false,
					'error'   => 'Uma tradução já está em andamento para este post e idioma',
				);
			}

			$force_overwrite = isset( $options['force_overwrite'] ) && $options['force_overwrite'];

			// Obtém o post original
			$source_post = get_post( $post_id );
			if ( ! $source_post ) {
				throw new Exception( 'Post original não encontrado' );
			}

			// Traduz o conteúdo
			AlvoBotPro::debug_log( 'multi-languages', "Iniciando tradução do post {$post_id} de {$source_lang} para {$target_lang}" );
			$translation_result = $this->translation_engine->translate_post_content( $post_id, $target_lang, $options );

			if ( ! $translation_result['success'] ) {
				throw new Exception( 'Erro na tradução: ' . $translation_result['error'] );
			}

			// Cria ou atualiza o post traduzido
			if ( $existing_translation && $force_overwrite ) {
				$new_post_id    = $this->update_translated_post( $existing_translation, $translation_result, $target_lang );
				$action_message = 'atualizado';
			} elseif ( ! $existing_translation ) {
				$new_post_id    = $this->create_translated_post( $source_post, $translation_result, $target_lang );
				$action_message = 'criado';

				if ( $new_post_id ) {
					$this->link_translated_posts( $post_id, $new_post_id, $source_lang, $target_lang );
				}
			} else {
				$this->release_translation_lock( $post_id, $target_lang );
				return array(
					'success' => false,
					'error'   => 'Já existe uma tradução para este idioma',
				);
			}

			if ( ! $new_post_id ) {
				$this->release_translation_lock( $post_id, $target_lang );
				return array(
					'success' => false,
					'error'   => 'Erro ao processar post traduzido',
				);
			}

			// Traduz e atribui taxonomias (categorias, tags, etc.)
			$this->translate_and_assign_taxonomies( $post_id, $new_post_id, $source_lang, $target_lang );

			// Incrementa contador de posts traduzidos (apenas uma vez por post)
			if ( $this->translation_engine && method_exists( $this->translation_engine, 'get_active_provider' ) ) {
				$provider = $this->translation_engine->get_active_provider();
				if ( $provider && method_exists( $provider, 'increment_post_translation_count' ) ) {
					$provider->increment_post_translation_count();
				}
			}

			$this->release_translation_lock( $post_id, $target_lang );
			return array(
				'success'            => true,
				'new_post_id'        => $new_post_id,
				'edit_url'           => get_edit_post_link( $new_post_id, 'raw' ),
				'view_url'           => get_permalink( $new_post_id ),
				'target_language'    => $target_language->name,
				'strings_translated' => $translation_result['strings_translated'] ?? 0,
				'message'            => sprintf( 'Post %s com sucesso para %s', $action_message, $target_language->name ),
			);

		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Erro crítico na tradução: ' . $e->getMessage() );
			$this->release_translation_lock( $post_id, $target_lang );
			return array(
				'success' => false,
				'error'   => 'Erro interno: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Adiciona post à fila de tradução
	 */
	public function add_to_translation_queue( $post_id, $target_languages, $options = array() ) {
		if ( ! $this->translation_queue ) {
			return false;
		}

		return $this->translation_queue->add_to_queue( $post_id, $target_languages, $options );
	}

	/**
	 * Processa próximo item da fila
	 */
	public function process_queue_next_item() {
		if ( ! $this->translation_queue ) {
			return false;
		}

		return $this->translation_queue->process_next_item();
	}

	/**
	 * Cria uma nova tradução via API REST
	 */
	public function create_translation( $request ) {
		if ( ! function_exists( 'pll_get_post_translations' ) || ! function_exists( 'pll_set_post_language' ) ) {
			return new WP_Error( 'polylang_not_active', __( 'O plugin Polylang não está ativo.', 'alvobot-pro' ), [ 'status' => 400 ] );
		}

		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$language_code = sanitize_text_field( $params['language_code'] );

		// Verifica se o post existe
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post original não encontrado.', 'alvobot-pro' ), [ 'status' => 404 ] );
		}

		// Verifica se o idioma existe
		if ( ! PLL()->model->get_language( $language_code ) ) {
			return new WP_Error( 'language_not_found', __( 'Idioma não encontrado.', 'alvobot-pro' ), [ 'status' => 404 ] );
		}

		// Verifica se já existe uma tradução para este idioma
		$translations = pll_get_post_translations( $post_id );
		if ( isset( $translations[ $language_code ] ) ) {
			return new WP_Error( 'translation_exists', __( 'Já existe uma tradução para este idioma.', 'alvobot-pro' ), [ 'status' => 400 ] );
		}

		// Prepara os dados do post
		$post_data = [
			'post_title'   => sanitize_text_field( $params['title'] ),
			'post_content' => wp_kses_post( $params['content'] ),
			'post_status'  => $post->post_status,
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id(),
		];

		// Cria o post traduzido
		$translated_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $translated_post_id ) ) {
			return $translated_post_id;
		}

		// Define o idioma do post traduzido
		pll_set_post_language( $translated_post_id, $language_code );

		// Atualiza as traduções
		$translations[ $language_code ] = $translated_post_id;
		pll_save_post_translations( $translations );

		return new WP_REST_Response(
			[
				'success' => true,
				'post_id' => $translated_post_id,
				'message' => __( 'Tradução criada com sucesso.', 'alvobot-pro' ),
			],
			201
		);
	}

	/**
	 * Cria um novo post com o conteúdo traduzido
	 */
	private function create_translated_post( $source_post, $translation_result, $target_lang ) {
		// Prepara dados do novo post
		$new_post_data = array(
			'post_title'   => $source_post->post_title,
			'post_content' => $translation_result['translated_content'],
			'post_status'  => $source_post->post_status, // Inherit status from source post
			'post_type'    => $source_post->post_type,
			'post_parent'  => $source_post->post_parent,
			'menu_order'   => $source_post->menu_order,
			'post_author'  => get_current_user_id(),
		);

		// Traduz o título se disponível
		if ( isset( $translation_result['translated_title'] ) ) {
			$new_post_data['post_title'] = $translation_result['translated_title'];
		} else {
			// Traduz o título separadamente
			$title_result = $this->translation_engine->translate_text(
				$source_post->post_title,
				$translation_result['source_language'],
				$target_lang
			);

			if ( $title_result['success'] ) {
				$new_post_data['post_title'] = $title_result['translated_text'];
			}
		}

		// Cria o post
		$new_post_id = wp_insert_post( $new_post_data );

		if ( is_wp_error( $new_post_id ) ) {
			return false;
		}

		// Copia meta fields relevantes
		$this->copy_post_meta( $source_post->ID, $new_post_id, $translation_result );

		// Define o idioma do novo post
		pll_set_post_language( $new_post_id, $target_lang );

		return $new_post_id;
	}

	/**
	 * Atualiza um post traduzido existente
	 */
	private function update_translated_post( $post_id, $translation_result, $target_lang ) {
		$update_data = array(
			'ID'           => $post_id,
			'post_content' => $translation_result['translated_content'],
		);

		if ( isset( $translation_result['translated_title'] ) ) {
			$update_data['post_title'] = $translation_result['translated_title'];
		}

		$result = wp_update_post( $update_data );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $post_id;
	}

	/**
	 * Copia meta fields do post original para o traduzido
	 */
	private function copy_post_meta( $source_post_id, $new_post_id, $translation_result ) {
		$copy_meta_fields = array(
			'_thumbnail_id',
			'_wp_page_template',
			'_elementor_edit_mode',
			'_elementor_template_type',
			'_elementor_version',
		);

		foreach ( $copy_meta_fields as $meta_key ) {
			$meta_value = get_post_meta( $source_post_id, $meta_key, true );
			if ( ! empty( $meta_value ) ) {
				update_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}

		// Aplica meta fields traduzidos se disponíveis
		if ( isset( $translation_result['translated_meta'] ) ) {
			foreach ( $translation_result['translated_meta'] as $meta_key => $translated_value ) {
				update_post_meta( $new_post_id, $meta_key, $translated_value );
			}
		}
	}

	/**
	 * Vincula os posts traduzidos no Polylang
	 */
	private function link_translated_posts( $source_post_id, $new_post_id, $source_lang, $target_lang ) {
		$translations                 = pll_get_post_translations( $source_post_id );
		$translations[ $target_lang ] = $new_post_id;

		if ( ! isset( $translations[ $source_lang ] ) ) {
			$translations[ $source_lang ] = $source_post_id;
		}

		pll_save_post_translations( $translations );
	}

	/**
	 * Ordena termos por hierarquia (pais antes de filhos)
	 */
	private function sort_terms_by_hierarchy( $terms ) {
		$sorted  = array();
		$pending = $terms;
		$max_iterations = count( $terms ) * 2;
		$iteration = 0;

		while ( ! empty( $pending ) && $iteration < $max_iterations ) {
			$iteration++;
			foreach ( $pending as $key => $term ) {
				if ( $term->parent == 0 || isset( $sorted[ $term->parent ] ) ) {
					$sorted[ $term->term_id ] = $term;
					unset( $pending[ $key ] );
				}
			}
		}

		// Adiciona termos restantes (caso haja pais órfãos)
		foreach ( $pending as $term ) {
			$sorted[ $term->term_id ] = $term;
		}

		return array_values( $sorted );
	}

	/**
	 * Obtém ou cria um termo traduzido
	 *
	 * @param WP_Term $term         Termo original
	 * @param string  $taxonomy     Nome da taxonomia
	 * @param string  $source_lang  Idioma de origem
	 * @param string  $target_lang  Idioma de destino
	 * @param array   &$cache       Cache em memória [source_term_id => translated_term_id]
	 * @return int|null              ID do termo traduzido ou null em caso de falha
	 */
	private function get_or_create_translated_term( $term, $taxonomy, $source_lang, $target_lang, &$cache ) {
		// Verifica cache em memória
		if ( isset( $cache[ $term->term_id ] ) ) {
			return $cache[ $term->term_id ];
		}

		// Verifica se já existe tradução no Polylang
		if ( function_exists( 'pll_get_term_translations' ) ) {
			$translations = pll_get_term_translations( $term->term_id );
			if ( ! empty( $translations[ $target_lang ] ) ) {
				$cache[ $term->term_id ] = $translations[ $target_lang ];
				AlvoBotPro::debug_log( 'multi-languages', "Termo '{$term->name}' já tem tradução (ID: {$translations[$target_lang]}) para {$target_lang}" );
				return $translations[ $target_lang ];
			}
		}

		// Traduz o nome do termo
		$name_result = $this->translation_engine->translate_text( $term->name, $source_lang, $target_lang );
		if ( ! $name_result['success'] || empty( $name_result['translated_text'] ) ) {
			AlvoBotPro::debug_log( 'multi-languages', "Falha ao traduzir termo '{$term->name}' para {$target_lang}" );
			return null;
		}

		$translated_name = $name_result['translated_text'];

		// Traduz o slug
		$translated_slug = sanitize_title( $translated_name );

		// Resolve parent traduzido (se hierárquico)
		$translated_parent = 0;
		if ( $term->parent > 0 && isset( $cache[ $term->parent ] ) ) {
			$translated_parent = $cache[ $term->parent ];
		}

		// Cria o termo traduzido
		$insert_args = array(
			'slug'   => $translated_slug,
			'parent' => $translated_parent,
		);

		// Traduz descrição se existir
		if ( ! empty( $term->description ) ) {
			$desc_result = $this->translation_engine->translate_text( $term->description, $source_lang, $target_lang );
			if ( $desc_result['success'] && ! empty( $desc_result['translated_text'] ) ) {
				$insert_args['description'] = $desc_result['translated_text'];
			}
		}

		$result = wp_insert_term( $translated_name, $taxonomy, $insert_args );

		// Se o termo já existe com esse nome, reutiliza
		if ( is_wp_error( $result ) ) {
			$existing_id = $result->get_error_data( 'term_exists' );
			if ( $existing_id ) {
				$new_term_id = intval( $existing_id );
				AlvoBotPro::debug_log( 'multi-languages', "Termo '{$translated_name}' já existe (ID: {$new_term_id}), reutilizando" );
			} else {
				AlvoBotPro::debug_log( 'multi-languages', "Erro ao criar termo '{$translated_name}': " . $result->get_error_message() );
				return null;
			}
		} else {
			$new_term_id = $result['term_id'];
			AlvoBotPro::debug_log( 'multi-languages', "Termo '{$translated_name}' criado (ID: {$new_term_id}) em {$target_lang}" );
		}

		// Define idioma e vincula traduções no Polylang
		if ( function_exists( 'pll_set_term_language' ) ) {
			pll_set_term_language( $new_term_id, $target_lang );
		}

		if ( function_exists( 'pll_save_term_translations' ) ) {
			$term_translations = pll_get_term_translations( $term->term_id );
			$term_translations[ $target_lang ] = $new_term_id;
			if ( ! isset( $term_translations[ $source_lang ] ) ) {
				$term_translations[ $source_lang ] = $term->term_id;
			}
			pll_save_term_translations( $term_translations );
		}

		$cache[ $term->term_id ] = $new_term_id;
		return $new_term_id;
	}

	/**
	 * Traduz e atribui taxonomias (categorias/tags) ao post traduzido
	 */
	private function translate_and_assign_taxonomies( $source_post_id, $translated_post_id, $source_lang, $target_lang ) {
		// Taxonomias internas do Polylang que devem ser ignoradas
		$skip_taxonomies = array( 'language', 'post_translations', 'term_translations' );

		// Obtém todas as taxonomias do post
		$post_type  = get_post_type( $source_post_id );
		$taxonomies = get_object_taxonomies( $post_type );

		if ( empty( $taxonomies ) ) {
			return;
		}

		// Cache compartilhado entre todas as taxonomias deste batch
		$term_cache = array();

		foreach ( $taxonomies as $taxonomy ) {
			// Pula taxonomias internas
			if ( in_array( $taxonomy, $skip_taxonomies, true ) ) {
				continue;
			}

			// Pula taxonomias não traduzíveis no Polylang
			if ( function_exists( 'pll_is_translated_taxonomy' ) && ! pll_is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}

			// Obtém termos do post original
			$terms = wp_get_post_terms( $source_post_id, $taxonomy );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			// Ordena por hierarquia (pais primeiro)
			$terms = $this->sort_terms_by_hierarchy( $terms );

			$translated_term_ids = array();

			foreach ( $terms as $term ) {
				$translated_id = $this->get_or_create_translated_term( $term, $taxonomy, $source_lang, $target_lang, $term_cache );
				if ( $translated_id ) {
					$translated_term_ids[] = $translated_id;
				}
			}

			// Atribui termos traduzidos ao post traduzido
			if ( ! empty( $translated_term_ids ) ) {
				wp_set_post_terms( $translated_post_id, $translated_term_ids, $taxonomy );
				AlvoBotPro::debug_log( 'multi-languages', "Atribuídos " . count( $translated_term_ids ) . " termos de '{$taxonomy}' ao post {$translated_post_id}" );
			}
		}
	}

	/**
	 * Obtém instância do motor de tradução
	 */
	public function get_translation_engine() {
		return $this->translation_engine;
	}

	/**
	 * Obtém instância da fila de tradução
	 */
	public function get_translation_queue() {
		return $this->translation_queue;
	}
}
