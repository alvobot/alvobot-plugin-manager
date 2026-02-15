<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'AlvoBotPro_Credit_Translation_Provider' ) ) {
	return;
}

/**
 * Provider de traducao usando creditos AlvoBot via Edge Function
 *
 * Substitui a dependencia de chave OpenAI por creditos centralizados.
 * Cada chamada de traducao consome 1 credito.
 */
class AlvoBotPro_Credit_Translation_Provider implements AlvoBotPro_Translation_Provider_Interface {

	/** @var array Cache de linguagens do Polylang */
	private $polylang_languages = array();

	/** @var AlvoBotPro_AI_API */
	private $ai_api;

	public function __construct() {
		$this->ai_api = AlvoBotPro_AI_API::get_instance();
		$this->load_polylang_languages();
	}

	/**
	 * Carrega as linguagens configuradas no Polylang
	 */
	private function load_polylang_languages() {
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return;
		}

		try {
			$languages = PLL()->model->get_languages_list();

			if ( empty( $languages ) ) {
				return;
			}

			foreach ( $languages as $language ) {
				if ( ! isset( $language->slug ) || ! isset( $language->name ) ) {
					continue;
				}

				$this->polylang_languages[ $language->slug ] = array(
					'name'        => $language->name,
					'native_name' => isset( $language->flag_title ) ? $language->flag_title : $language->name,
					'locale'      => isset( $language->locale ) ? $language->locale : $language->slug,
					'slug'        => $language->slug,
					'flag'        => isset( $language->flag_url ) ? $language->flag_url : '',
					'is_rtl'      => isset( $language->is_rtl ) ? $language->is_rtl : false,
				);
			}
		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Erro ao carregar linguagens do Polylang: ' . $e->getMessage() );
		}
	}

	/**
	 * Traduz texto usando creditos AlvoBot
	 */
	public function translate( $text, $source_lang, $target_lang, $options = array() ) {
		try {
			if ( ! $this->is_configured() ) {
				return $this->error_response( 'Site nao esta registrado no AlvoBot. Conecte o site primeiro.' );
			}

			if ( empty( $text ) ) {
				return $this->error_response( 'Texto para traducao esta vazio.' );
			}

			if ( ! isset( $this->polylang_languages[ $target_lang ] ) ) {
				return $this->error_response( "Idioma de destino '{$target_lang}' nao encontrado no Polylang." );
			}

			$source_language_name = isset( $this->polylang_languages[ $source_lang ] )
				? $this->polylang_languages[ $source_lang ]['native_name']
				: 'auto-detect';

			$target_language_name = $this->polylang_languages[ $target_lang ]['native_name'];

			$preserve_html = isset( $options['preserve_html'] ) && $options['preserve_html'];
			$context       = isset( $options['context'] ) ? $options['context'] : '';

			$params = array(
				'text'            => $text,
				'source_language' => $source_language_name,
				'target_language' => $target_language_name,
				'preserve_html'   => $preserve_html,
				'context'         => $context,
			);

			AlvoBotPro::debug_log( 'multi-languages', 'Credit Provider: Traduzindo para ' . $target_lang . ' (' . strlen( $text ) . ' chars)' );

			$result = $this->ai_api->call( 'translate_text', $params );

			if ( is_wp_error( $result ) ) {
				$error_msg = $result->get_error_message();
				AlvoBotPro::debug_log( 'multi-languages', 'Credit Provider: Erro - ' . $error_msg );
				return $this->error_response( $error_msg );
			}

			$translated_text = '';
			if ( isset( $result['data']['translated_text'] ) ) {
				$translated_text = $result['data']['translated_text'];
			}

			if ( empty( $translated_text ) ) {
				return $this->error_response( 'Resposta vazia do servico de traducao.' );
			}

			AlvoBotPro::debug_log( 'multi-languages', 'Credit Provider: Traducao concluida (' . strlen( $translated_text ) . ' chars)' );

			return array(
				'success'         => true,
				'translated_text' => $translated_text,
				'source_language' => $source_lang,
				'target_language' => $target_lang,
				'provider'        => 'credits',
				'usage'           => array(
					'credits_consumed'  => isset( $result['credits']['consumed'] ) ? $result['credits']['consumed'] : 1,
					'credits_remaining' => isset( $result['credits']['remaining'] ) ? $result['credits']['remaining'] : 0,
				),
			);

		} catch ( Exception $e ) {
			return $this->error_response( 'Erro na traducao: ' . $e->getMessage() );
		}
	}

	/**
	 * Verifica se o provider esta configurado (site registrado no AlvoBot)
	 */
	public function is_configured() {
		return ! empty( get_option( 'alvobot_site_token' ) );
	}

	/**
	 * Verifica se o provider esta disponivel
	 */
	public function is_available() {
		return $this->is_configured() && ! empty( $this->polylang_languages );
	}

	/**
	 * Retorna o nome do provider
	 */
	public function get_name() {
		return 'AlvoBot AI (Creditos)';
	}

	/**
	 * Retorna a descricao do provider
	 */
	public function get_description() {
		return 'Traducao usando creditos AlvoBot. Cada traducao consome 1 credito.';
	}

	/**
	 * Retorna as linguagens suportadas (baseadas no Polylang)
	 */
	public function get_supported_languages() {
		return $this->polylang_languages;
	}

	/**
	 * Retorna estatisticas de uso (creditos do workspace)
	 */
	public function get_usage_stats() {
		$stats = get_option(
			'alvobot_credit_translation_stats',
			array(
				'total_translations' => 0,
				'last_reset'         => current_time( 'mysql' ),
			)
		);

		$credits = $this->ai_api->get_credits();
		if ( ! empty( $credits ) && ! isset( $credits['error'] ) ) {
			$stats['credits_available'] = isset( $credits['total_available'] ) ? $credits['total_available'] : 0;
			$stats['monthly_limit']     = isset( $credits['monthly_limit'] ) ? $credits['monthly_limit'] : 0;
			$stats['has_active_plan']   = ! empty( $credits['has_active_plan'] );
		}

		return $stats;
	}

	/**
	 * No-op: creditos sao gerenciados pela Edge Function
	 */
	public function update_usage_stats( $usage_data ) {
		return true;
	}

	/**
	 * Incrementa contador de posts traduzidos
	 */
	public function increment_post_translation_count() {
		$stats = get_option(
			'alvobot_credit_translation_stats',
			array(
				'total_translations' => 0,
				'last_reset'         => current_time( 'mysql' ),
			)
		);

		++$stats['total_translations'];
		update_option( 'alvobot_credit_translation_stats', $stats );

		AlvoBotPro::debug_log( 'multi-languages', 'Credit Provider: Posts traduzidos: ' . $stats['total_translations'] );
	}

	/**
	 * Valida configuracoes do provider
	 */
	public function validate_settings( $settings ) {
		$errors = array();

		if ( empty( get_option( 'alvobot_site_token' ) ) ) {
			$errors[] = 'Site nao esta registrado no AlvoBot';
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Testa conectividade com o servico
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Site nao esta registrado no AlvoBot',
			);
		}

		$credits = $this->ai_api->get_credits( true );

		if ( isset( $credits['error'] ) ) {
			return array(
				'success' => false,
				'message' => 'Falha na conexao: ' . $credits['error'],
			);
		}

		$available = isset( $credits['total_available'] ) ? $credits['total_available'] : 0;

		return array(
			'success'       => true,
			'message'       => 'Conexao estabelecida. ' . $available . ' creditos disponiveis.',
			'response_time' => 0,
		);
	}

	/**
	 * Retorna resposta de erro padronizada
	 */
	private function error_response( $message ) {
		return array(
			'success'   => false,
			'error'     => $message,
			'provider'  => 'credits',
			'timestamp' => current_time( 'mysql' ),
		);
	}
}
