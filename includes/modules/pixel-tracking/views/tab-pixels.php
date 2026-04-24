<?php
/**
 * Pixel Tracking - Pixels Tab
 *
 * Unified pixel/tracker configuration with platform selection and official icons.
 * Follows Nielsen's Usability Heuristics: visibility, consistency, error prevention, recognition.
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
$alvobot_pt_google_trackers = isset( $settings['google_trackers'] ) && is_array( $settings['google_trackers'] ) ? $settings['google_trackers'] : array();
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

<!-- Platform SVG Icons (hidden, referenced by JS) -->
<div id="alvobot-platform-icons" style="display:none;">
	<svg id="icon-meta" viewBox="0 0 24 24" width="20" height="20"><path fill="#1877F2" d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>
	<svg id="icon-ga4" viewBox="0 0 24 24" width="20" height="20"><path fill="#E37400" d="M22.84 2.02v7.97a2.14 2.14 0 0 1-4.28 0V4.3H12.9a2.14 2.14 0 0 1 0-4.28h7.8c1.18 0 2.14.9 2.14 2Z"/><path fill="#F9AB00" d="M5.44 22c-1.9 0-3.44-1.5-3.44-3.36s1.54-3.36 3.44-3.36 3.44 1.5 3.44 3.36S7.34 22 5.44 22Z"/><path fill="#E37400" d="M12 22a2.14 2.14 0 0 1-2.14-2.14V9.73a2.14 2.14 0 0 1 4.28 0v10.13c0 1.18-.96 2.14-2.14 2.14Z"/></svg>
	<svg id="icon-gads" viewBox="0 0 24 24" width="20" height="20"><circle cx="6" cy="18" r="4" fill="#FBBC04"/><path fill="#4285F4" d="M21.2 7.2 12.6 22H7.4L16 7.2h5.2Z"/><path fill="#34A853" d="M16 7.2 7.4 22H2.8l8.6-14.8H16Z"/></svg>
</div>

<div class="alvobot-card">
	<form method="post" action="" class="alvobot-module-form">
		<?php wp_nonce_field( $this->module_id . '_settings' ); ?>
		<input type="hidden" name="active_tab" value="pixels">

		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="radio" class="alvobot-icon"></i>
				<?php esc_html_e( 'Pixels & Trackers', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Gerencie todos os seus pixels e trackers de rastreamento em um unico lugar.', 'alvobot-pro' ); ?></p>
		</div>

		<div class="alvobot-card-content">

			<!-- ══ Add New Pixel/Tracker ══ -->
			<div class="alvobot-add-pixel-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 24px; background: #f8fafc;">

				<!-- Step 1: Platform Selection -->
				<div class="alvobot-form-field">
					<label for="add_pixel_platform" class="alvobot-form-label"><?php esc_html_e( 'Adicionar Pixel / Tracker', 'alvobot-pro' ); ?></label>
					<select id="add_pixel_platform" class="alvobot-input" style="max-width: 320px;">
						<option value=""><?php esc_html_e( '-- Selecione a plataforma --', 'alvobot-pro' ); ?></option>
						<option value="meta_pixel"><?php esc_html_e( 'Meta Pixel (Facebook)', 'alvobot-pro' ); ?></option>
						<option value="ga4"><?php esc_html_e( 'Google Analytics (GA4)', 'alvobot-pro' ); ?></option>
						<option value="google_ads"><?php esc_html_e( 'Google Ads', 'alvobot-pro' ); ?></option>
						<option value="google_sitekit"><?php esc_html_e( 'Google (Tag existente — Site Kit / GTM)', 'alvobot-pro' ); ?></option>
					</select>
				</div>

				<!-- Step 2: Source (AlvoBot or Manual) — shown for all platforms -->
				<div id="add-pixel-source" class="alvobot-form-field" style="display:none;">
					<label class="alvobot-form-label"><?php esc_html_e( 'Origem', 'alvobot-pro' ); ?></label>
					<div class="alvobot-radio-group">
						<label class="alvobot-radio-label">
							<input type="radio" name="add_pixel_source" value="alvobot" checked>
							<span class="alvobot-radio-text">
								<strong><?php esc_html_e( 'AlvoBot', 'alvobot-pro' ); ?></strong>
								<small><?php esc_html_e( 'Buscar das conexoes do AlvoBot App', 'alvobot-pro' ); ?></small>
							</span>
						</label>
						<label class="alvobot-radio-label">
							<input type="radio" name="add_pixel_source" value="manual">
							<span class="alvobot-radio-text">
								<strong><?php esc_html_e( 'Manual', 'alvobot-pro' ); ?></strong>
								<small><?php esc_html_e( 'Inserir ID e token manualmente', 'alvobot-pro' ); ?></small>
							</span>
						</label>
					</div>
				</div>

				<!-- AlvoBot Fetch Section (Meta) -->
				<div id="add-pixel-alvobot-fetch" style="display:none;">
					<div class="alvobot-mode-header" style="margin-bottom: 8px;">
						<i data-lucide="cloud-download" class="alvobot-icon"></i>
						<span><?php esc_html_e( 'Pixels Conectados no AlvoBot', 'alvobot-pro' ); ?></span>
						<button type="button" id="alvobot-fetch-pixels-btn" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" style="margin-left:auto;">
							<i data-lucide="refresh-cw" class="alvobot-icon"></i>
							<?php esc_html_e( 'Buscar Pixels', 'alvobot-pro' ); ?>
						</button>
					</div>
					<div id="alvobot-pixel-selector">
						<div id="alvobot-pixel-list-loading" style="display:none;">
							<span class="spinner is-active" style="float:none;"></span>
							<?php esc_html_e( 'Buscando pixels...', 'alvobot-pro' ); ?>
						</div>
						<div id="alvobot-pixel-list-error" style="display:none;" class="alvobot-notice alvobot-notice-error"></div>
						<div id="alvobot-pixel-list" style="display:none;"></div>
					</div>
				</div>

				<!-- AlvoBot Fetch Section (Google) -->
				<div id="add-google-alvobot-fetch" style="display:none;">
					<div class="alvobot-mode-header" style="margin-bottom: 8px;">
						<i data-lucide="cloud-download" class="alvobot-icon"></i>
						<span><?php esc_html_e( 'Google Trackers Conectados no AlvoBot', 'alvobot-pro' ); ?></span>
						<button type="button" id="alvobot-fetch-google-btn" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" style="margin-left:auto;">
							<i data-lucide="refresh-cw" class="alvobot-icon"></i>
							<?php esc_html_e( 'Buscar Trackers', 'alvobot-pro' ); ?>
						</button>
					</div>
					<div id="alvobot-google-selector">
						<div id="alvobot-google-list-loading" style="display:none;">
							<span class="spinner is-active" style="float:none;"></span>
							<?php esc_html_e( 'Buscando Google trackers...', 'alvobot-pro' ); ?>
						</div>
						<div id="alvobot-google-list-error" style="display:none;" class="alvobot-notice alvobot-notice-error"></div>
						<div id="alvobot-google-list" style="display:none;"></div>
						<!-- ConversionActions picker (shown after selecting a Google Ads tracker) -->
						<div id="alvobot-gads-conversion-actions" style="display:none; margin-top:12px; padding:12px; border:1px solid #e2e8f0; border-radius:6px; background:#f8fafc;">
							<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
								<i data-lucide="target" class="alvobot-icon"></i>
								<strong><?php esc_html_e( 'Selecione a Conversao', 'alvobot-pro' ); ?></strong>
							</div>
							<div id="alvobot-gads-ca-loading" style="display:none;">
								<span class="spinner is-active" style="float:none;"></span>
								<?php esc_html_e( 'Buscando conversoes...', 'alvobot-pro' ); ?>
							</div>
							<div id="alvobot-gads-ca-error" style="display:none;" class="alvobot-notice alvobot-notice-error"></div>
							<div id="alvobot-gads-ca-list"></div>
						</div>
					</div>
				</div>

				<!-- Manual Entry Fields (dynamic per platform) -->
				<div id="add-pixel-manual-fields" style="display:none;">
					<div class="alvobot-manual-fields">
						<!-- Meta Pixel fields -->
						<div id="add-field-meta-id" class="alvobot-form-field" style="display:none;">
							<label for="manual_pixel_id" class="alvobot-form-label"><?php esc_html_e( 'Pixel ID', 'alvobot-pro' ); ?></label>
							<input type="text" id="manual_pixel_id" class="alvobot-input" placeholder="1234567890123456" pattern="^[0-9]{15,16}$">
							<p class="alvobot-description"><?php esc_html_e( 'ID numerico de 15-16 digitos do Meta Pixel.', 'alvobot-pro' ); ?></p>
						</div>
						<div id="add-field-meta-token" class="alvobot-form-field" style="display:none;">
							<label for="manual_api_token" class="alvobot-form-label"><?php esc_html_e( 'Conversion API Token', 'alvobot-pro' ); ?></label>
							<input type="password" id="manual_api_token" class="alvobot-input" placeholder="EAABsbCS...">
							<p class="alvobot-description"><?php esc_html_e( 'Token da Conversion API (Events Manager > Settings).', 'alvobot-pro' ); ?></p>
						</div>

						<!-- Google GA4 fields -->
						<div id="add-field-ga4-id" class="alvobot-form-field" style="display:none;">
							<label for="manual_ga4_id" class="alvobot-form-label"><?php esc_html_e( 'Measurement ID', 'alvobot-pro' ); ?></label>
							<input type="text" id="manual_ga4_id" class="alvobot-input" placeholder="G-XXXXXXXXXX">
							<p class="alvobot-description"><?php esc_html_e( 'Encontre em Google Analytics > Admin > Data Streams.', 'alvobot-pro' ); ?></p>
						</div>

						<!-- Google Ads fields -->
						<div id="add-field-gads-id" class="alvobot-form-field" style="display:none;">
								<label for="manual_gads_id" class="alvobot-form-label"><?php esc_html_e( 'Google Tag / Conversion ID', 'alvobot-pro' ); ?></label>
								<input type="text" id="manual_gads_id" class="alvobot-input" placeholder="AW-XXXXXXXXX">
								<p class="alvobot-description"><?php esc_html_e( 'Use o AW- do snippet da tag do Google, nao o numero da conta.', 'alvobot-pro' ); ?></p>
						</div>
						<div id="add-field-gads-label" class="alvobot-form-field" style="display:none;">
							<label for="manual_gads_conv_label" class="alvobot-form-label"><?php esc_html_e( 'Conversion Label', 'alvobot-pro' ); ?></label>
							<input type="text" id="manual_gads_conv_label" class="alvobot-input" placeholder="AbCdEfGh123">
							<p class="alvobot-description"><?php esc_html_e( 'Label padrao para conversoes deste tracker.', 'alvobot-pro' ); ?></p>
						</div>

						<!-- Common: Name -->
						<div id="add-field-label" class="alvobot-form-field" style="display:none;">
							<label for="manual_pixel_label" class="alvobot-form-label"><?php esc_html_e( 'Nome (opcional)', 'alvobot-pro' ); ?></label>
							<input type="text" id="manual_pixel_label" class="alvobot-input" placeholder="<?php esc_attr_e( 'Meu Pixel', 'alvobot-pro' ); ?>">
						</div>

						<button type="button" id="alvobot-add-pixel-btn" class="alvobot-btn alvobot-btn-primary" style="display:none;">
							<i data-lucide="plus" class="alvobot-icon"></i>
							<?php esc_html_e( 'Adicionar', 'alvobot-pro' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- ══ Configured Pixels & Trackers Table ══ -->
			<div class="alvobot-form-field">
				<label class="alvobot-form-label"><?php esc_html_e( 'Pixels & Trackers Configurados', 'alvobot-pro' ); ?></label>
				<div id="alvobot-configured-all-pixels">
					<!-- Rendered dynamically by JS from both pixels_json + google_trackers_json -->
				</div>
				<input type="hidden" name="pixels_json" id="pixels_json" value="<?php echo esc_attr( wp_json_encode( $alvobot_pt_pixels ) ); ?>">
				<input type="hidden" name="google_trackers_json" id="google_trackers_json" value="<?php echo esc_attr( wp_json_encode( $alvobot_pt_google_trackers ) ); ?>">
				<input type="hidden" name="mode" id="pixel_mode" value="<?php echo esc_attr( $alvobot_pt_mode ); ?>">
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
