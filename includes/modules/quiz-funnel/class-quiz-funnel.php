<?php
/**
 * Quiz Funnel — módulo isolado AlvoBot Pro
 *
 * Shortcode : [alvobot_quiz id="ID"]
 * Full-page : domain.com/?alvobot_quiz=ID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_QuizFunnel {

	private $version;
	private $option_key = 'alvobot_quiz_funnel_data';

	public function __construct() {
		$this->version = ALVOBOT_PRO_VERSION;
		$this->init();
	}

	private function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_action( 'wp_ajax_alvobot_qf_save',   array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_alvobot_qf_delete', array( $this, 'ajax_delete' ) );

		add_shortcode( 'alvobot_quiz', array( $this, 'render_shortcode' ) );

		// Prioridade 1 — roda antes de templates do tema
		add_action( 'template_redirect', array( $this, 'maybe_full_page' ), 1 );
	}

	// ── Admin ─────────────────────────────────────────────────────────────────

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$quizzes  = $this->get_all();
		$nonce    = wp_create_nonce( 'alvobot_qf_nonce' );
		$ajaxurl  = admin_url( 'admin-ajax.php' );
		$site_url = trailingslashit( home_url() );
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div class="alvobot-admin-header">
					<div class="alvobot-header-icon">
						<i data-lucide="list-checks" class="alvobot-icon"></i>
					</div>
					<div class="alvobot-header-content">
						<h1>Quiz Funnel</h1>
						<p>Crie quizzes interativos que encaminham o visitante para artigos específicos.</p>
					</div>
				</div>

				<div class="alvobot-notice-container"><?php settings_errors( 'alvobot_qf' ); ?></div>

				<div id="aqf-root"
					data-ajaxurl="<?php echo esc_attr( $ajaxurl ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-siteurl="<?php echo esc_attr( $site_url ); ?>"
					data-quizzes="<?php echo esc_attr( wp_json_encode( $quizzes ) ); ?>">
				</div>
			</div>
		</div>
		<?php
	}

	public function enqueue_admin_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'alvobot-quiz-funnel' !== $page ) {
			return;
		}

		// AlvoBot main admin styles
		wp_enqueue_style(
			'alvobot-admin',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/css/styles.css',
			array(),
			$this->version
		);

		wp_enqueue_style(
			'alvobot-qf-admin',
			plugin_dir_url( __FILE__ ) . 'css/admin.css',
			array( 'alvobot-admin' ),
			$this->version
		);

		wp_enqueue_script(
			'alvobot-qf-admin',
			plugin_dir_url( __FILE__ ) . 'js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_save() {
		check_ajax_referer( 'alvobot_qf_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada.' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw  = isset( $_POST['quiz'] ) ? wp_unslash( $_POST['quiz'] ) : '';
		$quiz = json_decode( $raw, true );

		if ( ! is_array( $quiz ) || empty( trim( $quiz['title'] ?? '' ) ) ) {
			wp_send_json_error( array( 'message' => 'Título obrigatório.' ) );
		}

		if ( empty( $quiz['id'] ) ) {
			$quiz['id'] = 'qf_' . substr( md5( uniqid( 'qf', true ) ), 0, 8 );
		}

		$quiz    = $this->sanitize( $quiz );
		$quizzes = $this->get_all();
		$found   = false;

		foreach ( $quizzes as &$q ) {
			if ( $q['id'] === $quiz['id'] ) {
				$q     = $quiz;
				$found = true;
				break;
			}
		}
		unset( $q );

		if ( ! $found ) {
			$quizzes[] = $quiz;
		}

		update_option( $this->option_key, $quizzes );
		wp_send_json_success( array( 'quiz' => $quiz ) );
	}

	public function ajax_delete() {
		check_ajax_referer( 'alvobot_qf_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$id      = sanitize_key( wp_unslash( $_POST['quiz_id'] ?? '' ) );
		$quizzes = array_values(
			array_filter( $this->get_all(), function( $q ) use ( $id ) {
				return $q['id'] !== $id;
			} )
		);

		update_option( $this->option_key, $quizzes );
		wp_send_json_success();
	}

	// ── Data ──────────────────────────────────────────────────────────────────

	private function get_all(): array {
		return array_values( (array) get_option( $this->option_key, array() ) );
	}

	public function get_quiz( string $id ): ?array {
		foreach ( $this->get_all() as $q ) {
			if ( $q['id'] === $id ) {
				return $q;
			}
		}
		return null;
	}

	private function sanitize( array $quiz ): array {
		$out = array(
			'id'            => sanitize_key( $quiz['id'] ?? '' ),
			'title'         => sanitize_text_field( $quiz['title'] ?? '' ),
			'subtitle'      => sanitize_text_field( $quiz['subtitle'] ?? '' ),
			'logo_text'     => sanitize_text_field( $quiz['logo_text'] ?? '' ),
			'logo_url'      => esc_url_raw( $quiz['logo_url'] ?? '' ),
			'color_primary' => sanitize_hex_color( $quiz['color_primary'] ?? '#22c55e' ) ?: '#22c55e',
			'color_btn_bg'  => sanitize_hex_color( $quiz['color_btn_bg'] ?? '#f0fdf4' ) ?: '#f0fdf4',
			'footer_text'   => sanitize_text_field( $quiz['footer_text'] ?? '' ),
			'final_url'     => esc_url_raw( $quiz['final_url'] ?? '' ),
			'questions'     => array(),
		);

		foreach ( (array) ( $quiz['questions'] ?? array() ) as $q ) {
			if ( empty( trim( $q['text'] ?? '' ) ) ) {
				continue;
			}
			$question = array(
				'id'      => sanitize_key( $q['id'] ?? 'q_' . wp_rand() ),
				'text'    => sanitize_text_field( $q['text'] ),
				'answers' => array(),
			);
			foreach ( (array) ( $q['answers'] ?? array() ) as $a ) {
				if ( empty( trim( $a['text'] ?? '' ) ) ) {
					continue;
				}
				$question['answers'][] = array(
					'id'   => sanitize_key( $a['id'] ?? 'a_' . wp_rand() ),
					'text' => sanitize_text_field( $a['text'] ),
					'url'  => esc_url_raw( $a['url'] ?? '' ),
				);
			}
			$out['questions'][] = $question;
		}

		return $out;
	}

	// ── Frontend ──────────────────────────────────────────────────────────────

	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'alvobot_quiz' );
		$quiz = $this->get_quiz( sanitize_key( $atts['id'] ) );

		if ( ! $quiz || empty( $quiz['questions'] ) ) {
			return '';
		}

		$this->enqueue_frontend();
		return $this->build_html( $quiz );
	}

	public function maybe_full_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = sanitize_key( wp_unslash( $_GET['alvobot_quiz'] ?? '' ) );
		if ( ! $id ) {
			return;
		}

		$quiz = $this->get_quiz( $id );
		if ( ! $quiz || empty( $quiz['questions'] ) ) {
			return;
		}

		$this->render_full_page( $quiz );
		exit;
	}

	private function enqueue_frontend() {
		static $loaded = false;
		if ( $loaded ) {
			return;
		}
		$loaded = true;

		wp_enqueue_style(
			'alvobot-qf',
			plugin_dir_url( __FILE__ ) . 'css/frontend.css',
			array(),
			$this->version
		);
		wp_enqueue_script(
			'alvobot-qf',
			plugin_dir_url( __FILE__ ) . 'js/frontend.js',
			array(),
			$this->version,
			true
		);
	}

	public function build_html( array $quiz, bool $fullpage = false ): string {
		$id    = esc_attr( $quiz['id'] );
		$color = esc_attr( $quiz['color_primary'] );
		$btnbg = esc_attr( $quiz['color_btn_bg'] );
		$total = count( $quiz['questions'] );

		ob_start();
		?>
<div class="aqf<?php echo $fullpage ? ' aqf--fullpage' : ''; ?>"
	 id="aqf-<?php echo $id; ?>"
	 style="--aqf-p:<?php echo $color; ?>;--aqf-bb:<?php echo $btnbg; ?>"
	 data-final-url="<?php echo esc_attr( $quiz['final_url'] ); ?>"
	 role="main"
	 aria-label="<?php echo esc_attr( $quiz['title'] ); ?>">

	<?php if ( ! empty( $quiz['logo_url'] ) || ! empty( $quiz['logo_text'] ) ) : ?>
	<header class="aqf__header">
		<?php if ( ! empty( $quiz['logo_url'] ) ) : ?>
			<img src="<?php echo esc_url( $quiz['logo_url'] ); ?>"
				 alt="<?php echo esc_attr( $quiz['logo_text'] ?: get_bloginfo( 'name' ) ); ?>"
				 class="aqf__logo-img">
		<?php else : ?>
			<span class="aqf__logo-text"><?php echo esc_html( $quiz['logo_text'] ); ?></span>
		<?php endif; ?>
	</header>
	<?php endif; ?>

	<div class="aqf__hero">
		<h1 class="aqf__title"><?php echo esc_html( $quiz['title'] ); ?></h1>
		<?php if ( ! empty( $quiz['subtitle'] ) ) : ?>
			<p class="aqf__subtitle"><?php echo esc_html( $quiz['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>

	<div class="aqf__progress" aria-hidden="true">
		<?php for ( $i = 0; $i < $total; $i++ ) : ?>
			<span class="aqf__step<?php echo 0 === $i ? ' aqf__step--on' : ''; ?>" data-step="<?php echo $i; ?>"></span>
		<?php endfor; ?>
	</div>

	<div class="aqf__body">
		<?php foreach ( $quiz['questions'] as $qi => $question ) : ?>
		<div class="aqf__q<?php echo 0 === $qi ? ' aqf__q--on' : ''; ?>"
			 data-qi="<?php echo $qi; ?>"
			 aria-hidden="<?php echo 0 === $qi ? 'false' : 'true'; ?>">
			<h2 class="aqf__q-text"><?php echo esc_html( $question['text'] ); ?></h2>
			<div class="aqf__answers">
				<?php foreach ( $question['answers'] as $answer ) : ?>
				<button class="aqf__ans"
						type="button"
						data-url="<?php echo esc_attr( $answer['url'] ); ?>"
						data-qi="<?php echo $qi; ?>">
					<?php echo esc_html( $answer['text'] ); ?>
				</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $quiz['footer_text'] ) ) : ?>
	<footer class="aqf__footer"><?php echo esc_html( $quiz['footer_text'] ); ?></footer>
	<?php endif; ?>

</div>
		<?php
		return ob_get_clean();
	}

	private function render_full_page( array $quiz ) {
		$html  = $this->build_html( $quiz, true );
		$css_file = __DIR__ . '/css/frontend.css';
		$js_file  = __DIR__ . '/js/frontend.js';
		$css = file_exists( $css_file ) ? file_get_contents( $css_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$js  = file_exists( $js_file )  ? file_get_contents( $js_file )  : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$title = esc_html( $quiz['title'] );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . $title . '</title>
<style>' . $css . '</style>
</head>
<body class="aqf-page">
' . $html . '
<script>' . $js . '</script>
</body>
</html>';
		// phpcs:enable
	}
}
