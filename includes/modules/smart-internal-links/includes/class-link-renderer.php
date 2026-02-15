<?php
/**
 * Smart Internal Links - Link Renderer
 * Renderiza o HTML dos blocos de links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Smart_Links_Renderer {

	/**
	 * Renderiza um bloco de links
	 */
	public function render_block( $block, $disclaimer = '', $settings = array() ) {
		$links = isset( $block['links'] ) ? $block['links'] : array();

		// Filtrar links de posts que ainda existem e estão publicados
		$links = array_filter(
			$links,
			function ( $link ) {
				$post_id = isset( $link['post_id'] ) ? absint( $link['post_id'] ) : 0;
				if ( ! $post_id ) {
					return false;
				}
				$post = get_post( $post_id );
				return $post && $post->post_status === 'publish';
			}
		);

		if ( empty( $links ) ) {
			return '';
		}

		$bg_color     = ! empty( $settings['button_bg_color'] ) ? $settings['button_bg_color'] : '#1B3A5C';
		$text_color   = ! empty( $settings['button_text_color'] ) ? $settings['button_text_color'] : '#FFFFFF';
		$border_color = ! empty( $settings['button_border_color'] ) ? $settings['button_border_color'] : ( ! empty( $settings['border_color'] ) ? $settings['border_color'] : '#D4A843' );
		$border_size  = isset( $settings['button_border_size'] ) ? absint( $settings['button_border_size'] ) : 2;
		$border_style = $border_size > 0 ? 'border:' . $border_size . 'px solid ' . esc_attr( $border_color ) : 'border:none';

		$html  = '<div class="alvobot-sil">';
		$html .= '<div class="alvobot-sil__list">';

		foreach ( $links as $link ) {
			$url  = ! empty( $link['url'] ) ? $link['url'] : get_permalink( $link['post_id'] );
			$text = isset( $link['text'] ) ? $link['text'] : '';

			$html .= '<a href="' . esc_url( $url ) . '" class="alvobot-sil__btn" style="background:' . esc_attr( $bg_color ) . ';color:' . esc_attr( $text_color ) . ';' . $border_style . '">';
			$html .= '<span class="alvobot-sil__text">' . esc_html( $text ) . '</span>';
			$html .= '<span class="alvobot-sil__arrow" aria-hidden="true">';
			$html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>';
			$html .= '</span>';
			$html .= '</a>';
		}

		$html .= '</div>';

		if ( ! empty( $disclaimer ) ) {
			$html .= '<p class="alvobot-sil__disclaimer">' . esc_html( $disclaimer ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}
}
