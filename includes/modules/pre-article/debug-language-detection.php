<?php
/**
 * Debug para detecção de idioma do Pre Article
 * Acesse: https://plugin-developers/wp-content/plugins/alvobot-plugin-manager/includes/modules/pre-article/debug-language-detection.php
 */

// Carrega WordPress
require_once '../../../../../wp-load.php';

// Carrega a classe de traduções
require_once 'includes/class-cta-translations.php';

echo '<h1>Debug - Detecção de Idioma Pre Article</h1>';
echo '<h2>Informações do Sistema</h2>';

// Informações básicas
echo '<ul>';
echo '<li><strong>WordPress Locale:</strong> ' . get_locale() . '</li>';
echo '<li><strong>WordPress Language:</strong> ' . get_bloginfo( 'language' ) . '</li>';
echo '<li><strong>Site URL:</strong> ' . home_url() . '</li>';
echo '<li><strong>Admin URL:</strong> ' . admin_url() . '</li>';
echo '</ul>';

// Verifica plugins de multilíngue
echo '<h2>Plugins Multilíngue</h2>';
echo '<ul>';
echo '<li><strong>Polylang ativo:</strong> ' . ( function_exists( 'pll_current_language' ) ? 'SIM' : 'NÃO' ) . '</li>';
if ( function_exists( 'pll_current_language' ) ) {
	echo '<li><strong>Polylang current language:</strong> ' . pll_current_language() . '</li>';
	echo '<li><strong>Polylang default language:</strong> ' . pll_default_language() . '</li>';
}
echo '<li><strong>AutoPoly detectado:</strong> ' . ( class_exists( 'Automatic_Polylang' ) ? 'SIM' : 'NÃO' ) . '</li>';
echo '<li><strong>WPML ativo:</strong> ' . ( defined( 'ICL_LANGUAGE_CODE' ) ? 'SIM - ' . ICL_LANGUAGE_CODE : 'NÃO' ) . '</li>';
echo '</ul>';

// Testa detecção da classe CTA
echo '<h2>Detecção pela Classe CTA_Translations</h2>';
if ( class_exists( 'Alvobot_PreArticle_CTA_Translations' ) ) {
	$debug_info = Alvobot_PreArticle_CTA_Translations::get_language_debug_info();

	echo '<h3>Informações Gerais</h3>';
	echo '<ul>';
	$general_info = [ 'detected_language', 'language_name', 'is_supported', 'site_locale', 'request_uri', 'http_host', 'accept_language' ];
	foreach ( $general_info as $key ) {
		if ( isset( $debug_info[ $key ] ) ) {
			echo '<li><strong>' . ucfirst( str_replace( '_', ' ', $key ) ) . ':</strong> ';
			echo esc_html( $debug_info[ $key ] );
			echo '</li>';
		}
	}
	echo '</ul>';

	echo '<h3>Métodos de Detecção (Por Prioridade)</h3>';
	if ( isset( $debug_info['detection_methods'] ) ) {
		echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
		echo '<tr><th>Método</th><th>Resultado</th><th>Status</th></tr>';

		$method_names = [
			'force_param'      => 'Parâmetro forçado (?force_lang=)',
			'url_detection'    => 'Detecção por URL (/es/)',
			'polylang'         => 'Polylang Plugin',
			'wpml'             => 'WPML Plugin',
			'wordpress_locale' => 'Locale do WordPress',
			'domain'           => 'Extensão do domínio (.es)',
			'browser'          => 'Accept-Language do navegador',
		];

		foreach ( $method_names as $method => $name ) {
			$result      = $debug_info['detection_methods'][ $method ] ?? null;
			$status      = $result ? '<span style="color: green; font-weight: bold;">✓ Detectado</span>' : '<span style="color: red;">✗ Não detectado</span>';
			$result_text = $result ? strtoupper( $result ) : 'N/A';

			echo '<tr>';
			echo '<td>' . $name . '</td>';
			echo '<td>' . $result_text . '</td>';
			echo '<td>' . $status . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	echo '<h3>Status dos Plugins</h3>';
	echo '<ul>';
	$plugin_info = [ 'polylang_active', 'autopoly_detected', 'wpml_active', 'pll_function_exists', 'PLL_function_exists' ];
	foreach ( $plugin_info as $key ) {
		if ( isset( $debug_info[ $key ] ) ) {
			echo '<li><strong>' . ucfirst( str_replace( '_', ' ', $key ) ) . ':</strong> ';
			echo $debug_info[ $key ] ? 'SIM' : 'NÃO';
			echo '</li>';
		}
	}
	echo '</ul>';

	echo '<h3>Idiomas Suportados</h3>';
	echo '<p>' . implode( ', ', $debug_info['supported_languages'] ) . '</p>';

	// Testa CTAs para diferentes idiomas
	echo '<h2>Teste de CTAs por Idioma</h2>';
	$languages = [ 'pt', 'es', 'en', 'fr', 'it' ];

	foreach ( $languages as $lang ) {
		echo '<h3>Idioma: ' . strtoupper( $lang ) . ' (' . Alvobot_PreArticle_CTA_Translations::get_language_native_name( $lang ) . ')</h3>';
		$ctas = Alvobot_PreArticle_CTA_Translations::get_translated_ctas( $lang );
		echo '<ol>';
		foreach ( array_slice( $ctas, 0, 3 ) as $cta ) {
			echo '<li>' . esc_html( $cta ) . '</li>';
		}
		echo '</ol>';
	}

	// Teste de tradução automática
	echo '<h2>Teste de Tradução Automática</h2>';
	$pt_texts = [
		'Desejo Saber Mais Sobre o Assunto',
		'Desbloquear o Conteúdo Agora',
		'Quero Ler o Artigo Completo!',
	];

	foreach ( [ 'es', 'en', 'fr' ] as $target_lang ) {
		echo '<h3>Para ' . strtoupper( $target_lang ) . '</h3>';
		echo '<ul>';
		foreach ( $pt_texts as $pt_text ) {
			$translated = Alvobot_PreArticle_CTA_Translations::translate_default_cta( $pt_text, $target_lang );
			echo '<li>"' . $pt_text . '" → "' . $translated . '"</li>';
		}
		echo '</ul>';
	}
} else {
	echo '<p style="color: red;">ERRO: Classe Alvobot_PreArticle_CTA_Translations não foi carregada!</p>';
}

// Mostra valor atual detectado vs esperado
echo '<h2>Resultado Final da Detecção</h2>';
if ( class_exists( 'Alvobot_PreArticle_CTA_Translations' ) ) {
	$detected_lang = Alvobot_PreArticle_CTA_Translations::get_current_language();
	echo '<p><strong>Idioma detectado atual:</strong> <span style="color: blue; font-size: 18px;">' . strtoupper( $detected_lang ) . '</span></p>';
	echo '<p><strong>CTAs que serão usadas:</strong></p>';
	$current_ctas = Alvobot_PreArticle_CTA_Translations::get_translated_ctas();
	echo '<ol>';
	foreach ( array_slice( $current_ctas, 0, 3 ) as $cta ) {
		echo '<li style="font-weight: bold; color: green;">' . esc_html( $cta ) . '</li>';
	}
	echo '</ol>';
}
