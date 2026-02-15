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

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$meta = get_post_meta( $post_id, '_alvobot_smart_links', true );

		if ( empty( $meta ) || empty( $meta['enabled'] ) || empty( $meta['blocks'] ) ) {
			return $content;
		}

		// Separar conteúdo em parágrafos
		$paragraphs = $this->parse_paragraphs( $content );
		$total      = count( $paragraphs );

		if ( $total < 2 ) {
			return $content;
		}

		$settings   = get_option( 'alvobot_smart_links_settings', array() );
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
			$mid_index               = intval( $total / 2 );
			$insertions[ $mid_index ] = $blocks_by_position['middle'];
		}

		if ( isset( $blocks_by_position['before_last'] ) && $total >= 4 ) {
			$last_index               = $total - 1;
			$insertions[ $last_index ] = $blocks_by_position['before_last'];
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
				array_splice( $paragraphs, $index, 0, array( $html ) );
			}
		}

		return implode( '', $paragraphs );
	}

	/**
	 * Separa o conteúdo HTML em parágrafos
	 */
	private function parse_paragraphs( $content ) {
		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

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
