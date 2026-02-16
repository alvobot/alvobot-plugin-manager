<?php
/**
 * Pixel Tracking - Conversoes Tab
 *
 * Conversion rules CRUD management.
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="alvobot-card">
	<div class="alvobot-card-header" style="display: flex; align-items: center; justify-content: space-between;">
		<div>
			<h2 class="alvobot-card-title">
				<i data-lucide="target" class="alvobot-icon"></i>
				<?php esc_html_e( 'Regras de Conversao', 'alvobot-pro' ); ?>
			</h2>
			<p class="alvobot-card-subtitle"><?php esc_html_e( 'Configure eventos personalizados baseados em acoes dos visitantes.', 'alvobot-pro' ); ?></p>
		</div>
		<button type="button" id="alvobot-new-conversion-btn" class="alvobot-btn alvobot-btn-primary">
			<i data-lucide="plus" class="alvobot-icon"></i>
			<?php esc_html_e( 'Nova Conversao', 'alvobot-pro' ); ?>
		</button>
	</div>

	<div class="alvobot-card-content">
		<!-- Conversion Form (hidden by default) -->
		<div id="alvobot-conversion-form" class="alvobot-conversion-form" style="display: none;">
			<h3 id="alvobot-conversion-form-title" class="alvobot-conversion-form-title">
				<?php esc_html_e( 'Nova Conversao', 'alvobot-pro' ); ?>
			</h3>
			<input type="hidden" id="conv_id" value="0">

			<div class="alvobot-form-field">
				<label for="conv_name" class="alvobot-form-label"><?php esc_html_e( 'Nome', 'alvobot-pro' ); ?></label>
				<input type="text" id="conv_name" class="alvobot-input" placeholder="<?php esc_attr_e( 'Ex: Lead Form Enviado', 'alvobot-pro' ); ?>">
			</div>

			<div class="alvobot-conversion-form-grid">
				<div class="alvobot-form-field">
					<label for="conv_event_type" class="alvobot-form-label"><?php esc_html_e( 'Tipo de Evento', 'alvobot-pro' ); ?></label>
					<select id="conv_event_type" class="alvobot-input">
						<option value="PageView">PageView</option>
						<option value="ViewContent">ViewContent</option>
						<option value="Lead">Lead</option>
						<option value="CompleteRegistration">CompleteRegistration</option>
						<option value="AddToCart">AddToCart</option>
						<option value="AddPaymentInfo">AddPaymentInfo</option>
						<option value="Purchase">Purchase</option>
						<option value="InitiateCheckout">InitiateCheckout</option>
						<option value="AddToWishlist">AddToWishlist</option>
						<option value="Search">Search</option>
						<option value="Contact">Contact</option>
						<option value="Schedule">Schedule</option>
						<option value="FindLocation">FindLocation</option>
						<option value="SubmitApplication">SubmitApplication</option>
						<option value="Subscribe">Subscribe</option>
						<option value="StartTrial">StartTrial</option>
						<option value="CustomEvent"><?php esc_html_e( 'Evento Personalizado', 'alvobot-pro' ); ?></option>
					</select>
				</div>

				<div class="alvobot-form-field">
					<label for="conv_trigger_type" class="alvobot-form-label"><?php esc_html_e( 'Gatilho', 'alvobot-pro' ); ?></label>
					<select id="conv_trigger_type" class="alvobot-input">
						<option value="page_load"><?php esc_html_e( 'Carregamento da Pagina', 'alvobot-pro' ); ?></option>
						<option value="page_time"><?php esc_html_e( 'Tempo na Pagina (segundos)', 'alvobot-pro' ); ?></option>
						<option value="form_submit"><?php esc_html_e( 'Envio de Formulario', 'alvobot-pro' ); ?></option>
						<option value="click"><?php esc_html_e( 'Clique em Elemento', 'alvobot-pro' ); ?></option>
						<option value="scroll"><?php esc_html_e( 'Scroll (%)', 'alvobot-pro' ); ?></option>
						<option value="view_element"><?php esc_html_e( 'Visualizacao de Elemento', 'alvobot-pro' ); ?></option>
					</select>
				</div>
			</div>

			<div id="conv-custom-name-field" class="alvobot-form-field alvobot-conditional-field">
				<label for="conv_event_custom_name" class="alvobot-form-label"><?php esc_html_e( 'Nome do Evento Personalizado', 'alvobot-pro' ); ?></label>
				<input type="text" id="conv_event_custom_name" class="alvobot-input" placeholder="MeuEvento">
			</div>

			<div id="conv-trigger-value-field" class="alvobot-form-field alvobot-conditional-field">
				<label for="conv_trigger_value" class="alvobot-form-label"><?php esc_html_e( 'Valor do Gatilho', 'alvobot-pro' ); ?></label>
				<input type="number" id="conv_trigger_value" class="alvobot-input" placeholder="30" min="1">
				<p class="alvobot-description"><?php esc_html_e( 'Segundos (tempo) ou porcentagem (scroll).', 'alvobot-pro' ); ?></p>
			</div>

			<div id="conv-selector-field" class="alvobot-form-field alvobot-conditional-field">
				<label for="conv_css_selector" class="alvobot-form-label"><?php esc_html_e( 'Seletor CSS', 'alvobot-pro' ); ?></label>
				<input type="text" id="conv_css_selector" class="alvobot-input" placeholder="form.contact-form, .btn-cta, #meu-elemento">
				<p class="alvobot-description"><?php esc_html_e( 'Seletor CSS do elemento alvo.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="alvobot-conversion-form-grid">
				<div class="alvobot-form-field">
					<label for="conv_display_on" class="alvobot-form-label"><?php esc_html_e( 'Exibir em', 'alvobot-pro' ); ?></label>
					<select id="conv_display_on" class="alvobot-input">
						<option value="all"><?php esc_html_e( 'Todas as paginas', 'alvobot-pro' ); ?></option>
						<option value="path"><?php esc_html_e( 'Paginas especificas (caminho)', 'alvobot-pro' ); ?></option>
					</select>
				</div>

				<div class="alvobot-form-field">
					<label for="conv_content_name" class="alvobot-form-label"><?php esc_html_e( 'Content Name (opcional)', 'alvobot-pro' ); ?></label>
					<input type="text" id="conv_content_name" class="alvobot-input" placeholder="<?php esc_attr_e( 'Formulario de Contato', 'alvobot-pro' ); ?>">
					<p class="alvobot-description"><?php esc_html_e( 'Identificador enviado como content_name no evento.', 'alvobot-pro' ); ?></p>
				</div>
			</div>

			<div id="conv-page-paths-field" class="alvobot-form-field alvobot-conditional-field">
				<label for="conv_page_paths" class="alvobot-form-label"><?php esc_html_e( 'Caminhos das Paginas', 'alvobot-pro' ); ?></label>
				<input type="text" id="conv_page_paths" class="alvobot-input alvobot-input-lg" placeholder="/contato, /obrigado, /checkout">
				<p class="alvobot-description"><?php esc_html_e( 'Caminhos separados por virgula.', 'alvobot-pro' ); ?></p>
			</div>

			<div class="alvobot-conversion-form-actions">
				<button type="button" id="alvobot-save-conversion-btn" class="alvobot-btn alvobot-btn-primary">
					<i data-lucide="save" class="alvobot-icon"></i>
					<?php esc_html_e( 'Salvar', 'alvobot-pro' ); ?>
				</button>
				<button type="button" id="alvobot-cancel-conversion-btn" class="alvobot-btn alvobot-btn-outline">
					<?php esc_html_e( 'Cancelar', 'alvobot-pro' ); ?>
				</button>
			</div>
		</div>

		<!-- Conversions Table -->
		<table class="alvobot-conversions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'alvobot-pro' ); ?></th>
					<th><?php esc_html_e( 'Evento', 'alvobot-pro' ); ?></th>
					<th><?php esc_html_e( 'Gatilho', 'alvobot-pro' ); ?></th>
					<th><?php esc_html_e( 'Ativo', 'alvobot-pro' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="alvobot-conversions-tbody">
				<tr>
					<td colspan="5" class="alvobot-empty-state">
						<span class="spinner is-active" style="float:none;"></span>
						<p><?php esc_html_e( 'Carregando conversoes...', 'alvobot-pro' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
