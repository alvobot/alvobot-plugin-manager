<?php
/**
 * Smart Internal Links - Link Generator
 * Busca posts candidatos e chama a AI API para gerar textos dos links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Smart_Links_Generator {

	/**
	 * Busca posts candidatos da mesma categoria + língua
	 */
	public function get_candidate_posts( $post_id, $limit = 15 ) {
		$categories = wp_get_post_categories( $post_id );
		$language   = null;

		if ( function_exists( 'pll_get_post_language' ) ) {
			$language = pll_get_post_language( $post_id, 'slug' );
		}

		$args = array(
			'post_type'      => 'post',
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

		$posts = get_posts( $args );

		// Fallback: se não tem posts suficientes na mesma categoria, buscar do mesmo idioma
		if ( count( $posts ) < $limit ) {
			$existing_ids = array_merge( array( $post_id ), wp_list_pluck( $posts, 'ID' ) );

			$fallback_args = array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit - count( $posts ),
				'post__not_in'   => $existing_ids,
				'orderby'        => 'rand',
				'no_found_rows'  => true,
			);

			if ( $language ) {
				$fallback_args['lang'] = $language;
			}

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
		if ( ! $post || $post->post_status !== 'publish' ) {
			return new WP_Error( 'invalid_post', 'Post não encontrado ou não publicado.' );
		}

		$defaults = get_option( 'alvobot_smart_links_settings', array() );
		$settings = wp_parse_args( $settings, $defaults );

		$links_per_block = isset( $settings['links_per_block'] ) ? absint( $settings['links_per_block'] ) : 3;
		$num_blocks      = isset( $settings['num_blocks'] ) ? absint( $settings['num_blocks'] ) : 3;
		$total_needed    = $links_per_block * $num_blocks;

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

		// Determinar língua
		$language = $this->get_language_name( $post_id );

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
				'current_post'    => array(
					'title'   => $post->post_title,
					'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '' ),
				),
				'candidate_posts' => $candidate_data,
				'language'        => $language,
				'links_per_block' => $links_per_block,
				'num_blocks'      => $num_blocks,
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

		// Adicionar URLs e posições aos blocos
		$blocks    = $result['data']['blocks'];
		$positions = array( 'after_first', 'middle', 'before_last' );

		foreach ( $blocks as $i => &$block ) {
			$block['position'] = isset( $positions[ $i ] ) ? $positions[ $i ] : $positions[0];
			if ( isset( $block['links'] ) && is_array( $block['links'] ) ) {
				foreach ( $block['links'] as &$link ) {
					$link['url'] = get_permalink( $link['post_id'] );
				}
			}
		}
		unset( $block, $link );

		// Salvar no post_meta
		$meta = array(
			'enabled'      => true,
			'generated_at' => current_time( 'mysql' ),
			'language'     => function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id, 'slug' ) : substr( get_locale(), 0, 2 ),
			'disclaimer'   => isset( $result['data']['disclaimer'] ) ? $result['data']['disclaimer'] : '',
			'blocks'       => $blocks,
		);

		update_post_meta( $post_id, '_alvobot_smart_links', $meta );
		delete_transient( 'alvobot_ai_credits' );

		AlvoBotPro::debug_log( 'smart-internal-links', "Links gerados para post {$post_id}: " . count( $blocks ) . ' blocos' );

		return $meta;
	}

	/**
	 * Obtém o nome legível da língua do post
	 */
	private function get_language_name( $post_id ) {
		$slug = null;

		if ( function_exists( 'pll_get_post_language' ) ) {
			$slug = pll_get_post_language( $post_id, 'slug' );
		}

		if ( ! $slug ) {
			$slug = substr( get_locale(), 0, 2 );
		}

		// Tentar pegar nome do Polylang
		if ( function_exists( 'PLL' ) && PLL()->model ) {
			$lang = PLL()->model->get_language( $slug );
			if ( $lang ) {
				return $lang->name;
			}
		}

		$map = array(
			'pt' => 'Português',
			'en' => 'English',
			'es' => 'Español',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'it' => 'Italiano',
			'ro' => 'Română',
			'nl' => 'Nederlands',
			'pl' => 'Polski',
			'tr' => 'Türkçe',
			'ja' => '日本語',
			'zh' => '中文',
			'ko' => '한국어',
			'ar' => 'العربية',
			'ru' => 'Русский',
		);

		return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
	}
}
