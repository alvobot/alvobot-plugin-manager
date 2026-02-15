<?php
/**
 * Smart Internal Links - Content Injector
 * Insere blocos de links no conteúdo dos posts via the_content filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Smart_Links_Injector {

	private $renderer;

	public function __construct() {
		$this->renderer = new AlvoBotPro_Smart_Links_Renderer();
		add_filter( 'the_content', array( $this, 'inject_links' ), 15 );
	}

	/**
	 * Injeta blocos de links no conteúdo do post
	 */
	public function inject_links( $content ) {
		// Guards
		if ( ! is_singular() || is_admin() || wp_doing_ajax() ) {
			return $content;
		}

		// Verificar se estamos dentro do loop principal (evitar loops secundários, widgets, etc.)
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// REST API requests devem retornar conteúdo original
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $content;
		}

		$meta = AlvoBotPro_Smart_Internal_Links::get_validated_meta( $post_id );

		if ( ! $meta || empty( $meta['enabled'] ) || empty( $meta['blocks'] ) ) {
			return $content;
		}

		// Separar conteúdo em parágrafos (suporta <p>, <div>, <section>, etc.)
		$paragraphs = $this->parse_paragraphs( $content );
		$total      = count( $paragraphs );

		if ( $total < 2 ) {
			return $content;
		}

		$settings = get_option( 'alvobot_smart_links_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$disclaimer = isset( $meta['disclaimer'] ) ? $meta['disclaimer'] : '';

		// Mapear blocos por posição
		$blocks_by_position = array();
		foreach ( $meta['blocks'] as $block ) {
			$pos = isset( $block['position'] ) ? $block['position'] : '';
			if ( $pos ) {
				$blocks_by_position[ $pos ] = $block;
			}
		}

		// Calcular índices de inserção
		$insertions = array();

		if ( isset( $blocks_by_position['after_first'] ) ) {
			$insertions[1] = $blocks_by_position['after_first'];
		}

		if ( isset( $blocks_by_position['middle'] ) && $total >= 6 ) {
			$mid_index                = intval( $total / 2 );
			$insertions[ $mid_index ] = $blocks_by_position['middle'];
		}

		if ( isset( $blocks_by_position['before_last'] ) && $total >= 4 ) {
			$last_index                = $total - 1;
			$insertions[ $last_index ] = $blocks_by_position['before_last'];
		}

		/**
		 * Filtra as posições de inserção calculadas.
		 *
		 * @param array $insertions         Mapa index => block.
		 * @param int   $total              Total de parágrafos.
		 * @param int   $post_id            Post ID.
		 * @param array $blocks_by_position Blocos indexados por posição.
		 */
		$insertions = apply_filters( 'alvobot_smart_links_insertion_positions', $insertions, $total, $post_id, $blocks_by_position );

		if ( empty( $insertions ) ) {
			return $content;
		}

		// Garantir distância mínima entre blocos (2 parágrafos)
		$sorted_keys = array_keys( $insertions );
		sort( $sorted_keys );
		$valid_keys = array();

		foreach ( $sorted_keys as $key ) {
			$too_close = false;
			foreach ( $valid_keys as $vk ) {
				if ( abs( $key - $vk ) < 2 ) {
					$too_close = true;
					break;
				}
			}
			if ( ! $too_close ) {
				$valid_keys[] = $key;
			}
		}

		$filtered_insertions = array();
		foreach ( $valid_keys as $key ) {
			$filtered_insertions[ $key ] = $insertions[ $key ];
		}

		// Inserir blocos de trás para frente (para manter índices corretos)
		krsort( $filtered_insertions );
		foreach ( $filtered_insertions as $index => $block ) {
			$html = $this->renderer->render_block( $block, $disclaimer, $settings );
			if ( ! empty( $html ) ) {
				/**
				 * Filtra o HTML de um bloco antes de inserir no conteúdo.
				 *
				 * @param string $html     HTML renderizado do bloco.
				 * @param array  $block    Dados do bloco.
				 * @param int    $post_id  Post ID.
				 * @param array  $settings Configurações do módulo.
				 */
				$html = apply_filters( 'alvobot_smart_links_render_block', $html, $block, $post_id, $settings );
				array_splice( $paragraphs, $index, 0, array( $html ) );
			}
		}

		return implode( '', $paragraphs );
	}

	/**
	 * Separa o conteúdo HTML em blocos lógicos.
	 * Suporta parágrafos <p>, divs de page builders, e outros blocos HTML.
	 */
	private function parse_paragraphs( $content ) {
		// Padrão 1: Conteúdo baseado em <p> (WordPress clássico)
		if ( preg_match( '/<\/p>/i', $content ) ) {
			return $this->parse_by_tag( $content, 'p' );
		}

		// Padrão 2: Conteúdo baseado em <div> (page builders como Elementor, Divi)
		if ( preg_match( '/<\/div>/i', $content ) ) {
			return $this->parse_by_tag( $content, 'div' );
		}

		// Padrão 3: Conteúdo baseado em <br> (editor simples)
		$parts = preg_split( '/(<br\s*\/?>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $parts ) > 2 ) {
			$paragraphs = array();
			for ( $i = 0; $i < count( $parts ); $i += 2 ) {
				$para = $parts[ $i ];
				if ( isset( $parts[ $i + 1 ] ) ) {
					$para .= $parts[ $i + 1 ];
				}
				if ( trim( wp_strip_all_tags( $para ) ) ) {
					$paragraphs[] = $para;
				} elseif ( ! empty( $paragraphs ) ) {
					$paragraphs[ count( $paragraphs ) - 1 ] .= $para;
				} else {
					$paragraphs[] = $para;
				}
			}
			if ( count( $paragraphs ) >= 2 ) {
				return $paragraphs;
			}
		}

		// Fallback: retorna o conteúdo inteiro como 1 bloco (sem injeção)
		return array( $content );
	}

	/**
	 * Separa conteúdo por tag de fechamento (</p>, </div>, etc.)
	 */
	private function parse_by_tag( $content, $tag ) {
		$parts = preg_split( '/(<\/' . preg_quote( $tag, '/' ) . '>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		$paragraphs = array();
		for ( $i = 0; $i < count( $parts ); $i += 2 ) {
			$para = $parts[ $i ];
			if ( isset( $parts[ $i + 1 ] ) ) {
				$para .= $parts[ $i + 1 ];
			}

			// Só conta como parágrafo se tem texto visível
			if ( trim( wp_strip_all_tags( $para ) ) ) {
				$paragraphs[] = $para;
			} else {
				// Anexar ao parágrafo anterior (whitespace, empty divs, etc.)
				if ( ! empty( $paragraphs ) ) {
					$paragraphs[ count( $paragraphs ) - 1 ] .= $para;
				} else {
					$paragraphs[] = $para;
				}
			}
		}

		return $paragraphs;
	}
}
