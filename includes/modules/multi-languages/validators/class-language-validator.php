<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_Language_Validator' ) ) {
	return;
}

/**
 * Validador de idiomas para conteúdo traduzido
 *
 * Implementa múltiplas estratégias de validação de idioma
 */
class AlvoBotPro_Language_Validator {

	/** @var array Indicadores linguísticos por idioma */
	private $language_indicators = array(
		'en' => array(
			'articles'        => array( 'the', 'a', 'an' ),
			'prepositions'    => array( 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from' ),
			'conjunctions'    => array( 'and', 'or', 'but', 'so', 'yet' ),
			'pronouns'        => array( 'this', 'that', 'these', 'those', 'he', 'she', 'it', 'they' ),
			'auxiliary_verbs' => array( 'is', 'are', 'was', 'were', 'have', 'has', 'had', 'will', 'would' ),
			'weight'          => 1.0,
		),
		'es' => array(
			'articles'        => array( 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas' ),
			'prepositions'    => array( 'en', 'de', 'del', 'con', 'por', 'para', 'desde', 'hasta' ),
			'conjunctions'    => array( 'y', 'o', 'pero', 'sino', 'aunque' ),
			'pronouns'        => array( 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas' ),
			'auxiliary_verbs' => array( 'es', 'son', 'era', 'fueron', 'tiene', 'tienen', 'ser', 'estar' ),
			'weight'          => 1.0,
		),
		'fr' => array(
			'articles'        => array( 'le', 'la', 'les', 'un', 'une', 'des', 'du' ),
			'prepositions'    => array( 'de', 'dans', 'avec', 'pour', 'par', 'sur', 'sous', 'entre' ),
			'conjunctions'    => array( 'et', 'ou', 'mais', 'donc', 'car', 'ni' ),
			'pronouns'        => array( 'ce', 'cette', 'ces', 'celui', 'celle', 'ceux', 'celles' ),
			'auxiliary_verbs' => array( 'est', 'sont', 'était', 'étaient', 'a', 'ont', 'être', 'avoir' ),
			'weight'          => 1.0,
		),
		'it' => array(
			'articles'        => array( 'il', 'la', 'gli', 'le', 'un', 'una', 'uno', 'dei', 'delle' ),
			'prepositions'    => array( 'di', 'in', 'con', 'per', 'da', 'su', 'tra', 'fra' ),
			'conjunctions'    => array( 'e', 'o', 'ma', 'però', 'perché', 'se' ),
			'pronouns'        => array( 'questo', 'questa', 'questi', 'queste', 'quello', 'quella' ),
			'auxiliary_verbs' => array( 'è', 'sono', 'era', 'erano', 'ha', 'hanno', 'essere', 'avere' ),
			'weight'          => 1.0,
		),
		'de' => array(
			'articles'        => array( 'der', 'die', 'das', 'ein', 'eine', 'den', 'dem', 'des' ),
			'prepositions'    => array( 'in', 'mit', 'für', 'von', 'zu', 'auf', 'an', 'über' ),
			'conjunctions'    => array( 'und', 'oder', 'aber', 'denn', 'sondern' ),
			'pronouns'        => array( 'dieser', 'diese', 'dieses', 'jener', 'jene', 'jenes' ),
			'auxiliary_verbs' => array( 'ist', 'sind', 'war', 'waren', 'hat', 'haben', 'sein', 'haben' ),
			'weight'          => 1.0,
		),
		'pt' => array(
			'articles'        => array( 'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas' ),
			'prepositions'    => array( 'de', 'em', 'com', 'por', 'para', 'sobre', 'entre', 'sem' ),
			'conjunctions'    => array( 'e', 'ou', 'mas', 'porém', 'contudo', 'se' ),
			'pronouns'        => array( 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas' ),
			'auxiliary_verbs' => array( 'é', 'são', 'era', 'eram', 'tem', 'têm', 'ser', 'estar', 'ter' ),
			'weight'          => 1.0,
		),
	);

	/** @var array Configurações de validação */
	private $validation_config = array(
		'min_text_length'         => 50,
		'min_indicators_percent'  => 0.05, // 5%
		'min_indicators_absolute' => 3,
		'confidence_threshold'    => 0.5, // Balanceado para boa precisão
	);

	/**
	 * Valida se um texto está no idioma esperado
	 */
	public function validate_language( $text, $expected_lang, $options = array() ) {
		// Configurações
		$config = array_merge( $this->validation_config, $options );

		// Limpa e normaliza o texto
		$clean_text = $this->normalize_text( $text );

		if ( strlen( $clean_text ) < $config['min_text_length'] ) {
			return false; // Texto muito curto para validar
		}

		// Calcula score de confiança
		$confidence = $this->get_confidence_score( $clean_text, $expected_lang );

		return $confidence >= $config['confidence_threshold'];
	}

	/**
	 * Detecta o idioma de um texto
	 */
	public function detect_language( $text, $options = array() ) {
		$clean_text = $this->normalize_text( $text );
		$scores     = array();

		// Calcula score para cada idioma suportado
		foreach ( $this->get_supported_languages() as $lang_code ) {
			$scores[ $lang_code ] = $this->get_confidence_score( $clean_text, $lang_code );
		}

		// Ordena por score
		arsort( $scores );

		$detected_lang = array_key_first( $scores );
		$confidence    = $scores[ $detected_lang ];

		// Prepara alternativas
		$alternatives = array_slice( $scores, 1, 3, true );

		return array(
			'language'     => $detected_lang,
			'confidence'   => $confidence,
			'alternatives' => $alternatives,
		);
	}

	/**
	 * Retorna a confiança da validação (0-1)
	 */
	public function get_confidence_score( $text, $expected_lang ) {
		if ( ! $this->supports_language( $expected_lang ) ) {
			return 0.0;
		}

		$clean_text  = $this->normalize_text( $text );
		$words       = $this->tokenize_text( $clean_text );
		$total_words = count( $words );

		if ( $total_words < 5 ) {
			return 0.0;
		}

		$indicators       = $this->language_indicators[ $expected_lang ];
		$found_indicators = 0;
		$category_scores  = array();

		// Analisa cada categoria de indicadores
		foreach ( $indicators as $category => $indicator_list ) {
			if ( $category === 'weight' ) {
				continue;
			}

			$category_found = 0;
			foreach ( $words as $word ) {
				if ( in_array( $word, $indicator_list ) ) {
					++$category_found;
				}
			}

			$category_scores[ $category ] = $category_found / $total_words;
			$found_indicators            += $category_found;
		}

		// Calcula score base
		$base_score = $found_indicators / $total_words;

		// Aplica pesos por categoria
		$weighted_score = 0;
		$total_weight   = 0;

		foreach ( $category_scores as $category => $score ) {
			$weight          = $this->get_category_weight( $category );
			$weighted_score += $score * $weight;
			$total_weight   += $weight;
		}

		if ( $total_weight > 0 ) {
			$weighted_score /= $total_weight;
		}

		// Combina scores
		$final_score = ( $base_score * 0.4 ) + ( $weighted_score * 0.6 );

		// Aplica penalidade para textos muito curtos
		if ( $total_words < 20 ) {
			$final_score *= ( $total_words / 20 );
		}

		return min( 1.0, $final_score * 2 ); // Amplifica o score final
	}

	/**
	 * Valida qualidade da tradução
	 */
	public function validate_translation_quality( $original_text, $translated_text, $source_lang, $target_lang ) {
		$issues = array();
		$score  = 1.0;

		// Verifica idioma do texto traduzido
		if ( ! $this->validate_language( $translated_text, $target_lang ) ) {
			$issues[] = 'Texto traduzido não está no idioma esperado';
			$score   -= 0.5;
		}

		// Verifica proporção de tamanho
		$orig_length  = strlen( wp_strip_all_tags( $original_text ) );
		$trans_length = strlen( wp_strip_all_tags( $translated_text ) );

		if ( $trans_length < $orig_length * 0.3 ) {
			$issues[] = 'Texto traduzido muito curto em relação ao original';
			$score   -= 0.3;
		} elseif ( $trans_length > $orig_length * 3 ) {
			$issues[] = 'Texto traduzido muito longo em relação ao original';
			$score   -= 0.2;
		}

		// Verifica presença de texto não traduzido
		$untranslated_ratio = $this->detect_untranslated_content( $original_text, $translated_text, $source_lang );
		if ( $untranslated_ratio > 0.2 ) {
			$issues[] = 'Muitas partes do texto não foram traduzidas';
			$score   -= $untranslated_ratio * 0.5;
		}

		return array(
			'score'       => max( 0, $score ),
			'issues'      => $issues,
			'suggestions' => $this->generate_quality_suggestions( $issues ),
		);
	}

	/**
	 * Verifica se o validador suporta o idioma
	 */
	public function supports_language( $language_code ) {
		return isset( $this->language_indicators[ $language_code ] );
	}

	/**
	 * Retorna lista de idiomas suportados
	 */
	public function get_supported_languages() {
		return array_keys( $this->language_indicators );
	}

	/**
	 * Configura regras específicas para validação
	 */
	public function configure_language_rules( $language_code, $rules ) {
		if ( ! is_array( $rules ) ) {
			return false;
		}

		if ( ! isset( $this->language_indicators[ $language_code ] ) ) {
			$this->language_indicators[ $language_code ] = array();
		}

		$this->language_indicators[ $language_code ] = array_merge(
			$this->language_indicators[ $language_code ],
			$rules
		);

		return true;
	}

	/**
	 * Normaliza texto para análise
	 */
	private function normalize_text( $text ) {
		// Remove HTML
		$clean_text = wp_strip_all_tags( $text );

		// Normaliza espaços
		$clean_text = preg_replace( '/\s+/', ' ', $clean_text );

		// Remove caracteres especiais mantendo palavras
		$clean_text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $clean_text );

		return trim( strtolower( $clean_text ) );
	}

	/**
	 * Tokeniza texto em palavras
	 */
	private function tokenize_text( $text ) {
		$words = explode( ' ', $text );
		return array_filter(
			array_map( 'trim', $words ),
			function ( $word ) {
				return strlen( $word ) > 1; // Remove palavras de 1 letra
			}
		);
	}

	/**
	 * Retorna peso de categoria de indicadores
	 */
	private function get_category_weight( $category ) {
		$weights = array(
			'articles'        => 1.5,
			'prepositions'    => 1.2,
			'auxiliary_verbs' => 1.3,
			'pronouns'        => 1.0,
			'conjunctions'    => 1.1,
		);

		return isset( $weights[ $category ] ) ? $weights[ $category ] : 1.0;
	}

	/**
	 * Detecta conteúdo não traduzido
	 */
	private function detect_untranslated_content( $original, $translated, $source_lang ) {
		// Implementação básica - pode ser expandida
		$orig_words  = $this->tokenize_text( $this->normalize_text( $original ) );
		$trans_words = $this->tokenize_text( $this->normalize_text( $translated ) );

		$untranslated_count = 0;

		foreach ( $orig_words as $word ) {
			if ( strlen( $word ) > 4 && in_array( $word, $trans_words ) ) {
				++$untranslated_count;
			}
		}

		return count( $orig_words ) > 0 ? $untranslated_count / count( $orig_words ) : 0;
	}

	/**
	 * Gera sugestões de melhoria
	 */
	private function generate_quality_suggestions( $issues ) {
		$suggestions = array();

		foreach ( $issues as $issue ) {
			if ( strpos( $issue, 'idioma esperado' ) !== false ) {
				$suggestions[] = 'Verifique as configurações do provider de tradução';
			} elseif ( strpos( $issue, 'muito curto' ) !== false ) {
				$suggestions[] = 'O texto pode ter sido truncado - verifique limites de caracteres';
			} elseif ( strpos( $issue, 'muito longo' ) !== false ) {
				$suggestions[] = 'O texto pode conter explicações extras - revise o prompt de tradução';
			} elseif ( strpos( $issue, 'não foram traduzidas' ) !== false ) {
				$suggestions[] = 'Ajuste o prompt para garantir tradução completa do conteúdo';
			}
		}

		return array_unique( $suggestions );
	}
}
