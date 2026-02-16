<?php
/**
 * Pixel Tracking - Configuracoes Tab
 *
 * General settings: test mode, role exclusion, consent, data retention.
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$alvobot_pt_test_mode      = ! empty( $settings['test_mode'] );
$alvobot_pt_test_code      = isset( $settings['test_event_code'] ) ? $settings['test_event_code'] : '';
$alvobot_pt_consent_check  = isset( $settings['consent_check'] ) ? $settings['consent_check'] : true;
$alvobot_pt_consent_cookie = isset( $settings['consent_cookie'] ) ? $settings['consent_cookie'] : 'alvobot_tracking_consent';
$alvobot_pt_excluded_roles = isset( $settings['excluded_roles'] ) ? $settings['excluded_roles'] : array();
$alvobot_pt_retention_days = isset( $settings['retention_days'] ) ? $settings['retention_days'] : 7;
$alvobot_pt_max_events     = isset( $settings['max_events'] ) ? $settings['max_events'] : 50000;
$alvobot_pt_max_leads      = isset( $settings['max_leads'] ) ? $settings['max_leads'] : 10000;
?>

<form method="post" action="" class="alvobot-module-form">
	<?php wp_nonce_field( $this->module_id . '_settings' ); ?>
	<input type="hidden" name="active_tab" value="configuracoes">

	<!-- Test Mode -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="flask-conical" class="alvobot-icon"></i>
				<?php esc_html_e( 'Modo Teste', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Envie eventos de teste para o Facebook Events Manager antes de ativar em producao.', 'alvobot-pro' ); ?></p>
		</div>
		<div class="alvobot-card-content">
			<div class="alvobot-form-field">
				<div class="alvobot-toggle-row">
					<label class="alvobot-toggle">
						<input type="checkbox" name="test_mode" value="1" <?php checked( $alvobot_pt_test_mode ); ?>>
						<span class="alvobot-toggle-slider"></span>
					</label>
					<span class="alvobot-toggle-label"><?php esc_html_e( 'Ativar modo teste', 'alvobot-pro' ); ?></span>
				</div>
				<p class="alvobot-description"><?php esc_html_e( 'Eventos serao enviados com o test_event_code para o Facebook Events Manager.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="alvobot-form-field" id="test-code-field" style="<?php echo ! $alvobot_pt_test_mode ? 'display:none' : ''; ?>">
				<label for="test_event_code" class="alvobot-form-label"><?php esc_html_e( 'Test Event Code', 'alvobot-pro' ); ?></label>
				<input type="text" id="test_event_code" name="test_event_code" class="alvobot-input" value="<?php echo esc_attr( $alvobot_pt_test_code ); ?>" placeholder="TEST12345">
				<p class="alvobot-description"><?php esc_html_e( 'Encontre o codigo em Facebook Events Manager > Test Events.', 'alvobot-pro' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Privacy & Consent -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="shield-check" class="alvobot-icon"></i>
				<?php esc_html_e( 'Privacidade e Consentimento', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Configure o controle de consentimento e a exclusao de usuarios.', 'alvobot-pro' ); ?></p>
		</div>
		<div class="alvobot-card-content">
			<div class="alvobot-form-field">
				<div class="alvobot-toggle-row">
					<label class="alvobot-toggle">
						<input type="checkbox" name="consent_check" value="1" <?php checked( $alvobot_pt_consent_check ); ?>>
						<span class="alvobot-toggle-slider"></span>
					</label>
					<span class="alvobot-toggle-label"><?php esc_html_e( 'Verificar consentimento antes de rastrear', 'alvobot-pro' ); ?></span>
				</div>
				<p class="alvobot-description"><?php esc_html_e( 'Compativel com CookieYes e Complianz automaticamente.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="alvobot-form-field">
				<label for="consent_cookie" class="alvobot-form-label"><?php esc_html_e( 'Nome do Cookie de Consentimento', 'alvobot-pro' ); ?></label>
				<input type="text" id="consent_cookie" name="consent_cookie" class="alvobot-input" value="<?php echo esc_attr( $alvobot_pt_consent_cookie ); ?>">
			</div>

			<hr class="alvobot-divider">

			<div class="alvobot-form-field">
				<label class="alvobot-form-label">
					<i data-lucide="user-x" class="alvobot-icon"></i>
					<?php esc_html_e( 'Excluir Roles do Rastreamento', 'alvobot-pro' ); ?>
				</label>
				<p class="alvobot-description" style="margin-bottom: 8px;"><?php esc_html_e( 'Usuarios com estes roles nao serao rastreados.', 'alvobot-pro' ); ?></p>
				<div class="alvobot-checkbox-grid">
					<?php
					$alvobot_pt_wp_roles = wp_roles();
					foreach ( $alvobot_pt_wp_roles->roles as $alvobot_pt_role_key => $alvobot_pt_role_data ) :
						?>
						<label class="alvobot-checkbox-label">
							<input type="checkbox" name="excluded_roles[]" value="<?php echo esc_attr( $alvobot_pt_role_key ); ?>" <?php checked( in_array( $alvobot_pt_role_key, $alvobot_pt_excluded_roles, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $alvobot_pt_role_data['name'] ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Data Retention -->
	<div class="alvobot-card">
		<div class="alvobot-card-header">
			<h2 class="alvobot-card-title">
				<i data-lucide="database" class="alvobot-icon"></i>
				<?php esc_html_e( 'Retencao de Dados', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Controle o armazenamento de eventos e leads no banco de dados.', 'alvobot-pro' ); ?></p>
		</div>
		<div class="alvobot-card-content">
			<div class="alvobot-retention-grid">
				<div class="alvobot-form-field">
					<label for="retention_days" class="alvobot-form-label"><?php esc_html_e( 'Dias de Retencao', 'alvobot-pro' ); ?></label>
					<input type="number" id="retention_days" name="retention_days" class="alvobot-input alvobot-input-sm" value="<?php echo esc_attr( $alvobot_pt_retention_days ); ?>" min="1" max="365">
					<p class="alvobot-description"><?php esc_html_e( 'Eventos e leads mais antigos serao removidos.', 'alvobot-pro' ); ?></p>
				</div>

				<div class="alvobot-form-field">
					<label for="max_events" class="alvobot-form-label"><?php esc_html_e( 'Maximo de Eventos', 'alvobot-pro' ); ?></label>
					<input type="number" id="max_events" name="max_events" class="alvobot-input alvobot-input-sm" value="<?php echo esc_attr( $alvobot_pt_max_events ); ?>" min="1000" max="500000" step="1000">
				</div>

				<div class="alvobot-form-field">
					<label for="max_leads" class="alvobot-form-label"><?php esc_html_e( 'Maximo de Leads', 'alvobot-pro' ); ?></label>
					<input type="number" id="max_leads" name="max_leads" class="alvobot-input alvobot-input-sm" value="<?php echo esc_attr( $alvobot_pt_max_leads ); ?>" min="1000" max="100000" step="1000">
				</div>
			</div>
		</div>
	</div>

	<!-- Save Button -->
	<div class="alvobot-card-footer alvobot-card-footer-standalone">
		<div class="alvobot-btn-group alvobot-btn-group-right">
			<button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
				<i data-lucide="save" class="alvobot-icon"></i>
				<?php esc_html_e( 'Salvar Configuracoes', 'alvobot-pro' ); ?>
			</button>
		</div>
	</div>
</form>
