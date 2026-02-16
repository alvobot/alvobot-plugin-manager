<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
	<div class="alvobot-admin-header">
		<div class="alvobot-header-icon">
			<i data-lucide="blocks" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-header-content">
			<h1>AlvoBot Pro</h1>
			<p>Gerencie todos os módulos do AlvoBot Pro em um só lugar.</p>
		</div>
	</div>

	<div class="alvobot-notice-container">
		<div class="alvobot-pro-notices"></div>
		<!-- Mensagens de sucesso/erro, se necessário -->
	</div>

	<!-- Card de Créditos IA -->
	<?php
	$ai_credits   = AlvoBotPro_AI_API::get_instance()->get_credits();
	$ai_token     = get_option( 'alvobot_site_token' );
	$ai_total     = isset( $ai_credits['total_available'] ) ? intval( $ai_credits['total_available'] ) : 0;
	$ai_limit     = isset( $ai_credits['monthly_limit'] ) ? intval( $ai_credits['monthly_limit'] ) : 0;
	$ai_used      = isset( $ai_credits['monthly_used'] ) ? intval( $ai_credits['monthly_used'] ) : 0;
	$ai_extra     = isset( $ai_credits['extra_available'] ) ? intval( $ai_credits['extra_available'] ) : 0;
	$ai_has_plan  = ! empty( $ai_credits['has_active_plan'] );
	$ai_has_error = isset( $ai_credits['error'] );
	$ai_pct       = ( $ai_has_plan && $ai_limit > 0 ) ? min( 100, round( ( $ai_used / $ai_limit ) * 100 ) ) : 0;
	$ai_bar_color = $ai_pct >= 90 ? 'red' : ( $ai_pct >= 75 ? 'yellow' : 'green' );
	?>
	<?php if ( ! empty( $ai_token ) ) : ?>
	<div class="alvobot-card alvobot-credits-card">
		<div class="alvobot-credits-card-inner">
			<div class="alvobot-credits-card-info">
				<div class="alvobot-credits-card-label">
					<i data-lucide="sparkles" class="alvobot-icon"></i>
					<span>Créditos IA</span>
					<button type="button" class="alvobot-credits-refresh" title="Atualizar créditos">
						<i data-lucide="refresh-cw" class="alvobot-icon"></i>
					</button>
				</div>
				<?php if ( $ai_has_error ) : ?>
					<p class="alvobot-credits-card-detail alvobot-credits-card-detail--error">Erro ao carregar créditos</p>
				<?php elseif ( ! $ai_has_plan ) : ?>
					<p class="alvobot-credits-card-detail alvobot-credits-card-detail--muted">Sem plano ativo</p>
				<?php else : ?>
					<p class="alvobot-credits-card-detail">
						<?php echo esc_html( $ai_used ); ?> de <?php echo esc_html( $ai_limit ); ?> créditos mensais usados
						<?php if ( $ai_extra > 0 ) : ?>
							<span class="alvobot-credits-card-extra">+<?php echo esc_html( $ai_extra ); ?> extras</span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
			<div class="alvobot-credits-card-number">
				<?php if ( $ai_has_error ) : ?>
					<span class="alvobot-credits-value alvobot-credits-value--error">&mdash;</span>
				<?php elseif ( ! $ai_has_plan ) : ?>
					<span class="alvobot-credits-value alvobot-credits-value--muted">0</span>
				<?php else : ?>
					<span class="alvobot-credits-value"><?php echo esc_html( $ai_total ); ?></span>
					<span class="alvobot-credits-unit">disponíveis</span>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $ai_has_plan && $ai_limit > 0 ) : ?>
		<div class="alvobot-credits-card-bar">
			<div class="alvobot-credits-bar">
				<div class="alvobot-credits-bar-fill <?php echo esc_attr( $ai_bar_color ); ?>" style="width: <?php echo esc_attr( $ai_pct ); ?>%"></div>
			</div>
		</div>
		<?php endif; ?>
		<div class="alvobot-credits-badge-container" style="display:none;">
			<?php AlvoBotPro_AI_API::render_credit_badge(); ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="alvobot-grid alvobot-grid-auto">
		<?php
		$registry = AlvoBotPro::get_module_registry();
		foreach ( $registry as $module_id => $meta ) :
			// Módulos sem settings_slug não aparecem como cards no dashboard
			if ( empty( $meta['settings_slug'] ) ) {
				continue;
			}
			$is_active = ! empty( $active_modules[ $module_id ] );
			?>
		<div class="alvobot-card <?php echo esc_attr( $is_active ? 'module-enabled' : '' ); ?>">
			<div class="alvobot-card-header">
				<div>
					<h2 class="alvobot-card-title"><?php echo esc_html( $meta['name'] ); ?></h2>
					<p class="alvobot-card-subtitle"><?php echo esc_html( $meta['description'] ); ?></p>
				</div>
				<label class="alvobot-toggle">
					<input type="checkbox"
						data-module="<?php echo esc_attr( $module_id ); ?>"
						<?php echo $is_active ? 'checked="checked"' : ''; ?>>
					<span class="alvobot-toggle-slider"></span>
				</label>
			</div>
			<div class="alvobot-card-footer">
				<?php if ( $is_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $meta['settings_slug'] ) ); ?>" class="alvobot-btn alvobot-btn-secondary"><?php esc_html_e( 'Configurações', 'alvobot-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Seção Status do Sistema -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
		<div>
			<h2 class="alvobot-card-title">Status do Sistema</h2>
			<p class="alvobot-card-subtitle">Monitore o status da conexão e integração com a plataforma AlvoBot</p>
		</div>
		</div>
	  
		<div class="alvobot-card-content">
		<table class="alvobot-form-table" role="presentation">
			<tr>
			<th scope="row"><?php esc_html_e( 'Usuário AlvoBot', 'alvobot-pro' ); ?></th>
			<td>
				<?php if ( $alvobot_user ) : ?>
				<span class="alvobot-badge alvobot-badge-success">
					<span class="alvobot-status-indicator success"></span>
					<?php esc_html_e( 'Criado', 'alvobot-pro' ); ?>
				</span>
				<?php else : ?>
				<span class="alvobot-badge alvobot-badge-error">
					<span class="alvobot-status-indicator error"></span>
					<?php esc_html_e( 'Não Criado', 'alvobot-pro' ); ?>
				</span>
				<?php endif; ?>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php esc_html_e( 'Application Password', 'alvobot-pro' ); ?></th>
			<td>
				<?php if ( $has_app_password ) : ?>
				<span class="alvobot-badge alvobot-badge-success">
					<span class="alvobot-status-indicator success"></span>
					<?php
				printf(
					/* translators: %d: number of app passwords */
					esc_html( _n( '1 senha criada', '%d senhas criadas', $app_password_count, 'alvobot-pro' ) ),
					intval( $app_password_count )
				);
				?>
				</span>
				<p class="description" style="margin-top: var(--alvobot-space-sm);">
					<?php esc_html_e( 'Application Password ativo para autenticação API', 'alvobot-pro' ); ?>
				</p>
				<?php else : ?>
				<span class="alvobot-badge alvobot-badge-error">
					<span class="alvobot-status-indicator error"></span>
					<?php esc_html_e( 'Não Criado', 'alvobot-pro' ); ?>
				</span>
				<p class="description" style="margin-top: var(--alvobot-space-sm); color: var(--alvobot-error);">
					<i data-lucide="alert-triangle" class="alvobot-icon" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"></i><?php esc_html_e( 'Sem Application Password - verifique se plugins de segurança não estão bloqueando', 'alvobot-pro' ); ?>
				</p>
				<?php endif; ?>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php esc_html_e( 'Token do Site', 'alvobot-pro' ); ?></th>
			<td>
				<?php if ( $site_token ) : ?>
				<span class="alvobot-badge alvobot-badge-success">
					<span class="alvobot-status-indicator success"></span>
					<?php esc_html_e( 'Gerado', 'alvobot-pro' ); ?>
				</span>
				<div class="alvobot-token-field alvobot-mt-sm">
					<code class="alvobot-token-value" data-token="<?php echo esc_attr( $site_token ); ?>">••••••••••••••••••••••••••••••••</code>
					<button type="button" class="alvobot-token-toggle" title="<?php esc_attr_e( 'Mostrar/Ocultar Token', 'alvobot-pro' ); ?>">
					<svg class="eye-show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
					</svg>
					<svg class="eye-hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
					</svg>
					</button>
				</div>
				<?php else : ?>
				<span class="alvobot-badge alvobot-badge-error">
					<span class="alvobot-status-indicator error"></span>
					<?php esc_html_e( 'Não Gerado', 'alvobot-pro' ); ?>
				</span>
				<?php endif; ?>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php esc_html_e( 'Status da Conexão', 'alvobot-pro' ); ?></th>
			<td>
				<?php
				if ( $connection_status && is_array( $connection_status ) ) :
					$status      = $connection_status['status'];
					$error_type  = isset( $connection_status['error_type'] ) ? $connection_status['error_type'] : '';
					$http_status = isset( $connection_status['http_status'] ) ? $connection_status['http_status'] : '';
					$message     = isset( $connection_status['message'] ) ? $connection_status['message'] : '';

					if ( $status === 'connected' ) :
						?>
					<span class="alvobot-badge alvobot-badge-success">
					<span class="alvobot-status-indicator success"></span>
						<?php esc_html_e( 'Conectado', 'alvobot-pro' ); ?>
					</span>
					<p class="description" style="margin-top: var(--alvobot-space-sm);">
						<?php echo esc_html( $message ); ?>
					</p>
					<?php else : ?>
					<span class="alvobot-badge alvobot-badge-error">
					<span class="alvobot-status-indicator error"></span>
						<?php esc_html_e( 'Erro de Conexão', 'alvobot-pro' ); ?>
						<?php if ( $http_status ) : ?>
						<code style="margin-left: var(--alvobot-space-xs);">HTTP <?php echo esc_html( $http_status ); ?></code>
					<?php endif; ?>
					</span>
					<div class="alvobot-notice alvobot-notice-warning" style="margin-top: var(--alvobot-space-sm);">
					<p style="margin: 0; font-weight: 500;">
						<?php echo esc_html( $message ); ?>
					</p>
						<?php if ( $error_type === 'app_password_failed' ) : ?>
						<p style="margin: var(--alvobot-space-md) 0 0 0; color: var(--alvobot-gray-600); font-size: var(--alvobot-font-size-xs);">
						<strong>Tipo:</strong> Falha na criação de Application Password<br>
						<strong>Causa comum:</strong> Plugin de segurança bloqueando
						</p>
					<?php elseif ( $error_type === 'registration_failed' ) : ?>
						<p style="margin: var(--alvobot-space-md) 0 0 0; color: var(--alvobot-gray-600); font-size: var(--alvobot-font-size-xs);">
						<strong>Tipo:</strong> Falha no registro com servidor AlvoBot<br>
						<?php if ( $http_status == 404 ) : ?>
							<strong>Causa:</strong> Domínio não cadastrado no painel AlvoBot<br>
							<strong>Solução:</strong> Cadastre <code><?php echo esc_html( get_site_url() ); ?></code> em <a href="https://app.alvobot.com" target="_blank">app.alvobot.com</a>
						<?php endif; ?>
						</p>
					<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php else : ?>
				<span class="alvobot-badge alvobot-badge-warning">
					<span class="alvobot-status-indicator warning"></span>
					<?php esc_html_e( 'Aguardando Inicialização', 'alvobot-pro' ); ?>
				</span>
				<?php endif; ?>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php esc_html_e( 'URL do Site', 'alvobot-pro' ); ?></th>
			<td>
				<code><?php echo esc_html( get_site_url() ); ?></code>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php esc_html_e( 'Versão do WordPress', 'alvobot-pro' ); ?></th>
			<td>
				<code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code>
			</td>
			</tr>
		</table>
		</div>
	  
		<div class="alvobot-card-footer">
		<?php if ( ! $alvobot_user || ! $site_token ) : ?>
			<form method="post" action="">
			<?php wp_nonce_field( 'activate_plugin_manager' ); ?>
			<input type="hidden" name="action" value="activate_plugin_manager">
			<div class="alvobot-btn-group">
				<input type="submit" name="submit" class="alvobot-btn alvobot-btn-primary" value="<?php esc_attr_e( 'Inicializar Sistema', 'alvobot-pro' ); ?>">
			</div>
			</form>
		<?php else : ?>
			<form method="post" action="" id="retry-registration-form">
			<?php wp_nonce_field( 'alvobot_retry_registration', 'alvobot_retry_registration_nonce' ); ?>
			<input type="hidden" name="action" value="retry_registration">
			<div class="alvobot-btn-group">
				<input type="submit" name="submit" class="alvobot-btn alvobot-btn-secondary retry-registration-btn" value="<?php esc_attr_e( 'Refazer Registro', 'alvobot-pro' ); ?>">
			</div>
			</form>
		<?php endif; ?>
		</div>
	</div>


	<!-- Seção Debug dos Módulos -->
	<div class="alvobot-card alvobot-collapsible-card">
		<div class="alvobot-card-header alvobot-collapsible-header">
		<div>
			<h2 class="alvobot-card-title">Debug dos Módulos</h2>
			<p class="alvobot-card-subtitle">Configure o debug individual de cada módulo (logs salvos em debug.log)</p>
		</div>
		<button type="button" class="alvobot-collapse-toggle" aria-expanded="false" aria-controls="debug-modules-content" title="<?php esc_attr_e( 'Alternar visibilidade', 'alvobot-pro' ); ?>">
			<i data-lucide="chevron-down" class="alvobot-icon"></i>
		</button>
		</div>
	  
		<div class="alvobot-card-content collapsed" id="debug-modules-content">
		<form method="post" action="">
			<?php wp_nonce_field( 'alvobot_debug_settings' ); ?>
			<input type="hidden" name="action" value="save_debug_settings">

			<div class="alvobot-btn-group" style="margin-bottom: var(--alvobot-space-lg);">
				<button type="button" id="debug-enable-all" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline"><?php esc_html_e( 'Ativar Todos', 'alvobot-pro' ); ?></button>
				<button type="button" id="debug-disable-all" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline"><?php esc_html_e( 'Desativar Todos', 'alvobot-pro' ); ?></button>
			</div>

			<table class="alvobot-form-table" role="presentation">
			<?php
			$debug_settings = get_option( 'alvobot_pro_debug_modules', array() );
			foreach ( $module_names as $module_id => $module_name ) :
				// Sempre mostrar updater, plugin-manager e auth, outros só se ativos
				if ( $module_id === 'updater' || $module_id === 'plugin-manager' || $module_id === 'auth' || ( isset( $active_modules[ $module_id ] ) && $active_modules[ $module_id ] ) ) :
					?>
			<tr>
				<th scope="row"><?php echo esc_html( $module_name ); ?></th>
				<td>
				<label class="alvobot-toggle">
					<input type="checkbox" 
						name="debug_modules[<?php echo esc_attr( $module_id ); ?>]" 
						value="1"
						<?php echo isset( $debug_settings[ $module_id ] ) && $debug_settings[ $module_id ] ? 'checked="checked"' : ''; ?>>
					<span class="alvobot-toggle-slider"></span>
				</label>
				<span class="alvobot-debug-status">
					<?php echo esc_html( isset( $debug_settings[ $module_id ] ) && $debug_settings[ $module_id ] ? 'Ativo' : 'Inativo' ); ?>
				</span>
				</td>
			</tr>
					<?php
				endif;
			endforeach;
			?>
			</table>
		  
			<div class="alvobot-card-footer">
			<div class="alvobot-btn-group">
				<input type="submit" name="submit" class="alvobot-btn alvobot-btn-primary" value="<?php esc_attr_e( 'Salvar Configurações de Debug', 'alvobot-pro' ); ?>">
			</div>
			</div>
		</form>
		
		<div class="alvobot-debug-info">
			<p><strong>Nota:</strong> O arquivo de log está localizado em: <code><?php echo esc_html( WP_CONTENT_DIR . '/debug.log' ); ?></code></p>
			<p>Para visualizar os logs, certifique-se de que WP_DEBUG e WP_DEBUG_LOG estejam habilitados no wp-config.php.</p>
		</div>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Collapsible card for Debug dos Módulos
		var $debugCard = $('.alvobot-collapsible-card').has('#debug-modules-content');
		var $debugCardHeader = $debugCard.find('.alvobot-collapsible-header');
		
		if ($debugCardHeader.length) {
			$debugCardHeader.on('click', function(e) {
				var $clickedElement = $(e.target);
				// Only toggle if the click is on the header itself or the toggle button/its icon,
				// not on other interactive elements within the header.
				if ($clickedElement.is('.alvobot-collapsible-header, .alvobot-card-title, .alvobot-card-subtitle, .alvobot-collapse-toggle, .alvobot-icon')) {
					// Proceed to toggle if the click is on a non-interactive part of the header or the toggle button itself.
				} else if ($clickedElement.closest('.alvobot-collapse-toggle').length) {
					// Proceed if click is within the toggle button (e.g. on the span/icon)
				} else {
					return; // Click was on another interactive element, do not toggle
				}

				var $header = $(this);
				var $toggleButton = $header.find('.alvobot-collapse-toggle');
				var $content = $('#' + $toggleButton.attr('aria-controls'));

				if (!$content.length) return;

				var isExpanded = $toggleButton.attr('aria-expanded') === 'true';

				$toggleButton.attr('aria-expanded', !isExpanded);
				
				if (isExpanded) {
					$content.slideUp(200, function() { $(this).addClass('collapsed'); });
				} else {
					$content.removeClass('collapsed').slideDown(200);
				}
			});
		}

		// Botões Ativar/Desativar Todos no Debug dos Módulos
		$('#debug-enable-all').on('click', function() {
			$('#debug-modules-content .alvobot-form-table input[type="checkbox"]').prop('checked', true);
			$('#debug-modules-content .alvobot-debug-status').text('Ativo');
		});
		$('#debug-disable-all').on('click', function() {
			$('#debug-modules-content .alvobot-form-table input[type="checkbox"]').prop('checked', false);
			$('#debug-modules-content .alvobot-debug-status').text('Inativo');
		});

		// Original jQuery ready content starts below:
		// Verifica se há algum erro na URL
		const urlParams = new URLSearchParams(window.location.search);
		const error = urlParams.get('error');
		if (error) {
			showNotice(decodeURIComponent(error), 'error');
		}

		// Verifica se há alguma mensagem de sucesso na URL
		const success = urlParams.get('success');
		if (success) {
			showNotice(decodeURIComponent(success));
		}
	});
	</script>
