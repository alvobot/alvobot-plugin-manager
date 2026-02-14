<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verifica se o Polylang está ativo
$polylang_active = class_exists( 'Polylang' ) || function_exists( 'pll_the_languages' );

// Processa atualização de modelos se solicitado
if ( isset( $_POST['refresh_openai_models'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'alvobot_openai_settings' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../includes/class-translation-providers.php';
	$openai_provider = new AlvoBotPro_OpenAI_Translation_Provider();
	$updated_models  = $openai_provider->refresh_models();

	if ( ! empty( $updated_models ) ) {
		echo '<div class="alvobot-notice alvobot-notice-success"><p>Modelos atualizados com sucesso! ' . count( $updated_models ) . ' modelos encontrados.</p></div>';
	} else {
		echo '<div class="alvobot-notice alvobot-notice-error"><p>Não foi possível carregar modelos do Flowise. Verifique a conectividade ou os logs de erro.</p></div>';
	}
}

// Limpa todos os caches se solicitado
if ( isset( $_POST['clear_all_caches'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'alvobot_openai_settings' ) ) {
	// Limpa cache de traduções
	delete_transient( 'alvobot_translation_cache' );

	// Limpa cache de modelos OpenAI
	delete_transient( 'alvobot_openai_models' );

	// Limpa cache de logs
	delete_transient( 'alvobot_multi_languages_logs' );

	echo '<div class="alvobot-notice alvobot-notice-success"><p>Todos os caches foram limpos com sucesso!</p></div>';
}

// Salva configurações se solicitado
if ( isset( $_POST['save_openai_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'alvobot_openai_settings' ) ) {
	$openai_settings = array(
		'api_key'              => sanitize_text_field( $_POST['openai_api_key'] ?? '' ),
		'model'                => sanitize_text_field( $_POST['openai_model'] ?? 'gpt-4o-mini' ),
		'max_tokens'           => intval( $_POST['openai_max_tokens'] ?? 3000 ),
		'temperature'          => floatval( $_POST['openai_temperature'] ?? 0.3 ),
		'timeout'              => intval( $_POST['openai_timeout'] ?? 60 ),
		'disable_cache'        => isset( $_POST['openai_disable_cache'] ) ? true : false,
		'disable_models_cache' => isset( $_POST['openai_disable_models_cache'] ) ? true : false,
	);

	update_option( 'alvobot_openai_settings', $openai_settings );

	echo '<div class="alvobot-notice alvobot-notice-success"><p>Configurações do OpenAI salvas com sucesso!</p></div>';
}

// Carrega configurações atuais
$openai_settings = get_option(
	'alvobot_openai_settings',
	array(
		'api_key'     => '',
		'model'       => 'gpt-4o-mini',
		'max_tokens'  => 3000,
		'temperature' => 0.3,
		'timeout'     => 60,
	)
);

// Carrega estatísticas e modelos disponíveis
$usage_stats      = null;
$available_models = array();
require_once plugin_dir_path( __FILE__ ) . '../includes/class-translation-providers.php';
$openai_provider = new AlvoBotPro_OpenAI_Translation_Provider();

if ( ! empty( $openai_settings['api_key'] ) ) {
	$usage_stats = $openai_provider->get_usage_stats();
}

// Carrega modelos disponíveis (requer API key para buscar da API OpenAI)
$available_models = $openai_provider->get_available_models();

// Se não conseguiu carregar modelos da API e tem API key, tenta forçar carregamento
if ( empty( $available_models ) && ! empty( $openai_settings['api_key'] ) ) {
	$available_models = $openai_provider->refresh_models();
}

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
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="bot" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php echo esc_html__( 'Tradução Multilíngue com OpenAI', 'alvobot-pro' ); ?></h1>
				<p><?php echo esc_html__( 'Configure o sistema de tradução automática usando OpenAI ChatGPT integrado com o Polylang.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<?php if ( ! $polylang_active ) : ?>
			<!-- Aviso: Polylang não está ativo -->
			<div class="alvobot-notice alvobot-notice-error">
				<p><strong>Plugin Necessário:</strong> O Polylang deve estar instalado e ativo para usar o sistema de tradução.</p>
				<p><a href="<?php echo admin_url( 'plugin-install.php?s=polylang&tab=search&type=term' ); ?>" class="button">Instalar Polylang</a></p>
			</div>
		<?php endif; ?>

		<div class="alvobot-grid alvobot-grid-1">
			
			<!-- Status do Sistema -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Status do Sistema</h2>
					<button type="button" id="reset-stats-btn" class="alvobot-reset-stats-btn" title="Resetar Estatísticas">
						<i data-lucide="refresh-cw" class="alvobot-icon"></i>
					</button>
				</div>
				<div class="alvobot-card-content">
					<div class="alvobot-stats-grid">
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
								<i data-lucide="<?php echo ! empty( $openai_settings['api_key'] ) ? 'check-circle' : 'x-circle'; ?>" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number"><?php echo ! empty( $openai_settings['api_key'] ) ? 'Configurado' : 'Pendente'; ?></div>
								<div class="alvobot-stat-label">OpenAI Status</div>
							</div>
						</div>

						<?php if ( $usage_stats ) : ?>
						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="line-chart" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number"><?php echo number_format( $usage_stats['total_translations'] ); ?></div>
								<div class="alvobot-stat-label">Traduções Realizadas</div>
							</div>
						</div>

						<div class="alvobot-stat-item">
							<div class="alvobot-stat-icon">
								<i data-lucide="dollar-sign" class="alvobot-icon"></i>
							</div>
							<div>
								<div class="alvobot-stat-number">$<?php echo number_format( $usage_stats['total_cost_estimate'], 4 ); ?></div>
								<div class="alvobot-stat-label">Custo Estimado</div>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Idiomas Disponíveis -->
			<?php if ( ! empty( $polylang_languages ) ) : ?>
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Idiomas Disponíveis para Tradução</h2>
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

			<!-- Configurações do OpenAI -->
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Configurações do OpenAI</h2>
					<p class="alvobot-card-subtitle">Configure sua API key e parâmetros de tradução</p>
				</div>
				<div class="alvobot-card-content">
					<form method="post" action="">
						<?php wp_nonce_field( 'alvobot_openai_settings' ); ?>
						
						<table class="alvobot-form-table">
							<tr>
								<th><label for="openai_api_key">API Key do OpenAI <span class="alvobot-required">*</span></label></th>
								<td>
									<input type="password" 
											id="openai_api_key" 
											name="openai_api_key" 
											value="<?php echo esc_attr( $openai_settings['api_key'] ); ?>" 
											class="alvobot-input alvobot-input-lg"
											placeholder="sk-..." />
									<p class="alvobot-description">
										Obtenha sua API key em: <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
									</p>
								</td>
							</tr>
							
							<tr>
								<th><label for="openai_model">Modelo</label></th>
								<td>
									<div class="alvobot-input-group">
										<select id="openai_model" name="openai_model" class="alvobot-select">
											<?php if ( ! empty( $available_models ) ) : ?>
												<?php foreach ( $available_models as $model ) : ?>
													<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $openai_settings['model'], $model['id'] ); ?>>
														<?php echo esc_html( $model['name'] ); ?>
														<?php if ( $model['cost_input'] > 0 ) : ?>
															($<?php echo number_format( $model['cost_input'], 4 ); ?>/1K tokens)
														<?php endif; ?>
													</option>
												<?php endforeach; ?>
											<?php else : ?>
												<option value="gpt-4o-mini" <?php selected( $openai_settings['model'], 'gpt-4o-mini' ); ?>>GPT-4o Mini (Econômico)</option>
												<option value="gpt-4o" <?php selected( $openai_settings['model'], 'gpt-4o' ); ?>>GPT-4o (Alta Qualidade)</option>
												<option value="gpt-4-turbo" <?php selected( $openai_settings['model'], 'gpt-4-turbo' ); ?>>GPT-4 Turbo (Balanceado)</option>
												<option value="gpt-3.5-turbo" <?php selected( $openai_settings['model'], 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Mais Econômico)</option>
											<?php endif; ?>
										</select>
										<button type="submit" name="refresh_openai_models" class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" title="Atualizar modelos da OpenAI">
											<i data-lucide="refresh-cw" class="alvobot-icon"></i>
										</button>
									</div>
									<p class="alvobot-description">
										<?php if ( ! empty( $openai_settings['api_key'] ) ) : ?>
											Modelos carregados diretamente da API OpenAI. Clique no botão de atualização para buscar novos modelos.
											<?php if ( ! empty( $available_models ) ) : ?>
												<br><strong><?php echo count( $available_models ); ?> modelos disponíveis</strong>
											<?php endif; ?>
										<?php else : ?>
											Configure sua API key primeiro para carregar modelos disponíveis da OpenAI.
											<br>Os modelos padrão estarão disponíveis após a configuração.
										<?php endif; ?>
									</p>
									<?php
									// Mostra informações detalhadas do modelo selecionado
									if ( ! empty( $available_models ) ) :
										foreach ( $available_models as $model ) :
											if ( $model['id'] === $openai_settings['model'] ) :
												?>
									<div class="alvobot-model-info" style="margin-top: 12px; padding: 12px; background: var(--alvobot-gray-50); border-radius: 6px;">
										<strong><?php echo esc_html( $model['name'] ); ?></strong>
												<?php if ( ! empty( $model['description'] ) ) : ?>
											<br><span style="color: var(--alvobot-gray-600);"><?php echo esc_html( $model['description'] ); ?></span>
										<?php endif; ?>
										<br><small>
											Máx. tokens: <?php echo number_format( $model['max_tokens'] ); ?>
												<?php if ( $model['cost_input'] > 0 ) : ?>
												| Entrada: $<?php echo number_format( $model['cost_input'], 4 ); ?>/1K
												| Saída: $<?php echo number_format( $model['cost_output'], 4 ); ?>/1K
											<?php endif; ?>
										</small>
									</div>
												<?php
											endif;
										endforeach;
									endif;
									?>
								</td>
							</tr>
							
							<tr>
								<th><label for="openai_max_tokens">Máximo de Tokens</label></th>
								<td>
									<div class="alvobot-input-with-unit">
										<input type="number" 
												id="openai_max_tokens" 
												name="openai_max_tokens" 
												value="<?php echo esc_attr( $openai_settings['max_tokens'] ); ?>" 
												class="alvobot-input"
												min="100" 
												max="4000" />
										<span class="alvobot-input-unit">tokens</span>
									</div>
									<p class="alvobot-description">
										Limite de tokens por tradução. Textos maiores serão divididos automaticamente.
									</p>
								</td>
							</tr>
							
							<tr>
								<th><label for="openai_temperature">Temperatura</label></th>
								<td>
									<input type="number" 
											id="openai_temperature" 
											name="openai_temperature" 
											value="<?php echo esc_attr( $openai_settings['temperature'] ); ?>" 
											class="alvobot-input alvobot-input-sm"
											min="0" 
											max="1" 
											step="0.1" />
									<p class="alvobot-description">
										Controla a criatividade: 0.0 = mais preciso, 1.0 = mais criativo. Recomendado: 0.3
									</p>
								</td>
							</tr>
							
							<tr>
								<th><label for="openai_timeout">Timeout (segundos)</label></th>
								<td>
									<div class="alvobot-input-with-unit">
										<input type="number" 
												id="openai_timeout" 
												name="openai_timeout" 
												value="<?php echo esc_attr( $openai_settings['timeout'] ); ?>" 
												class="alvobot-input"
												min="30" 
												max="300" />
										<span class="alvobot-input-unit">segundos</span>
									</div>
									<p class="alvobot-description">
										Tempo limite para requisições à API OpenAI.
									</p>
								</td>
							</tr>

							<tr>
								<th><label for="openai_disable_cache">Cache de Traduções</label></th>
								<td>
									<label class="alvobot-checkbox-label">
										<input type="checkbox" name="openai_disable_cache" value="1" <?php checked( isset( $openai_settings['disable_cache'] ) && $openai_settings['disable_cache'] ); ?> />
										<?php echo esc_html__( 'Desativar cache de traduções', 'alvobot-pro' ); ?>
									</label>
									<br/>
									<label class="alvobot-checkbox-label">
										<input type="checkbox" name="openai_disable_models_cache" value="1" <?php checked( isset( $openai_settings['disable_models_cache'] ) && $openai_settings['disable_models_cache'] ); ?> />
										<?php echo esc_html__( 'Desativar cache de modelos OpenAI', 'alvobot-pro' ); ?>
									</label>
									<p class="alvobot-description">
										Você pode desativar o cache de traduções e/ou o cache de modelos OpenAI. O cache de traduções afeta o resultado das traduções, enquanto o cache de modelos apenas afeta a listagem de modelos disponíveis.
									</p>
								</td>
							</tr>
						</table>
						
						<div class="alvobot-btn-group">
							<button type="submit" name="save_openai_settings" class="alvobot-btn alvobot-btn-primary">
								<i data-lucide="check" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Salvar Configurações', 'alvobot-pro' ); ?>
							</button>
							
							<button type="button" id="test-connection-btn" class="alvobot-btn alvobot-btn-secondary">
								<i data-lucide="database" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Testar Conexão', 'alvobot-pro' ); ?>
							</button>
							
							<button type="submit" name="refresh_openai_models" class="alvobot-btn alvobot-btn-secondary">
								<i data-lucide="refresh-cw" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Atualizar Modelos', 'alvobot-pro' ); ?>
							</button>

							<button type="submit" name="clear_all_caches" class="alvobot-btn alvobot-btn-secondary">
								<i data-lucide="trash-2" class="alvobot-icon"></i>
								<?php echo esc_html__( 'Limpar Todos os Caches', 'alvobot-pro' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- Estatísticas de Uso -->
			<?php if ( $usage_stats && $usage_stats['total_translations'] > 0 ) : ?>
			<div class="alvobot-card">
				<div class="alvobot-card-header">
					<h2 class="alvobot-card-title">Estatísticas de Uso</h2>
					<p class="alvobot-card-subtitle">Monitoramento de consumo da API OpenAI</p>
				</div>
				<div class="alvobot-card-content">
					<table class="alvobot-table">
						<tr>
							<td><strong>Total de Traduções:</strong></td>
							<td><?php echo number_format( $usage_stats['total_translations'] ); ?></td>
						</tr>
						<tr>
							<td><strong>Total de Tokens:</strong></td>
							<td><?php echo number_format( $usage_stats['total_tokens'] ); ?></td>
						</tr>
						<tr>
							<td><strong>Custo Estimado:</strong></td>
							<td>$<?php echo number_format( $usage_stats['total_cost_estimate'], 4 ); ?></td>
						</tr>
						<tr>
							<td><strong>Última Atualização:</strong></td>
							<td><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $usage_stats['last_reset'] ) ); ?></td>
						</tr>
					</table>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Modal de Teste de Conexão -->
<div id="connection-test-modal" class="alvobot-modal" style="display: none;">
	<div class="alvobot-modal-content">
		<div class="alvobot-modal-header">
			<h2>Teste de Conexão OpenAI</h2>
			<span class="alvobot-modal-close" id="close-connection-modal">&times;</span>
		</div>
		
		<div class="alvobot-modal-body">
			<div id="connection-test-loading" style="display: none;">
				<div class="alvobot-loading">
					<i data-lucide="refresh-cw" class="alvobot-icon alvobot-spin"></i>
					<p>Testando conexão com OpenAI...</p>
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
						<div id="result-details" style="display: none;">
							<ul id="result-details-list"></ul>
						</div>
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

<!-- Modal de Reset de Estatísticas -->
<div id="reset-stats-modal" class="alvobot-modal" style="display: none;">
	<div class="alvobot-modal-content alvobot-modal-small">
		<div class="alvobot-modal-header">
			<h2>Resetar Estatísticas</h2>
			<span class="alvobot-modal-close" id="close-reset-modal">&times;</span>
		</div>
		
		<div class="alvobot-modal-body">
			<div class="alvobot-reset-warning">
				<div class="alvobot-warning-icon">
					<i data-lucide="alert-triangle" class="alvobot-icon"></i>
				</div>
				<div class="alvobot-warning-content">
					<p><strong>Tem certeza que deseja resetar as estatísticas?</strong></p>
					<p>Esta ação irá zerar permanentemente:</p>
					<ul>
						<li>Contador de traduções realizadas</li>
						<li>Total de tokens consumidos</li>
						<li>Custo estimado acumulado</li>
					</ul>
					<p><em>Esta ação não pode ser desfeita.</em></p>
				</div>
			</div>
		</div>
		
		<div class="alvobot-modal-footer">
			<button type="button" class="alvobot-btn alvobot-btn-outline" id="cancel-reset">
				Cancelar
			</button>
			<button type="button" class="alvobot-btn alvobot-btn-danger" id="confirm-reset">
				<i data-lucide="refresh-cw" class="alvobot-icon"></i>
				Resetar Estatísticas
			</button>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	
	// === MODAL DE TESTE DE CONEXÃO ===
	
	// Handler do botão Testar Conexão
	$('#test-connection-btn').on('click', function(e) {
		e.preventDefault();
		openConnectionTestModal();
	});
	
	// Handlers do modal de conexão
	$('#close-connection-modal, #close-test-modal').on('click', function() {
		closeConnectionTestModal();
	});
	
	$('#retry-connection-test').on('click', function() {
		testConnection();
	});
	
	// Fechar modal ao clicar fora
	$('#connection-test-modal').on('click', function(e) {
		if (e.target === this) {
			closeConnectionTestModal();
		}
	});
	
	// Fechar modal com ESC
	$(document).on('keydown', function(e) {
		if (e.keyCode === 27) {
			if ($('#connection-test-modal').is(':visible')) {
				closeConnectionTestModal();
			}
			if ($('#reset-stats-modal').is(':visible')) {
				closeResetStatsModal();
			}
		}
	});
	
	function openConnectionTestModal() {
		$('#connection-test-modal').show();
		$('#connection-test-loading').hide();
		$('#connection-test-result').hide();
		$('#retry-connection-test').hide();
		
		// Inicia o teste automaticamente
		testConnection();
	}
	
	function closeConnectionTestModal() {
		$('#connection-test-modal').hide();
	}
	
	function testConnection() {
		// Mostra loading
		$('#connection-test-loading').show();
		$('#connection-test-result').hide();
		$('#retry-connection-test').hide();
		
		// Faz chamada AJAX
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_test_openai_connection',
				nonce: '<?php echo wp_create_nonce( 'alvobot_nonce' ); ?>'
			},
			success: function(response) {
				$('#connection-test-loading').hide();
				
				if (response.success) {
					showTestResult(true, response.data);
				} else {
					showTestResult(false, response.data);
				}
			},
			error: function(xhr, status, error) {
				$('#connection-test-loading').hide();
				showTestResult(false, {
					message: 'Erro de rede: ' + error
				});
			}
		});
	}
	
	function showTestResult(success, data) {
		$('#connection-test-result').show();
		$('#retry-connection-test').show();

		if (success) {
			$('#result-icon').replaceWith('<i data-lucide="check-circle" class="alvobot-icon" id="result-icon" style="color: #46b450;"></i>');
			if(typeof lucide!=='undefined') lucide.createIcons();
			$('#result-title').text('Conexão Estabelecida');
			$('#result-message').text(data.message || 'Conexão com OpenAI funcionando corretamente.');

			// Adiciona detalhes se disponíveis
			if (data.model) {
				$('#result-details-list').html('<li><strong>Modelo:</strong> ' + data.model + '</li>');
				$('#result-details').show();
			}
		} else {
			$('#result-icon').replaceWith('<i data-lucide="x-circle" class="alvobot-icon" id="result-icon" style="color: #dc3232;"></i>');
			if(typeof lucide!=='undefined') lucide.createIcons();
			$('#result-title').text('Falha na Conexão');
			$('#result-message').text(data.message || data.error || 'Não foi possível conectar com a OpenAI.');
			$('#result-details').hide();
		}
	}
	
	// === MODAL DE RESET DE ESTATÍSTICAS ===
	
	// Handler do botão Reset Estatísticas
	$('#reset-stats-btn').on('click', function(e) {
		e.preventDefault();
		openResetStatsModal();
	});
	
	// Handlers do modal de reset
	$('#close-reset-modal, #cancel-reset').on('click', function() {
		closeResetStatsModal();
	});
	
	$('#confirm-reset').on('click', function() {
		resetStatistics();
	});
	
	// Fechar modal de reset ao clicar fora
	$('#reset-stats-modal').on('click', function(e) {
		if (e.target === this) {
			closeResetStatsModal();
		}
	});
	
	function openResetStatsModal() {
		$('#reset-stats-modal').show();
	}
	
	function closeResetStatsModal() {
		$('#reset-stats-modal').hide();
	}
	
	function resetStatistics() {
		// Desabilita botão durante processamento
		$('#confirm-reset').prop('disabled', true).html('<i data-lucide="refresh-cw" class="alvobot-icon alvobot-spin"></i> Resetando...'); if(typeof lucide!=='undefined') lucide.createIcons();
		
		// Faz chamada AJAX
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_reset_usage_stats',
				nonce: '<?php echo wp_create_nonce( 'alvobot_nonce' ); ?>'
			},
			success: function(response) {
				$('#confirm-reset').prop('disabled', false).html('<i data-lucide="refresh-cw" class="alvobot-icon"></i> Resetar Estatísticas'); if(typeof lucide!=='undefined') lucide.createIcons();
				
				if (response.success) {
					closeResetStatsModal();
					// Recarrega a página para mostrar estatísticas zeradas
					window.location.reload();
				} else {
					alert('Erro ao resetar estatísticas: ' + (response.data.message || 'Erro desconhecido'));
				}
			},
			error: function(xhr, status, error) {
				$('#confirm-reset').prop('disabled', false).html('<i data-lucide="refresh-cw" class="alvobot-icon"></i> Resetar Estatísticas'); if(typeof lucide!=='undefined') lucide.createIcons();
				alert('Erro de rede: ' + error);
			}
		});
	}
});
</script>