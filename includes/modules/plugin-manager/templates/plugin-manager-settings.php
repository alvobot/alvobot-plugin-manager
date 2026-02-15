<?php
/**
 * Template de configurações do Plugin Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="alvobot-admin-wrap">
	<div class="alvobot-admin-container">
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="server" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php esc_html_e( 'Status do Sistema', 'alvobot-pro' ); ?></h1>
				<p><?php esc_html_e( 'Monitore o status da conexão e gerencie a integração com a plataforma AlvoBot.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<div class="alvobot-notice-container">
			<div class="alvobot-pro-notices"></div>
			<?php settings_errors(); ?>
		</div>

	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<div>
				<h2 class="alvobot-card-title"><?php esc_html_e( 'Status do Sistema', 'alvobot-pro' ); ?></h2>
				<p class="alvobot-card-subtitle"><?php esc_html_e( 'Verifique o status da conexão com a plataforma AlvoBot', 'alvobot-pro' ); ?></p>
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
								<?php printf( esc_html( _n( '1 senha criada', '%d senhas criadas', $app_password_count, 'alvobot-pro' ) ), intval( $app_password_count ) ); ?>
							</span>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Application Password ativo para autenticação API', 'alvobot-pro' ); ?>
							</p>
						<?php else : ?>
							<span class="alvobot-badge alvobot-badge-error">
								<span class="alvobot-status-indicator error"></span>
								<?php esc_html_e( 'Não Criado', 'alvobot-pro' ); ?>
							</span>
							<p class="description" style="margin-top: 8px; color: #d63638;">
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
								<p class="description" style="margin-top: 8px;">
									<?php echo esc_html( $message ); ?>
								</p>
							<?php else : ?>
								<span class="alvobot-badge alvobot-badge-error">
									<span class="alvobot-status-indicator error"></span>
									<?php esc_html_e( 'Erro de Conexão', 'alvobot-pro' ); ?>
									<?php if ( $http_status ) : ?>
										<code style="margin-left: 5px;">HTTP <?php echo esc_html( $http_status ); ?></code>
									<?php endif; ?>
								</span>
								<div style="margin-top: 8px; padding: 10px; background: #fff8e5; border-left: 3px solid #f0b849; border-radius: 3px;">
									<p style="margin: 0; color: #8a6d3b; font-weight: 500;">
										<?php echo esc_html( $message ); ?>
									</p>
									<?php if ( $error_type === 'app_password_failed' ) : ?>
										<p style="margin: 10px 0 0 0; color: #666; font-size: 12px;">
											<strong>Tipo:</strong> Falha na criação de Application Password<br>
											<strong>Causa comum:</strong> Plugin de segurança bloqueando
										</p>
									<?php elseif ( $error_type === 'registration_failed' ) : ?>
										<p style="margin: 10px 0 0 0; color: #666; font-size: 12px;">
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
						<input type="submit" name="submit" class="alvobot-btn alvobot-btn-primary" value="<?php esc_attr_e( 'Inicializar Plugin Manager', 'alvobot-pro' ); ?>">
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

	<?php if ( $alvobot_user && $site_token ) : ?>
	<?php endif; ?>
	</div>
</div>