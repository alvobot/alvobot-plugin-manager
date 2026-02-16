<?php
/**
 * Pixel Tracking - Status Tab
 *
 * Dashboard with stats, system info, and quick actions.
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$alvobot_pt_pixels         = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
$alvobot_pt_mode           = isset( $settings['mode'] ) ? $settings['mode'] : 'alvobot';
$alvobot_pt_test_mode      = ! empty( $settings['test_mode'] );
$alvobot_pt_consent_check  = ! empty( $settings['consent_check'] );
$alvobot_pt_retention_days = isset( $settings['retention_days'] ) ? $settings['retention_days'] : 7;
?>

<!-- Stats Grid -->
<div class="alvobot-pixel-status-grid">
	<div class="alvobot-stat-card">
		<div class="alvobot-stat-card-icon alvobot-stat-icon-primary">
			<i data-lucide="radio" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-stat-card-value"><?php echo esc_html( count( $alvobot_pt_pixels ) ); ?></div>
		<div class="alvobot-stat-card-label"><?php esc_html_e( 'Pixels Configurados', 'alvobot-pro' ); ?></div>
	</div>

	<div class="alvobot-stat-card">
		<div class="alvobot-stat-card-icon alvobot-stat-icon-warning">
			<i data-lucide="clock" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-stat-card-value" id="stat-pending"><span class="alvobot-skeleton"></span></div>
		<div class="alvobot-stat-card-label"><?php esc_html_e( 'Eventos Pendentes', 'alvobot-pro' ); ?></div>
	</div>

	<div class="alvobot-stat-card">
		<div class="alvobot-stat-card-icon alvobot-stat-icon-success">
			<i data-lucide="check-circle" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-stat-card-value" id="stat-sent"><span class="alvobot-skeleton"></span></div>
		<div class="alvobot-stat-card-label"><?php esc_html_e( 'Eventos Enviados', 'alvobot-pro' ); ?></div>
	</div>

	<div class="alvobot-stat-card">
		<div class="alvobot-stat-card-icon alvobot-stat-icon-info">
			<i data-lucide="users" class="alvobot-icon"></i>
		</div>
		<div class="alvobot-stat-card-value" id="stat-leads"><span class="alvobot-skeleton"></span></div>
		<div class="alvobot-stat-card-label"><?php esc_html_e( 'Leads Capturados', 'alvobot-pro' ); ?></div>
	</div>
</div>

<!-- Info + Actions Row -->
<div class="alvobot-pixel-status-row">
	<!-- System Info -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="info" class="alvobot-icon"></i>
				<?php esc_html_e( 'Informacoes do Sistema', 'alvobot-pro' ); ?>
			</h2>
		</div>
		<div class="alvobot-card-content">
			<ul class="alvobot-info-list">
				<li>
					<span class="alvobot-info-label"><?php esc_html_e( 'Modo', 'alvobot-pro' ); ?></span>
					<span class="alvobot-badge <?php echo 'alvobot' === $alvobot_pt_mode ? 'alvobot-badge-info' : 'alvobot-badge-neutral'; ?>">
						<?php echo 'alvobot' === $alvobot_pt_mode ? 'AlvoBot' : 'Manual'; ?>
					</span>
				</li>
				<li>
					<span class="alvobot-info-label"><?php esc_html_e( 'Modo Teste', 'alvobot-pro' ); ?></span>
					<?php if ( $alvobot_pt_test_mode ) : ?>
						<span class="alvobot-badge alvobot-badge-warning"><?php esc_html_e( 'Ativo', 'alvobot-pro' ); ?></span>
					<?php else : ?>
						<span class="alvobot-badge alvobot-badge-neutral"><?php esc_html_e( 'Inativo', 'alvobot-pro' ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="alvobot-info-label"><?php esc_html_e( 'Consentimento', 'alvobot-pro' ); ?></span>
					<?php if ( $alvobot_pt_consent_check ) : ?>
						<span class="alvobot-badge alvobot-badge-success"><?php esc_html_e( 'Ativo', 'alvobot-pro' ); ?></span>
					<?php else : ?>
						<span class="alvobot-badge alvobot-badge-neutral"><?php esc_html_e( 'Desativado', 'alvobot-pro' ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="alvobot-info-label"><?php esc_html_e( 'Retencao', 'alvobot-pro' ); ?></span>
					<span>
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html__( '%d dias', 'alvobot-pro' ),
							(int) $alvobot_pt_retention_days
						);
						?>
					</span>
				</li>
				<li>
					<span class="alvobot-info-label"><?php esc_html_e( 'Eventos com Erro', 'alvobot-pro' ); ?></span>
					<span id="stat-error"><span class="alvobot-skeleton alvobot-skeleton-sm"></span></span>
				</li>
			</ul>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="zap" class="alvobot-icon"></i>
				<?php esc_html_e( 'Acoes Rapidas', 'alvobot-pro' ); ?>
			</h2>
		</div>
		<div class="alvobot-card-content">
			<div class="alvobot-action-grid">
				<button type="button" class="alvobot-action-btn" data-action="send-pending">
					<i data-lucide="send" class="alvobot-icon"></i>
					<div>
						<strong><?php esc_html_e( 'Enviar Pendentes', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Processar fila agora', 'alvobot-pro' ); ?></small>
					</div>
				</button>

				<button type="button" class="alvobot-action-btn" data-action="test-pixel">
					<i data-lucide="flask-conical" class="alvobot-icon"></i>
					<div>
						<strong><?php esc_html_e( 'Testar Pixel', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Enviar evento de teste', 'alvobot-pro' ); ?></small>
					</div>
				</button>

				<button type="button" class="alvobot-action-btn" data-action="cleanup">
					<i data-lucide="trash-2" class="alvobot-icon"></i>
					<div>
						<strong><?php esc_html_e( 'Limpar Dados', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Remover dados antigos', 'alvobot-pro' ); ?></small>
					</div>
				</button>

				<button type="button" class="alvobot-action-btn" data-action="refresh-pixels">
					<i data-lucide="refresh-cw" class="alvobot-icon"></i>
					<div>
						<strong><?php esc_html_e( 'Atualizar Pixels', 'alvobot-pro' ); ?></strong>
						<small><?php esc_html_e( 'Recarregar do AlvoBot', 'alvobot-pro' ); ?></small>
					</div>
				</button>
			</div>

			<div id="alvobot-action-feedback" style="display:none; margin-top: 12px;"></div>
		</div>
	</div>
</div>
