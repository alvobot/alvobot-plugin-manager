<?php
/**
 * Script de debug para testar traduções de CTAs
 * Execute no admin: /wp-admin/admin.php?page=alvobot-pro-pre-article&debug_translations=1
 */

// Só executa se for chamado com parâmetro debug
if ( ! isset( $_GET['debug_translations'] ) || ! current_user_can( 'manage_options' ) ) {
	return;
}

// Carrega a classe de traduções
require_once __DIR__ . '/includes/class-cta-translations.php';

echo '<div style="background: #fff; padding: 20px; margin: 20px; border: 1px solid #ddd; border-radius: 5px;">';
echo '<h2>🔍 Debug: Traduções de CTAs do Pre-Article</h2>';

// Informações de idioma detectado
echo '<h3>📍 Detecção de Idioma</h3>';
$debug_info = Alvobot_PreArticle_CTA_Translations::get_language_debug_info();
echo '<pre>' . print_r( $debug_info, true ) . '</pre>';

// Lista de idiomas suportados
echo '<h3>🌐 Idiomas Suportados (' . count( Alvobot_PreArticle_CTA_Translations::get_supported_languages() ) . ')</h3>';
$supported_langs = Alvobot_PreArticle_CTA_Translations::get_supported_languages();
foreach ( $supported_langs as $lang_code ) {
	$native_name = Alvobot_PreArticle_CTA_Translations::get_language_native_name( $lang_code );
	echo "<strong>{$lang_code}</strong>: {$native_name}<br>";
}

// CTAs para o idioma atual
echo '<h3>🎯 CTAs para o Idioma Atual (' . $debug_info['detected_language'] . ')</h3>';
$current_ctas = Alvobot_PreArticle_CTA_Translations::get_translated_ctas();
foreach ( $current_ctas as $index => $cta_text ) {
	echo '<strong>CTA ' . ( $index + 1 ) . ":</strong> {$cta_text}<br>";
}

// Teste de tradução automática
echo '<h3>🔄 Teste de Tradução Automática</h3>';
$test_texts = [
	'Desejo Saber Mais Sobre o Assunto',
	'Quero Ler o Artigo Completo!',
	'Texto personalizado que não será traduzido',
];

echo '<table border="1" cellpadding="10" style="border-collapse: collapse;">';
echo '<tr><th>Texto Original (PT)</th><th>Idioma Atual (' . $debug_info['detected_language'] . ')</th><th>Traduzido?</th></tr>';

foreach ( $test_texts as $original_text ) {
	$translated    = Alvobot_PreArticle_CTA_Translations::translate_default_cta( $original_text );
	$is_translated = ( $translated !== $original_text ) ? 'SIM' : 'NÃO';

	echo '<tr>';
	echo '<td>' . htmlspecialchars( $original_text ) . '</td>';
	echo '<td>' . htmlspecialchars( $translated ) . '</td>';
	echo '<td>' . $is_translated . '</td>';
	echo '</tr>';
}

echo '</table>';

// Amostras de todas as traduções
echo '<h3>📝 Primeiras 3 CTAs em Todos os Idiomas</h3>';
echo '<table border="1" cellpadding="8" style="border-collapse: collapse; font-size: 12px;">';
echo '<tr><th>Idioma</th><th>CTA 1</th><th>CTA 2</th><th>CTA 3</th></tr>';

$all_translations = Alvobot_PreArticle_CTA_Translations::get_all_translations();
foreach ( $all_translations as $lang_code => $translations ) {
	$native_name = Alvobot_PreArticle_CTA_Translations::get_language_native_name( $lang_code );

	echo '<tr>';
	echo '<td><strong>' . $lang_code . '</strong><br><small>' . $native_name . '</small></td>';
	echo '<td>' . htmlspecialchars( $translations[0] ?? '' ) . '</td>';
	echo '<td>' . htmlspecialchars( $translations[1] ?? '' ) . '</td>';
	echo '<td>' . htmlspecialchars( $translations[2] ?? '' ) . '</td>';
	echo '</tr>';
}

echo '</table>';

// Configurações atuais do Pre-Article
echo '<h3>⚙️ Configurações Atuais do Pre-Article</h3>';
$options  = get_option( 'alvobot_pre_artigo_options', [] );
$num_ctas = $options['num_ctas'] ?? 2;

echo "<strong>Número de CTAs configuradas:</strong> {$num_ctas}<br>";

for ( $i = 1; $i <= $num_ctas; $i++ ) {
	$saved_text  = $options[ "button_text_{$i}" ] ?? '';
	$saved_color = $options[ "button_color_{$i}" ] ?? '#1E73BE';

	// Se texto está vazio, mostra qual seria usado
	if ( empty( $saved_text ) ) {
		$default_text = Alvobot_PreArticle_CTA_Translations::get_translated_cta_by_index( $i - 1 );
		echo "<strong>CTA {$i}:</strong> <em>(vazio - usaria: \"{$default_text}\")</em> | Cor: {$saved_color}<br>";
	} else {
		$translated_text  = Alvobot_PreArticle_CTA_Translations::translate_default_cta( $saved_text );
		$translation_note = ( $translated_text !== $saved_text ) ? " → <em>traduzido para: \"{$translated_text}\"</em>" : '';
		echo "<strong>CTA {$i}:</strong> \"{$saved_text}\"{$translation_note} | Cor: {$saved_color}<br>";
	}
}

echo '<p><em>💡 Dica: Para testar diferentes idiomas, instale e configure o Polylang ou WPML.</em></p>';
echo '</div>';
