<?php
/**
 * Smart Internal Links Module
 * Links internos inteligentes com copy gerada por IA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_Smart_Internal_Links {

	private $generator;

	public function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies() {
		$dir = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/includes/';
		require_once $dir . 'class-link-generator.php';
		require_once $dir . 'class-link-renderer.php';
		require_once $dir . 'class-content-injector.php';

		$this->generator = new AlvoBotPro_Smart_Links_Generator();
	}

	private function init_hooks() {
		// Admin
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX
		add_action( 'wp_ajax_alvobot_generate_smart_links', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_alvobot_bulk_generate_smart_links', array( $this, 'ajax_bulk_generate' ) );
		add_action( 'wp_ajax_alvobot_load_posts_for_bulk', array( $this, 'ajax_load_posts' ) );
		add_action( 'wp_ajax_alvobot_save_smart_links_settings', array( $this, 'ajax_save_settings' ) );

		// Frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Content injector is initialized in its own constructor
		new AlvoBotPro_Smart_Links_Injector();

		AlvoBotPro::debug_log( 'smart-internal-links', 'Module initialized' );
	}

	/**
	 * Settings defaults
	 */
	public function get_default_settings() {
		return array(
			'links_per_block'      => 3,
			'num_blocks'           => 3,
			'positions'            => array( 'after_first', 'middle', 'before_last' ),
			'post_types'           => array( 'post' ),
			'button_bg_color'      => '#1B3A5C',
			'button_text_color'    => '#FFFFFF',
			'button_border_color'  => '#D4A843',
			'button_border_size'   => 2,
		);
	}

	// ========================
	// Admin Menu
	// ========================

	public function add_admin_menu() {
		add_submenu_page(
			'alvobot-pro',
			'Smart Internal Links',
			'Smart Links',
			'manage_options',
			'alvobot-smart-links',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		$settings = wp_parse_args(
			get_option( 'alvobot_smart_links_settings', array() ),
			$this->get_default_settings()
		);
		include ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/templates/admin-page.php';
	}

	// ========================
	// Meta Box
	// ========================

	public function register_meta_box() {
		$settings   = wp_parse_args(
			get_option( 'alvobot_smart_links_settings', array() ),
			$this->get_default_settings()
		);
		$post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array( 'post' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'alvobot_smart_links',
				'Smart Internal Links',
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( $post ) {
		$meta    = get_post_meta( $post->ID, '_alvobot_smart_links', true );
		$enabled = ! empty( $meta['enabled'] );
		$has_links = ! empty( $meta['blocks'] );

		wp_nonce_field( 'alvobot_smart_links_meta', 'alvobot_smart_links_nonce' );
		?>
		<div id="alvobot-sil-metabox">
			<p>
				<label>
					<input type="checkbox" name="alvobot_sil_enabled" value="1" <?php checked( $enabled ); ?>>
					Ativar Smart Links
				</label>
			</p>

			<?php if ( $has_links ) : ?>
				<div class="alvobot-sil-status">
					<p style="color:#46b450;margin:4px 0;display:flex;align-items:center;gap:4px;">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
						Links gerados
					</p>
					<p class="description" style="margin:2px 0;">
						<?php echo esc_html( $meta['generated_at'] ?? '' ); ?>
					</p>
					<?php
					$total_links = 0;
					foreach ( $meta['blocks'] as $block ) {
						$total_links += count( $block['links'] ?? array() );
					}
					?>
					<p class="description">
						<?php echo esc_html( count( $meta['blocks'] ) ); ?> blocos,
						<?php echo esc_html( $total_links ); ?> links
					</p>

					<details style="margin-top:8px;">
						<summary style="cursor:pointer;font-size:12px;color:#666;">Ver links</summary>
						<div style="margin-top:6px;">
						<?php foreach ( $meta['blocks'] as $i => $block ) : ?>
							<p style="font-size:11px;font-weight:600;margin:6px 0 2px;color:#1B3A5C;">
								Bloco <?php echo esc_html( $i + 1 ); ?> (<?php echo esc_html( $block['position'] ?? '' ); ?>)
							</p>
							<?php foreach ( $block['links'] as $link ) : ?>
								<div style="font-size:11px;padding:2px 0;border-bottom:1px solid #eee;">
									<?php echo esc_html( $link['text'] ?? '' ); ?>
								</div>
							<?php endforeach; ?>
						<?php endforeach; ?>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<p style="margin-top:10px;">
				<button type="button" class="button" id="alvobot-sil-generate" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
					<?php echo $has_links ? 'Regenerar Links' : 'Gerar Links'; ?>
				</button>
				<span class="spinner" id="alvobot-sil-spinner" style="float:none;"></span>
			</p>
			<p class="description">Custo: 2 créditos</p>
			<div id="alvobot-sil-message" style="display:none;"></div>
		</div>
		<?php
	}

	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['alvobot_smart_links_nonce'] ) ||
			! wp_verify_nonce( $_POST['alvobot_smart_links_nonce'], 'alvobot_smart_links_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$meta = get_post_meta( $post_id, '_alvobot_smart_links', true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$meta['enabled'] = isset( $_POST['alvobot_sil_enabled'] ) && $_POST['alvobot_sil_enabled'] === '1';
		update_post_meta( $post_id, '_alvobot_smart_links', $meta );
	}

	// ========================
	// AJAX Handlers
	// ========================

	public function ajax_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$result = $this->generator->generate_for_post( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => 'Links gerados com sucesso!',
				'data'    => $result,
			)
		);
	}

	public function ajax_bulk_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$result = $this->generator->generate_for_post( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
					'post_id'    => $post_id,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => 'Links gerados!',
				'post_id' => $post_id,
			)
		);
	}

	public function ajax_load_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $category ) {
			$args['cat'] = $category;
		}

		if ( $language && function_exists( 'pll_get_post_language' ) ) {
			$args['lang'] = $language;
		}

		$posts = get_posts( $args );
		$data  = array();

		foreach ( $posts as $post ) {
			$meta      = get_post_meta( $post->ID, '_alvobot_smart_links', true );
			$has_links = ! empty( $meta['blocks'] );

			$data[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'url'          => get_permalink( $post->ID ),
				'has_links'    => $has_links,
				'generated_at' => $has_links ? ( $meta['generated_at'] ?? '' ) : '',
			);
		}

		wp_send_json_success( array( 'posts' => $data ) );
	}

	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$settings = array(
			'links_per_block'   => isset( $_POST['links_per_block'] ) ? absint( $_POST['links_per_block'] ) : 3,
			'num_blocks'        => isset( $_POST['num_blocks'] ) ? absint( $_POST['num_blocks'] ) : 3,
			'positions'         => isset( $_POST['positions'] ) && is_array( $_POST['positions'] )
				? array_map( 'sanitize_text_field', $_POST['positions'] )
				: array( 'after_first', 'middle', 'before_last' ),
			'post_types'        => isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? array_map( 'sanitize_text_field', $_POST['post_types'] )
				: array( 'post' ),
			'button_bg_color'      => isset( $_POST['button_bg_color'] ) ? sanitize_hex_color( $_POST['button_bg_color'] ) : '#1B3A5C',
			'button_text_color'    => isset( $_POST['button_text_color'] ) ? sanitize_hex_color( $_POST['button_text_color'] ) : '#FFFFFF',
			'button_border_color'  => isset( $_POST['button_border_color'] ) ? sanitize_hex_color( $_POST['button_border_color'] ) : '#D4A843',
			'button_border_size'   => isset( $_POST['button_border_size'] ) ? absint( $_POST['button_border_size'] ) : 2,
		);

		// Validar ranges
		$settings['links_per_block']    = max( 1, min( 5, $settings['links_per_block'] ) );
		$settings['num_blocks']         = max( 1, min( 3, $settings['num_blocks'] ) );
		$settings['button_border_size'] = max( 0, min( 6, $settings['button_border_size'] ) );

		update_option( 'alvobot_smart_links_settings', $settings );
		wp_send_json_success( array( 'message' => 'Configurações salvas!' ) );
	}

	// ========================
	// Assets
	// ========================

	public function enqueue_frontend_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$meta = get_post_meta( $post_id, '_alvobot_smart_links', true );
		if ( empty( $meta ) || empty( $meta['enabled'] ) || empty( $meta['blocks'] ) ) {
			return;
		}

		wp_enqueue_style(
			'alvobot-smart-internal-links',
			ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/smart-internal-links/assets/css/smart-internal-links.css',
			array(),
			ALVOBOT_PRO_VERSION
		);
	}

	public function enqueue_admin_assets( $hook ) {
		global $post;

		$is_settings_page = ( strpos( $hook, 'alvobot-smart-links' ) !== false );
		$is_post_edit     = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_settings_page && ! $is_post_edit ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'alvobot-smart-links-admin',
			ALVOBOT_PRO_PLUGIN_URL . 'includes/modules/smart-internal-links/assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			ALVOBOT_PRO_VERSION,
			true
		);

		wp_localize_script(
			'alvobot-smart-links-admin',
			'alvobotSmartLinks',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'alvobot_smart_links_nonce' ),
			)
		);
	}

	// ========================
	// Module Info
	// ========================

	public static function get_module_info() {
		return array(
			'name'        => 'Smart Internal Links',
			'description' => 'Links internos inteligentes com copy gerada por IA para aumentar o engajamento.',
			'version'     => '1.0.0',
		);
	}
}
