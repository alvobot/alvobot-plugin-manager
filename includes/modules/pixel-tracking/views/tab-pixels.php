<?php
/**
 * Pixel Tracking - Pixels Tab
 *
 * Pixel configuration: mode selection, AlvoBot/manual entry, configured pixels table.
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$alvobot_pt_mode      = isset( $settings['mode'] ) ? $settings['mode'] : 'alvobot';
$alvobot_pt_pixels    = isset( $settings['pixels'] ) ? $settings['pixels'] : array();
$alvobot_pt_test_mode = ! empty( $settings['test_mode'] );
$alvobot_pt_test_code = isset( $settings['test_event_code'] ) ? $settings['test_event_code'] : '';
?>

<?php if ( $alvobot_pt_test_mode ) : ?>
<div class="alvobot-notice alvobot-notice-warning" style="margin-bottom: 16px;">
	<p>
		<strong><?php esc_html_e( 'Modo Teste Ativo', 'alvobot-pro' ); ?></strong> &mdash;
		<?php
		printf(
			/* translators: %s: test event code */
			esc_html__( 'Eventos enviados com test_event_code: %s', 'alvobot-pro' ),
			'<code>' . esc_html( $alvobot_pt_test_code ) . '</code>'
		);
		?>
	</p>
</div>
<?php endif; ?>

<div class="alvobot-card">
	<form method="post" action="" class="alvobot-module-form">
		<?php wp_nonce_field( $this->module_id . '_settings' ); ?>
		<input type="hidden" name="active_tab" value="pixels">

		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="radio" class="alvobot-icon"></i>
				<?php esc_html_e( 'Configuracao do Pixel', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Escolha como configurar seus pixels Meta.', 'alvobot-pro' ); ?></p>
		</div>

		<div class="alvobot-card-content">
			<!-- Mode Selection -->
			<div class="alvobot-form-field">
				<label class="alvobot-form-label"><?php esc_html_e( 'Modo de Configuracao', 'alvobot-pro' ); ?></label>
				<div class="alvobot-radio-group">
					<label class="alvobot-radio-label">
						<input type="radio" name="mode" value="alvobot" <?php checked( $alvobot_pt_mode, 'alvobot' ); ?>>
						<span class="alvobot-radio-text">
							<strong><?php esc_html_e( 'AlvoBot', 'alvobot-pro' ); ?></strong>
							<small><?php esc_html_e( 'Busca automaticamente do AlvoBot App', 'alvobot-pro' ); ?></small>
						</span>
					</label>
					<label class="alvobot-radio-label">
						<input type="radio" name="mode" value="manual" <?php checked( $alvobot_pt_mode, 'manual' ); ?>>
						<span class="alvobot-radio-text">
							<strong><?php esc_html_e( 'Manual', 'alvobot-pro' ); ?></strong>
							<small><?php esc_html_e( 'Configure Pixel ID e Token manualmente', 'alvobot-pro' ); ?></small>
						</span>
					</label>
				</div>
			</div>

			<!-- AlvoBot Mode -->
			<div id="alvobot-pixel-mode-alvobot" class="alvobot-pixel-mode-section" style="<?php echo 'alvobot' !== $alvobot_pt_mode ? 'display:none' : ''; ?>">
				<div class="alvobot-mode-header">
					<i data-lucide="cloud-download" class="alvobot-icon"></i>
					<span><?php esc_html_e( 'Pixels Conectados no AlvoBot', 'alvobot-pro' ); ?></span>
					<button type="button" id="alvobot-fetch-pixels-btn" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" style="margin-left:auto;">
						<i data-lucide="refresh-cw" class="alvobot-icon"></i>
						<?php esc_html_e( 'Buscar Pixels', 'alvobot-pro' ); ?>
					</button>
				</div>
				<div class="alvobot-form-field">
					<div id="alvobot-pixel-selector">
						<div id="alvobot-pixel-list-loading" style="display:none;">
							<span class="spinner is-active" style="float:none;"></span>
							<?php esc_html_e( 'Buscando pixels...', 'alvobot-pro' ); ?>
						</div>
						<div id="alvobot-pixel-list-error" style="display:none;" class="alvobot-notice alvobot-notice-error"></div>
						<div id="alvobot-pixel-list" style="display:none;"></div>
					</div>
				</div>
			</div>

			<!-- Manual Mode -->
			<div id="alvobot-pixel-mode-manual" class="alvobot-pixel-mode-section" style="<?php echo 'manual' !== $alvobot_pt_mode ? 'display:none' : ''; ?>">
				<div class="alvobot-mode-header">
					<i data-lucide="pencil" class="alvobot-icon"></i>
					<span><?php esc_html_e( 'Adicionar Pixel Manualmente', 'alvobot-pro' ); ?></span>
				</div>
				<div class="alvobot-manual-fields">
					<div class="alvobot-form-field">
						<label for="manual_pixel_id" class="alvobot-form-label"><?php esc_html_e( 'Pixel ID', 'alvobot-pro' ); ?></label>
						<input type="text" id="manual_pixel_id" class="alvobot-input" placeholder="1234567890123456" pattern="[0-9]{15,16}">
						<p class="alvobot-description"><?php esc_html_e( 'ID numerico de 15-16 digitos do Meta Pixel.', 'alvobot-pro' ); ?></p>
					</div>
					<div class="alvobot-form-field">
						<label for="manual_api_token" class="alvobot-form-label"><?php esc_html_e( 'Conversion API Token', 'alvobot-pro' ); ?></label>
						<input type="password" id="manual_api_token" class="alvobot-input" placeholder="EAABsbCS...">
						<p class="alvobot-description"><?php esc_html_e( 'Token de acesso da Conversion API (encontre em Events Manager > Settings).', 'alvobot-pro' ); ?></p>
					</div>
					<div class="alvobot-form-field">
						<label for="manual_pixel_label" class="alvobot-form-label"><?php esc_html_e( 'Nome (opcional)', 'alvobot-pro' ); ?></label>
						<input type="text" id="manual_pixel_label" class="alvobot-input" placeholder="Meu Pixel">
					</div>
					<button type="button" id="alvobot-add-manual-pixel-btn" class="alvobot-btn alvobot-btn-outline">
						<i data-lucide="plus" class="alvobot-icon"></i>
						<?php esc_html_e( 'Adicionar Pixel', 'alvobot-pro' ); ?>
					</button>
				</div>
			</div>

			<!-- Configured Pixels Table -->
			<div class="alvobot-form-field alvobot-configured-pixels-section">
				<label class="alvobot-form-label"><?php esc_html_e( 'Pixels Configurados', 'alvobot-pro' ); ?></label>
				<div id="alvobot-configured-pixels">
					<?php if ( empty( $alvobot_pt_pixels ) ) : ?>
						<div class="alvobot-empty-state alvobot-empty-state-compact">
							<i data-lucide="radio" class="alvobot-icon" style="width:32px;height:32px;opacity:0.3;"></i>
							<p><?php esc_html_e( 'Nenhum pixel configurado.', 'alvobot-pro' ); ?></p>
							<p class="alvobot-description"><?php esc_html_e( 'Adicione um pixel usando o modo acima.', 'alvobot-pro' ); ?></p>
						</div>
					<?php else : ?>
						<table class="alvobot-table" style="width:100%;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Pixel ID', 'alvobot-pro' ); ?></th>
									<th><?php esc_html_e( 'Nome', 'alvobot-pro' ); ?></th>
									<th><?php esc_html_e( 'Origem', 'alvobot-pro' ); ?></th>
									<th><?php esc_html_e( 'Status', 'alvobot-pro' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $alvobot_pt_pixels as $alvobot_pt_pixel_index => $alvobot_pt_pixel ) : ?>
								<tr>
									<td><code><?php echo esc_html( $alvobot_pt_pixel['pixel_id'] ); ?></code></td>
									<td><?php echo esc_html( isset( $alvobot_pt_pixel['label'] ) && $alvobot_pt_pixel['label'] ? $alvobot_pt_pixel['label'] : '-' ); ?></td>
									<td>
										<?php if ( 'alvobot' === $alvobot_pt_pixel['source'] ) : ?>
											<span class="alvobot-badge alvobot-badge-info"><?php esc_html_e( 'AlvoBot', 'alvobot-pro' ); ?></span>
										<?php else : ?>
											<span class="alvobot-badge alvobot-badge-neutral"><?php esc_html_e( 'Manual', 'alvobot-pro' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $alvobot_pt_pixel['token_expired'] ) ) : ?>
											<span class="alvobot-badge alvobot-badge-error"><?php esc_html_e( 'Token Expirado', 'alvobot-pro' ); ?></span>
										<?php elseif ( ! empty( $alvobot_pt_pixel['api_token'] ) ) : ?>
											<span class="alvobot-badge alvobot-badge-success"><?php esc_html_e( 'CAPI Ativo', 'alvobot-pro' ); ?></span>
										<?php else : ?>
											<span class="alvobot-badge alvobot-badge-warning"><?php esc_html_e( 'Sem Token', 'alvobot-pro' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-danger alvobot-remove-pixel-btn" data-index="<?php echo esc_attr( $alvobot_pt_pixel_index ); ?>">
											<i data-lucide="trash-2" class="alvobot-icon"></i>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<input type="hidden" name="pixels_json" id="pixels_json" value="<?php echo esc_attr( wp_json_encode( $alvobot_pt_pixels ) ); ?>">
			</div>
		</div>

		<div class="alvobot-card-footer">
			<div class="alvobot-btn-group alvobot-btn-group-right">
				<button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
					<i data-lucide="save" class="alvobot-icon"></i>
					<?php esc_html_e( 'Salvar Configuracoes', 'alvobot-pro' ); ?>
				</button>
			</div>
		</div>
	</form>
</div>
