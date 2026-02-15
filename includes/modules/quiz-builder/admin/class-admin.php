<?php
/**
 * Admin functionality class
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin interface and settings
 */
class Alvobot_Quiz_Admin {

	/**
	 * Instance
	 *
	 * @var Alvobot_Quiz_Admin
	 */
	private static $instance = null;

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $plugin_name = 'alvobot-pro';

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version = ALVOBOT_QUIZ_VERSION;

	/**
	 * Get instance
	 *
	 * @return Alvobot_Quiz_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Menu is now handled by AlvoBotPro_QuizBuilder

		// Handle CSV export early, before any output
		add_action( 'admin_init', array( $this, 'handle_early_csv_export' ) );
	}

	/**
	 * Handle CSV export early before any output
	 */
	public function handle_early_csv_export() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection only, no data modification.
		if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'alvobot-quiz-builder' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab detection only, no data modification.
		if ( ! isset( $_GET['tab'] ) || sanitize_text_field( wp_unslash( $_GET['tab'] ) ) !== 'submissions' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Export action detection; actual permission check done in export_csv.
		if ( ! isset( $_GET['export'] ) || sanitize_text_field( wp_unslash( $_GET['export'] ) ) !== 'csv' ) {
			return;
		}

		$this->export_csv();
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Quiz Builder', 'alvobot-pro' ),
			__( 'Quiz Builder', 'alvobot-pro' ),
			'manage_options',
			'alvobot-pro',
			array( $this, 'admin_page' ),
			'dashicons-forms',
			30
		);
	}

	public function admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation display logic, no data modification.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'builder';

		// Handle bulk actions
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified inside handle_bulk_actions method.
		if ( $active_tab === 'submissions' && isset( $_POST['bulk_action'] ) && isset( $_POST['submission_ids'] ) ) {
			$this->handle_bulk_actions();
		}

		// Get submissions count for badge
		$submissions       = new Alvobot_Quiz_Submissions();
		$total_submissions = $submissions->get_total_count();
		?>
		<div class="alvobot-admin-header">
			<div class="alvobot-header-icon">
				<i data-lucide="list-checks" class="alvobot-icon"></i>
			</div>
			<div class="alvobot-header-content">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<p><?php esc_html_e( 'Crie quizzes interativos com navegação por URL única, otimizados para monetização.', 'alvobot-pro' ); ?></p>
			</div>
		</div>

		<nav class="nav-tab-wrapper">
			<a href="?page=alvobot-quiz-builder&tab=builder"
				class="nav-tab <?php echo esc_attr( $active_tab == 'builder' ? 'nav-tab-active' : '' ); ?>">
				<i data-lucide="wrench" class="alvobot-icon"></i>
				<?php esc_html_e( 'Gerador', 'alvobot-pro' ); ?>
			</a>
			<a href="?page=alvobot-quiz-builder&tab=submissions"
				class="nav-tab <?php echo esc_attr( $active_tab == 'submissions' ? 'nav-tab-active' : '' ); ?>">
				<i data-lucide="users" class="alvobot-icon"></i>
				<?php esc_html_e( 'Leads Capturados', 'alvobot-pro' ); ?>
				<?php if ( $total_submissions > 0 ) : ?>
					<span class="count-badge"><?php echo esc_html( $total_submissions ); ?></span>
				<?php endif; ?>
			</a>
			<a href="?page=alvobot-quiz-builder&tab=docs"
				class="nav-tab <?php echo esc_attr( $active_tab == 'docs' ? 'nav-tab-active' : '' ); ?>">
				<i data-lucide="book-open" class="alvobot-icon"></i>
				<?php esc_html_e( 'Documentação', 'alvobot-pro' ); ?>
			</a>
		</nav>

		<div class="alvobot-card">
			<div class="alvobot-card-content">
				<?php
				switch ( $active_tab ) {
					case 'builder':
						$this->builder_tab();
						break;
					case 'submissions':
						$this->submissions_tab();
						break;
					case 'docs':
						$this->docs_tab();
						break;
					default:
						$this->builder_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Builder tab
	 */
	private function builder_tab() {
		include ALVOBOT_QUIZ_PATH . 'admin/views/view-builder.php';
	}

	/**
	 * Documentation tab
	 */
	private function docs_tab() {
		include ALVOBOT_QUIZ_PATH . 'admin/views/view-docs.php';
	}

	/**
	 * Submissions tab
	 */
	private function submissions_tab() {
		include ALVOBOT_QUIZ_PATH . 'admin/views/view-submissions.php';
	}

	/**
	 * Handle bulk actions
	 */
	private function handle_bulk_actions() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'alvobot_quiz_bulk_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['bulk_action'], $_POST['submission_ids'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
		$ids    = array_map( 'intval', wp_unslash( $_POST['submission_ids'] ) );

		if ( empty( $ids ) ) {
			return;
		}

		$submissions = new Alvobot_Quiz_Submissions();

		switch ( $action ) {
			case 'delete':
				$deleted = $submissions->delete_submissions( $ids );
				if ( $deleted ) {
					add_settings_error(
						'alvobot_quiz_submissions',
						'deleted',
						sprintf( __( '%d submission(s) deleted successfully.', 'alvobot-pro' ), count( $ids ) ),
						'success'
					);
				}
				break;
		}
	}

	/**
	 * Export submissions to CSV
	 */
	private function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'alvobot-pro' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'alvobot_quiz_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'alvobot-pro' ) );
		}

		$submissions = new Alvobot_Quiz_Submissions();

		// Get filters -- Nonce verified above.
		$filters = array();
		if ( ! empty( $_GET['quiz_id'] ) ) {
			$filters['quiz_id'] = sanitize_text_field( wp_unslash( $_GET['quiz_id'] ) );
		}
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}

		$data = $submissions->get_submissions( $filters, -1, 0 ); // -1 = all

		// Set headers for CSV download
		$filename = 'quiz-submissions-' . date( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Add BOM for Excel UTF-8 compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header row
		fputcsv(
			$output,
			array(
				'ID',
				'Quiz ID',
				'Nome',
				'Email',
				'Telefone',
				'Respostas',
				'URL da Página',
				'Data',
			)
		);

		// Data rows
		foreach ( $data as $row ) {
			fputcsv(
				$output,
				array(
					$row->id,
					$row->quiz_id,
					$row->name,
					$row->email,
					$row->phone,
					$row->answers,
					$row->page_url ?? '',
					$row->created_at,
				)
			);
		}

		fclose( $output );
		exit;
	}
}