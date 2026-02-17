<?php
/**
 * Pixel Tracking - Events Tab
 *
 * Full event log with filters, details modal, resend & debug capabilities.
 *
 * @package AlvoBotPro
 * @subpackage Modules/PixelTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Filters Bar -->
<div class="alvobot-card">
	<div class="alvobot-card-header">
		<h2 class="alvobot-card-title">
			<i data-lucide="filter" class="alvobot-icon"></i>
			<?php esc_html_e( 'Filtros', 'alvobot-pro' ); ?>
		</h2>
	</div>
	<div class="alvobot-card-content">
		<div class="alvobot-events-filters">
			<div class="alvobot-filter-group">
				<label for="filter-status"><?php esc_html_e( 'Status', 'alvobot-pro' ); ?></label>
				<select id="filter-status" class="alvobot-select">
					<option value=""><?php esc_html_e( 'Todos', 'alvobot-pro' ); ?></option>
					<option value="pixel_pending"><?php esc_html_e( 'Pendente', 'alvobot-pro' ); ?></option>
					<option value="pixel_sent"><?php esc_html_e( 'Enviado', 'alvobot-pro' ); ?></option>
					<option value="pixel_error"><?php esc_html_e( 'Erro', 'alvobot-pro' ); ?></option>
				</select>
			</div>
			<div class="alvobot-filter-group">
				<label for="filter-event-name"><?php esc_html_e( 'Evento', 'alvobot-pro' ); ?></label>
				<select id="filter-event-name" class="alvobot-select">
					<option value=""><?php esc_html_e( 'Todos', 'alvobot-pro' ); ?></option>
					<option value="PageView">PageView</option>
					<option value="Lead">Lead</option>
					<option value="ViewContent">ViewContent</option>
					<option value="Purchase">Purchase</option>
					<option value="AddToCart">AddToCart</option>
					<option value="InitiateCheckout">InitiateCheckout</option>
					<option value="CompleteRegistration">CompleteRegistration</option>
					<option value="Contact">Contact</option>
					<option value="Schedule">Schedule</option>
				</select>
			</div>
			<div class="alvobot-filter-group alvobot-filter-actions">
				<button type="button" class="alvobot-btn alvobot-btn-outline alvobot-btn-sm" id="events-filter-btn">
					<i data-lucide="search" class="alvobot-icon"></i>
					<?php esc_html_e( 'Filtrar', 'alvobot-pro' ); ?>
				</button>
				<button type="button" class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" id="events-clear-filters-btn">
					<?php esc_html_e( 'Limpar', 'alvobot-pro' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Stats Summary -->
<div class="alvobot-events-stats-bar">
	<span class="alvobot-events-stat" id="events-stat-total">
		<strong>-</strong> <?php esc_html_e( 'total', 'alvobot-pro' ); ?>
	</span>
	<span class="alvobot-events-stat alvobot-events-stat-pending">
		<span class="alvobot-dot alvobot-dot-warning"></span>
		<strong id="events-stat-pending">-</strong> <?php esc_html_e( 'pendentes', 'alvobot-pro' ); ?>
	</span>
	<span class="alvobot-events-stat alvobot-events-stat-sent">
		<span class="alvobot-dot alvobot-dot-success"></span>
		<strong id="events-stat-sent">-</strong> <?php esc_html_e( 'enviados', 'alvobot-pro' ); ?>
	</span>
	<span class="alvobot-events-stat alvobot-events-stat-error">
		<span class="alvobot-dot alvobot-dot-error"></span>
		<strong id="events-stat-error">-</strong> <?php esc_html_e( 'erros', 'alvobot-pro' ); ?>
	</span>
</div>

<!-- Events Table -->
<div class="alvobot-card">
	<div class="alvobot-card-content alvobot-card-content-table">
		<div class="alvobot-events-table-wrap">
			<table class="alvobot-events-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Evento', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Pagina', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'IP / Localizacao', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Identificadores', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Pixels', 'alvobot-pro' ); ?></th>
						<th><?php esc_html_e( 'Data', 'alvobot-pro' ); ?></th>
						<th class="alvobot-events-col-actions"><?php esc_html_e( 'Acoes', 'alvobot-pro' ); ?></th>
					</tr>
				</thead>
				<tbody id="alvobot-events-tbody">
					<tr>
						<td colspan="8" class="alvobot-events-loading">
							<span class="alvobot-skeleton" style="width: 120px;"></span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Pagination -->
<div class="alvobot-events-pagination" id="alvobot-events-pagination" style="display:none;">
	<div class="alvobot-events-pagination-info" id="events-pagination-info"></div>
	<div class="alvobot-events-pagination-btns">
		<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" id="events-prev-btn" disabled>
			<i data-lucide="chevron-left" class="alvobot-icon"></i>
			<?php esc_html_e( 'Anterior', 'alvobot-pro' ); ?>
		</button>
		<button type="button" class="alvobot-btn alvobot-btn-sm alvobot-btn-outline" id="events-next-btn" disabled>
			<?php esc_html_e( 'Proximo', 'alvobot-pro' ); ?>
			<i data-lucide="chevron-right" class="alvobot-icon"></i>
		</button>
	</div>
</div>

<!-- Event Detail Modal -->
<div id="alvobot-event-modal" class="alvobot-modal" style="display:none;">
	<div class="alvobot-modal-backdrop"></div>
	<div class="alvobot-modal-content">
		<div class="alvobot-modal-header">
			<h3 id="alvobot-modal-title"><?php esc_html_e( 'Detalhes do Evento', 'alvobot-pro' ); ?></h3>
			<button type="button" class="alvobot-modal-close" id="alvobot-modal-close">
				<i data-lucide="x" class="alvobot-icon"></i>
			</button>
		</div>
		<div class="alvobot-modal-body" id="alvobot-modal-body">
			<div class="alvobot-events-loading">
				<span class="alvobot-skeleton" style="width: 200px;"></span>
			</div>
		</div>
	</div>
</div>
