<?php
/**
 * Teste rápido da correção de idioma
 * Acesse: https://plugin-developers/wp-content/plugins/alvobot-plugin-manager/includes/modules/pre-article/test-language-fix.php
 */

// Carrega WordPress
require_once('../../../../../wp-load.php');

// Carrega a classe de traduções
require_once('includes/class-cta-translations.php');

echo '<h1>🛠️ Teste da Correção de Tradução</h1>';

// Teste 1: Forçar espanhol via URL
echo '<h2>Teste 1: Forçar Espanhol via URL</h2>';
echo '<p><a href="?force_lang=es" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🇪🇸 Testar em Espanhol</a></p>';

// Teste 2: Mostrar resultado atual
if (class_exists('Alvobot_PreArticle_CTA_Translations')) {
    $detected_lang = Alvobot_PreArticle_CTA_Translations::get_current_language();
    $lang_name = Alvobot_PreArticle_CTA_Translations::get_language_native_name($detected_lang);

    echo '<h2>Resultado Atual</h2>';
    echo '<div style="background: #f0f0f0; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo '<p><strong>Idioma detectado:</strong> <span style="color: blue; font-size: 20px; font-weight: bold;">' . strtoupper($detected_lang) . '</span> (' . $lang_name . ')</p>';

    // Mostra as CTAs que serão usadas
    $ctas = Alvobot_PreArticle_CTA_Translations::get_translated_ctas();
    echo '<p><strong>CTAs que aparecerão:</strong></p>';
    echo '<ol style="background: white; padding: 15px; border-radius: 5px;">';
    foreach (array_slice($ctas, 0, 3) as $i => $cta) {
        $color = $detected_lang === 'es' ? '#28a745' : '#dc3545';
        echo '<li style="color: ' . $color . '; font-weight: bold; margin: 5px 0;">' . esc_html($cta) . '</li>';
    }
    echo '</ol>';

    if ($detected_lang === 'es') {
        echo '<p style="color: #28a745; font-weight: bold;">✅ SUCESSO! Os textos estão em espanhol!</p>';
    } else {
        echo '<p style="color: #dc3545; font-weight: bold;">❌ Os textos ainda estão em ' . $lang_name . '</p>';
    }
    echo '</div>';

    // Testes rápidos para outros idiomas
    echo '<h2>Testes Rápidos</h2>';
    $test_languages = ['es' => '🇪🇸 Espanhol', 'en' => '🇺🇸 Inglês', 'fr' => '🇫🇷 Francês', 'pt' => '🇧🇷 Português'];

    foreach ($test_languages as $lang_code => $lang_display) {
        echo '<p><a href="?force_lang=' . $lang_code . '" style="background: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;">' . $lang_display . '</a></p>';
    }

    // Mostra informações de debug
    echo '<h2>Informações de Debug</h2>';
    $debug_info = Alvobot_PreArticle_CTA_Translations::get_language_debug_info();

    echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;">';
    echo '<strong>REQUEST_URI:</strong> ' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . '<br>';
    echo '<strong>HTTP_HOST:</strong> ' . ($_SERVER['HTTP_HOST'] ?? 'N/A') . '<br>';
    echo '<strong>WordPress Locale:</strong> ' . get_locale() . '<br>';
    echo '<strong>Accept-Language:</strong> ' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A') . '<br>';
    echo '</div>';

} else {
    echo '<p style="color: red;">❌ ERRO: Classe Alvobot_PreArticle_CTA_Translations não carregada!</p>';
}

echo '<hr style="margin: 30px 0;">';
echo '<p><strong>📋 Próximos passos:</strong></p>';
echo '<ol>';
echo '<li>Se o teste com "?force_lang=es" mostrar textos em espanhol, a correção funcionou!</li>';
echo '<li>Agora você precisa configurar o site para reconhecer automaticamente que está em espanhol</li>';
echo '<li>Verifique se o Polylang está configurado corretamente ou se a URL do site tem padrão /es/</li>';
echo '<li>Para debug completo, acesse: <a href="debug-language-detection.php">debug-language-detection.php</a></li>';
echo '</ol>';
?>