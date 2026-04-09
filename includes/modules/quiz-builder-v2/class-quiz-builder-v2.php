<?php
/**
 * Quiz Builder V2 — Full-screen quiz with lead capture and article redirect.
 *
 * Designed for ad-arbitrage workflows:
 *   1. Visitor lands on page
 *   2. Full-screen quiz engages them
 *   3. Optional lead capture (name + email)
 *   4. Redirect to monetised article
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_QuizBuilderV2 {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// REST API
		require_once __DIR__ . '/class-quiz-builder-v2-api.php';

		// Admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_alvobot_qbv2_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_alvobot_qbv2_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_alvobot_qbv2_get_leads', array( $this, 'ajax_get_leads' ) );
		add_action( 'wp_ajax_alvobot_qbv2_export_leads', array( $this, 'ajax_export_leads' ) );
		add_action( 'wp_ajax_alvobot_qbv2_fetch_article', array( $this, 'ajax_fetch_article' ) );

		// Frontend
		add_action( 'template_redirect', array( $this, 'maybe_full_page' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend' ) );
		add_shortcode( 'alvobot_quiz', array( $this, 'render_shortcode' ) );

		// Lead capture endpoint (public)
		add_action( 'wp_ajax_alvobot_qbv2_lead', array( $this, 'ajax_save_lead' ) );
		add_action( 'wp_ajax_nopriv_alvobot_qbv2_lead', array( $this, 'ajax_save_lead' ) );
	}

	/* =========================================================================
	 * DATA HELPERS
	 * ====================================================================== */

	private function get_all_quizzes() {
		return get_option( 'alvobot_qbv2_data', array() );
	}

	private function save_all_quizzes( $quizzes ) {
		update_option( 'alvobot_qbv2_data', $quizzes, false );
	}

	private function get_quiz( $id ) {
		$quizzes = $this->get_all_quizzes();
		return isset( $quizzes[ $id ] ) ? $quizzes[ $id ] : null;
	}

	/* =========================================================================
	 * ADMIN
	 * ====================================================================== */

	public function render_settings_page() {
		$quizzes = $this->get_all_quizzes();
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div id="alvobot-qbv2-root"
					 data-quizzes="<?php echo esc_attr( wp_json_encode( array_values( $quizzes ) ) ); ?>"
					 data-nonce="<?php echo esc_attr( wp_create_nonce( 'alvobot_qbv2_nonce' ) ); ?>"
					 data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
				</div>
			</div>
		</div>
		<?php
	}

	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'alvobot-quiz-builder-v2' ) ) {
			return;
		}

		// AlvoBot design-system base
		$design_css = ALVOBOT_PRO_PLUGIN_URL . 'assets/css/alvobot-pro-admin.css';
		if ( file_exists( ALVOBOT_PRO_PLUGIN_DIR . 'assets/css/alvobot-pro-admin.css' ) ) {
			wp_enqueue_style( 'alvobot-pro-admin-css', $design_css, array(), ALVOBOT_PRO_VERSION );
		}

		$base = ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/quiz-builder-v2/';
		wp_enqueue_style( 'alvobot-qbv2-admin', $base . 'css/admin.css', array(), ALVOBOT_PRO_VERSION );
		wp_enqueue_script( 'alvobot-qbv2-admin', $base . 'js/admin.js', array( 'jquery', 'alvobot-ai' ), ALVOBOT_PRO_VERSION, true );
	}

	/* =========================================================================
	 * AJAX — QUIZ CRUD
	 * ====================================================================== */

	public function ajax_save() {
		check_ajax_referer( 'alvobot_qbv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$raw = json_decode( wp_unslash( $_POST['quiz'] ?? '' ), true );
		if ( ! $raw || empty( $raw['title'] ) ) {
			wp_send_json_error( 'Dados inválidos.' );
		}

		$quiz    = $this->sanitize_quiz( $raw );
		$quizzes = $this->get_all_quizzes();

		if ( empty( $quiz['id'] ) ) {
			$quiz['id'] = 'qb_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		}

		$quizzes[ $quiz['id'] ] = $quiz;
		$this->save_all_quizzes( $quizzes );

		wp_send_json_success( $quiz );
	}

	public function ajax_delete() {
		check_ajax_referer( 'alvobot_qbv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$id      = sanitize_text_field( $_POST['id'] ?? '' );
		$quizzes = $this->get_all_quizzes();

		if ( ! isset( $quizzes[ $id ] ) ) {
			wp_send_json_error( 'Quiz não encontrado.' );
		}

		unset( $quizzes[ $id ] );
		$this->save_all_quizzes( $quizzes );

		// Also remove leads for this quiz
		delete_option( 'alvobot_qbv2_leads_' . $id );

		wp_send_json_success();
	}

	/* =========================================================================
	 * AJAX — LEADS
	 * ====================================================================== */

	public function ajax_save_lead() {
		// Public endpoint — uses its own nonce
		check_ajax_referer( 'alvobot_qbv2_public', 'nonce' );

		$quiz_id = sanitize_text_field( $_POST['quiz_id'] ?? '' );
		$name    = sanitize_text_field( $_POST['lead_name'] ?? '' );
		$email   = sanitize_email( $_POST['lead_email'] ?? '' );

		if ( ! $quiz_id || ! $email ) {
			wp_send_json_error( 'Dados incompletos.' );
		}

		$leads   = get_option( 'alvobot_qbv2_leads_' . $quiz_id, array() );
		$leads[] = array(
			'name'       => $name,
			'email'      => $email,
			'created_at' => current_time( 'mysql' ),
			'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
		);

		update_option( 'alvobot_qbv2_leads_' . $quiz_id, $leads, false );

		wp_send_json_success();
	}

	public function ajax_get_leads() {
		check_ajax_referer( 'alvobot_qbv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$quiz_id = sanitize_text_field( $_POST['quiz_id'] ?? '' );
		$leads   = get_option( 'alvobot_qbv2_leads_' . $quiz_id, array() );

		wp_send_json_success( $leads );
	}

	public function ajax_export_leads() {
		check_ajax_referer( 'alvobot_qbv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$quiz_id = sanitize_text_field( $_GET['quiz_id'] ?? '' );
		$leads   = get_option( 'alvobot_qbv2_leads_' . $quiz_id, array() );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=leads-' . $quiz_id . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Nome', 'Email', 'Data', 'IP' ) );

		foreach ( $leads as $lead ) {
			fputcsv( $out, array(
				$lead['name'] ?? '',
				$lead['email'] ?? '',
				$lead['created_at'] ?? '',
				$lead['ip'] ?? '',
			) );
		}

		fclose( $out );
		exit;
	}

	/* =========================================================================
	 * AJAX — FETCH ARTICLE CONTENT (for AI generation)
	 * ====================================================================== */

	public function ajax_fetch_article() {
		check_ajax_referer( 'alvobot_qbv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$url = esc_url_raw( $_POST['url'] ?? '' );
		if ( empty( $url ) ) {
			wp_send_json_error( 'URL não informada.' );
		}

		// Try to find as a local post first (by URL)
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				wp_send_json_success( array(
					'title'   => $post->post_title,
					'content' => wp_strip_all_tags( strip_shortcodes( $post->post_content ) ),
					'source'  => 'local',
				) );
			}
		}

		// Fallback: fetch external URL
		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Erro ao acessar a URL: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			wp_send_json_error( 'Página retornou conteúdo vazio.' );
		}

		// Extract title
		$title = '';
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $matches ) ) {
			$title = html_entity_decode( trim( $matches[1] ), ENT_QUOTES, 'UTF-8' );
		}

		// Extract main content (try <article>, then <main>, then <body>)
		$content = '';
		if ( preg_match( '/<article[^>]*>(.*?)<\/article>/is', $body, $matches ) ) {
			$content = $matches[1];
		} elseif ( preg_match( '/<main[^>]*>(.*?)<\/main>/is', $body, $matches ) ) {
			$content = $matches[1];
		} elseif ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $body, $matches ) ) {
			$content = $matches[1];
		}

		$content = wp_strip_all_tags( $content );
		// Limit content to ~3000 chars to avoid oversized AI requests
		if ( strlen( $content ) > 3000 ) {
			$content = substr( $content, 0, 3000 ) . '...';
		}

		if ( empty( $content ) ) {
			wp_send_json_error( 'Não foi possível extrair conteúdo da página.' );
		}

		wp_send_json_success( array(
			'title'   => $title,
			'content' => $content,
			'source'  => 'remote',
		) );
	}

	/* =========================================================================
	 * SANITIZATION
	 * ====================================================================== */

	private function sanitize_quiz( $raw ) {
		$quiz = array(
			'id'              => sanitize_text_field( $raw['id'] ?? '' ),
			'title'           => sanitize_text_field( $raw['title'] ?? '' ),
			'headline'        => sanitize_text_field( $raw['headline'] ?? '' ),
			'subheadline'     => sanitize_text_field( $raw['subheadline'] ?? '' ),
			'logo_url'        => esc_url_raw( $raw['logo_url'] ?? '' ),
			'color_header'    => sanitize_hex_color( $raw['color_header'] ?? '#1a3a2a' ) ?: '#1a3a2a',
			'color_primary'   => sanitize_hex_color( $raw['color_primary'] ?? '#22c55e' ) ?: '#22c55e',
			'color_accent'    => sanitize_hex_color( $raw['color_accent'] ?? '#f97316' ) ?: '#f97316',
			'redirect_url'    => esc_url_raw( $raw['redirect_url'] ?? '' ),
			'enable_lead'     => ! empty( $raw['enable_lead'] ),
			'lead_title'      => sanitize_text_field( $raw['lead_title'] ?? '' ),
			'lead_subtitle'   => sanitize_text_field( $raw['lead_subtitle'] ?? '' ),
			'lead_btn_text'   => sanitize_text_field( $raw['lead_btn_text'] ?? 'ENVIAR RESPOSTAS' ),
			'footer_text'     => sanitize_text_field( $raw['footer_text'] ?? '' ),
			'footer_links'    => array(),
			'questions'       => array(),
		);

		// Footer links (e.g. Termos de uso, Política de privacidade)
		if ( ! empty( $raw['footer_links'] ) && is_array( $raw['footer_links'] ) ) {
			foreach ( $raw['footer_links'] as $link ) {
				if ( empty( $link['text'] ) ) {
					continue;
				}
				$quiz['footer_links'][] = array(
					'text' => sanitize_text_field( $link['text'] ),
					'url'  => esc_url_raw( $link['url'] ?? '' ),
				);
			}
		}

		// Questions
		if ( ! empty( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
			foreach ( $raw['questions'] as $q ) {
				if ( empty( $q['text'] ) ) {
					continue;
				}
				$question = array(
					'id'      => sanitize_text_field( $q['id'] ?? ( 'q_' . substr( md5( uniqid( '', true ) ), 0, 6 ) ) ),
					'text'    => sanitize_text_field( $q['text'] ),
					'answers' => array(),
				);
				if ( ! empty( $q['answers'] ) && is_array( $q['answers'] ) ) {
					foreach ( $q['answers'] as $a ) {
						if ( empty( $a['text'] ) ) {
							continue;
						}
						$question['answers'][] = array(
							'id'   => sanitize_text_field( $a['id'] ?? ( 'a_' . substr( md5( uniqid( '', true ) ), 0, 6 ) ) ),
							'text' => sanitize_text_field( $a['text'] ),
							'url'  => esc_url_raw( $a['url'] ?? '' ),
						);
					}
				}
				$quiz['questions'][] = $question;
			}
		}

		return $quiz;
	}

	/* =========================================================================
	 * FRONTEND — FULL PAGE MODE
	 * ====================================================================== */

	public function maybe_full_page() {
		// Trigger: ?alvobot_quiz=<id>
		if ( empty( $_GET['alvobot_quiz'] ) ) {
			return;
		}

		$id   = sanitize_text_field( $_GET['alvobot_quiz'] );
		$quiz = $this->get_quiz( $id );

		if ( ! $quiz ) {
			return;
		}

		$this->render_full_page( $quiz );
		exit;
	}

	private function render_full_page( $quiz ) {
		$html = $this->build_html( $quiz, true );
		$css  = file_get_contents( ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/quiz-builder-v2/css/frontend.css' );
		$js   = file_get_contents( ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/quiz-builder-v2/js/frontend.js' );

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $quiz['title'] ); ?></title>
<style><?php echo $css; ?></style>
</head>
<body>
<?php echo $html; ?>
<script><?php echo $js; ?></script>
</body>
</html><?php
	}

	/* =========================================================================
	 * FRONTEND — SHORTCODE MODE
	 * ====================================================================== */

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'alvobot_quiz' );

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		$quiz = $this->get_quiz( $atts['id'] );
		if ( ! $quiz ) {
			return '<!-- Quiz not found -->';
		}

		return $this->build_html( $quiz, false );
	}

	public function maybe_enqueue_frontend() {
		// Only enqueue if the post/page contains our shortcode
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'alvobot_quiz' ) ) {
			$base = ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/quiz-builder-v2/';
			wp_enqueue_style( 'alvobot-qbv2-front', $base . 'css/frontend.css', array(), ALVOBOT_PRO_VERSION );
			wp_enqueue_script( 'alvobot-qbv2-front', $base . 'js/frontend.js', array(), ALVOBOT_PRO_VERSION, true );
		}
	}

	/* =========================================================================
	 * HTML BUILDER
	 * ====================================================================== */

	private function build_html( $quiz, $fullpage = false ) {
		$id           = esc_attr( $quiz['id'] );
		$nonce        = wp_create_nonce( 'alvobot_qbv2_public' );
		$ajax_url     = admin_url( 'admin-ajax.php' );
		$questions    = $quiz['questions'] ?? array();
		$total        = count( $questions );
		$redirect_url = esc_url( $quiz['redirect_url'] ?? '' );
		$enable_lead  = ! empty( $quiz['enable_lead'] );

		$wrapper_class = 'qbv2' . ( $fullpage ? ' qbv2--fullpage' : '' );

		ob_start();
		?>
		<div class="<?php echo $wrapper_class; ?>"
			 id="qbv2-<?php echo $id; ?>"
			 data-quiz-id="<?php echo $id; ?>"
			 data-redirect="<?php echo $redirect_url; ?>"
			 data-enable-lead="<?php echo $enable_lead ? '1' : '0'; ?>"
			 data-nonce="<?php echo esc_attr( $nonce ); ?>"
			 data-ajax="<?php echo esc_url( $ajax_url ); ?>"
			 style="--qbv2-header:<?php echo esc_attr( $quiz['color_header'] ?? '#1a3a2a' ); ?>;--qbv2-primary:<?php echo esc_attr( $quiz['color_primary'] ?? '#22c55e' ); ?>;--qbv2-accent:<?php echo esc_attr( $quiz['color_accent'] ?? '#f97316' ); ?>">

			<!-- HEADER -->
			<div class="qbv2__header">
				<?php if ( ! empty( $quiz['logo_url'] ) ) : ?>
					<img class="qbv2__logo" src="<?php echo esc_url( $quiz['logo_url'] ); ?>" alt="">
				<?php endif; ?>
			</div>

			<!-- HERO -->
			<div class="qbv2__hero">
				<?php if ( ! empty( $quiz['headline'] ) ) : ?>
					<h1 class="qbv2__headline"><?php echo esc_html( $quiz['headline'] ); ?></h1>
				<?php endif; ?>
				<?php if ( ! empty( $quiz['subheadline'] ) ) : ?>
					<p class="qbv2__subheadline"><?php echo esc_html( $quiz['subheadline'] ); ?></p>
				<?php endif; ?>
			</div>

			<!-- PROGRESS -->
			<div class="qbv2__stepper">
				<?php for ( $si = 0; $si < $total; $si++ ) : ?>
					<div class="qbv2__step<?php echo 0 === $si ? ' qbv2__step--active' : ''; ?>" data-step="<?php echo $si; ?>">
						<span class="qbv2__step-dot"><?php echo $si + 1; ?></span>
					</div>
					<?php if ( $si < $total - 1 ) : ?>
						<div class="qbv2__step-line"></div>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
			<div class="qbv2__step-label">Pergunta <span class="qbv2__step-current">1</span> de <?php echo (int) $total; ?></div>

			<!-- QUESTIONS -->
			<div class="qbv2__body">
				<?php foreach ( $questions as $qi => $q ) : ?>
					<div class="qbv2__question <?php echo 0 === $qi ? 'qbv2__question--active' : ''; ?>"
						 data-qi="<?php echo (int) $qi; ?>"
						 <?php echo 0 !== $qi ? 'aria-hidden="true"' : ''; ?>>
						<h2 class="qbv2__q-text"><?php echo esc_html( $q['text'] ); ?></h2>
						<div class="qbv2__answers">
							<?php foreach ( ( $q['answers'] ?? array() ) as $a ) : ?>
								<button class="qbv2__ans"
										data-url="<?php echo esc_url( $a['url'] ?? '' ); ?>">
									<?php echo esc_html( $a['text'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<!-- LEAD CAPTURE STEP (hidden by default) -->
				<?php if ( $enable_lead ) : ?>
					<div class="qbv2__lead" aria-hidden="true">
						<h2 class="qbv2__lead-title"><?php echo esc_html( $quiz['lead_title'] ?: 'VOCÊ ESTÁ NO CAMINHO CERTO!' ); ?></h2>
						<p class="qbv2__lead-subtitle"><?php echo esc_html( $quiz['lead_subtitle'] ?: 'Preencha as informações abaixo para receber seu resultado.' ); ?></p>
						<form class="qbv2__lead-form">
							<input type="text" name="lead_name" class="qbv2__input" placeholder="Seu nome" required>
							<input type="email" name="lead_email" class="qbv2__input" placeholder="Seu email" required>
							<button type="submit" class="qbv2__lead-btn">
								<?php echo esc_html( $quiz['lead_btn_text'] ?: 'ENVIAR RESPOSTAS' ); ?>
							</button>
						</form>
						<p class="qbv2__lead-note">Ao clicar no botão, você será redirecionado para o conteúdo.</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- FOOTER -->
			<?php if ( ! empty( $quiz['footer_text'] ) || ! empty( $quiz['footer_links'] ) ) : ?>
				<footer class="qbv2__footer">
					<?php if ( ! empty( $quiz['footer_links'] ) ) : ?>
						<div class="qbv2__footer-links">
							<?php foreach ( $quiz['footer_links'] as $link ) : ?>
								<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $link['text'] ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $quiz['footer_text'] ) ) : ?>
						<div class="qbv2__footer-text"><?php echo esc_html( $quiz['footer_text'] ); ?></div>
					<?php endif; ?>
				</footer>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
