<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$polylang_active = class_exists( 'Polylang' ) || function_exists( 'pll_the_languages' );
$site_token      = get_option( 'grp_site_token' );
$is_connected    = ! empty( $site_token );

// Carrega creditos
$ai_api  = class_exists( 'AlvoBotPro_AI_API' ) ? AlvoBotPro_AI_API::get_instance() : null;
$credits = $ai_api ? $ai_api->get_credits() : array();

// Carrega linguagens do Polylang
$polylang_languages = array();
if ( $polylang_active && function_exists( 'PLL' ) && PLL()->model ) {
	$languages = PLL()->model->get_languages_list();
	foreach ( $languages as $language ) {
		$polylang_languages[] = array(
			'name'        => $language->name,
			'native_name' => isset( $language->flag_title ) ? $language->flag_title : $language->name,
			'slug'        => $language->slug,
			'flag'        => isset( $language->flag_url ) ? $language->flag_url : '',
		);
	}
}

// Carrega estatisticas do provider de creditos
$translation_stats = get_option(
	'alvobot_credit_translation_stats',
	array(
		'total_translations' => 0,
		'last_reset'         => current_time( 'mysql' ),
	)
);

// Salva configuracoes de cache se solicitado
if ( isset( $_POST['save_credits_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'alvobot_credits_settings' ) ) {
	$openai_settings = get_option( 'alvobot_openai_settings', array() );
	$openai_settings['disable_cache'] = isset( $_POST['disable_cache'] ) ? true : false;
	update_option( 'alvobot_openai_settings', $openai_settings );

	echo '<div class="alvobot-notice alvobot-notice-success"><p>Configuracoes salvas com sucesso!</p></div>';
}

// Limpa caches se solicitado
if ( isset( $_POST['clear_all_caches'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'alvobot_credits_settings' ) ) {
	delete_transient( 'alvobot_translation_cache' );
	delete_transient( 'alvobot_multi_languages_logs' );
	delete_transient( 'alvobot_ai_credits' );
	echo '<div class="alvobot-notice alvobot-notice-success"><p>Todos os caches foram limpos com sucesso!</p></div>';
}

$openai_settings = get_option( 'alvobot_openai_settings', array() );
$cache_disabled  = isset( $openai_settings['disable_cache'] ) && $openai_settings['disable_cache'];
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="ticket" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php echo esc_html__( 'Traducao com Creditos AlvoBot', 'alvobot-pro' ); ?></h1>
				<p><?php echo esc_html__( 'Traduza posts automaticamente usando creditos do seu plano AlvoBot. Cada chunk de traducao consome 1 credito.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<?php if ( ! $is_connected ) : ?>
			<div class="alvobot-notice alvobot-notice-error">
				<p><strong>Site nao conectado:</strong> Registre este site no AlvoBot para usar o sistema de traducao com creditos.</p>
				<p><a href="<?php echo admin_url( 'admin.php?page=alvobot-pro' ); ?>" class="button">Conectar ao AlvoBot</a></p>
			</div>
		<?php endif; ?>

		<?php if ( ! $polylang_active ) : ?>
			<div class="alvobot-notice alvobot-notice-error">
				<p><strong>Plugin Necessario:</strong> O Polylang deve estar instalado e ativo para usar o sistema de traducao.</p>
				<p><a href="<?php echo admin_url( 'plugin-install.php?s=polylang&tab=search&type=term' ); ?>" class="button">Instalar Polylang</a></p>
			</div>
		<?php endif; ?>

		<div class="alvobot-grid alvobot-grid-1">

			<!-- Status do Sistema -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Status do Sistema</h2>
				</div>
				<div class="alvobot-card-content">
					<div class="alvobot-stats-grid">
						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="ticket" class="alvobot-icon"></i>
							</div>
							<div>
								<?php if ( $is_connected && ! isset( $credits['error'] ) ) : ?>
									<div class="alvobot-stat-number"><?php echo esc_html( isset( $credits['total_available'] ) ? number_format( $credits['total_available'] ) : '0' ); ?></div>
									<div class="alvobot-stat-label">Creditos Disponiveis</div>
								<?php elseif ( $is_connected ) : ?>
									<div class="alvobot-stat-number">Erro</div>
									<div class="alvobot-stat-label">Erro ao carregar creditos</div>
								<?php else : ?>
									<div class="alvobot-stat-number">--</div>
									<div class="alvobot-stat-label">Site nao conectado</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="languages" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number"><?php echo count( $polylang_languages ); ?></div>
								<div class="alvobot-stat-label">Idiomas Configurados</div>
							</div>
						</div>

						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="zap" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number">1</div>
								<div class="alvobot-stat-label">Credito por Chunk</div>
							</div>
						</div>

						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="line-chart" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number"><?php echo number_format( $translation_stats['total_translations'] ); ?></div>
								<div class="alvobot-stat-label">Posts Traduzidos</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Idiomas Disponiveis -->
			<?php if ( ! empty( $polylang_languages ) ) : ?>
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Idiomas Disponiveis para Traducao</h2>
					<p class="alvobot-card-subtitle">
						Configurados no Polylang
						<a href="<?php echo admin_url( 'admin.php?page=mlang' ); ?>" class="alvobot-link-inline" title="Configurar idiomas no Polylang">
							<i data-lucide="settings" class="alvobot-icon" style="font-size: 14px; vertical-align: middle;"></i>
						</a>
					</p>
				</div>
				<div class="alvobot-card-content">
					<div class="alvobot-grid alvobot-grid-auto-sm">
						<?php foreach ( $polylang_languages as $language ) : ?>
						<div class="alvobot-badge alvobot-badge-info">
							<?php if ( ! empty( $language['flag'] ) ) : ?>
								<img src="<?php echo esc_url( $language['flag'] ); ?>" alt="<?php echo esc_attr( $language['name'] ); ?>" style="width: 16px; height: 12px; margin-right: 8px;">
							<?php endif; ?>
							<?php echo esc_html( $language['native_name'] ); ?>
							<small>(<?php echo esc_html( $language['slug'] ); ?>)</small>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Como funciona -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Como Funciona</h2>
				</div>
				<div class="alvobot-card-content">
					<div style="line-height: 1.8;">
						<p>O sistema de traducao divide o conteudo do post em blocos (chunks) baseados nos subtitulos e traduz cada bloco separadamente:</p>
						<ul style="list-style: disc; padding-left: 20px;">
							<li><strong>Titulo:</strong> 1 credito</li>
							<li><strong>Excerpt:</strong> 1 credito (se existir)</li>
							<li><strong>Conteudo:</strong> 1 credito por chunk (~500 palavras cada)</li>
						</ul>
						<p>Exemplo: Um post com titulo, excerpt e 3 chunks de conteudo custara <strong>5 creditos</strong> por idioma.</p>
					</div>
				</div>
			</div>

			<!-- Configuracoes -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Configuracoes</h2>
				</div>
				<div class="alvobot-card-content">
					<form method="post" action="">
						<?php wp_nonce_field( 'alvobot_credits_settings' ); ?>

						<table class="alvobot-form-table">
							<tr>
								<th><label for="disable_cache">Cache de Traducoes</label></th>
								<td>
									<label class="alvobot-checkbox-label">
										<input type="checkbox" name="disable_cache" value="1" <?php checked( $cache_disabled ); ?> />
										<?php echo esc_html__( 'Desativar cache de traducoes', 'alvobot-pro' ); ?>
									</label>
									<p class="alvobot-description">
										O cache evita traducoes duplicadas. Desative apenas para debug.
									</p>
								</td>
							</tr>
						</table>

						<div class="alvobot-btn-group">
							<button type="submit" name="save_credits_settings" class="alvobot-btn alvobot-btn-primary">
								<i data-lucide="check" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Salvar Configuracoes', 'alvobot-pro' ); ?>
							</button>

							<button type="button" id="test-connection-btn" class="alvobot-btn alvobot-btn-secondary">
								<i data-lucide="database" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Testar Conexao', 'alvobot-pro' ); ?>
							</button>

							<button type="submit" name="clear_all_caches" class="alvobot-btn alvobot-btn-secondary">
								<i data-lucide="trash-2" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Limpar Caches', 'alvobot-pro' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

		</div>
	</div>
</div>

<!-- Modal de Teste de Conexao -->
<div id="connection-test-modal" class="alvobot-modal" style="display: none;">
	<div class="alvobot-modal-content">
		<div class="alvobot-modal-header">
			<h2>Teste de Conexao</h2>
			<span class="alvobot-modal-close" id="close-connection-modal">&times;</span>
		</div>

		<div class="alvobot-modal-body">
			<div id="connection-test-loading" style="display: none;">
				<div class="alvobot-loading">
					<i data-lucide="refresh-cw" class="alvobot-icon alvobot-spin"></i>
					<p>Testando conexao com AlvoBot...</p>
				</div>
			</div>

			<div id="connection-test-result" style="display: none;">
				<div class="alvobot-test-result">
					<div class="alvobot-result-icon">
						<i class="alvobot-icon" id="result-icon"></i>
					</div>
					<div class="alvobot-result-content">
						<h3 id="result-title"></h3>
						<p id="result-message"></p>
					</div>
				</div>
			</div>
		</div>

		<div class="alvobot-modal-footer">
			<button type="button" class="alvobot-btn alvobot-btn-outline" id="close-test-modal">
				Fechar
			</button>
			<button type="button" class="alvobot-btn alvobot-btn-primary" id="retry-connection-test" style="display: none;">
				Testar Novamente
			</button>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {

	$('#test-connection-btn').on('click', function(e) {
		e.preventDefault();
		$('#connection-test-modal').show();
		testConnection();
	});

	$('#close-connection-modal, #close-test-modal').on('click', function() {
		$('#connection-test-modal').hide();
	});

	$('#retry-connection-test').on('click', function() {
		testConnection();
	});

	$('#connection-test-modal').on('click', function(e) {
		if (e.target === this) $(this).hide();
	});

	$(document).on('keydown', function(e) {
		if (e.keyCode === 27 && $('#connection-test-modal').is(':visible')) {
			$('#connection-test-modal').hide();
		}
	});

	function testConnection() {
		$('#connection-test-loading').show();
		$('#connection-test-result').hide();
		$('#retry-connection-test').hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_test_translation_connection',
				nonce: '<?php echo wp_create_nonce( 'alvobot_nonce' ); ?>'
			},
			success: function(response) {
				$('#connection-test-loading').hide();
				showTestResult(response.success, response.data);
			},
			error: function(xhr, status, error) {
				$('#connection-test-loading').hide();
				showTestResult(false, { message: 'Erro de rede: ' + error });
			}
		});
	}

	function showTestResult(success, data) {
		$('#connection-test-result').show();
		$('#retry-connection-test').show();

		if (success) {
			$('#result-icon').replaceWith('<i data-lucide="check-circle" class="alvobot-icon" id="result-icon" style="color: #46b450;"></i>');
			$('#result-title').text('Conexao Estabelecida');
			$('#result-message').text(data.message || 'Conexao com AlvoBot funcionando corretamente.');
		} else {
			$('#result-icon').replaceWith('<i data-lucide="x-circle" class="alvobot-icon" id="result-icon" style="color: #dc3232;"></i>');
			$('#result-title').text('Falha na Conexao');
			$('#result-message').text(data.message || data.error || 'Nao foi possivel conectar com o AlvoBot.');
		}

		if (typeof lucide !== 'undefined') lucide.createIcons();
	}
});
</script>
