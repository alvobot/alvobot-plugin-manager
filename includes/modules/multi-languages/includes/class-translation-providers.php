<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 3 ) . '/shared/countries-languages.php';

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_OpenAI_Translation_Provider' ) ) {
	return;
}

/**
 * Provider OpenAI para tradução usando ChatGPT
 *
 * Integrado com as linguagens configuradas no Polylang
 */
class AlvoBotPro_OpenAI_Translation_Provider implements AlvoBotPro_Translation_Provider_Interface {

	/** @var array Configurações do provider */
	private $settings = array();

	/** @var array Cache de linguagens do Polylang */
	private $polylang_languages = array();

	/** @var string URL da API OpenAI */
	private $api_url = 'https://api.openai.com/v1/chat/completions';

	/** @var string URL da API OpenAI para buscar modelos */
	private $openai_models_url = 'https://api.openai.com/v1/models';

	/** @var array Cache de modelos disponíveis */
	private $available_models = array();

	public function __construct() {
		$this->load_settings();
		$this->load_polylang_languages();
		$this->load_available_models();
	}

	/**
	 * Carrega as configurações do provider
	 */
	private function load_settings() {
		$default_settings = array(
			'api_key'     => '',
			'model'       => 'gpt-4o-mini',
			'max_tokens'  => 3000,
			'temperature' => 0.3,
			'timeout'     => 60,
		);

		$saved_settings = get_option( 'alvobot_openai_settings', array() );
		$this->settings = array_merge( $default_settings, $saved_settings );

		// SEGURANÇA: Prioriza API key do wp-config.php sobre banco de dados
		if ( defined( 'ALVOBOT_OPENAI_API_KEY' ) && ! empty( ALVOBOT_OPENAI_API_KEY ) ) {
			$this->settings['api_key'] = ALVOBOT_OPENAI_API_KEY;
			AlvoBotPro::debug_log( 'multi-languages', 'OpenAI API key carregada do wp-config.php' );
		} elseif ( ! empty( $this->settings['api_key'] ) ) {
			AlvoBotPro::debug_log( 'multi-languages', 'OpenAI API key carregada do banco de dados' );
		} else {
			AlvoBotPro::debug_log( 'multi-languages', 'ALERTA: Nenhuma API key configurada' );
		}
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
				// Verifica se o objeto de linguagem tem as propriedades necessárias
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
	 * Traduz texto usando OpenAI
	 *
	 * @param string $text Texto a ser traduzido
	 * @param string $source_lang Idioma de origem (slug do Polylang)
	 * @param string $target_lang Idioma de destino (slug do Polylang)
	 * @param array  $options Opções adicionais
	 * @return array Resultado da tradução
	 */
	public function translate( $text, $source_lang, $target_lang, $options = array() ) {
		try {
			// Validação
			if ( ! $this->is_configured() ) {
				return $this->error_response( 'OpenAI não está configurado. Adicione uma API key.' );
			}

			if ( empty( $text ) ) {
				return $this->error_response( 'Texto para tradução está vazio.' );
			}

			// Verifica se as linguagens estão configuradas no Polylang
			if ( ! isset( $this->polylang_languages[ $target_lang ] ) ) {
				return $this->error_response( "Idioma de destino '{$target_lang}' não encontrado no Polylang." );
			}

			$source_language_name = isset( $this->polylang_languages[ $source_lang ] )
				? $this->polylang_languages[ $source_lang ]['native_name']
				: 'auto-detect';

			$target_language_name = $this->polylang_languages[ $target_lang ]['native_name'];

			// Prepara o prompt
			$prompt = $this->build_translation_prompt( $text, $source_language_name, $target_language_name, $options );

			// Prepara os dados da requisição
			$request_data = array(
				'model'       => $this->settings['model'],
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are a professional translator. Translate the given text accurately while preserving formatting, tone, and context. Return only the translated text without explanations.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'max_tokens'  => $this->settings['max_tokens'],
				'temperature' => $this->settings['temperature'],
			);

			// Headers da requisição
			$headers = array(
				'Authorization' => 'Bearer ' . $this->settings['api_key'],
				'Content-Type'  => 'application/json',
			);

			// Faz a requisição
			$response = $this->make_request( $request_data, $headers );

			if ( ! $response['success'] ) {
				return $response;
			}

			$result = json_decode( $response['data'], true );

			if ( ! $result || ! isset( $result['choices'][0]['message']['content'] ) ) {
				return $this->error_response( 'Resposta inválida da OpenAI.' );
			}

			$translated_text = trim( $result['choices'][0]['message']['content'] );

			// Remove aspas adicionais se presentes
			if ( substr( $translated_text, 0, 1 ) === '"' && substr( $translated_text, -1 ) === '"' ) {
				$translated_text = substr( $translated_text, 1, -1 );
			}

			// LIMPEZA AUTOMÁTICA: Remove tags órfãs e limpa entidades HTML
			$translated_text = $this->clean_orphaned_tags( $translated_text );

			$usage_info = array(
				'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
				'total_tokens'      => $result['usage']['total_tokens'] ?? 0,
			);

			return array(
				'success'         => true,
				'translated_text' => $translated_text,
				'source_language' => $source_lang,
				'target_language' => $target_lang,
				'provider'        => 'openai',
				'model'           => $this->settings['model'],
				'usage'           => $usage_info,
			);

		} catch ( Exception $e ) {
			return $this->error_response( 'Erro na OpenAI: ' . $e->getMessage() );
		}
	}

	/**
	 * Constrói o prompt de tradução
	 */
	private function build_translation_prompt( $text, $source_lang_name, $target_lang_name, $options ) {
		$preserve_html = isset( $options['preserve_html'] ) && $options['preserve_html'];
		$context       = isset( $options['context'] ) ? $options['context'] : '';

		// Determinar código ISO para maior precisão
		$target_lang_code = $this->get_language_iso_code( $target_lang_name );

		$prompt = "You are an expert multilingual translator. Your task is to translate the provided text accurately and fluently.

**Instructions:**
1. Translate the text from {$source_lang_name} to {$target_lang_name} ({$target_lang_code}).
2. Preserve the original tone, context, and intent.
3. **CRITICAL**: Preserve ALL paragraph breaks and line breaks EXACTLY as they appear in the original text. Do not merge paragraphs or remove line breaks.

**CRITICAL - Shortcodes and JSON:**
1. DO NOT modify or translate ANY content between shortcode tags [shortcode]...[/shortcode]
2. DO NOT modify or translate ANY content that looks like JSON (text between { and })
3. Treat the following as immutable blocks that must be preserved exactly as they are:
   - [quiz]...content...[/quiz]
   - Any JSON objects/arrays with properties like 'question', 'answers', 'styles'
   - Any shortcode parameters like [shortcode param=\"value\"]
4. If you see JSON or shortcodes, copy them verbatim without any changes
5. NEVER add spaces or line breaks inside JSON or shortcode content";

		// Instruções específicas para HTML
		if ( $preserve_html ) {
			$prompt .= "
3. If the text contains HTML tags like <p>, <strong>, <h1>, <h2>, <h3>, <div>, or <img>, keep them intact. **PRESERVE ALL LINE BREAKS EXACTLY AS THEY APPEAR IN THE ORIGINAL TEXT** - do not add or remove any line breaks.
4. **CRITICAL**: NEVER add new HTML tags, IDs, spans, divs, or attributes that were not in the original text.
5. **CRITICAL**: Do not add any <span>, <div>, or other wrapper elements around translated text.
6. Maintain the exact same HTML structure - do not modify IDs, classes, or tag attributes.
7. Do not add closing tags like </div> if they were not in the original text.
8. **CRITICAL**: Convert HTML entities like &nbsp; to regular spaces (convert &nbsp; to space).
9. **CRITICAL**: ALWAYS remove orphaned closing tags like </span>, </div>, </section> that appear without matching opening tags.
10. **CRITICAL**: Remove any standalone closing tags that don't have corresponding opening tags in the text.
11. Do not wrap translated text in additional HTML elements.
12. **NEVER** add structural elements like <span>, <div>, <section> that don't exist in the original.
13. **CLEAN-UP RULE**: If you see closing tags like </span> or </div> without matching opening tags, remove them completely.";
		} else {
			$prompt .= '
3. Return only clean text without any HTML tags or scripts.';
		}

		$prompt .= "

**FINAL CRITICAL INSTRUCTIONS:**
14. **Crucially, your response must contain ONLY the translated text and nothing else.** Do not add any explanations, apologies, or introductory phrases like \"Here is the translation:\".
15. **CRITICAL: Do not add, modify, or remove any HTML structure. Only translate the text content within existing tags.**
16. **ABSOLUTELY FORBIDDEN**: Do not add <span>, <div>, <section>, or any wrapper elements.
17. **ABSOLUTELY FORBIDDEN**: Do not add closing tags like </div>, </span>, </section> that weren't in the original.
18. **MANDATORY CLEAN-UP**: Convert &nbsp; to regular spaces, remove orphaned closing tags without opening pairs.
19. **QUALITY CHECK**: Before responding, verify no orphaned </span>, </div>, </section> tags exist in your output.";

		if ( ! empty( $context ) ) {
			$prompt .= "

**Context for this text:** {$context}";
		}

		$prompt .= "

Now, translate the following text:

---
{$text}
---";

		return $prompt;
	}

	/**
	 * Converte nome de idioma para código ISO 639-1
	 */
	private function get_language_iso_code( $language_name ) {
		return alvobot_get_language_iso_code( $language_name );
	}

	/**
	 * Faz requisição para a API OpenAI com logging detalhado
	 */
	private function make_request( $data, $headers ) {
		$request_start_time = microtime( true );
		$timestamp          = date( 'Y-m-d H:i:s' );

		// Prepara dados da requisição
		$args = array(
			'body'       => wp_json_encode( $data ),
			'headers'    => $headers,
			'timeout'    => $this->settings['timeout'],
			'user-agent' => 'AlvoBot Multi Languages',
		);

		// LOGGING DETALHADO - INÍCIO DA REQUISIÇÃO
		$this->log_detailed_request_start( $data, $headers, $args, $timestamp );

		// Faz a requisição
		$response = wp_remote_post( $this->api_url, $args );

		$request_end_time = microtime( true );
		$request_duration = round( ( $request_end_time - $request_start_time ) * 1000, 2 ); // ms

		if ( is_wp_error( $response ) ) {
			$this->log_detailed_request_error( $response, $args, $request_duration, $timestamp );

			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$body             = wp_remote_retrieve_body( $response );
		$code             = wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		// LOGGING DETALHADO - RESPOSTA COMPLETA
		$this->log_detailed_response( $code, $body, $response_headers, $request_duration, $timestamp );

		// Atualiza informações de rate limit para o motor de tradução
		if ( class_exists( 'AlvoBotPro_Translation_Engine' ) ) {
			global $alvobot_translation_engine;
			if ( $alvobot_translation_engine && method_exists( $alvobot_translation_engine, 'update_api_rate_limit_info' ) ) {
				$alvobot_translation_engine->update_api_rate_limit_info( (array) $response_headers );
			}
		}

		if ( $code !== 200 ) {
			$error_data    = json_decode( $body, true );
			$error_message = isset( $error_data['error']['message'] )
				? $error_data['error']['message']
				: 'HTTP Error: ' . $code;

			$this->log_detailed_api_error( $code, $error_data, $body, $request_duration, $timestamp );

			return array(
				'success' => false,
				'error'   => $error_message,
			);
		}

		// LOGGING DETALHADO - SUCESSO
		$this->log_detailed_success( $body, $request_duration, $timestamp );

		return array(
			'success'       => true,
			'data'          => $body,
			'response_code' => $code,
		);
	}

	/**
	 * Log detalhado do início da requisição com payload completo
	 */
	private function log_detailed_request_start( $data, $headers, $args, $timestamp ) {
		// Calcula estatísticas do texto
		$text_to_translate = '';
		if ( isset( $data['messages'] ) && is_array( $data['messages'] ) ) {
			foreach ( $data['messages'] as $message ) {
				if ( isset( $message['content'] ) ) {
					$text_to_translate .= $message['content'] . ' ';
				}
			}
		}

		$text_length            = strlen( trim( $text_to_translate ) );
		$estimated_input_tokens = ceil( $text_length / 4 ); // Estimativa aproximada
		$word_count             = str_word_count( trim( $text_to_translate ) );

		// Custo estimado
		$model_info            = $this->get_model_info( $data['model'] ?? 'unknown' );
		$estimated_input_cost  = 0;
		$estimated_output_cost = 0;

		if ( $model_info ) {
			$estimated_input_cost  = ( $estimated_input_tokens / 1000 ) * $model_info['cost_input'];
			$estimated_output_cost = ( ( $data['max_tokens'] ?? 1000 ) / 1000 ) * $model_info['cost_output'];
		}

		$total_estimated_cost = $estimated_input_cost + $estimated_output_cost;

		// Headers limpos para log (sem API key)
		$safe_headers = $headers;
		if ( isset( $safe_headers['Authorization'] ) ) {
			$api_key_preview               = 'sk-' . substr( $this->settings['api_key'], 3, 4 ) . '...' . substr( $this->settings['api_key'], -4 );
			$safe_headers['Authorization'] = 'Bearer ' . $api_key_preview;
		}

		// Payload limpo para log
		$safe_data = $this->sanitize_log_data( $data, 0, 350 );
		$safe_args = $this->sanitize_log_data( $args, 0, 350 );

		// Log estruturado
		$log_entry = [
			'timestamp'       => $timestamp,
			'event'           => 'OPENAI_REQUEST_START',
			'request_details' => [
				'url'         => $this->api_url,
				'method'      => 'POST',
				'model'       => $data['model'] ?? 'unknown',
				'max_tokens'  => $data['max_tokens'] ?? 0,
				'temperature' => $data['temperature'] ?? 0,
				'timeout'     => $this->settings['timeout'],
				'user_agent'  => 'AlvoBot Multi Languages',
			],
			'text_analytics'  => [
				'text_length_chars'      => $text_length,
				'estimated_input_tokens' => $estimated_input_tokens,
				'word_count'             => $word_count,
				'text_preview'           => substr( trim( $text_to_translate ), 0, 200 ) . '...',
			],
			'cost_estimation' => [
				'model_input_cost_per_1k'   => $model_info['cost_input'] ?? 0,
				'model_output_cost_per_1k'  => $model_info['cost_output'] ?? 0,
				'estimated_input_cost_usd'  => $estimated_input_cost,
				'estimated_output_cost_usd' => $estimated_output_cost,
				'total_estimated_cost_usd'  => $total_estimated_cost,
			],
			'headers'         => $safe_headers,
			'payload'         => $safe_data,
			'request_args'    => $safe_args,
		];

		AlvoBotPro::debug_log( 'multi-languages', '[START] OPENAI REQUEST:' . wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Log detalhado de erro de requisição
	 */
	private function log_detailed_request_error( $wp_error, $args, $duration_ms, $timestamp ) {
		$safe_args = $args;
		if ( isset( $safe_args['headers']['Authorization'] ) ) {
			$api_key_preview                       = 'sk-' . substr( $this->settings['api_key'], 3, 4 ) . '...' . substr( $this->settings['api_key'], -4 );
			$safe_args['headers']['Authorization'] = 'Bearer ' . $api_key_preview;
		}
		$safe_args = $this->sanitize_log_data( $safe_args, 0, 300 );

		$log_entry = [
			'timestamp'           => $timestamp,
			'event'               => 'OPENAI_REQUEST_ERROR',
			'error_details'       => [
				'type'    => 'wp_error',
				'message' => $wp_error->get_error_message(),
				'code'    => $wp_error->get_error_code(),
				'data'    => $wp_error->get_error_data(),
			],
			'request_duration_ms' => $duration_ms,
			'request_args'        => $safe_args,
		];

		AlvoBotPro::debug_log( 'multi-languages', '[ERROR] OPENAI REQUEST:' . wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Log detalhado da resposta completa
	 */
	private function log_detailed_response( $code, $body, $response_headers, $duration_ms, $timestamp ) {
		$response_size_bytes = strlen( $body );
		$response_size_kb    = round( $response_size_bytes / 1024, 2 );

		// Rate limit headers importantes
		$rate_limit_info = [
			'remaining_requests' => $response_headers['x-ratelimit-remaining-requests'] ?? 'unknown',
			'remaining_tokens'   => $response_headers['x-ratelimit-remaining-tokens'] ?? 'unknown',
			'reset_requests'     => $response_headers['x-ratelimit-reset-requests'] ?? 'unknown',
			'reset_tokens'       => $response_headers['x-ratelimit-reset-tokens'] ?? 'unknown',
			'limit_requests'     => $response_headers['x-ratelimit-limit-requests'] ?? 'unknown',
			'limit_tokens'       => $response_headers['x-ratelimit-limit-tokens'] ?? 'unknown',
		];

		$log_entry = [
			'timestamp'             => $timestamp,
			'event'                 => 'OPENAI_RESPONSE_RECEIVED',
			'response_details'      => [
				'http_code'                => $code,
				'response_size_bytes'      => $response_size_bytes,
				'response_size_kb'         => $response_size_kb,
				'request_duration_ms'      => $duration_ms,
				'throughput_kb_per_second' => $duration_ms > 0 ? round( ( $response_size_kb / $duration_ms ) * 1000, 2 ) : 0,
			],
			'rate_limit_info'       => $rate_limit_info,
			'response_body_preview' => $this->truncate_log_string( $body, 500 ),
		];

		AlvoBotPro::debug_log( 'multi-languages', '[RESPONSE] OPENAI:' . wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Log detalhado de erro da API
	 */
	private function log_detailed_api_error( $code, $error_data, $body, $duration_ms, $timestamp ) {
		$log_entry = [
			'timestamp'           => $timestamp,
			'event'               => 'OPENAI_API_ERROR',
			'error_details'       => [
				'http_code'     => $code,
				'error_type'    => $error_data['error']['type'] ?? 'unknown',
				'error_code'    => $error_data['error']['code'] ?? 'unknown',
				'error_message' => $error_data['error']['message'] ?? 'Unknown error',
				'error_param'   => $error_data['error']['param'] ?? null,
			],
			'rate_limit_analysis' => [
				'is_rate_limit_error'   => $code === 429,
				'retry_after_suggested' => $this->extract_retry_after( $error_data ),
				'rate_limit_type'       => $this->identify_rate_limit_type( $error_data ),
			],
			'request_duration_ms' => $duration_ms,
			'error_response'      => $this->truncate_log_string( $body, 500 ),
		];

		AlvoBotPro::debug_log( 'multi-languages', '[ERROR] OPENAI API:' . wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Log detalhado de sucesso
	 */
	private function log_detailed_success( $body, $duration_ms, $timestamp ) {
		$response_data = json_decode( $body, true );

		// Extrai informações de uso
		$usage_info        = $response_data['usage'] ?? [];
		$prompt_tokens     = $usage_info['prompt_tokens'] ?? 0;
		$completion_tokens = $usage_info['completion_tokens'] ?? 0;
		$total_tokens      = $usage_info['total_tokens'] ?? 0;

		// Calcula custos reais
		$model_id   = $response_data['model'] ?? $this->settings['model'];
		$model_info = $this->get_model_info( $model_id );

		$actual_input_cost  = 0;
		$actual_output_cost = 0;

		if ( $model_info ) {
			$actual_input_cost  = ( $prompt_tokens / 1000 ) * $model_info['cost_input'];
			$actual_output_cost = ( $completion_tokens / 1000 ) * $model_info['cost_output'];
		}

		$total_actual_cost = $actual_input_cost + $actual_output_cost;

		// Extrai texto traduzido
		$translated_text = '';
		if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
			$translated_text = trim( $response_data['choices'][0]['message']['content'] );
		}

		$log_entry = [
			'timestamp'          => $timestamp,
			'event'              => 'OPENAI_SUCCESS',
			'success_details'    => [
				'request_duration_ms' => $duration_ms,
				'tokens_per_second'   => $duration_ms > 0 ? round( ( $total_tokens / $duration_ms ) * 1000, 2 ) : 0,
				'model_used'          => $model_id,
				'finish_reason'       => $response_data['choices'][0]['finish_reason'] ?? 'unknown',
			],
			'token_usage'        => [
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens'      => $total_tokens,
				'efficiency_ratio'  => $prompt_tokens > 0 ? round( $completion_tokens / $prompt_tokens, 2 ) : 0,
			],
			'cost_calculation'   => [
				'input_cost_usd'  => $actual_input_cost,
				'output_cost_usd' => $actual_output_cost,
				'total_cost_usd'  => $total_actual_cost,
				'cost_per_token'  => $total_tokens > 0 ? round( $total_actual_cost / $total_tokens, 6 ) : 0,
			],
			'translation_result' => [
				'output_length_chars' => strlen( $translated_text ),
				'output_word_count'   => str_word_count( $translated_text ),
				'translation_preview' => $this->truncate_log_string( $translated_text, 200 ),
			],
		];

		AlvoBotPro::debug_log( 'multi-languages', '[SUCCESS] OPENAI:' . wp_json_encode( $log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Gera comando curl equivalente para debug
	 */
	private function generate_curl_equivalent( $data, $headers ) {
		$curl_parts   = [ 'curl -X POST' ];
		$curl_parts[] = '"' . $this->api_url . '"';

		foreach ( $headers as $key => $value ) {
			$curl_parts[] = '-H "' . $key . ': ' . $value . '"';
		}

		$curl_parts[] = '-H "Content-Type: application/json"';
		$curl_parts[] = "-d '" . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . "'";
		$curl_parts[] = '--connect-timeout ' . $this->settings['timeout'];
		$curl_parts[] = '--max-time ' . ( $this->settings['timeout'] + 10 );

		return implode( ' ', $curl_parts );
	}

	/**
	 * Sanitiza dados antes do log para reduzir vazamento e volume.
	 */
	private function sanitize_log_data( $data, $depth = 0, $max_string_length = 300 ) {
		if ( $depth > 4 ) {
			return '[max-depth]';
		}

		if ( is_array( $data ) ) {
			$sanitized = [];
			foreach ( $data as $key => $value ) {
				$normalized_key = is_string( $key ) ? strtolower( $key ) : '';
				if ( $this->is_sensitive_log_key( $normalized_key ) ) {
					$sanitized[ $key ] = '[redacted]';
					continue;
				}
				$sanitized[ $key ] = $this->sanitize_log_data( $value, $depth + 1, $max_string_length );
			}
			return $sanitized;
		}

		if ( is_object( $data ) ) {
			if ( $data instanceof Traversable ) {
				return $this->sanitize_log_data( iterator_to_array( $data ), $depth + 1, $max_string_length );
			}
			return '[object ' . get_class( $data ) . ']';
		}

		if ( is_string( $data ) ) {
			return $this->truncate_log_string( $data, $max_string_length );
		}

		return $data;
	}

	/**
	 * Indica chaves sensíveis para mascaramento no log.
	 */
	private function is_sensitive_log_key( $key ) {
		$sensitive_fragments = [
			'authorization',
			'api_key',
			'token',
			'password',
			'secret',
		];

		foreach ( $sensitive_fragments as $fragment ) {
			if ( strpos( $key, $fragment ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Trunca string para manter logs leves.
	 */
	private function truncate_log_string( $value, $max_length = 300 ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$length = strlen( $value );
		if ( $length <= $max_length ) {
			return $value;
		}

		return substr( $value, 0, $max_length ) . '...[truncated ' . ( $length - $max_length ) . ' chars]';
	}

	/**
	 * Extrai informação de retry-after do erro
	 */
	private function extract_retry_after( $error_data ) {
		if ( isset( $error_data['error']['message'] ) ) {
			$message = $error_data['error']['message'];

			// Procura por padrões como "Try again in 6s" ou "retry after 30s"
			if ( preg_match( '/try again in (\d+)s/i', $message, $matches ) ) {
				return (int) $matches[1];
			}

			if ( preg_match( '/retry after (\d+)s/i', $message, $matches ) ) {
				return (int) $matches[1];
			}

			if ( preg_match( '/please wait (\d+) seconds/i', $message, $matches ) ) {
				return (int) $matches[1];
			}
		}

		return null;
	}

	/**
	 * Identifica o tipo de rate limit
	 */
	private function identify_rate_limit_type( $error_data ) {
		if ( isset( $error_data['error']['message'] ) ) {
			$message = strtolower( $error_data['error']['message'] );

			if ( strpos( $message, 'requests per minute' ) !== false ) {
				return 'requests_per_minute';
			}

			if ( strpos( $message, 'tokens per minute' ) !== false ) {
				return 'tokens_per_minute';
			}

			if ( strpos( $message, 'requests per day' ) !== false ) {
				return 'requests_per_day';
			}

			if ( strpos( $message, 'tokens per day' ) !== false ) {
				return 'tokens_per_day';
			}
		}

		return 'unknown';
	}

	/**
	 * Verifica se o provider está configurado
	 */
	public function is_configured() {
		return ! empty( $this->settings['api_key'] );
	}

	/**
	 * Verifica se o provider está disponível
	 */
	public function is_available() {
		return $this->is_configured() && ! empty( $this->polylang_languages );
	}

	/**
	 * Retorna o nome do provider
	 */
	public function get_name() {
		return 'OpenAI ChatGPT';
	}

	/**
	 * Retorna a descrição do provider
	 */
	public function get_description() {
		return 'Tradução de alta qualidade usando OpenAI ChatGPT. Integrado com idiomas do Polylang.';
	}

	/**
	 * Retorna as linguagens suportadas (baseadas no Polylang)
	 */
	public function get_supported_languages() {
		return $this->polylang_languages;
	}

	/**
	 * Atualiza as configurações
	 */
	public function update_settings( $new_settings ) {
		$this->settings = array_merge( $this->settings, $new_settings );
		update_option( 'alvobot_openai_settings', $this->settings );
		return true;
	}

	/**
	 * Retorna as configurações atuais
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Testa a conexão com a OpenAI
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'error'   => 'API key não configurada',
			);
		}

		// Teste simples de tradução
		$test_result = $this->translate( 'Hello', 'en', 'pt', array( 'test' => true ) );

		if ( $test_result['success'] ) {
			return array(
				'success' => true,
				'message' => 'Conexão com OpenAI estabelecida com sucesso',
				'model'   => $this->settings['model'],
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Falha na conexão: ' . $test_result['error'],
			);
		}
	}

	/**
	 * Retorna estatísticas de uso
	 */
	public function get_usage_stats() {
		$stats = get_option(
			'alvobot_openai_usage_stats',
			array(
				'total_translations'  => 0,
				'total_tokens'        => 0,
				'total_cost_estimate' => 0,
				'last_reset'          => current_time( 'mysql' ),
			)
		);

		return $stats;
	}

	/**
	 * Atualiza estatísticas de uso
	 */
	public function update_usage_stats( $usage_data ) {
		$stats = $this->get_usage_stats();

		// NÃO incrementa total_translations aqui - será feito apenas quando post completo for traduzido
		$stats['total_tokens'] += $usage_data['total_tokens'] ?? 0;

		// Calcula custo real baseado no modelo usado
		$model_info = $this->get_model_info( $this->settings['model'] );
		if ( $model_info && isset( $usage_data['prompt_tokens'] ) && isset( $usage_data['completion_tokens'] ) ) {
			$input_cost  = ( $usage_data['prompt_tokens'] / 1000 ) * $model_info['cost_input'];
			$output_cost = ( $usage_data['completion_tokens'] / 1000 ) * $model_info['cost_output'];
			$total_cost  = $input_cost + $output_cost;
		} else {
			// Fallback para estimativa básica
			$cost_per_token = 0.000002;
			$total_cost     = ( $usage_data['total_tokens'] ?? 0 ) * $cost_per_token;
		}

		$stats['total_cost_estimate'] += $total_cost;

		update_option( 'alvobot_openai_usage_stats', $stats );
	}

	/**
	 * Incrementa contador de posts traduzidos (chamado apenas uma vez por post)
	 */
	public function increment_post_translation_count() {
		$stats = $this->get_usage_stats();
		++$stats['total_translations'];
		update_option( 'alvobot_openai_usage_stats', $stats );

		AlvoBotPro::debug_log( 'multi-languages', 'Contador de posts traduzidos incrementado. Total: ' . $stats['total_translations'] );
	}


	/**
	 * Verifica se o cache está habilitado nas configurações
	 */
	private function is_cache_enabled() {
		$openai_settings = get_option( 'alvobot_openai_settings', array() );
		return ! ( isset( $openai_settings['disable_cache'] ) && $openai_settings['disable_cache'] );
	}

	/**
	 * Verifica se o cache de modelos está habilitado
	 * Independente do cache geral, mantemos o cache de modelos por padrão
	 */
	private function is_models_cache_enabled() {
		$openai_settings = get_option( 'alvobot_openai_settings', array() );
		return ! ( isset( $openai_settings['disable_models_cache'] ) && $openai_settings['disable_models_cache'] );
	}

	/**
	 * Carrega modelos disponíveis da API OpenAI
	 */
	private function load_available_models() {
		// Verifica cache de modelos
		if ( $this->is_models_cache_enabled() ) {
			$cached_models = get_transient( 'alvobot_openai_models' );
			if ( $cached_models !== false ) {
				$this->available_models = $cached_models;
				return true;
			}
		}

		// Se não tiver API key, usa apenas modelos fallback
		if ( empty( $this->settings['api_key'] ) ) {
			$this->load_fallback_models();
			return;
		}

		AlvoBotPro::debug_log( 'multi-languages', 'Buscando modelos da API OpenAI...' );

		// Busca modelos da API OpenAI
		$headers = array(
			'Authorization' => 'Bearer ' . $this->settings['api_key'],
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'AlvoBot Multi Languages',
		);

		$response = wp_remote_get(
			$this->openai_models_url,
			array(
				'timeout' => 30,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Erro na requisição - ' . $response->get_error_message() );
			$this->load_fallback_models();
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Response code: ' . $response_code );
			$this->load_fallback_models();
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['data'] ) ) {
			AlvoBotPro::debug_log( 'multi-languages', 'JSON inválido ou estrutura inesperada' );
			$this->load_fallback_models();
			return;
		}

		$openai_models = array();
		foreach ( $data['data'] as $model ) {
			// Filtra apenas modelos GPT compatíveis com chat
			if ( isset( $model['id'] ) && $this->is_chat_model( $model['id'] ) ) {
				$openai_models[] = array(
					'id'          => $model['id'],
					'name'        => $this->get_model_display_name( $model['id'] ),
					'description' => $this->get_model_description( $model['id'] ),
					'max_tokens'  => $this->get_model_max_tokens( $model['id'] ),
					'cost_input'  => $this->get_model_cost_input( $model['id'] ),
					'cost_output' => $this->get_model_cost_output( $model['id'] ),
					'created'     => $model['created'] ?? 0,
					'owned_by'    => $model['owned_by'] ?? 'openai',
				);
			}
		}

		if ( ! empty( $openai_models ) ) {
			// Ordena por data de criação (mais recentes primeiro)
			usort(
				$openai_models,
				function ( $a, $b ) {
					return $b['created'] - $a['created'];
				}
			);

			$this->available_models = $openai_models;
			// Cache por 24 horas se o cache de modelos estiver habilitado
			if ( $this->is_models_cache_enabled() ) {
				set_transient( 'alvobot_openai_models', $openai_models, 24 * HOUR_IN_SECONDS );
			}
			AlvoBotPro::debug_log( 'multi-languages', count( $openai_models ) . ' modelos carregados com sucesso da API OpenAI' );
		} else {
			AlvoBotPro::debug_log( 'multi-languages', 'Nenhum modelo de chat compatível encontrado' );
			$this->load_fallback_models();
		}
	}

	/**
	 * Carrega modelos padrão como fallback
	 */
	public function load_fallback_models() {
		$this->available_models = array(
			array(
				'id'          => 'gpt-4o-mini',
				'name'        => 'GPT-4o Mini',
				'description' => 'Modelo otimizado para velocidade e custo',
				'max_tokens'  => 16385,
				'cost_input'  => 0.00015,
				'cost_output' => 0.0006,
			),
			array(
				'id'          => 'gpt-4o',
				'name'        => 'GPT-4o',
				'description' => 'Modelo mais avançado da OpenAI',
				'max_tokens'  => 4096,
				'cost_input'  => 0.005,
				'cost_output' => 0.015,
			),
			array(
				'id'          => 'gpt-4-turbo',
				'name'        => 'GPT-4 Turbo',
				'description' => 'Balanceado entre performance e custo',
				'max_tokens'  => 4096,
				'cost_input'  => 0.01,
				'cost_output' => 0.03,
			),
			array(
				'id'          => 'gpt-3.5-turbo',
				'name'        => 'GPT-3.5 Turbo',
				'description' => 'Modelo econômico e rápido',
				'max_tokens'  => 4096,
				'cost_input'  => 0.0005,
				'cost_output' => 0.0015,
			),
		);
	}

	/**
	 * Retorna modelos disponíveis
	 */
	public function get_available_models() {
		return $this->available_models;
	}

	/**
	 * Força atualização dos modelos
	 */
	public function refresh_models() {
		delete_transient( 'alvobot_openai_models' );
		$this->load_available_models();
		return $this->available_models;
	}

	/**
	 * Retorna informações de um modelo específico
	 */
	public function get_model_info( $model_id ) {
		foreach ( $this->available_models as $model ) {
			if ( $model['id'] === $model_id ) {
				return $model;
			}
		}
		return null;
	}

	/**
	 * Estima custo de uma tradução
	 */
	/**
	 * Valida configurações do provider
	 *
	 * @param array $settings Configurações a serem validadas
	 * @return array Array com 'valid' e 'errors'
	 */
	public function validate_settings( $settings ) {
		$errors = array();

		// Valida API key
		if ( empty( $settings['api_key'] ) ) {
			$errors[] = 'API Key é obrigatória';
		} elseif ( ! preg_match( '/^sk-[a-zA-Z0-9]+$/', $settings['api_key'] ) ) {
			$errors[] = 'Formato de API Key inválido';
		}

		// Valida modelo
		if ( empty( $settings['model'] ) ) {
			$errors[] = 'Modelo é obrigatório';
		}

		// Valida parâmetros numéricos
		if ( isset( $settings['max_tokens'] ) && ( ! is_numeric( $settings['max_tokens'] ) || $settings['max_tokens'] < 1 ) ) {
			$errors[] = 'Max tokens deve ser um número positivo';
		}

		if ( isset( $settings['temperature'] ) && ( ! is_numeric( $settings['temperature'] ) || $settings['temperature'] < 0 || $settings['temperature'] > 2 ) ) {
			$errors[] = 'Temperature deve estar entre 0 e 2';
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	public function estimate_cost( $text, $model_id = null ) {
		if ( ! $model_id ) {
			$model_id = $this->settings['model'];
		}

		$model_info = $this->get_model_info( $model_id );
		if ( ! $model_info ) {
			return 0;
		}

		// Estimativa básica: ~1 token por 4 caracteres
		$estimated_tokens = ceil( strlen( $text ) / 4 );
		$input_cost       = $estimated_tokens * ( $model_info['cost_input'] / 1000 );
		$output_cost      = $estimated_tokens * ( $model_info['cost_output'] / 1000 );

		return $input_cost + $output_cost;
	}

	/**
	 * Verifica se o modelo é compatível com chat/completions
	 */
	private function is_chat_model( $model_id ) {
		$chat_models = array(
			'gpt-4o',
			'gpt-4o-mini',
			'gpt-4o-2024-11-20',
			'gpt-4o-2024-08-06',
			'gpt-4o-2024-05-13',
			'gpt-4-turbo',
			'gpt-4-turbo-2024-04-09',
			'gpt-4-turbo-preview',
			'gpt-4',
			'gpt-4-0613',
			'gpt-4-0314',
			'gpt-3.5-turbo',
			'gpt-3.5-turbo-0125',
			'gpt-3.5-turbo-1106',
			'gpt-3.5-turbo-0613',
		);

		// Verifica se é exatamente um dos modelos conhecidos ou começa com gpt- e contém turbo/4o/4
		return in_array( $model_id, $chat_models ) ||
				( strpos( $model_id, 'gpt-' ) === 0 &&
				( strpos( $model_id, 'turbo' ) !== false ||
				strpos( $model_id, '4o' ) !== false ||
				strpos( $model_id, 'gpt-4' ) === 0 ) );
	}

	/**
	 * Retorna nome de exibição do modelo
	 */
	private function get_model_display_name( $model_id ) {
		$display_names = array(
			'gpt-4o'        => 'GPT-4o',
			'gpt-4o-mini'   => 'GPT-4o Mini',
			'gpt-4-turbo'   => 'GPT-4 Turbo',
			'gpt-4'         => 'GPT-4',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
		);

		return $display_names[ $model_id ] ?? ucfirst( str_replace( '-', ' ', $model_id ) );
	}

	/**
	 * Retorna descrição do modelo baseado no nome
	 */
	private function get_model_description( $model_name ) {
		$descriptions = array(
			'gpt-4o'        => 'Modelo mais avançado da OpenAI com capacidades multimodais',
			'gpt-4o-mini'   => 'Versão otimizada e econômica do GPT-4o',
			'gpt-4-turbo'   => 'Modelo balanceado entre performance e custo',
			'gpt-4'         => 'Modelo de alta qualidade para tarefas complexas',
			'gpt-3.5-turbo' => 'Modelo rápido e econômico para uso geral',
		);

		// Detecta versões com data
		if ( strpos( $model_name, 'gpt-4o' ) === 0 ) {
			return 'Modelo avançado GPT-4o com capacidades multimodais';
		} elseif ( strpos( $model_name, 'gpt-4-turbo' ) === 0 ) {
			return 'Modelo GPT-4 Turbo otimizado';
		} elseif ( strpos( $model_name, 'gpt-4' ) === 0 ) {
			return 'Modelo GPT-4 de alta qualidade';
		} elseif ( strpos( $model_name, 'gpt-3.5' ) === 0 ) {
			return 'Modelo GPT-3.5 rápido e econômico';
		}

		return $descriptions[ $model_name ] ?? 'Modelo OpenAI GPT';
	}

	/**
	 * Retorna max_tokens baseado no modelo
	 */
	private function get_model_max_tokens( $model_name ) {
		$max_tokens = array(
			'gpt-4o'        => 128000,
			'gpt-4o-mini'   => 16385,
			'gpt-4-turbo'   => 128000,
			'gpt-4'         => 8192,
			'gpt-3.5-turbo' => 4096,
		);

		return $max_tokens[ $model_name ] ?? 4096;
	}

	/**
	 * Retorna custo de input por 1K tokens
	 */
	private function get_model_cost_input( $model_id ) {
		$costs = array(
			'gpt-4o'        => 0.005,
			'gpt-4o-mini'   => 0.00015,
			'gpt-4-turbo'   => 0.01,
			'gpt-4'         => 0.03,
			'gpt-3.5-turbo' => 0.0005,
		);

		// Detecta por prefixo
		if ( strpos( $model_id, 'gpt-4o-mini' ) === 0 ) {
			return 0.00015;
		} elseif ( strpos( $model_id, 'gpt-4o' ) === 0 ) {
			return 0.005;
		} elseif ( strpos( $model_id, 'gpt-4-turbo' ) === 0 ) {
			return 0.01;
		} elseif ( strpos( $model_id, 'gpt-4' ) === 0 ) {
			return 0.03;
		} elseif ( strpos( $model_id, 'gpt-3.5' ) === 0 ) {
			return 0.0005;
		}

		return $costs[ $model_id ] ?? 0.002; // Default fallback
	}

	/**
	 * Retorna custo de output por 1K tokens
	 */
	private function get_model_cost_output( $model_id ) {
		$costs = array(
			'gpt-4o'        => 0.015,
			'gpt-4o-mini'   => 0.0006,
			'gpt-4-turbo'   => 0.03,
			'gpt-4'         => 0.06,
			'gpt-3.5-turbo' => 0.0015,
		);

		// Detecta por prefixo
		if ( strpos( $model_id, 'gpt-4o-mini' ) === 0 ) {
			return 0.0006;
		} elseif ( strpos( $model_id, 'gpt-4o' ) === 0 ) {
			return 0.015;
		} elseif ( strpos( $model_id, 'gpt-4-turbo' ) === 0 ) {
			return 0.03;
		} elseif ( strpos( $model_id, 'gpt-4' ) === 0 ) {
			return 0.06;
		} elseif ( strpos( $model_id, 'gpt-3.5' ) === 0 ) {
			return 0.0015;
		}

		return $costs[ $model_id ] ?? 0.002; // Default fallback
	}

	/**
	 * Limpa HTML traduzido removendo elementos indesejados e corrigindo problemas comuns
	 *
	 * @param string $translated_text Texto traduzido pela AI
	 * @param string $original_text Texto original para comparação
	 * @return string Texto limpo
	 */
	private function clean_translated_html( $translated_text, $original_text ) {
		// 1. Remove spans e divs desnecessários que a AI pode ter adicionado
		$cleaned_text = $this->remove_unwanted_html_elements( $translated_text, $original_text );

		// 2. Corrige entidades HTML
		$cleaned_text = $this->fix_html_entities( $cleaned_text );

		// 3. Remove atributos ID/classes não presentes no original
		$cleaned_text = $this->preserve_original_attributes( $cleaned_text, $original_text );

		// 4. Normaliza espaçamento
		$cleaned_text = $this->normalize_spacing( $cleaned_text );

		return $cleaned_text;
	}

	/**
	 * Remove spans e divs que não existiam no texto original
	 */
	private function remove_unwanted_html_elements( $translated_text, $original_text ) {
		$cleaned_text = $translated_text;

		// Lista de elementos para verificar (mais elementos problemáticos)
		$elements_to_check = array( 'span', 'div', 'section', 'article', 'aside' );

		foreach ( $elements_to_check as $element ) {
			// Conta quantos elementos existem no original vs traduzido
			$original_count   = preg_match_all( "/<{$element}[^>]*>/i", $original_text );
			$translated_count = preg_match_all( "/<{$element}[^>]*>/i", $cleaned_text );

			$this->log_html_comparison( $element, $original_count, $translated_count );

			// MAIS AGRESSIVO: Remove qualquer elemento extra, independente da contagem
			if ( $translated_count > $original_count || $element === 'span' ) {
				// Remove spans e divs que envolvem apenas texto
				$cleaned_text = preg_replace( "/<{$element}(?:\s+[^>]*)?>([^<]+)<\/{$element}>/i", '$1', $cleaned_text );

				// Remove elementos vazios
				$cleaned_text = preg_replace( "/<{$element}[^>]*><\/{$element}>/i", '', $cleaned_text );

				// Remove elementos que apenas envolvem outros elementos válidos
				$cleaned_text = preg_replace( "/<{$element}[^>]*>(<[^>]+>[^<]*<\/[^>]+>)<\/{$element}>/i", '$1', $cleaned_text );

				// Remove tags de fechamento órfãs
				$cleaned_text = $this->remove_orphaned_closing_tags( $cleaned_text, $element );
			}
		}

		// LIMPEZA EXTRA: Remove spans simples que a AI pode ter adicionado
		$cleaned_text = preg_replace( '/<span[^>]*>([^<]+)<\/span>/i', '$1', $cleaned_text );

		return $cleaned_text;
	}

	/**
	 * Log para debug de comparação HTML
	 */
	private function log_html_comparison( $element, $original_count, $translated_count ) {
		if ( $translated_count > $original_count ) {
			AlvoBotPro::debug_log( 'multi-languages', "HTML Cleanup: {$element} - Original: {$original_count}, Translated: {$translated_count} (removing extras)" );
		}
	}

	/**
	 * Remove tags de fechamento órfãs que não têm abertura correspondente
	 */
	private function remove_orphaned_closing_tags( $text, $element ) {
		// Conta tags de abertura e fechamento
		$opening_count = preg_match_all( "/<{$element}[^>]*>/i", $text );
		$closing_count = preg_match_all( "/<\/{$element}>/i", $text );

		// Se há mais fechamentos que aberturas, remove os extras
		if ( $closing_count > $opening_count ) {
			$excess_closings = $closing_count - $opening_count;

			// Remove as últimas tags de fechamento em excesso
			for ( $i = 0; $i < $excess_closings; $i++ ) {
				$text = preg_replace( "/<\/{$element}>/i", '', $text, 1 );
			}
		}

		// Remove também tags </div> soltas que aparecem no meio do texto
		if ( $element === 'div' ) {
			// Remove </div> que aparece isolado ou após espaços/quebras
			$text = preg_replace( '/\s*<\/div>\s*(?!\s*<)/i', ' ', $text );
			// Remove </div> no final de linha
			$text = preg_replace( '/\s*<\/div>\s*$/i', '', $text );
		}

		return $text;
	}

	/**
	 * Corrige entidades HTML comuns
	 */
	private function fix_html_entities( $text ) {
		$replacements = array(
			'&nbsp;'     => ' ',
			'&#160;'     => ' ',
			'&amp;nbsp;' => ' ',
			'&rsquo;'    => "'",
			'&lsquo;'    => "'",
			'&rdquo;'    => '"',
			'&ldquo;'    => '"',
			'&mdash;'    => '—',
			'&ndash;'    => '–',
			'&hellip;'   => '…',
		);

		$cleaned_text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );

		// Remove múltiplos espaços criados pela conversão de &nbsp;
		$cleaned_text = preg_replace( '/\s+/', ' ', $cleaned_text );

		return $cleaned_text;
	}

	/**
	 * Preserva atributos originais e remove os adicionados pela AI
	 */
	private function preserve_original_attributes( $translated_text, $original_text ) {
		// Extrai todas as tags com atributos do texto original
		preg_match_all( '/<(\w+)([^>]*)>/i', $original_text, $original_tags, PREG_SET_ORDER );

		$original_attributes = array();
		foreach ( $original_tags as $tag_match ) {
			$tag_name   = strtolower( $tag_match[1] );
			$attributes = trim( $tag_match[2] );

			if ( ! empty( $attributes ) ) {
				$original_attributes[ $tag_name ][] = $attributes;
			}
		}

		// Remove IDs e classes que não existiam no original
		$cleaned_text = preg_replace_callback(
			'/<(\w+)([^>]*)>/i',
			function ( $matches ) use ( $original_attributes ) {
				// Removed HTML cleaning to prevent corruption of shortcodes and JSON structures
				// The translation prompt has been updated to handle HTML preservation
				return $matches[0];
			},
			$translated_text
		);

		return $cleaned_text;
	}

	/**
	 * Normaliza espaçamento e quebras de linha - VERSÃO MAIS AGRESSIVA
	 */
	private function normalize_spacing( $text ) {
		// LIMPEZA FINAL: Remove todas as tags estruturais soltas que a AI pode ter adicionado
		$unwanted_closing_tags = array( '</div>', '</span>', '</section>', '</article>', '</aside>' );
		foreach ( $unwanted_closing_tags as $tag ) {
			$text = preg_replace( '/\s*' . preg_quote( $tag, '/' ) . '\s*/i', ' ', $text );
		}

		// Remove tags de abertura órfãs também (spans e divs sem fechamento)
		$text = preg_replace( '/<(span|div|section|article|aside)[^>]*>(?![^<]*<\/\1>)/i', '', $text );

		// Remove espaços extras dentro de tags
		$text = preg_replace( '/(<[^>]+)\s+([^>]*>)/', '$1 $2', $text );

		// Normaliza espaços entre palavras (mas preserva quebras de linha)
		// Usa negative lookbehind/lookahead para não afetar quebras de linha
		$text = preg_replace( '/[^\S\n]{2,}/', ' ', $text );

		// Remove espaços no início e fim de elementos block
		$block_elements = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote' );
		foreach ( $block_elements as $element ) {
			$text = preg_replace( "/(<{$element}[^>]*>)\s+/", '$1', $text );
			$text = preg_replace( "/\s+(<\/{$element}>)/", '$1', $text );
		}

		// PRESERVE LINE BREAKS - Only remove excessive line breaks (more than 2 consecutive)
		// This preserves single and double line breaks as they are important for formatting
		$text = preg_replace( '/\n{4,}/', "\n\n\n", $text );

		// Limpeza final de espaços em volta de elementos HTML (preserva quebras de linha)
		// Remove apenas espaços, não quebras de linha entre tags
		$text = preg_replace( '/>[^\S\n]+</', '><', $text );

		AlvoBotPro::debug_log( 'multi-languages', 'HTML Normalize: Completed aggressive cleanup' );

		return trim( $text );
	}

	/**
	 * Valida idioma de um texto usando OpenAI (método especializado)
	 *
	 * @param string $text Texto para validar
	 * @return array Resultado da validação
	 */
	public function validate_language_with_ai( $text ) {
		try {
			if ( ! $this->is_configured() ) {
				return array(
					'success' => false,
					'error'   => 'OpenAI não configurado',
				);
			}

			// Prompt especializado para detecção de idioma
			$prompt = 'Your only task is to identify the primary language of the text provided below.

**Output format rules:**
- Return ONLY the two-letter ISO 639-1 code for the language.
- Do not include any other words, explanations, punctuation, or formatting.
- If the text is primarily in Spanish, return "es".
- If the text is primarily in English, return "en".
- If the text is primarily in Portuguese, return "pt".
- If the text is primarily in French, return "fr".
- If the text is primarily in Italian, return "it".
- If the text is primarily in German, return "de".
- If the text is a mix of languages or you cannot determine a single primary language, return "mix".

Analyze the following text and provide your response:

---
' . substr( trim( $text ), 0, 2000 ) . '
---';

			// Dados da requisição otimizada para validação
			$request_data = array(
				'model'       => 'gpt-4o-mini',
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are a language detection expert. Respond only with the ISO 639-1 language code.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'max_tokens'  => 10, // Muito baixo já que esperamos apenas 2 caracteres
				'temperature' => 0, // Determinístico
			);

			// Headers da requisição
			$headers = array(
				'Authorization' => 'Bearer ' . $this->settings['api_key'],
				'Content-Type'  => 'application/json',
			);

			// Faz a requisição
			$response = $this->make_request( $request_data, $headers );

			if ( ! $response['success'] ) {
				return array(
					'success' => false,
					'error'   => $response['error'],
				);
			}

			$result = json_decode( $response['data'], true );

			if ( ! $result || ! isset( $result['choices'][0]['message']['content'] ) ) {
				return array(
					'success' => false,
					'error'   => 'Resposta inválida da OpenAI',
				);
			}

			$detected_lang = trim( strtolower( $result['choices'][0]['message']['content'] ) );

			return array(
				'success'           => true,
				'detected_language' => $detected_lang,
				'usage'             => array(
					'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? 0,
					'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
					'total_tokens'      => $result['usage']['total_tokens'] ?? 0,
				),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => 'Erro na validação AI: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Retorna resposta de erro padronizada
	 */
	private function error_response( $message ) {
		return array(
			'success'   => false,
			'error'     => $message,
			'provider'  => 'openai',
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Limpa tags órfãs e entidades HTML problemáticas
	 */
	private function clean_orphaned_tags( $text ) {
		// 1. Converte entidades HTML comuns para espaços normais
		$text = str_replace( '&nbsp;', ' ', $text );
		$text = str_replace( '&amp;', '&', $text );
		$text = str_replace( '&lt;', '<', $text );
		$text = str_replace( '&gt;', '>', $text );
		$text = str_replace( '&quot;', '"', $text );
		$text = str_replace( '&#39;', "'", $text );

		// 2. Lista de tags que frequentemente aparecem órfãs
		$orphaned_closing_tags = [
			'</span>',
			'</div>',
			'</section>',
			'</article>',
			'</aside>',
			'</header>',
			'</footer>',
			'</main>',
			'</nav>',
		];

		// 3. Remove tags de fechamento órfãs (sem abertura correspondente)
		foreach ( $orphaned_closing_tags as $closing_tag ) {
			$opening_tag = str_replace( '</', '<', $closing_tag );
			$opening_tag = str_replace( '>', '', $opening_tag ) . '>';

			// Conta aberturas e fechamentos
			$opening_count = substr_count( $text, $opening_tag );
			$closing_count = substr_count( $text, $closing_tag );

			// Se há mais fechamentos que aberturas, remove os extras
			if ( $closing_count > $opening_count ) {
				$excess_closings = $closing_count - $opening_count;

				// Remove as tags de fechamento em excesso (da direita para esquerda)
				for ( $i = 0; $i < $excess_closings; $i++ ) {
					$last_pos = strrpos( $text, $closing_tag );
					if ( $last_pos !== false ) {
						$text = substr_replace( $text, '', $last_pos, strlen( $closing_tag ) );
					}
				}
			}
		}

		// 4. Remove espaços múltiplos criados pela limpeza
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}
}
