<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation display logic, no data modification.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'modules';
$page_slug  = 'alvobot-pro';
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
	</div>

	<nav class="nav-tab-wrapper">
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=modules"
			class="nav-tab <?php echo 'modules' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<i data-lucide="blocks" class="alvobot-icon"></i>
			<?php esc_html_e( 'Módulos', 'alvobot-pro' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=diagnostics"
			class="nav-tab <?php echo 'diagnostics' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<i data-lucide="stethoscope" class="alvobot-icon"></i>
			<?php esc_html_e( 'Diagnóstico', 'alvobot-pro' ); ?>
		</a>
	</nav>

	<div class="alvobot-tab-content">
	<?php
	switch ( $active_tab ) :
		case 'diagnostics':
			?>
			<!-- Seção Diagnóstico (Health Check) -->
			<div class="alvobot-card alvobot-collapsible-card">
				<div class="alvobot-card-header alvobot-collapsible-header">
				<div>
					<h2 class="alvobot-card-title">
						<i data-lucide="stethoscope" class="alvobot-icon" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;"></i>
						Diagnóstico do Sistema
					</h2>
					<p class="alvobot-card-subtitle">Verifique todos os pré-requisitos para o funcionamento correto do AlvoBot</p>
				</div>
				<button type="button" class="alvobot-collapse-toggle" aria-expanded="true" aria-controls="health-check-content" title="<?php esc_attr_e( 'Alternar visibilidade', 'alvobot-pro' ); ?>">
					<i data-lucide="chevron-down" class="alvobot-icon"></i>
				</button>
				</div>

				<div class="alvobot-card-content" id="health-check-content">
				<?php
				$plugin_manager_instance = isset( $this->modules['plugin-manager'] ) ? $this->modules['plugin-manager'] : null;
				if ( ! $plugin_manager_instance ) {
					$plugin_manager_instance = new AlvoBotPro_PluginManager();
				}
				$health_checks = $plugin_manager_instance->run_health_check();

				$total_checks = count( $health_checks );
				$ok_checks    = 0;
				$warn_checks  = 0;
				$err_checks   = 0;
				foreach ( $health_checks as $check ) {
					if ( $check['status'] === 'ok' ) {
						++$ok_checks;
					} elseif ( $check['status'] === 'warning' ) {
						++$warn_checks;
					} else {
						++$err_checks;
					}
				}
				?>

				<div style="display: flex; gap: var(--alvobot-space-md, 12px); margin-bottom: var(--alvobot-space-lg, 20px); flex-wrap: wrap;">
					<div class="alvobot-badge alvobot-badge-success" style="font-size: 13px; padding: 6px 12px;">
						<?php echo esc_html( $ok_checks ); ?> OK
					</div>
					<?php if ( $warn_checks > 0 ) : ?>
					<div class="alvobot-badge alvobot-badge-warning" style="font-size: 13px; padding: 6px 12px;">
						<?php echo esc_html( $warn_checks ); ?> Avisos
					</div>
					<?php endif; ?>
					<?php if ( $err_checks > 0 ) : ?>
					<div class="alvobot-badge alvobot-badge-error" style="font-size: 13px; padding: 6px 12px;">
						<?php echo esc_html( $err_checks ); ?> Erros
					</div>
					<?php endif; ?>
				</div>

				<?php wp_nonce_field( 'alvobot_fix_htaccess', 'alvobot_fix_htaccess_nonce' ); ?>
				<table class="alvobot-form-table" role="presentation">
					<?php foreach ( $health_checks as $check_id => $check ) : ?>
					<tr>
						<th scope="row" style="width: 250px;"><?php echo esc_html( $check['label'] ); ?></th>
						<td>
							<?php
							$badge_class = 'alvobot-badge-success';
							if ( $check['status'] === 'warning' ) {
								$badge_class = 'alvobot-badge-warning';
							} elseif ( $check['status'] === 'error' ) {
								$badge_class = 'alvobot-badge-error';
							}
							?>
							<span class="alvobot-badge <?php echo esc_attr( $badge_class ); ?>">
								<span class="alvobot-status-indicator <?php echo esc_attr( $check['status'] === 'ok' ? 'success' : ( $check['status'] === 'warning' ? 'warning' : 'error' ) ); ?>"></span>
								<?php echo esc_html( $check['value'] ); ?>
							</span>
							<?php if ( ! empty( $check['help'] ) ) : ?>
								<p class="description" style="margin-top: 4px; font-size: 12px; color: <?php echo $check['status'] === 'error' ? '#d63638' : '#8c8f94'; ?>;">
									<?php echo esc_html( $check['help'] ); ?>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $check['fixable'] ) && $check_id === 'auth_header' ) : ?>
								<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-primary alvobot-fix-htaccess-btn" style="margin-top: 8px;">
									<i data-lucide="wrench" class="alvobot-icon" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>
									<?php esc_html_e( 'Corrigir .htaccess', 'alvobot-pro' ); ?>
								</button>
								<span class="alvobot-fix-htaccess-status" style="display:none; margin-left: 8px; font-size: 12px;"></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>
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
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=alvobot-pro&tab=diagnostics' ) ); ?>">
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
			<?php
			break;

		case 'modules':
		default:
			?>
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

			<!-- Status do Sistema -->
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
								<?php
								$site_token_str    = (string) $site_token;
								$token_length      = strlen( $site_token_str );
								$masked_site_token = $token_length <= 4
									? str_repeat( '•', $token_length )
									: str_repeat( '•', $token_length - 4 ) . substr( $site_token_str, -4 );
								?>
								<code class="alvobot-token-value"><?php echo esc_html( $masked_site_token ); ?></code>
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
					<form method="post" action="">
					<?php wp_nonce_field( 'alvobot_retry_registration', 'alvobot_retry_registration_nonce' ); ?>
					<input type="hidden" name="action" value="retry_registration">
					<div class="alvobot-btn-group">
						<input type="submit" name="submit" class="alvobot-btn alvobot-btn-secondary retry-registration-btn" value="<?php esc_attr_e( 'Refazer Registro', 'alvobot-pro' ); ?>">
					</div>
					</form>
				<?php endif; ?>
				</div>
			</div>

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
			<?php
			break;
	endswitch;
	?>
	</div>

	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Generic collapsible card handler (works for Health Check, Debug, etc.)
	$('.alvobot-collapsible-card .alvobot-collapsible-header').on('click', function(e) {
		var $clickedElement = $(e.target);
		if ($clickedElement.is('.alvobot-collapsible-header, .alvobot-card-title, .alvobot-card-subtitle, .alvobot-collapse-toggle, .alvobot-icon, i, h2, p, svg, line, polyline, path, circle')) {
			// Proceed
		} else if ($clickedElement.closest('.alvobot-collapse-toggle').length) {
			// Proceed
		} else {
			return;
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

	// Botões Ativar/Desativar Todos no Debug dos Módulos
	$('#debug-enable-all').on('click', function() {
		$('#debug-modules-content .alvobot-form-table input[type="checkbox"]').prop('checked', true);
		$('#debug-modules-content .alvobot-debug-status').text('Ativo');
	});
	$('#debug-disable-all').on('click', function() {
		$('#debug-modules-content .alvobot-form-table input[type="checkbox"]').prop('checked', false);
		$('#debug-modules-content .alvobot-debug-status').text('Inativo');
	});

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

	// Fix .htaccess Authorization header
	$('.alvobot-fix-htaccess-btn').on('click', function() {
		var $btn = $(this);
		var $status = $btn.siblings('.alvobot-fix-htaccess-status');
		$btn.prop('disabled', true).text('Corrigindo...');
		$status.hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'alvobot_fix_htaccess_auth',
				nonce: $('#alvobot_fix_htaccess_nonce').val()
			},
			success: function(response) {
				if (response.success) {
					$status.css('color', '#00a32a').text(response.data.message).show();
					$btn.replaceWith('<span class="alvobot-badge alvobot-badge-success" style="margin-top: 8px; display: inline-block;"><span class="alvobot-status-indicator success"></span>Corrigido!</span>');
				} else {
					$status.css('color', '#d63638').text(response.data.message).show();
					$btn.prop('disabled', false).html('<i data-lucide="wrench" class="alvobot-icon" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Corrigir .htaccess');
					if (typeof lucide !== 'undefined') lucide.createIcons();
				}
			},
			error: function() {
				$status.css('color', '#d63638').text('Erro de rede. Tente novamente.').show();
				$btn.prop('disabled', false).html('<i data-lucide="wrench" class="alvobot-icon" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Corrigir .htaccess');
				if (typeof lucide !== 'undefined') lucide.createIcons();
			}
		});
	});
});
</script>
