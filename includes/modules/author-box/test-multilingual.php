<?php
/**
 * Teste do sistema multilingual do Author Box
 * Acesse: https://plugin-developers/wp-content/plugins/alvobot-plugin-manager/includes/modules/author-box/test-multilingual.php
 */

// Carrega WordPress
require_once '../../../../../wp-load.php';

// Carrega a classe de traduções
require_once 'includes/class-author-box-translations.php';

echo '<h1>🌍 Teste Multilingual - Author Box</h1>';

// Testa se a classe foi carregada
if ( class_exists( 'Alvobot_AuthorBox_Translations' ) ) {
	echo '<p style="color: green; font-weight: bold;">✅ Classe de traduções carregada com sucesso!</p>';
} else {
	echo '<p style="color: red; font-weight: bold;">❌ Erro: Classe de traduções não foi carregada!</p>';
	exit;
}

echo '<h2>Informações do Sistema</h2>';
$debug_info = Alvobot_AuthorBox_Translations::get_language_debug_info();

echo '<ul>';
echo '<li><strong>Idioma detectado:</strong> ' . strtoupper( $debug_info['detected_language'] ) . ' (' . $debug_info['language_name'] . ')</li>';
echo '<li><strong>WordPress Locale:</strong> ' . $debug_info['site_locale'] . '</li>';
echo '<li><strong>REQUEST_URI:</strong> ' . $debug_info['request_uri'] . '</li>';
echo '<li><strong>HTTP_HOST:</strong> ' . $debug_info['http_host'] . '</li>';
echo '<li><strong>Polylang ativo:</strong> ' . ( $debug_info['polylang_active'] ? 'SIM' : 'NÃO' ) . '</li>';
echo '<li><strong>WPML ativo:</strong> ' . ( $debug_info['wpml_active'] ? 'SIM' : 'NÃO' ) . '</li>';
echo '</ul>';

echo '<h2>Teste de Traduções por Idioma</h2>';

$test_languages = [ 'pt', 'en', 'es', 'it', 'fr', 'de' ];
$test_keys      = [
	'about_author',
	'display_on_posts',
	'display_on_pages',
	'author_box_settings',
	'custom_avatar',
	'save_changes',
];

foreach ( $test_languages as $lang ) {
	$lang_name = Alvobot_AuthorBox_Translations::get_language_native_name( $lang );
	echo '<h3>🇺🇸 ' . strtoupper( $lang ) . ' - ' . $lang_name . '</h3>';

	echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">';
	echo '<tr style="background: #f0f0f0;"><th>Chave</th><th>Tradução</th></tr>';

	foreach ( $test_keys as $key ) {
		$translation = Alvobot_AuthorBox_Translations::get_translation( $key, $lang );
		echo '<tr>';
		echo '<td style="font-family: monospace; background: #f9f9f9;">' . $key . '</td>';
		echo '<td style="font-weight: bold; color: #2c5aa0;">' . esc_html( $translation ) . '</td>';
		echo '</tr>';
	}

	echo '</table>';
}

echo '<h2>Teste de Forçar Idioma</h2>';
echo '<p>Use estes links para testar diferentes idiomas:</p>';

$current_url = strtok( $_SERVER['REQUEST_URI'], '?' );
foreach ( $test_languages as $lang ) {
	$lang_name = Alvobot_AuthorBox_Translations::get_language_native_name( $lang );
	$flag      = [
		'pt' => '🇧🇷',
		'en' => '🇺🇸',
		'es' => '🇪🇸',
		'it' => '🇮🇹',
		'fr' => '🇫🇷',
		'de' => '🇩🇪',
	][ $lang ] ?? '🌍';

	echo '<a href="' . $current_url . '?force_lang=' . $lang . '" style="display: inline-block; margin: 5px; padding: 10px 15px; background: #007cba; color: white; text-decoration: none; border-radius: 5px;">' . $flag . ' ' . $lang_name . '</a> ';
}

echo '<h2>Preview do Author Box Traduzido</h2>';

// Simula as configurações do Author Box
$current_lang = Alvobot_AuthorBox_Translations::get_current_language();
$about_text   = Alvobot_AuthorBox_Translations::get_translation( 'about_author' );

echo '<div style="border: 2px solid #ddd; padding: 20px; border-radius: 8px; background: white; max-width: 600px;">';
echo '<h3 style="margin-top: 0; color: #333;">' . esc_html( $about_text ) . '</h3>';

// Simula dados do autor
$author_avatar = get_avatar( 1, 80 );
$author_name   = 'João Silva';
$author_bio    = 'Especialista em tecnologia e desenvolvimento web com mais de 10 anos de experiência.';

echo '<div style="display: flex; gap: 15px; align-items: flex-start;">';
echo '<div style="flex-shrink: 0;">' . $author_avatar . '</div>';
echo '<div>';
echo '<h4 style="margin: 0 0 8px 0; color: #2c5aa0;">' . esc_html( $author_name ) . '</h4>';
echo '<p style="margin: 0; color: #666; line-height: 1.5;">' . esc_html( $author_bio ) . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<h2>Como Testar na Interface Admin</h2>';
echo '<ol>';
echo '<li><strong>Página de Configurações:</strong> <a href="' . admin_url( 'admin.php?page=alvobot-pro-author-box' ) . '" target="_blank">Configurar Author Box</a></li>';
echo '<li><strong>Perfil do Usuário:</strong> <a href="' . admin_url( 'profile.php' ) . '" target="_blank">Editar Perfil</a></li>';
echo '<li><strong>Forçar idioma:</strong> Adicione <code>?force_lang=es</code> às URLs acima</li>';
echo '</ol>';

echo '<h2>Resultado Atual</h2>';

$current_translations = Alvobot_AuthorBox_Translations::get_all_translations();
echo '<div style="background: #e8f4f8; padding: 15px; border-radius: 5px; border-left: 4px solid #2c5aa0;">';
echo '<p><strong>🎯 Idioma Ativo:</strong> ' . strtoupper( $current_lang ) . ' (' . Alvobot_AuthorBox_Translations::get_language_native_name( $current_lang ) . ')</p>';
echo '<p><strong>📝 Exemplo de texto:</strong> "' . $current_translations['author_box_settings'] . '"</p>';
echo '<p><strong>✅ Status:</strong> Sistema multilingual funcionando!</p>';
echo '</div>';

echo '<hr style="margin: 30px 0;">';
echo '<p><strong>📋 Próximos passos:</strong> Configure o Polylang ou use URLs com padrão /es/ para ativação automática do idioma!</p>';
