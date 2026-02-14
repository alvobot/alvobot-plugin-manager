<?php
/**
 * Teste para verificar se pré-artigo vem habilitado por padrão
 * Acesse: https://plugin-developers/wp-content/plugins/alvobot-plugin-manager/includes/modules/pre-article/test-default-enabled.php
 */

// Carrega WordPress
require_once '../../../../../wp-load.php';

echo '<h1>🧪 Teste: Pré-Artigo Habilitado por Padrão</h1>';

// Lista alguns posts para verificar
$posts = get_posts(
	[
		'numberposts' => 5,
		'post_status' => 'any',
		'orderby'     => 'date',
		'order'       => 'DESC',
	]
);

echo '<h2>Status dos Posts Recentes</h2>';
echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
echo '<tr>';
echo '<th>Post</th>';
echo '<th>Pré-Artigo Habilitado</th>';
echo '<th>Configurado Manualmente</th>';
echo '<th>Ações</th>';
echo '</tr>';

foreach ( $posts as $post ) {
	$use_custom = get_post_meta( $post->ID, '_alvobot_use_custom', true );
	$is_set     = get_post_meta( $post->ID, '_alvobot_use_custom_set', true );

	// Simula a lógica do sistema
	$would_be_enabled = false;
	if ( $use_custom === '' && ! $is_set ) {
		$would_be_enabled = true;
	}

	$status      = $use_custom === '1' ? '✅ SIM' : '❌ NÃO';
	$manual      = $is_set ? '✅ SIM' : '❌ NÃO';
	$auto_status = $would_be_enabled ? ' (🔄 Seria habilitado automaticamente)' : '';

	echo '<tr>';
	echo '<td><strong>' . esc_html( $post->post_title ) . '</strong><br>';
	echo '<small>ID: ' . $post->ID . ' | Status: ' . $post->post_status . '</small></td>';
	echo '<td>' . $status . $auto_status . '</td>';
	echo '<td>' . $manual . '</td>';
	echo '<td>';
	echo '<a href="' . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . '" target="_blank">Editar Post</a><br>';
	echo '<a href="' . home_url( '/pre/' . $post->post_name ) . '" target="_blank">Ver Pré-Artigo</a>';
	echo '</td>';
	echo '</tr>';
}

echo '</table>';

echo '<h2>Como Testar</h2>';
echo '<ol>';
echo '<li><strong>Crie um novo post:</strong> <a href="' . admin_url( 'post-new.php' ) . '" target="_blank">Novo Post</a></li>';
echo '<li><strong>Vá para a seção "Configuração do Pré-Artigo"</strong> no editor</li>';
echo '<li><strong>Verifique se o checkbox "Habilitar página de pré-artigo" está marcado</strong></li>';
echo '<li><strong>Verifique se as opções estão visíveis</strong> (não mais ocultas)</li>';
echo '<li><strong>O número padrão de CTAs deve ser 3</strong></li>';
echo '</ol>';

echo '<h2>Comportamento Esperado</h2>';
echo '<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">';
echo '<p><strong>✅ Posts Novos:</strong> Checkbox marcado automaticamente</p>';
echo '<p><strong>✅ Opções Visíveis:</strong> Seção de configuração aberta por padrão</p>';
echo '<p><strong>✅ 3 CTAs Padrão:</strong> Número definido como 3 automaticamente</p>';
echo '<p><strong>✅ Textos Traduzidos:</strong> CTAs no idioma correto (inglês neste caso)</p>';
echo '</div>';

echo '<h2>Logs de Debug</h2>';
echo '<p>Para ver logs detalhados, ative WP_DEBUG e verifique os logs do WordPress.</p>';
echo '<p>Logs específicos aparecerão com o prefixo: <code>[pre-article]</code></p>';

// Teste da classe CTA Translations
if ( class_exists( 'Alvobot_PreArticle_CTA_Translations' ) ) {
	echo '<h2>Teste das Traduções</h2>';
	$detected_lang = Alvobot_PreArticle_CTA_Translations::get_current_language();
	$ctas          = Alvobot_PreArticle_CTA_Translations::get_translated_ctas();

	echo '<p><strong>Idioma detectado:</strong> ' . strtoupper( $detected_lang ) . '</p>';
	echo '<p><strong>Primeiros 3 CTAs que serão usados:</strong></p>';
	echo '<ul>';
	for ( $i = 0; $i < 3; $i++ ) {
		if ( isset( $ctas[ $i ] ) ) {
			echo '<li style="color: green; font-weight: bold;">' . esc_html( $ctas[ $i ] ) . '</li>';
		}
	}
	echo '</ul>';
}

echo '<hr>';
echo '<p><strong>📝 Resultado Esperado:</strong> Novos posts devem ter o pré-artigo habilitado automaticamente, com 3 CTAs pré-configuradas no idioma correto!</p>';
