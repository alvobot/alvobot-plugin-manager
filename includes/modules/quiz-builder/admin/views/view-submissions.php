<?php
/**
 * Submissions tab view
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$submissions_handler = new Alvobot_Quiz_Submissions();

// Ensure table exists with correct schema
$submissions_handler->create_table();

// Get filters from URL
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display logic for admin list table filters, no data modification.
$current_quiz_id   = isset( $_GET['quiz_id'] ) ? sanitize_text_field( wp_unslash( $_GET['quiz_id'] ) ) : '';
$current_search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$current_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$current_date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$current_page      = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$per_page          = 20;

$filters = array(
	'quiz_id'   => $current_quiz_id,
	'search'    => $current_search,
	'date_from' => $current_date_from,
	'date_to'   => $current_date_to,
);

$total_items = $submissions_handler->get_total_count( $filters );
$total_pages = ceil( $total_items / $per_page );
$offset      = ( $current_page - 1 ) * $per_page;

$submissions = $submissions_handler->get_submissions( $filters, $per_page, $offset );
$quiz_ids    = $submissions_handler->get_quiz_ids();

// Build export URL with current filters
$export_url = wp_nonce_url(
	add_query_arg(
		array_merge(
			array(
				'page'   => 'alvobot-quiz-builder',
				'tab'    => 'submissions',
				'export' => 'csv',
			),
			array_filter( $filters )
		),
		admin_url( 'admin.php' )
	),
	'alvobot_quiz_export_csv'
);
?>

<div class="alvobot-submissions-container">
	<?php settings_errors( 'alvobot_quiz_submissions' ); ?>

	<!-- Header Actions -->
	<div class="alvobot-submissions-header">
		<div class="header-info">
			<h2><?php esc_html_e( 'Leads Capturados', 'alvobot-pro' ); ?></h2>
			<span class="alvobot-badge alvobot-badge-info"><?php printf( esc_html__( '%d registro(s)', 'alvobot-pro' ), intval( $total_items ) ); ?></span>
		</div>
		<div class="header-actions">
			<a href="<?php echo esc_url( $export_url ); ?>" class="alvobot-btn alvobot-btn-outline">
				<i data-lucide="download" class="alvobot-icon"></i>
				<?php esc_html_e( 'Exportar CSV', 'alvobot-pro' ); ?>
			</a>
		</div>
	</div>

	<!-- Filters -->
	<div class="alvobot-filters-card">
		<form method="get" action="">
			<input type="hidden" name="page" value="alvobot-quiz-builder">
			<input type="hidden" name="tab" value="submissions">

			<div class="alvobot-filter-row">
				<div class="alvobot-filter-group">
					<label for="quiz_id"><?php esc_html_e( 'Quiz:', 'alvobot-pro' ); ?></label>
					<select name="quiz_id" id="quiz_id" class="alvobot-select">
						<option value=""><?php esc_html_e( 'Todos os quizzes', 'alvobot-pro' ); ?></option>
						<?php foreach ( $quiz_ids as $quiz_id ) : ?>
							<option value="<?php echo esc_attr( $quiz_id ); ?>" <?php selected( $current_quiz_id, $quiz_id ); ?>>
								<?php echo esc_html( substr( $quiz_id, 0, 12 ) . '...' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="alvobot-filter-group">
					<label for="search"><?php esc_html_e( 'Buscar:', 'alvobot-pro' ); ?></label>
					<input type="text" name="search" id="search" class="alvobot-input"
							placeholder="<?php esc_attr_e( 'Nome, email ou telefone...', 'alvobot-pro' ); ?>"
							value="<?php echo esc_attr( $current_search ); ?>">
				</div>

				<div class="alvobot-filter-group">
					<label for="date_from"><?php esc_html_e( 'De:', 'alvobot-pro' ); ?></label>
					<input type="date" name="date_from" id="date_from" class="alvobot-input"
							value="<?php echo esc_attr( $current_date_from ); ?>">
				</div>

				<div class="alvobot-filter-group">
					<label for="date_to"><?php esc_html_e( 'Até:', 'alvobot-pro' ); ?></label>
					<input type="date" name="date_to" id="date_to" class="alvobot-input"
							value="<?php echo esc_attr( $current_date_to ); ?>">
				</div>

				<div class="alvobot-filter-actions">
					<button type="submit" class="alvobot-btn alvobot-btn-primary"><?php esc_html_e( 'Filtrar', 'alvobot-pro' ); ?></button>
					<a href="?page=alvobot-quiz-builder&tab=submissions" class="alvobot-btn alvobot-btn-ghost"><?php esc_html_e( 'Limpar', 'alvobot-pro' ); ?></a>
				</div>
			</div>
		</form>
	</div>

	<!-- Bulk Actions Form -->
	<form method="post" id="submissions-form">
		<?php wp_nonce_field( 'alvobot_quiz_bulk_action' ); ?>

		<!-- Bulk Actions -->
		<div class="alvobot-bulk-actions">
			<select name="bulk_action" class="alvobot-select">
				<option value=""><?php esc_html_e( 'Ações em massa', 'alvobot-pro' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Excluir selecionados', 'alvobot-pro' ); ?></option>
			</select>
			<button type="submit" class="alvobot-btn alvobot-btn-outline" onclick="return confirm('<?php esc_html_e( 'Tem certeza que deseja executar esta ação?', 'alvobot-pro' ); ?>');">
				<?php esc_html_e( 'Aplicar', 'alvobot-pro' ); ?>
			</button>
			<span class="alvobot-selected-count" style="display: none;">
				<span class="count">0</span> <?php esc_html_e( 'selecionado(s)', 'alvobot-pro' ); ?>
			</span>
		</div>

		<!-- Table -->
		<div class="alvobot-table-wrapper">
			<?php if ( empty( $submissions ) ) : ?>
				<div class="alvobot-empty-state">
					<div class="alvobot-empty-icon">
						<i data-lucide="users" class="alvobot-icon"></i>
					</div>
					<h3><?php esc_html_e( 'Nenhum lead capturado ainda', 'alvobot-pro' ); ?></h3>
					<p><?php esc_html_e( 'Os leads aparecerão aqui quando usuários preencherem os formulários do quiz.', 'alvobot-pro' ); ?></p>
				</div>
			<?php else : ?>
				<table class="alvobot-table">
					<thead>
						<tr>
							<th class="check-column">
								<input type="checkbox" id="select-all">
							</th>
							<th class="column-name"><?php esc_html_e( 'Nome', 'alvobot-pro' ); ?></th>
							<th class="column-email"><?php esc_html_e( 'Email', 'alvobot-pro' ); ?></th>
							<th class="column-phone"><?php esc_html_e( 'Telefone', 'alvobot-pro' ); ?></th>
							<th class="column-quiz"><?php esc_html_e( 'Quiz ID', 'alvobot-pro' ); ?></th>
							<th class="column-page"><?php esc_html_e( 'Página', 'alvobot-pro' ); ?></th>
							<th class="column-date"><?php esc_html_e( 'Data', 'alvobot-pro' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Ações', 'alvobot-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submissions as $submission ) : ?>
							<tr>
								<td class="check-column">
									<input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr( $submission->id ); ?>">
								</td>
								<td class="column-name">
									<strong><?php echo esc_html( $submission->name ?: '-' ); ?></strong>
								</td>
								<td class="column-email">
									<?php if ( $submission->email ) : ?>
										<a href="mailto:<?php echo esc_attr( $submission->email ); ?>" class="alvobot-link">
											<?php echo esc_html( $submission->email ); ?>
										</a>
									<?php else : ?>
										<span class="alvobot-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td class="column-phone">
									<?php if ( $submission->phone ) : ?>
										<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $submission->phone ) ); ?>" target="_blank" class="alvobot-link alvobot-whatsapp-link" title="<?php esc_attr_e( 'Abrir no WhatsApp', 'alvobot-pro' ); ?>">
											<i data-lucide="message-circle" class="alvobot-icon"></i>
											<?php echo esc_html( $submission->phone ); ?>
										</a>
									<?php else : ?>
										<span class="alvobot-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td class="column-quiz">
									<code class="alvobot-code"><?php echo esc_html( substr( $submission->quiz_id, 0, 8 ) ); ?>...</code>
								</td>
								<td class="column-page">
									<?php if ( ! empty( $submission->page_url ) ) : ?>
										<a href="<?php echo esc_url( $submission->page_url ); ?>" target="_blank" class="alvobot-link" title="<?php echo esc_attr( $submission->page_url ); ?>">
											<?php echo esc_html( wp_parse_url( $submission->page_url, PHP_URL_PATH ) ?: '/' ); ?>
										</a>
									<?php else : ?>
										<span class="alvobot-text-muted">-</span>
									<?php endif; ?>
								</td>
								<td class="column-date">
									<?php
									$date = strtotime( $submission->created_at );
									echo '<span class="alvobot-date">' . esc_html( date_i18n( get_option( 'date_format' ), $date ) ) . '</span>';
									echo '<span class="alvobot-time">' . esc_html( date_i18n( get_option( 'time_format' ), $date ) ) . '</span>';
									?>
								</td>
								<td class="column-actions">
									<button type="button" class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm view-details"
											data-id="<?php echo esc_attr( $submission->id ); ?>"
											data-answers="<?php echo esc_attr( $submission->answers ); ?>"
											title="<?php esc_attr_e( 'Ver detalhes', 'alvobot-pro' ); ?>">
										<i data-lucide="eye" class="alvobot-icon"></i>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</form>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="alvobot-pagination">
			<span class="alvobot-pagination-info">
				<?php printf( esc_html__( '%d itens', 'alvobot-pro' ), intval( $total_items ) ); ?>
			</span>
			<div class="alvobot-pagination-links">
				<?php
				$base_url = add_query_arg(
					array_merge(
						array(
							'page' => 'alvobot-quiz-builder',
							'tab'  => 'submissions',
						),
						array_filter( $filters )
					),
					admin_url( 'admin.php' )
				);

				if ( $current_page > 1 ) :
					?>
					<a class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
						<span aria-hidden="true">«</span>
					</a>
					<a class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">
						<span aria-hidden="true">‹</span>
					</a>
				<?php else : ?>
					<span class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm disabled">«</span>
					<span class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm disabled">‹</span>
				<?php endif; ?>

				<span class="alvobot-pagination-current">
					<?php echo intval( $current_page ); ?> / <?php echo intval( $total_pages ); ?>
				</span>

				<?php if ( $current_page < $total_pages ) : ?>
					<a class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">
						<span aria-hidden="true">›</span>
					</a>
					<a class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>">
						<span aria-hidden="true">»</span>
					</a>
				<?php else : ?>
					<span class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm disabled">›</span>
					<span class="alvobot-btn alvobot-btn-ghost alvobot-btn-sm disabled">»</span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Details Modal -->
<div id="submission-details-modal" class="alvobot-modal" style="display: none;">
	<div class="alvobot-modal-overlay"></div>
	<div class="alvobot-modal-content">
		<div class="alvobot-modal-header">
			<h3><?php esc_html_e( 'Detalhes do Lead', 'alvobot-pro' ); ?></h3>
			<button type="button" class="alvobot-modal-close">&times;</button>
		</div>
		<div class="alvobot-modal-body">
			<h4><?php esc_html_e( 'Respostas do Quiz:', 'alvobot-pro' ); ?></h4>
			<pre id="answers-content" class="alvobot-code-block"></pre>
		</div>
	</div>
</div>

<style>
/* Submissions Container */
.alvobot-submissions-container {
	margin-top: var(--alvobot-space-lg, 16px);
}

/* Header */
.alvobot-submissions-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: var(--alvobot-space-xl, 20px);
	padding-bottom: var(--alvobot-space-lg, 16px);
	border-bottom: 1px solid var(--alvobot-gray-300, #e5e5e5);
}

.alvobot-submissions-header .header-info {
	display: flex;
	align-items: center;
	gap: var(--alvobot-space-md, 12px);
}

.alvobot-submissions-header h2 {
	margin: 0;
	font-size: var(--alvobot-font-size-xl, 18px);
	font-weight: 600;
	color: var(--alvobot-gray-900, #1d2327);
}

/* Badge */
.alvobot-badge {
	display: inline-flex;
	align-items: center;
	padding: var(--alvobot-space-xs, 4px) var(--alvobot-space-sm, 8px);
	font-size: var(--alvobot-font-size-xs, 12px);
	font-weight: 500;
	border-radius: var(--alvobot-radius-full, 50px);
}

.alvobot-badge-info {
	background: var(--alvobot-info-bg, #f0f6fc);
	color: var(--alvobot-info, #2271b1);
}

/* Filters Card */
.alvobot-filters-card {
	background: var(--alvobot-gray-50, #f9f9f9);
	padding: var(--alvobot-space-lg, 16px);
	border-radius: var(--alvobot-radius-md, 8px);
	margin-bottom: var(--alvobot-space-xl, 20px);
	border: 1px solid var(--alvobot-gray-300, #e5e5e5);
}

.alvobot-filter-row {
	display: flex;
	flex-wrap: wrap;
	gap: var(--alvobot-space-lg, 16px);
	align-items: flex-end;
}

.alvobot-filter-group {
	display: flex;
	flex-direction: column;
	gap: var(--alvobot-space-xs, 4px);
}

.alvobot-filter-group label {
	font-weight: 500;
	font-size: var(--alvobot-font-size-sm, 13px);
	color: var(--alvobot-gray-700, #50575e);
}

.alvobot-filter-group .alvobot-input,
.alvobot-filter-group .alvobot-select {
	min-width: 160px;
	max-width: none;
}

.alvobot-filter-actions {
	display: flex;
	gap: var(--alvobot-space-sm, 8px);
	align-items: center;
}

/* Bulk Actions */
.alvobot-bulk-actions {
	display: flex;
	gap: var(--alvobot-space-md, 12px);
	align-items: center;
	margin-bottom: var(--alvobot-space-lg, 16px);
	padding: var(--alvobot-space-md, 12px);
	background: var(--alvobot-gray-100, #f6f7f7);
	border-radius: var(--alvobot-radius-sm, 4px);
}

.alvobot-bulk-actions .alvobot-select {
	max-width: 200px;
}

.alvobot-selected-count {
	margin-left: auto;
	color: var(--alvobot-primary, #CD9042);
	font-weight: 500;
	font-size: var(--alvobot-font-size-sm, 13px);
}

/* Table Wrapper */
.alvobot-table-wrapper {
	background: var(--alvobot-white, #fff);
	border: 1px solid var(--alvobot-gray-300, #e5e5e5);
	border-radius: var(--alvobot-radius-md, 8px);
	overflow: hidden;
}

/* Table */
.alvobot-table {
	width: 100%;
	border-collapse: collapse;
	font-size: var(--alvobot-font-size-sm, 13px);
}

.alvobot-table thead {
	background: var(--alvobot-gray-100, #f6f7f7);
}

.alvobot-table th {
	padding: var(--alvobot-space-md, 12px) var(--alvobot-space-lg, 16px);
	text-align: left;
	font-weight: 600;
	color: var(--alvobot-gray-700, #50575e);
	border-bottom: 2px solid var(--alvobot-gray-300, #e5e5e5);
	white-space: nowrap;
}

.alvobot-table td {
	padding: var(--alvobot-space-md, 12px) var(--alvobot-space-lg, 16px);
	border-bottom: 1px solid var(--alvobot-gray-200, #f0f0f1);
	color: var(--alvobot-gray-800, #2c3338);
}

.alvobot-table tbody tr:hover {
	background: var(--alvobot-gray-50, #f9f9f9);
}

.alvobot-table tbody tr:last-child td {
	border-bottom: none;
}

.alvobot-table .check-column {
	width: 40px;
	text-align: center;
}

.alvobot-table .column-quiz {
	width: 120px;
}

.alvobot-table .column-page {
	max-width: 150px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.alvobot-table .column-date {
	width: 130px;
}

.alvobot-table .column-actions {
	width: 70px;
	text-align: center;
}

/* Date Column */
.alvobot-date {
	display: block;
	font-weight: 500;
	color: var(--alvobot-gray-800, #2c3338);
}

.alvobot-time {
	display: block;
	font-size: var(--alvobot-font-size-xs, 12px);
	color: var(--alvobot-gray-600, #646970);
}

/* Code */
.alvobot-code {
	background: var(--alvobot-gray-100, #f6f7f7);
	padding: 2px 6px;
	border-radius: var(--alvobot-radius-sm, 4px);
	font-size: var(--alvobot-font-size-xs, 12px);
	font-family: monospace;
	color: var(--alvobot-gray-700, #50575e);
}

/* Links */
.alvobot-link {
	color: var(--alvobot-accent, #2271b1);
	text-decoration: none;
}

.alvobot-link:hover {
	color: var(--alvobot-accent-dark, #135e96);
	text-decoration: underline;
}

.alvobot-whatsapp-link {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.alvobot-whatsapp-link .alvobot-icon {
	font-size: 14px;
	width: 14px;
	height: 14px;
	color: #25D366;
}

/* Text Muted */
.alvobot-text-muted {
	color: var(--alvobot-gray-500, #c3c4c7);
}

/* Empty State */
.alvobot-empty-state {
	text-align: center;
	padding: var(--alvobot-space-3xl, 32px) var(--alvobot-space-xl, 20px);
	padding-top: 60px;
	padding-bottom: 60px;
}

.alvobot-empty-icon {
	margin-bottom: var(--alvobot-space-lg, 16px);
}

.alvobot-empty-icon .alvobot-icon {
	font-size: 64px;
	width: 64px;
	height: 64px;
	color: var(--alvobot-gray-400, #dcdcde);
}

.alvobot-empty-state h3 {
	margin: 0 0 var(--alvobot-space-sm, 8px);
	color: var(--alvobot-gray-700, #50575e);
	font-size: var(--alvobot-font-size-lg, 16px);
}

.alvobot-empty-state p {
	margin: 0;
	color: var(--alvobot-gray-600, #646970);
	font-size: var(--alvobot-font-size-sm, 13px);
}

/* Pagination */
.alvobot-pagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: var(--alvobot-space-lg, 16px);
	padding: var(--alvobot-space-md, 12px) 0;
}

.alvobot-pagination-info {
	color: var(--alvobot-gray-600, #646970);
	font-size: var(--alvobot-font-size-sm, 13px);
}

.alvobot-pagination-links {
	display: flex;
	align-items: center;
	gap: var(--alvobot-space-xs, 4px);
}

.alvobot-pagination-current {
	padding: 0 var(--alvobot-space-md, 12px);
	font-weight: 500;
	color: var(--alvobot-gray-700, #50575e);
}

.alvobot-btn.disabled {
	opacity: 0.5;
	cursor: not-allowed;
	pointer-events: none;
}

/* Modal */
.alvobot-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 100000;
}

.alvobot-modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.6);
}

.alvobot-modal-content {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: var(--alvobot-white, #fff);
	border-radius: var(--alvobot-radius-lg, 12px);
	max-width: 600px;
	width: 90%;
	max-height: 80vh;
	overflow: auto;
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.alvobot-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: var(--alvobot-space-lg, 16px) var(--alvobot-space-xl, 20px);
	border-bottom: 1px solid var(--alvobot-gray-300, #e5e5e5);
	background: var(--alvobot-gray-50, #f9f9f9);
}

.alvobot-modal-header h3 {
	margin: 0;
	font-size: var(--alvobot-font-size-lg, 16px);
	font-weight: 600;
	color: var(--alvobot-gray-900, #1d2327);
}

.alvobot-modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: var(--alvobot-gray-600, #646970);
	padding: 0;
	line-height: 1;
	transition: color 0.2s ease;
}

.alvobot-modal-close:hover {
	color: var(--alvobot-gray-900, #1d2327);
}

.alvobot-modal-body {
	padding: var(--alvobot-space-xl, 20px);
}

.alvobot-modal-body h4 {
	margin: 0 0 var(--alvobot-space-md, 12px);
	font-size: var(--alvobot-font-size-base, 14px);
	font-weight: 600;
	color: var(--alvobot-gray-700, #50575e);
}

.alvobot-code-block {
	background: var(--alvobot-gray-100, #f6f7f7);
	padding: var(--alvobot-space-lg, 16px);
	border-radius: var(--alvobot-radius-sm, 4px);
	overflow-x: auto;
	font-size: var(--alvobot-font-size-sm, 13px);
	font-family: monospace;
	white-space: pre-wrap;
	word-wrap: break-word;
	border: 1px solid var(--alvobot-gray-300, #e5e5e5);
	margin: 0;
}

/* Count badge in tab */
.nav-tab .count-badge {
	display: inline-block;
	background: var(--alvobot-primary, #CD9042);
	color: var(--alvobot-white, #fff);
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 11px;
	margin-left: 5px;
	vertical-align: middle;
}

/* Responsive */
@media (max-width: 782px) {
	.alvobot-submissions-header {
		flex-direction: column;
		align-items: flex-start;
		gap: var(--alvobot-space-md, 12px);
	}

	.alvobot-filter-row {
		flex-direction: column;
	}

	.alvobot-filter-group {
		width: 100%;
	}

	.alvobot-filter-group .alvobot-input,
	.alvobot-filter-group .alvobot-select {
		width: 100%;
	}

	.alvobot-bulk-actions {
		flex-wrap: wrap;
	}

	.alvobot-table-wrapper {
		overflow-x: auto;
	}

	.alvobot-pagination {
		flex-direction: column;
		gap: var(--alvobot-space-md, 12px);
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Select all checkbox
	$('#select-all').on('change', function() {
		$('input[name="submission_ids[]"]').prop('checked', $(this).is(':checked'));
		updateSelectedCount();
	});

	// Individual checkbox
	$('input[name="submission_ids[]"]').on('change', function() {
		updateSelectedCount();
	});

	function updateSelectedCount() {
		var count = $('input[name="submission_ids[]"]:checked').length;
		if (count > 0) {
			$('.alvobot-selected-count').show().find('.count').text(count);
		} else {
			$('.alvobot-selected-count').hide();
		}
	}

	// View details modal
	$('.view-details').on('click', function() {
		var answers = $(this).data('answers');
		var formatted = '';

		if (answers) {
			try {
				// Try to parse if it's JSON
				if (typeof answers === 'string') {
					formatted = answers;
				} else {
					formatted = JSON.stringify(answers, null, 2);
				}
			} catch (e) {
				formatted = answers;
			}
		} else {
			formatted = '<?php esc_html_e( 'Nenhuma resposta registrada', 'alvobot-pro' ); ?>';
		}

		$('#answers-content').text(formatted);
		$('#submission-details-modal').show();
	});

	// Close modal
	$('.alvobot-modal-close, .alvobot-modal-overlay').on('click', function() {
		$('#submission-details-modal').hide();
	});

	// Close on ESC
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			$('#submission-details-modal').hide();
		}
	});
});
</script>
