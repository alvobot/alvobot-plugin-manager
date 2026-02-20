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

	/**
	 * REST API namespace
	 */
	const REST_NAMESPACE = 'alvobot/v1';

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
		add_action( 'wp_ajax_alvobot_delete_smart_links', array( $this, 'ajax_delete_links' ) );
		add_action( 'wp_ajax_alvobot_toggle_smart_links', array( $this, 'ajax_toggle_links' ) );
		add_action( 'wp_ajax_alvobot_get_smart_links', array( $this, 'ajax_get_links' ) );
		add_action( 'wp_ajax_alvobot_update_smart_links', array( $this, 'ajax_update_links' ) );
		add_action( 'wp_ajax_alvobot_search_posts_for_links', array( $this, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_alvobot_get_all_post_ids_for_bulk', array( $this, 'ajax_get_all_post_ids' ) );

		// REST API
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Content injector is initialized in its own constructor
		new AlvoBotPro_Smart_Links_Injector();

		AlvoBotPro::debug_log( 'smart-internal-links', 'Module initialized' );
	}

	// ========================
	// Meta Validation
	// ========================

	/**
	 * Valida e sanitiza a estrutura do meta _alvobot_smart_links.
	 * Retorna null se a estrutura for completamente inválida.
	 */
	public static function validate_meta( $meta ) {
		if ( ! is_array( $meta ) ) {
			return null;
		}

		$validated = array(
			'enabled'      => ! empty( $meta['enabled'] ),
			'generated_at' => isset( $meta['generated_at'] ) && is_string( $meta['generated_at'] ) ? $meta['generated_at'] : '',
			'language'     => isset( $meta['language'] ) && is_string( $meta['language'] ) ? $meta['language'] : '',
			'disclaimer'   => isset( $meta['disclaimer'] ) && is_string( $meta['disclaimer'] ) ? $meta['disclaimer'] : '',
			'blocks'       => array(),
		);

		if ( ! isset( $meta['blocks'] ) || ! is_array( $meta['blocks'] ) ) {
			return $validated;
		}

		$valid_positions = array( 'after_first', 'middle', 'before_last' );

		foreach ( $meta['blocks'] as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$valid_block = array(
				'position' => isset( $block['position'] ) && in_array( $block['position'], $valid_positions, true )
					? $block['position']
					: 'after_first',
				'links'    => array(),
			);

			if ( ! isset( $block['links'] ) || ! is_array( $block['links'] ) ) {
				continue;
			}

			foreach ( $block['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$post_id = isset( $link['post_id'] ) ? absint( $link['post_id'] ) : 0;
				if ( ! $post_id ) {
					continue;
				}

				$valid_block['links'][] = array(
					'post_id' => $post_id,
					'text'    => isset( $link['text'] ) && is_string( $link['text'] ) ? sanitize_text_field( $link['text'] ) : '',
					'url'     => isset( $link['url'] ) && is_string( $link['url'] ) ? esc_url_raw( $link['url'] ) : '',
				);
			}

			if ( ! empty( $valid_block['links'] ) ) {
				$validated['blocks'][] = $valid_block;
			}
		}

		return $validated;
	}

	/**
	 * Retorna meta validado para um post, ou null se não existir/inválido.
	 */
	public static function get_validated_meta( $post_id ) {
		$meta = get_post_meta( $post_id, '_alvobot_smart_links', true );
		if ( empty( $meta ) ) {
			return null;
		}
		return self::validate_meta( $meta );
	}

	// ========================
	// Settings defaults
	// ========================

	public function get_default_settings() {
		return array(
			'links_per_block'     => 3,
			'num_blocks'          => 3,
			'positions'           => array( 'after_first', 'middle', 'before_last' ),
			'post_types'          => array( 'post' ),
			'button_bg_color'     => '#1B3A5C',
			'button_text_color'   => '#FFFFFF',
			'button_border_color' => '#D4A843',
			'button_border_size'  => 2,
		);
	}

	/**
	 * Retorna settings validados, fazendo merge com defaults.
	 */
	public function get_settings() {
		$defaults = $this->get_default_settings();
		$settings = wp_parse_args(
			get_option( 'alvobot_smart_links_settings', array() ),
			$defaults
		);

		// Garantir que positions e post_types nunca sejam arrays vazios
		if ( empty( $settings['positions'] ) || ! is_array( $settings['positions'] ) ) {
			$settings['positions'] = $defaults['positions'];
		}
		if ( empty( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) ) {
			$settings['post_types'] = $defaults['post_types'];
		}

		// Validar ranges
		$settings['links_per_block']    = max( 1, min( 5, absint( $settings['links_per_block'] ) ) );
		$settings['num_blocks']         = max( 1, min( 3, absint( $settings['num_blocks'] ) ) );
		$settings['button_border_size'] = max( 0, min( 6, absint( $settings['button_border_size'] ) ) );

		// Validar cores
		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $settings['button_bg_color'] ) ) {
			$settings['button_bg_color'] = $defaults['button_bg_color'];
		}
		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $settings['button_text_color'] ) ) {
			$settings['button_text_color'] = $defaults['button_text_color'];
		}
		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $settings['button_border_color'] ) ) {
			$settings['button_border_color'] = $defaults['button_border_color'];
		}

		// Filtrar posições válidas
		$valid_positions       = array( 'after_first', 'middle', 'before_last' );
		$settings['positions'] = array_values( array_intersect( $settings['positions'], $valid_positions ) );
		if ( empty( $settings['positions'] ) ) {
			$settings['positions'] = $defaults['positions'];
		}

		return $settings;
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
		$settings = $this->get_settings();
		include ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/templates/admin-page.php';
	}

	// ========================
	// Meta Box
	// ========================

	public function register_meta_box() {
		$settings   = $this->get_settings();
		$post_types = $settings['post_types'];

		foreach ( $post_types as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
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
	}

	public function render_meta_box( $post ) {
		$meta      = self::get_validated_meta( $post->ID );
		$enabled   = $meta && ! empty( $meta['enabled'] );
		$has_links = $meta && ! empty( $meta['blocks'] );

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
						<?php echo esc_html( $meta['generated_at'] ); ?>
					</p>
					<?php
					$total_links = 0;
					foreach ( $meta['blocks'] as $block ) {
						$total_links += count( $block['links'] );
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
								Bloco <?php echo esc_html( $i + 1 ); ?> (<?php echo esc_html( $block['position'] ); ?>)
							</p>
							<?php foreach ( $block['links'] as $link ) : ?>
								<div style="font-size:11px;padding:2px 0;border-bottom:1px solid #eee;">
									<?php echo esc_html( $link['text'] ); ?>
								</div>
							<?php endforeach; ?>
						<?php endforeach; ?>
						</div>
					</details>
				</div>
			<?php endif; ?>

			<p style="margin-top:10px;display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
				<button type="button" class="button" id="alvobot-sil-generate" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
					<?php echo $has_links ? 'Regenerar Links' : 'Gerar Links'; ?>
				</button>
				<?php if ( $has_links ) : ?>
				<button type="button" class="button sil-edit-links-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
					Editar
				</button>
				<?php endif; ?>
				<span class="spinner" id="alvobot-sil-spinner" style="float:none;"></span>
			</p>
			<p class="description">Custo: 2 créditos</p>
			<div id="alvobot-sil-message" style="display:none;"></div>
		</div>
		<?php
	}

	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['alvobot_smart_links_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alvobot_smart_links_nonce'] ) ), 'alvobot_smart_links_meta' ) ) {
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

		$meta['enabled'] = isset( $_POST['alvobot_sil_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['alvobot_sil_enabled'] ) ) === '1';
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

	private function build_bulk_query_args( $args, $status, $category, $language ) {
		if ( $category ) {
			$args['cat'] = $category;
		}

		if ( $language && function_exists( 'pll_get_post_language' ) ) {
			$args['lang'] = $language;
		}

		if ( 'missing' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_alvobot_smart_links',
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ( 'generated' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_alvobot_smart_links',
					'compare' => 'EXISTS',
				),
			);
		}

		return apply_filters( 'alvobot_smart_links_bulk_query_args', $args, $category, $language );
	}

	public function ajax_load_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'all';
		$sort     = isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : 'title_asc';
		$per_page = 50;

		$sort_map = array(
			'title_asc'  => array( 'orderby' => 'title', 'order' => 'ASC' ),
			'title_desc' => array( 'orderby' => 'title', 'order' => 'DESC' ),
			'date_desc'  => array( 'orderby' => 'date', 'order' => 'DESC' ),
			'date_asc'   => array( 'orderby' => 'date', 'order' => 'ASC' ),
		);
		$sort_cfg = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : $sort_map['title_asc'];

		$args = $this->build_bulk_query_args(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => $sort_cfg['orderby'],
				'order'          => $sort_cfg['order'],
			),
			$status,
			$category,
			$language
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;
		$data  = array();

		foreach ( $posts as $post ) {
			$meta      = self::get_validated_meta( $post->ID );
			$has_links = $meta && ! empty( $meta['blocks'] );

			$data[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'url'          => get_permalink( $post->ID ),
				'has_links'    => $has_links,
				'generated_at' => $has_links ? $meta['generated_at'] : '',
				'post_date'    => get_the_date( 'd/m/Y', $post->ID ),
			);
		}

		wp_send_json_success(
			array(
				'posts'       => $data,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	public function ajax_get_all_post_ids() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'all';

		$max_posts = 5000;

		$args = $this->build_bulk_query_args(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $max_posts,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			),
			$status,
			$category,
			$language
		);

		$query = new WP_Query( $args );

		$response = array(
			'ids'   => array_map( 'intval', $query->posts ),
			'total' => count( $query->posts ),
		);

		if ( $query->found_posts > $max_posts ) {
			$response['truncated'] = true;
			$response['total_available'] = (int) $query->found_posts;
		}

		wp_send_json_success( $response );
	}

	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$defaults = $this->get_default_settings();

		// Sanitizar positions
		$positions = array();
		if ( isset( $_POST['positions'] ) && is_array( $_POST['positions'] ) ) {
			$valid_positions = array( 'after_first', 'middle', 'before_last' );
			$raw_positions   = array_map( 'sanitize_text_field', wp_unslash( $_POST['positions'] ) );
			foreach ( $raw_positions as $pos ) {
				if ( in_array( $pos, $valid_positions, true ) ) {
					$positions[] = $pos;
				}
			}
		}
		// Garantir pelo menos uma posição
		if ( empty( $positions ) ) {
			$positions = $defaults['positions'];
		}

		// Sanitizar post_types
		$post_types = array();
		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$available_types = get_post_types( array( 'public' => true ), 'names' );
			$raw_post_types  = array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) );
			foreach ( $raw_post_types as $pt ) {
				if ( isset( $available_types[ $pt ] ) ) {
					$post_types[] = $pt;
				}
			}
		}
		// Garantir pelo menos um post type
		if ( empty( $post_types ) ) {
			$post_types = $defaults['post_types'];
		}

		// Sanitizar cores com fallback
		$bg_color     = isset( $_POST['button_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_bg_color'] ) ) : null;
		$text_color   = isset( $_POST['button_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_text_color'] ) ) : null;
		$border_color = isset( $_POST['button_border_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_border_color'] ) ) : null;

		$settings = array(
			'links_per_block'     => isset( $_POST['links_per_block'] ) ? absint( $_POST['links_per_block'] ) : 3,
			'num_blocks'          => isset( $_POST['num_blocks'] ) ? absint( $_POST['num_blocks'] ) : 3,
			'positions'           => $positions,
			'post_types'          => $post_types,
			'button_bg_color'     => $bg_color ? $bg_color : $defaults['button_bg_color'],
			'button_text_color'   => $text_color ? $text_color : $defaults['button_text_color'],
			'button_border_color' => $border_color ? $border_color : $defaults['button_border_color'],
			'button_border_size'  => isset( $_POST['button_border_size'] ) ? absint( $_POST['button_border_size'] ) : 2,
		);

		// Validar ranges
		$settings['links_per_block']    = max( 1, min( 5, $settings['links_per_block'] ) );
		$settings['num_blocks']         = max( 1, min( 3, $settings['num_blocks'] ) );
		$settings['button_border_size'] = max( 0, min( 6, $settings['button_border_size'] ) );

		update_option( 'alvobot_smart_links_settings', $settings );

		/**
		 * Fires after Smart Links settings are saved.
		 *
		 * @param array $settings The saved settings.
		 */
		do_action( 'alvobot_smart_links_settings_saved', $settings );

		wp_send_json_success( array( 'message' => 'Configurações salvas!' ) );
	}

	/**
	 * AJAX: Deletar links gerados de um post
	 */
	public function ajax_delete_links() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post não encontrado' ) );
		}

		delete_post_meta( $post_id, '_alvobot_smart_links' );

		wp_send_json_success(
			array(
				'message' => 'Links removidos com sucesso!',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * AJAX: Ativar/desativar links de um post sem regenerar
	 */
	public function ajax_toggle_links() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$enabled = isset( $_POST['enabled'] ) && sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) === '1';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post não encontrado' ) );
		}

		$meta = self::get_validated_meta( $post_id );
		if ( ! $meta || empty( $meta['blocks'] ) ) {
			wp_send_json_error( array( 'message' => 'Nenhum link gerado para este post' ) );
		}

		$meta['enabled'] = $enabled;
		update_post_meta( $post_id, '_alvobot_smart_links', $meta );

		wp_send_json_success(
			array(
				'message' => $enabled ? 'Links ativados!' : 'Links desativados!',
				'post_id' => $post_id,
				'enabled' => $enabled,
			)
		);
	}

	/**
	 * AJAX: Buscar links de um post para edição
	 */
	public function ajax_get_links() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post não encontrado' ) );
		}

		$meta = self::get_validated_meta( $post_id );
		if ( ! $meta ) {
			wp_send_json_error( array( 'message' => 'Nenhum link gerado para este post' ) );
		}

		// Enriquecer links com títulos dos posts alvo
		foreach ( $meta['blocks'] as &$block ) {
			foreach ( $block['links'] as &$link ) {
				$target_post = get_post( $link['post_id'] );
				$link['target_title'] = $target_post ? $target_post->post_title : '(Post removido)';
				$link['url']          = $target_post ? get_permalink( $target_post->ID ) : '';
			}
		}
		unset( $block, $link );

		wp_send_json_success(
			array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'meta'       => $meta,
			)
		);
	}

	/**
	 * AJAX: Atualizar links editados manualmente
	 */
	public function ajax_update_links() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Post ID inválido' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post não encontrado' ) );
		}

		$existing_meta = self::get_validated_meta( $post_id );
		if ( ! $existing_meta ) {
			wp_send_json_error( array( 'message' => 'Nenhum link gerado para este post' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We JSON-decode and then sanitize each field individually below.
		$raw_blocks = isset( $_POST['blocks'] ) ? json_decode( wp_unslash( $_POST['blocks'] ), true ) : null;
		if ( ! is_array( $raw_blocks ) ) {
			wp_send_json_error( array( 'message' => 'Dados de blocos inválidos' ) );
		}

		$valid_positions = array( 'after_first', 'middle', 'before_last' );
		$updated_blocks  = array();

		foreach ( $raw_blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['links'] ) || ! is_array( $block['links'] ) ) {
				continue;
			}

			$position = isset( $block['position'] ) && in_array( $block['position'], $valid_positions, true )
				? $block['position']
				: 'after_first';

			$valid_links = array();
			foreach ( $block['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$link_post_id = isset( $link['post_id'] ) ? absint( $link['post_id'] ) : 0;
				if ( ! $link_post_id ) {
					continue;
				}

				$link_post = get_post( $link_post_id );
				if ( ! $link_post || $link_post->post_status !== 'publish' ) {
					continue;
				}

				$text = isset( $link['text'] ) && is_string( $link['text'] ) ? trim( $link['text'] ) : '';
				if ( empty( $text ) ) {
					continue;
				}

				$valid_links[] = array(
					'post_id' => $link_post_id,
					'text'    => sanitize_text_field( $text ),
					'url'     => get_permalink( $link_post_id ),
				);
			}

			if ( ! empty( $valid_links ) ) {
				$updated_blocks[] = array(
					'position' => $position,
					'links'    => $valid_links,
				);
			}
		}

		if ( empty( $updated_blocks ) ) {
			wp_send_json_error( array( 'message' => 'Nenhum link válido encontrado. Cada link precisa de um texto e um post de destino.' ) );
		}

		$disclaimer = isset( $_POST['disclaimer'] ) ? sanitize_text_field( wp_unslash( $_POST['disclaimer'] ) ) : $existing_meta['disclaimer'];
		$enabled    = isset( $_POST['enabled'] ) ? ( sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) === '1' ) : $existing_meta['enabled'];

		$meta = array(
			'enabled'      => $enabled,
			'generated_at' => $existing_meta['generated_at'],
			'language'     => $existing_meta['language'],
			'disclaimer'   => $disclaimer,
			'blocks'       => $updated_blocks,
		);

		update_post_meta( $post_id, '_alvobot_smart_links', $meta );

		/**
		 * Fires after links are manually updated for a post.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $meta    The saved meta data.
		 */
		do_action( 'alvobot_smart_links_updated', $post_id, $meta );

		wp_send_json_success(
			array(
				'message' => 'Links atualizados com sucesso!',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * AJAX: Buscar posts para autocomplete (adicionar link)
	 */
	public function ajax_search_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		check_ajax_referer( 'alvobot_smart_links_nonce', 'nonce' );

		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : array();

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'posts' => array() ) );
		}

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => $search,
			'post__not_in'   => $exclude,
			'no_found_rows'  => true,
		);

		$posts  = get_posts( $args );
		$result = array();

		foreach ( $posts as $p ) {
			$result[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'url'   => get_permalink( $p->ID ),
				'type'  => get_post_type_object( $p->post_type )->labels->singular_name,
			);
		}

		wp_send_json_success( array( 'posts' => $result ) );
	}

	// ========================
	// REST API Endpoints
	// ========================

	public function register_rest_routes() {
		// GET /alvobot/v1/smart-links/settings
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_settings' ),
					'permission_callback' => array( $this, 'rest_manage_options_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_update_settings' ),
					'permission_callback' => array( $this, 'rest_manage_options_check' ),
				),
			)
		);

		// POST /alvobot/v1/smart-links/generate
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_generate' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value > 0;
						},
					),
				),
			)
		);

		// POST /alvobot/v1/smart-links/bulk-generate
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/bulk-generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_bulk_generate' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
				'args'                => array(
					'post_ids' => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'validate_callback' => function ( $value ) {
							if ( ! is_array( $value ) || empty( $value ) ) {
								return false;
							}
							foreach ( $value as $id ) {
								if ( ! is_numeric( $id ) || intval( $id ) <= 0 ) {
									return false;
								}
							}
							return true;
						},
						'sanitize_callback' => function ( $value ) {
							return array_map( 'absint', (array) $value );
						},
					),
				),
			)
		);

		// GET /alvobot/v1/smart-links/posts
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_posts' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
				'args'                => array(
					'category' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'language' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /alvobot/v1/smart-links/post/{id}
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/post/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_post_links' ),
					'permission_callback' => array( $this, 'rest_manage_options_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_post_links' ),
					'permission_callback' => array( $this, 'rest_manage_options_check' ),
				),
			)
		);

		// POST /alvobot/v1/smart-links/post/{id}/toggle
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/post/(?P<id>\d+)/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_toggle_post_links' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
				'args'                => array(
					'enabled' => array(
						'required' => true,
						'type'     => 'boolean',
					),
				),
			)
		);

		// PUT /alvobot/v1/smart-links/post/{id}
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/post/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rest_update_post_links' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
			)
		);

		// GET /alvobot/v1/smart-links/search-posts
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/search-posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_search_posts' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
				'args'                => array(
					'search'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'exclude' => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// GET /alvobot/v1/smart-links/stats
		register_rest_route(
			self::REST_NAMESPACE,
			'/smart-links/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_stats' ),
				'permission_callback' => array( $this, 'rest_manage_options_check' ),
			)
		);
	}

	/**
	 * Permission callback: manage_options
	 */
	public function rest_manage_options_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST: Get settings
	 */
	public function rest_get_settings( $request ) {
		return rest_ensure_response( $this->get_settings() );
	}

	/**
	 * REST: Update settings
	 */
	public function rest_update_settings( $request ) {
		$defaults = $this->get_default_settings();
		$params   = $request->get_json_params();

		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		// Sanitizar positions
		$positions = array();
		if ( isset( $params['positions'] ) && is_array( $params['positions'] ) ) {
			$valid_positions = array( 'after_first', 'middle', 'before_last' );
			foreach ( $params['positions'] as $pos ) {
				if ( in_array( sanitize_text_field( $pos ), $valid_positions, true ) ) {
					$positions[] = sanitize_text_field( $pos );
				}
			}
		}
		if ( empty( $positions ) ) {
			$positions = $defaults['positions'];
		}

		// Sanitizar post_types
		$post_types = array();
		if ( isset( $params['post_types'] ) && is_array( $params['post_types'] ) ) {
			$available_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $params['post_types'] as $pt ) {
				$pt = sanitize_text_field( $pt );
				if ( isset( $available_types[ $pt ] ) ) {
					$post_types[] = $pt;
				}
			}
		}
		if ( empty( $post_types ) ) {
			$post_types = $defaults['post_types'];
		}

		$bg_color     = isset( $params['button_bg_color'] ) ? sanitize_hex_color( $params['button_bg_color'] ) : null;
		$text_color   = isset( $params['button_text_color'] ) ? sanitize_hex_color( $params['button_text_color'] ) : null;
		$border_color = isset( $params['button_border_color'] ) ? sanitize_hex_color( $params['button_border_color'] ) : null;

		$settings = array(
			'links_per_block'     => max( 1, min( 5, isset( $params['links_per_block'] ) ? absint( $params['links_per_block'] ) : 3 ) ),
			'num_blocks'          => max( 1, min( 3, isset( $params['num_blocks'] ) ? absint( $params['num_blocks'] ) : 3 ) ),
			'positions'           => $positions,
			'post_types'          => $post_types,
			'button_bg_color'     => $bg_color ? $bg_color : $defaults['button_bg_color'],
			'button_text_color'   => $text_color ? $text_color : $defaults['button_text_color'],
			'button_border_color' => $border_color ? $border_color : $defaults['button_border_color'],
			'button_border_size'  => max( 0, min( 6, isset( $params['button_border_size'] ) ? absint( $params['button_border_size'] ) : 2 ) ),
		);

		update_option( 'alvobot_smart_links_settings', $settings );
		do_action( 'alvobot_smart_links_settings_saved', $settings );

		return rest_ensure_response(
			array(
				'message'  => 'Configurações salvas!',
				'settings' => $settings,
			)
		);
	}

	/**
	 * REST: Generate links for a single post
	 */
	public function rest_generate( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$result = $this->generator->generate_for_post( $post_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				),
				400
			);
		}

		return rest_ensure_response(
			array(
				'message' => 'Links gerados com sucesso!',
				'data'    => $result,
			)
		);
	}

	/**
	 * REST: Bulk generate links for multiple posts (sequential, returns results per post)
	 */
	public function rest_bulk_generate( $request ) {
		$post_ids = $request->get_param( 'post_ids' );

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return new WP_REST_Response(
				array( 'message' => 'post_ids deve ser um array de IDs.' ),
				400
			);
		}

		// Limitar a 50 por requisição para evitar timeout
		$post_ids = array_slice( array_map( 'absint', $post_ids ), 0, 50 );

		$results = array();
		foreach ( $post_ids as $post_id ) {
			$result = $this->generator->generate_for_post( $post_id );

			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'post_id' => $post_id,
					'success' => false,
					'message' => $result->get_error_message(),
				);
			} else {
				$results[] = array(
					'post_id' => $post_id,
					'success' => true,
					'message' => 'Links gerados!',
				);
			}
		}

		$success_count = count(
			array_filter(
				$results,
				function ( $r ) {
					return $r['success'];
				}
			)
		);

		return rest_ensure_response(
			array(
				'message' => "{$success_count}/" . count( $results ) . ' posts processados.',
				'results' => $results,
			)
		);
	}

	/**
	 * REST: Get posts with pagination and link status
	 */
	public function rest_get_posts( $request ) {
		$category = $request->get_param( 'category' );
		$language = $request->get_param( 'language' );
		$page     = max( 1, $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, $request->get_param( 'per_page' ) ) );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $category ) {
			$args['cat'] = $category;
		}

		if ( $language && function_exists( 'pll_get_post_language' ) ) {
			$args['lang'] = $language;
		}

		$args  = apply_filters( 'alvobot_smart_links_bulk_query_args', $args, $category, $language );
		$query = new WP_Query( $args );
		$data  = array();

		foreach ( $query->posts as $post ) {
			$meta      = self::get_validated_meta( $post->ID );
			$has_links = $meta && ! empty( $meta['blocks'] );

			$data[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'url'          => get_permalink( $post->ID ),
				'has_links'    => $has_links,
				'enabled'      => $meta ? $meta['enabled'] : false,
				'generated_at' => $has_links ? $meta['generated_at'] : '',
			);
		}

		return rest_ensure_response(
			array(
				'posts'       => $data,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * REST: Get links for a specific post
	 */
	public function rest_get_post_links( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_REST_Response(
				array( 'message' => 'Post não encontrado.' ),
				404
			);
		}

		$meta = self::get_validated_meta( $post_id );

		return rest_ensure_response(
			array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'has_links'  => $meta && ! empty( $meta['blocks'] ),
				'meta'       => $meta,
			)
		);
	}

	/**
	 * REST: Delete links for a specific post
	 */
	public function rest_delete_post_links( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_REST_Response(
				array( 'message' => 'Post não encontrado.' ),
				404
			);
		}

		delete_post_meta( $post_id, '_alvobot_smart_links' );

		return rest_ensure_response(
			array(
				'message' => 'Links removidos com sucesso!',
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * REST: Toggle links enabled/disabled for a post
	 */
	public function rest_toggle_post_links( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$enabled = (bool) $request->get_param( 'enabled' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_REST_Response(
				array( 'message' => 'Post não encontrado.' ),
				404
			);
		}

		$meta = self::get_validated_meta( $post_id );
		if ( ! $meta || empty( $meta['blocks'] ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Nenhum link gerado para este post.' ),
				404
			);
		}

		$meta['enabled'] = $enabled;
		update_post_meta( $post_id, '_alvobot_smart_links', $meta );

		return rest_ensure_response(
			array(
				'message' => $enabled ? 'Links ativados!' : 'Links desativados!',
				'post_id' => $post_id,
				'enabled' => $enabled,
			)
		);
	}

	/**
	 * REST: Update links for a specific post
	 */
	public function rest_update_post_links( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_REST_Response(
				array( 'message' => 'Post não encontrado.' ),
				404
			);
		}

		$existing_meta = self::get_validated_meta( $post_id );
		if ( ! $existing_meta ) {
			return new WP_REST_Response(
				array( 'message' => 'Nenhum link gerado para este post.' ),
				404
			);
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$raw_blocks = isset( $params['blocks'] ) && is_array( $params['blocks'] ) ? $params['blocks'] : null;
		if ( ! $raw_blocks ) {
			return new WP_REST_Response(
				array( 'message' => 'Dados de blocos inválidos.' ),
				400
			);
		}

		$valid_positions = array( 'after_first', 'middle', 'before_last' );
		$updated_blocks  = array();

		foreach ( $raw_blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['links'] ) || ! is_array( $block['links'] ) ) {
				continue;
			}

			$position = isset( $block['position'] ) && in_array( $block['position'], $valid_positions, true )
				? $block['position']
				: 'after_first';

			$valid_links = array();
			foreach ( $block['links'] as $link ) {
				if ( ! is_array( $link ) ) {
					continue;
				}

				$link_post_id = isset( $link['post_id'] ) ? absint( $link['post_id'] ) : 0;
				if ( ! $link_post_id ) {
					continue;
				}

				$link_post = get_post( $link_post_id );
				if ( ! $link_post || $link_post->post_status !== 'publish' ) {
					continue;
				}

				$text = isset( $link['text'] ) && is_string( $link['text'] ) ? trim( $link['text'] ) : '';
				if ( empty( $text ) ) {
					continue;
				}

				$valid_links[] = array(
					'post_id' => $link_post_id,
					'text'    => sanitize_text_field( $text ),
					'url'     => get_permalink( $link_post_id ),
				);
			}

			if ( ! empty( $valid_links ) ) {
				$updated_blocks[] = array(
					'position' => $position,
					'links'    => $valid_links,
				);
			}
		}

		if ( empty( $updated_blocks ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Nenhum link válido encontrado.' ),
				400
			);
		}

		$meta = array(
			'enabled'      => isset( $params['enabled'] ) ? (bool) $params['enabled'] : $existing_meta['enabled'],
			'generated_at' => $existing_meta['generated_at'],
			'language'     => $existing_meta['language'],
			'disclaimer'   => isset( $params['disclaimer'] ) ? sanitize_text_field( $params['disclaimer'] ) : $existing_meta['disclaimer'],
			'blocks'       => $updated_blocks,
		);

		update_post_meta( $post_id, '_alvobot_smart_links', $meta );
		do_action( 'alvobot_smart_links_updated', $post_id, $meta );

		return rest_ensure_response(
			array(
				'message' => 'Links atualizados com sucesso!',
				'post_id' => $post_id,
				'meta'    => $meta,
			)
		);
	}

	/**
	 * REST: Search posts for adding links
	 */
	public function rest_search_posts( $request ) {
		$search  = $request->get_param( 'search' );
		$exclude = $request->get_param( 'exclude' );

		if ( strlen( $search ) < 2 ) {
			return rest_ensure_response( array( 'posts' => array() ) );
		}

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => $search,
			'post__not_in'   => ! empty( $exclude ) ? array_map( 'absint', $exclude ) : array(),
			'no_found_rows'  => true,
		);

		$posts  = get_posts( $args );
		$result = array();

		foreach ( $posts as $p ) {
			$result[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'url'   => get_permalink( $p->ID ),
				'type'  => get_post_type_object( $p->post_type )->labels->singular_name,
			);
		}

		return rest_ensure_response( array( 'posts' => $result ) );
	}

	/**
	 * REST: Get statistics about Smart Links usage
	 */
	public function rest_get_stats( $request ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_with_links = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_alvobot_smart_links'"
		);

		// Contar posts com links ativos (meta contém enabled:true)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_metas = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_alvobot_smart_links'"
		);

		$enabled_count  = 0;
		$disabled_count = 0;
		$total_blocks   = 0;
		$total_links    = 0;

		foreach ( $all_metas as $row ) {
			$meta = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $meta ) ) {
				continue;
			}

			if ( ! empty( $meta['enabled'] ) ) {
				++$enabled_count;
			} else {
				++$disabled_count;
			}

			if ( isset( $meta['blocks'] ) && is_array( $meta['blocks'] ) ) {
				$total_blocks += count( $meta['blocks'] );
				foreach ( $meta['blocks'] as $block ) {
					if ( isset( $block['links'] ) && is_array( $block['links'] ) ) {
						$total_links += count( $block['links'] );
					}
				}
			}
		}

		return rest_ensure_response(
			array(
				'total_posts_with_links' => (int) $total_with_links,
				'enabled_count'          => $enabled_count,
				'disabled_count'         => $disabled_count,
				'total_blocks'           => $total_blocks,
				'total_links'            => $total_links,
			)
		);
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

		$meta = self::get_validated_meta( $post_id );
		if ( ! $meta || empty( $meta['enabled'] ) || empty( $meta['blocks'] ) ) {
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
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'alvobot_smart_links_nonce' ),
				'rest_url'   => rest_url( self::REST_NAMESPACE . '/smart-links/' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		// Modal CSS for post edit screens (settings page has its own <style> block)
		if ( $is_post_edit ) {
			wp_add_inline_style( 'wp-color-picker', $this->get_edit_modal_css() );
		}
	}

	/**
	 * CSS do modal de edição de links (usado no post editor).
	 */
	private function get_edit_modal_css() {
		return '
.sil-edit-modal{position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;display:flex;align-items:center;justify-content:center;animation:silModalFadeIn .15s ease-out}
.sil-modal-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5)}
.sil-modal-container{position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,.1);width:90vw;max-width:680px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;animation:silModalSlideIn .2s ease-out}
.sil-modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;background:#f9fafb}
.sil-modal-title{margin:0;font-size:16px;font-weight:600;color:#18181b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sil-modal-close-btn{background:none;border:none;font-size:22px;cursor:pointer;color:#a1a1aa;padding:4px 8px;border-radius:4px;line-height:1}
.sil-modal-close-btn:hover{background:#e5e7eb;color:#344054}
.sil-modal-body{padding:20px;overflow-y:auto;flex:1}
.sil-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e5e7eb;background:#f9fafb}
.sil-edit-block{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:12px}
.sil-edit-block-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.sil-edit-block-title{font-size:13px;font-weight:600;color:#52525b}
.sil-edit-position{border:1px solid #d4d4d8;border-radius:4px;background:#fff;color:#52525b;font-size:12px;padding:2px 6px}
.sil-edit-link-row{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #e5e7eb}
.sil-edit-link-row:last-child{border-bottom:none}
.sil-edit-link-target{flex:0 0 auto;max-width:180px;overflow:hidden}
.sil-link-post-label{font-size:11px;color:#a1a1aa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.sil-edit-link-text{flex:1;min-width:0}
.sil-link-text-input{width:100%;padding:6px 10px;border:1px solid #d4d4d8;border-radius:6px;font-size:13px;color:#344054;background:#fff;box-sizing:border-box}
.sil-link-text-input:focus{border-color:#fbbf24;outline:none;box-shadow:0 0 0 2px rgba(251,191,36,.2)}
.sil-remove-link-btn{flex-shrink:0;background:none;border:none;cursor:pointer;color:#d4d4d8;padding:4px;border-radius:4px;display:flex;align-items:center}
.sil-remove-link-btn:hover{color:#f63d68;background:#fee2e2}
.sil-add-link-btn{display:inline-flex;align-items:center;gap:4px;margin-top:8px;padding:6px 12px;background:none;border:1px dashed #d4d4d8;border-radius:6px;color:#6b7280;font-size:12px;cursor:pointer}
.sil-add-link-btn:hover{border-color:#fbbf24;color:#344054}
.sil-post-search-wrap{position:relative}
.sil-post-search-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d4d4d8;border-radius:6px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);z-index:10;max-height:200px;overflow-y:auto;margin-top:4px}
.sil-search-result{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;cursor:pointer}
.sil-search-result:hover{background:#f9fafb}
.sil-search-result-title{font-size:13px;color:#344054;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-right:8px}
.sil-search-result-type{font-size:11px;color:#d4d4d8;flex-shrink:0;background:#f3f4f6;padding:1px 6px;border-radius:3px}
@keyframes silModalFadeIn{from{opacity:0}to{opacity:1}}
@keyframes silModalSlideIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}
@media(max-width:600px){.sil-modal-container{width:96vw;max-height:92vh}.sil-edit-link-row{flex-wrap:wrap}.sil-edit-link-target{max-width:100%;flex:0 0 100%}}
';
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
