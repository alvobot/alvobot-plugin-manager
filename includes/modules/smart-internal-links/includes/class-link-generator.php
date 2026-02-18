<?php
/**
 * Smart Internal Links - Link Generator
 * Busca posts candidatos e chama a AI API para gerar textos dos links.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base compartilhada de países/idiomas (mantida em sincronia com frontend).
 */
if ( file_exists( dirname( __DIR__, 3 ) . '/shared/countries-languages.php' ) ) {
	require_once dirname( __DIR__, 3 ) . '/shared/countries-languages.php';
}

/**
 * Gera e valida blocos de Smart Internal Links para posts.
 */
class AlvoBotPro_Smart_Links_Generator {

	/**
	 * Busca posts candidatos da mesma categoria + língua
	 */
	public function get_candidate_posts( $post_id, $limit = 15 ) {
		$post       = get_post( $post_id );
		$post_type  = $post ? $post->post_type : 'post';
		$categories = wp_get_post_categories( $post_id );
		$language   = null;

		if ( function_exists( 'pll_get_post_language' ) ) {
			$language = $this->normalize_language_slug( pll_get_post_language( $post_id, 'slug' ) );
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( ! empty( $categories ) ) {
			$args['category__in'] = $categories;
		}

		if ( $language ) {
			$args['lang'] = $language;
		}

		/**
		 * Filtra os argumentos da query de candidatos (primeira busca: mesma categoria).
		 *
		 * @param array  $args    WP_Query args.
		 * @param int    $post_id Post sendo processado.
		 * @param int    $limit   Limite de candidatos.
		 */
		$args = apply_filters( 'alvobot_smart_links_candidate_args', $args, $post_id, $limit );

		$posts = get_posts( $args );

		// Fallback: se não tem posts suficientes na mesma categoria, buscar do mesmo idioma
		if ( count( $posts ) < $limit ) {
			$existing_ids = array_merge( array( $post_id ), wp_list_pluck( $posts, 'ID' ) );

			$fallback_args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit - count( $posts ),
				'post__not_in'   => $existing_ids,
				'orderby'        => 'rand',
				'no_found_rows'  => true,
			);

			if ( $language ) {
				$fallback_args['lang'] = $language;
			}

			/**
			 * Filtra os argumentos da query de candidatos fallback (busca aleatória).
			 *
			 * @param array $fallback_args WP_Query args.
			 * @param int   $post_id      Post sendo processado.
			 * @param array $existing_ids  IDs já encontrados.
			 */
			$fallback_args = apply_filters( 'alvobot_smart_links_candidate_fallback_args', $fallback_args, $post_id, $existing_ids );

			$fallback = get_posts( $fallback_args );
			$posts    = array_merge( $posts, $fallback );
		}

		return $posts;
	}

	/**
	 * Gera links para um post via AI API
	 */
	public function generate_for_post( $post_id, $settings = array() ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'invalid_post', 'Post não encontrado ou não publicado.' );
		}

		$defaults = get_option( 'alvobot_smart_links_settings', array() );
		$settings = wp_parse_args( $settings, $defaults );

		$links_per_block = isset( $settings['links_per_block'] ) ? absint( $settings['links_per_block'] ) : 3;
		$num_blocks      = isset( $settings['num_blocks'] ) ? absint( $settings['num_blocks'] ) : 3;

		// Validar ranges
		$links_per_block = max( 1, min( 5, $links_per_block ) );
		$num_blocks      = max( 1, min( 3, $num_blocks ) );

		$total_needed = $links_per_block * $num_blocks;

		// Buscar candidatos (2x o necessário para IA escolher os melhores)
		$candidates = $this->get_candidate_posts( $post_id, $total_needed * 2 );

		if ( empty( $candidates ) ) {
			AlvoBotPro::debug_log( 'smart-internal-links', "Nenhum candidato encontrado para post {$post_id}" );
			return new WP_Error( 'no_candidates', 'Nenhum post relacionado encontrado.' );
		}

		// Ajustar num_blocks se não tem candidatos suficientes
		$available = count( $candidates );
		if ( $available < $links_per_block ) {
			$links_per_block = $available;
			$num_blocks      = 1;
		} elseif ( $available < $total_needed ) {
			$num_blocks = max( 1, intval( floor( $available / $links_per_block ) ) );
		}

		// Hint de idioma canônico (ISO) para evitar ambiguidades no parser remoto.
		$language_hint_slug = $this->detect_post_language_slug( $post_id );
		$language_hint_name = $this->get_language_name_from_slug( $language_hint_slug );

		// Mapear candidatos para formato da API
		$candidate_data = array_map(
			function ( $p ) {
				return array(
					'id'    => $p->ID,
					'title' => $p->post_title,
				);
			},
			$candidates
		);

		// Chamar AI API
		$api    = AlvoBotPro_AI_API::get_instance();
		$result = $api->call(
			'generate_internal_links',
			array(
				'current_post'       => array(
					'title'   => $post->post_title,
					'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '' ),
				),
				'candidate_posts'    => $candidate_data,
				'language'           => 'auto-detect',
				'language_hint'      => $language_hint_slug,
				'language_hint_name' => $language_hint_name,
				'links_per_block'    => $links_per_block,
				'num_blocks'         => $num_blocks,
			)
		);

		if ( is_wp_error( $result ) ) {
			AlvoBotPro::debug_log( 'smart-internal-links', 'Erro na API: ' . $result->get_error_message() );
			return $result;
		}

		if ( empty( $result['success'] ) || empty( $result['data']['blocks'] ) ) {
			AlvoBotPro::debug_log( 'smart-internal-links', 'Resposta inválida da API: ' . wp_json_encode( $result ) );
			return new WP_Error( 'invalid_response', 'Resposta inválida da API.' );
		}

		// Validar e processar blocos da resposta
		$blocks    = $this->validate_api_blocks( $result['data']['blocks'], $candidates );
		$positions = array( 'after_first', 'middle', 'before_last' );

		if ( empty( $blocks ) ) {
			AlvoBotPro::debug_log( 'smart-internal-links', 'Nenhum bloco válido após validação da resposta' );
			return new WP_Error( 'invalid_response', 'A API não retornou links válidos.' );
		}

		$detected_language_slug = '';
		if ( isset( $result['data']['detected_language'] ) && is_string( $result['data']['detected_language'] ) ) {
			$detected_language_slug = $this->normalize_language_slug( $result['data']['detected_language'] );
		}
		if ( '' === $detected_language_slug ) {
			$detected_language_slug = $language_hint_slug;
		}

		// Adicionar posições aos blocos
		foreach ( $blocks as $i => &$block ) {
			$block['position'] = isset( $positions[ $i ] ) ? $positions[ $i ] : $positions[0];
		}
		unset( $block );

		// Salvar no post_meta
		$meta = array(
			'enabled'      => true,
			'generated_at' => current_time( 'mysql' ),
			'language'     => $detected_language_slug,
			'disclaimer'   => isset( $result['data']['disclaimer'] ) && is_string( $result['data']['disclaimer'] ) ? $result['data']['disclaimer'] : '',
			'blocks'       => $blocks,
		);

		update_post_meta( $post_id, '_alvobot_smart_links', $meta );
		delete_transient( 'alvobot_ai_credits' );

		/**
		 * Fires after links are generated for a post.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $meta    The saved meta data.
		 */
		do_action( 'alvobot_smart_links_generated', $post_id, $meta );

		AlvoBotPro::debug_log( 'smart-internal-links', "Links gerados para post {$post_id}: " . count( $blocks ) . ' blocos' );

		return $meta;
	}

	/**
	 * Valida a estrutura dos blocos retornados pela AI API.
	 * Garante que cada bloco tenha links com post_id válido e texto.
	 *
	 * @param array $api_blocks  Blocos da resposta da API.
	 * @param array $candidates  Posts candidatos (para validar IDs).
	 * @return array Blocos validados com URLs adicionadas.
	 */
	private function validate_api_blocks( $api_blocks, $candidates ) {
		if ( ! is_array( $api_blocks ) ) {
			return array();
		}

		// Construir mapa de IDs válidos dos candidatos
		$valid_ids = array();
		foreach ( $candidates as $c ) {
			$valid_ids[ $c->ID ] = true;
		}

		$validated_blocks = array();

		foreach ( $api_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ! isset( $block['links'] ) || ! is_array( $block['links'] ) ) {
				continue;
			}

			$valid_links = array();

			foreach ( $block['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				// post_id é obrigatório e deve ser de um candidato válido
				$link_post_id = isset( $link['post_id'] ) ? absint( $link['post_id'] ) : 0;
				if ( ! $link_post_id ) {
					continue;
				}

				// Verificar se o post_id é de um candidato ou ao menos existe e está publicado
				if ( ! isset( $valid_ids[ $link_post_id ] ) ) {
					$link_post = get_post( $link_post_id );
					if ( ! $link_post || 'publish' !== $link_post->post_status ) {
						continue;
					}
				}

				// text é obrigatório
				$text = isset( $link['text'] ) && is_string( $link['text'] ) ? trim( $link['text'] ) : '';
				if ( empty( $text ) ) {
					continue;
				}

				$valid_links[] = array(
					'post_id' => $link_post_id,
					'text'    => sanitize_text_field( $text ),
					'url'     => get_permalink( $link_post_id ),
				);
			}

			if ( ! empty( $valid_links ) ) {
				$validated_blocks[] = array(
					'links' => $valid_links,
				);
			}
		}

		return $validated_blocks;
	}

	/**
	 * Detecta slug de idioma do post por integrações da plataforma.
	 * Não faz inferência textual local; a detecção fina fica a cargo da IA.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function detect_post_language_slug( $post_id ) {
		$slug = '';

		if ( function_exists( 'pll_get_post_language' ) ) {
			$slug = $this->normalize_language_slug( pll_get_post_language( $post_id, 'slug' ) );
		}

		if ( '' === $slug ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- External WPML hook.
			$wpml_details = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $wpml_details ) && ! empty( $wpml_details['language_code'] ) ) {
				$slug = $this->normalize_language_slug( (string) $wpml_details['language_code'] );
			}
		}

		if ( '' === $slug ) {
			$slug = $this->normalize_language_slug( (string) substr( get_locale(), 0, 2 ) );
		}

		return '' !== $slug ? $slug : 'pt';
	}

	/**
	 * Normaliza slug de idioma (pt_BR -> pt, ja -> ja).
	 *
	 * @param string $slug Slug bruto.
	 * @return string
	 */
	private function normalize_language_slug( $slug ) {
		$slug = is_string( $slug ) ? trim( strtolower( str_replace( '_', '-', $slug ) ) ) : '';
		if ( '' === $slug ) {
			return '';
		}

		$parts   = explode( '-', $slug );
		$primary = isset( $parts[0] ) ? preg_replace( '/[^a-z]/', '', $parts[0] ) : '';
		if ( ! is_string( $primary ) || '' === $primary ) {
			return '';
		}

		return substr( $primary, 0, 3 );
	}

	/**
	 * Obtém o nome legível da língua a partir do slug.
	 *
	 * @param string $slug Slug ISO de idioma.
	 * @return string
	 */
	private function get_language_name_from_slug( $slug ) {
		$slug = $this->normalize_language_slug( $slug );
		if ( '' === $slug ) {
			$slug = 'pt';
		}

		$language_name = '';
		$native_name   = '';

		if ( function_exists( 'alvobot_get_languages' ) ) {
			$languages = alvobot_get_languages();
			if ( isset( $languages[ $slug ] ) && is_string( $languages[ $slug ] ) ) {
				$language_name = trim( $languages[ $slug ] );
			}
		}

		if ( function_exists( 'alvobot_get_language_native_name' ) ) {
			$maybe_native_name = alvobot_get_language_native_name( $slug );
			if ( is_string( $maybe_native_name ) && $slug !== $maybe_native_name ) {
				$native_name = trim( $maybe_native_name );
			}
		}

		if ( '' !== $language_name && '' !== $native_name && $language_name !== $native_name ) {
			return "{$language_name} ({$native_name})";
		}
		if ( '' !== $native_name ) {
			return $native_name;
		}
		if ( '' !== $language_name ) {
			return $language_name;
		}

		// Fallback para nome vindo do Polylang.
		if ( function_exists( 'PLL' ) && PLL()->model ) {
			$lang = PLL()->model->get_language( $slug );
			if ( $lang ) {
				return $lang->name;
			}
		}

		return $slug;
	}
}
