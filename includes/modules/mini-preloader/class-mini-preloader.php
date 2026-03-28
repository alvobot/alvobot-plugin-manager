<?php
/**
 * Mini Preloader Module
 *
 * Full-screen loading overlay with customizable animations, colors,
 * timing, and optional Google Ads wait logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_MiniPreloader {

	private $version;
	private $option_name = 'alvobot_pro_mini_preloader';

	public function __construct() {
		AlvoBotPro::debug_log( 'mini_preloader', 'Initializing Mini Preloader module' );
		$this->version = ALVOBOT_PRO_VERSION;
		$this->init();
		AlvoBotPro::debug_log( 'mini_preloader', 'Mini Preloader module initialized successfully' );
	}

	public function init() {
		add_action( 'wp_body_open', array( $this, 'render_preloader' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	// ─── Options ──────────────────────────────────────────────

	public function get_default_options() {
		return array(
			'message'          => __( 'Carregando...', 'alvobot-pro' ),
			'animation'        => 'infinity',      // infinity | spinner | dots | pulse | bars
			'bg_color'         => '#ffffff',
			'bg_opacity'       => 50,               // 0-100
			'text_color'       => '#000000',
			'accent_color'     => '#FF0000',
			'blur'             => 10,               // px
			'min_display_time' => 2.5,              // seconds — minimum time before fade-out starts
			'max_display_time' => 10,               // seconds — force hide after this time
			'fade_duration'    => 0.6,              // seconds
			'wait_for_ads'     => false,            // wait for Google Ads before hiding
			'show_on'          => 'all',            // all | homepage | posts | pages
		);
	}

	private function get_options() {
		return wp_parse_args( get_option( $this->option_name, array() ), $this->get_default_options() );
	}

	// ─── Sanitization ────────────────────────────────────────

	private function sanitize_options( $input ) {
		$defaults  = $this->get_default_options();
		$sanitized = array();

		$sanitized['message']          = sanitize_text_field( $input['message'] ?? $defaults['message'] );
		$sanitized['animation']        = in_array( $input['animation'] ?? '', array( 'infinity', 'spinner', 'dots', 'pulse', 'bars' ), true )
			? $input['animation'] : $defaults['animation'];
		$sanitized['bg_color']         = sanitize_hex_color( $input['bg_color'] ?? '' ) ?: $defaults['bg_color'];
		$sanitized['bg_opacity']       = max( 0, min( 100, intval( $input['bg_opacity'] ?? $defaults['bg_opacity'] ) ) );
		$sanitized['text_color']       = sanitize_hex_color( $input['text_color'] ?? '' ) ?: $defaults['text_color'];
		$sanitized['accent_color']     = sanitize_hex_color( $input['accent_color'] ?? '' ) ?: $defaults['accent_color'];
		$sanitized['blur']             = max( 0, min( 30, intval( $input['blur'] ?? $defaults['blur'] ) ) );
		$sanitized['min_display_time'] = max( 0.2, min( 15, round( floatval( str_replace( ',', '.', $input['min_display_time'] ?? $defaults['min_display_time'] ) ), 1 ) ) );
		$sanitized['max_display_time'] = max( 1, min( 30, round( floatval( str_replace( ',', '.', $input['max_display_time'] ?? $defaults['max_display_time'] ) ), 1 ) ) );
		if ( $sanitized['min_display_time'] > $sanitized['max_display_time'] ) {
			$sanitized['min_display_time'] = $sanitized['max_display_time'];
		}
		$sanitized['fade_duration']    = max( 0.1, min( 3, round( floatval( str_replace( ',', '.', $input['fade_duration'] ?? $defaults['fade_duration'] ) ), 1 ) ) );
		$sanitized['wait_for_ads']     = ! empty( $input['wait_for_ads'] );
		$sanitized['show_on']          = in_array( $input['show_on'] ?? '', array( 'all', 'homepage', 'posts', 'pages' ), true )
			? $input['show_on'] : $defaults['show_on'];

		return $sanitized;
	}

	// ─── Admin Settings Page ──────────────────────────────────

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'alvobot-pro' ) );
		}

		// Process form submission.
		$notice = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified inside the condition.
		if ( 'POST' === strtoupper( (string) filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) )
			&& isset( $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mini_preloader_settings' ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			$input     = isset( $_POST[ $this->option_name ] ) ? wp_unslash( $_POST[ $this->option_name ] ) : array();
			$sanitized = $this->sanitize_options( $input );
			update_option( $this->option_name, $sanitized );
			$notice = array( 'type' => 'success', 'message' => __( 'Configurações salvas com sucesso!', 'alvobot-pro' ) );
		}

		$options = $this->get_options();
		?>
		<style>
			/* Module-scoped form spacing — these classes are used by the base class
			   pattern but not yet defined in the global stylesheet. */
			.alvobot-form-sections {
				display: flex;
				flex-direction: column;
				gap: var(--alvobot-space-xl, 24px);
			}
			.alvobot-form-section {
				display: flex;
				flex-direction: column;
				gap: var(--alvobot-space-md, 16px);
			}
			.alvobot-section-title {
				font-size: var(--alvobot-font-size-lg, 16px);
				font-weight: 600;
				color: var(--alvobot-gray-900, #111827);
				margin: 0;
				padding-bottom: var(--alvobot-space-sm, 8px);
				border-bottom: 1px solid var(--alvobot-gray-200, #e5e7eb);
			}
			.alvobot-form-field {
				display: flex;
				flex-direction: column;
				gap: var(--alvobot-space-xs, 4px);
			}
			.alvobot-form-control {
				display: flex;
				flex-direction: column;
				gap: var(--alvobot-space-xs, 4px);
			}
			.alvobot-description {
				font-size: var(--alvobot-font-size-sm, 13px);
				color: var(--alvobot-gray-500, #6b7280);
				margin: 0;
			}
		</style>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">

				<!-- Header -->
				<div class="alvobot-admin-header">
					<div class="alvobot-header-icon">
						<i data-lucide="loader" class="alvobot-icon"></i>
					</div>
					<div class="alvobot-header-content">
						<h1><?php esc_html_e( 'Mini Preloader', 'alvobot-pro' ); ?></h1>
						<p><?php esc_html_e( 'Overlay de carregamento exibido aos visitantes enquanto a página carrega.', 'alvobot-pro' ); ?></p>
					</div>
				</div>

				<!-- Admin notice -->
				<?php if ( $notice ) : ?>
					<div class="alvobot-notice alvobot-notice-<?php echo esc_attr( $notice['type'] ); ?>">
						<p><?php echo esc_html( $notice['message'] ); ?></p>
					</div>
				<?php endif; ?>

				<!-- Main layout: form + preview -->
				<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

					<!-- Settings card -->
					<div class="alvobot-card">
						<form method="post" action="" class="alvobot-module-form">
							<?php wp_nonce_field( 'mini_preloader_settings' ); ?>

							<div class="alvobot-form-sections">

								<!-- Section: Aparência -->
								<div class="alvobot-form-section">
									<h3 class="alvobot-section-title"><?php esc_html_e( 'Aparência', 'alvobot-pro' ); ?></h3>

									<?php
									$this->render_form_field( 'message', __( 'Mensagem de carregamento', 'alvobot-pro' ), 'text', $options, array(
										'placeholder' => 'Carregando...',
									) );

									$this->render_form_field( 'animation', __( 'Estilo de animação', 'alvobot-pro' ), 'select', $options, array(
										'options' => array(
											'infinity' => 'Infinity Loop (∞)',
											'spinner'  => 'Spinner circular',
											'dots'     => 'Dots pulsantes',
											'pulse'    => 'Pulse (logotipo)',
											'bars'     => 'Barras equalizador',
										),
									) );
									?>

									<div class="alvobot-form-field">
										<label class="alvobot-form-label"><?php esc_html_e( 'Cores', 'alvobot-pro' ); ?></label>
										<div class="alvobot-form-control">
											<div style="display:flex;gap:16px;flex-wrap:wrap;">
												<?php
												$this->render_color_field( 'bg_color', __( 'Fundo', 'alvobot-pro' ), $options );
												$this->render_color_field( 'text_color', __( 'Texto', 'alvobot-pro' ), $options );
												$this->render_color_field( 'accent_color', __( 'Loader', 'alvobot-pro' ), $options );
												?>
											</div>
										</div>
									</div>

									<?php
									$this->render_form_field( 'bg_opacity', __( 'Opacidade do fundo (%)', 'alvobot-pro' ), 'range', $options, array(
										'min' => 0, 'max' => 100,
									) );

									$this->render_form_field( 'blur', __( 'Desfoque do fundo (px)', 'alvobot-pro' ), 'range', $options, array(
										'min' => 0, 'max' => 30,
									) );
									?>
								</div>

								<!-- Section: Comportamento -->
								<div class="alvobot-form-section">
									<h3 class="alvobot-section-title"><?php esc_html_e( 'Comportamento', 'alvobot-pro' ); ?></h3>

									<?php
									$this->render_form_field( 'min_display_time', __( 'Tempo mínimo', 'alvobot-pro' ), 'range_decimal', $options, array(
										'min' => 0.2, 'max' => 15, 'step' => 0.1, 'suffix' => 's',
										'description' => __( 'Tempo mínimo antes do fade-out iniciar.', 'alvobot-pro' ),
									) );

									$this->render_form_field( 'max_display_time', __( 'Tempo máximo', 'alvobot-pro' ), 'range_decimal', $options, array(
										'min' => 1, 'max' => 30, 'step' => 0.5, 'suffix' => 's',
										'description' => __( 'Força o fechamento após este tempo.', 'alvobot-pro' ),
									) );

									$this->render_form_field( 'fade_duration', __( 'Duração do fade-out', 'alvobot-pro' ), 'range_decimal', $options, array(
										'min' => 0.1, 'max' => 3, 'step' => 0.1, 'suffix' => 's',
									) );

									$this->render_form_field( 'wait_for_ads', __( 'Aguardar Google Ads (AdSense / Ad Manager) antes de esconder', 'alvobot-pro' ), 'checkbox', $options, array(
										'description' => __( 'Mantém o preloader visível até detectar um anúncio carregado na página.', 'alvobot-pro' ),
									) );
									?>
								</div>

								<!-- Section: Exibição -->
								<div class="alvobot-form-section">
									<h3 class="alvobot-section-title"><?php esc_html_e( 'Exibição', 'alvobot-pro' ); ?></h3>

									<?php
									$this->render_form_field( 'show_on', __( 'Exibir em', 'alvobot-pro' ), 'select', $options, array(
										'options' => array(
											'all'      => __( 'Todas as páginas', 'alvobot-pro' ),
											'homepage' => __( 'Somente homepage', 'alvobot-pro' ),
											'posts'    => __( 'Somente posts', 'alvobot-pro' ),
											'pages'    => __( 'Somente páginas', 'alvobot-pro' ),
										),
									) );
									?>
								</div>
							</div>

							<!-- Footer -->
							<div class="alvobot-card-footer">
								<div class="alvobot-btn-group alvobot-btn-group-right">
									<button type="button" class="alvobot-btn alvobot-btn-outline" onclick="window.history.back()">
										<?php esc_html_e( 'Voltar', 'alvobot-pro' ); ?>
									</button>
									<button type="submit" class="alvobot-btn alvobot-btn-primary">
										<?php esc_html_e( 'Salvar Configurações', 'alvobot-pro' ); ?>
									</button>
								</div>
							</div>
						</form>
					</div>

					<!-- Preview card (sticky) -->
					<div class="alvobot-card" style="position:sticky;top:40px;">
						<div class="alvobot-card-header">
							<h3 style="margin:0;"><?php esc_html_e( 'Preview', 'alvobot-pro' ); ?></h3>
						</div>
						<div id="alvobot-preloader-preview"
							style="position:relative;width:100%;height:220px;border-radius:6px;overflow:hidden;background:#f0f0f0;">
							<div id="preview-overlay" style="
								position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;
								background-color:<?php echo esc_attr( $this->hex_to_rgba( $options['bg_color'], $options['bg_opacity'] ) ); ?>;
								backdrop-filter:blur(<?php echo intval( $options['blur'] ); ?>px);
								-webkit-backdrop-filter:blur(<?php echo intval( $options['blur'] ); ?>px);
							">
								<div id="preview-animation" style="width:80px;height:40px;">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded markup; interpolated color is escaped with esc_attr() inside get_animation_svg().
									echo $this->get_animation_svg( $options['animation'], $options['accent_color'] );
									?>
								</div>
								<b id="preview-message" style="color:<?php echo esc_attr( $options['text_color'] ); ?>;font-weight:bold;font-size:12px;">
									<?php echo esc_html( $options['message'] ); ?>
								</b>
							</div>
						</div>
					</div>

				</div><!-- /grid -->
			</div>
		</div>

		<?php $this->render_live_preview_script(); ?>
		<?php
	}

	// ─── Form Field Helpers ──────────────────────────────────

	private function render_form_field( $key, $label, $type, $options, $args = array() ) {
		$value = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$name  = $this->option_name . '[' . $key . ']';
		$id    = 'mini_preloader_' . $key;
		?>
		<div class="alvobot-form-field">
			<?php if ( 'checkbox' !== $type ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>" class="alvobot-form-label">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endif; ?>

			<div class="alvobot-form-control">
				<?php
				switch ( $type ) {
					case 'text':
						printf(
							'<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="alvobot-input">',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value ),
							esc_attr( $args['placeholder'] ?? '' )
						);
						break;

					case 'number':
						printf(
							'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="alvobot-input" style="max-width:160px;">',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $value ),
							esc_attr( $args['min'] ?? '' ),
							esc_attr( $args['max'] ?? '' )
						);
						break;

					case 'range_decimal':
						$min_val  = $args['min'] ?? 0;
						$max_val  = $args['max'] ?? 99;
						$step_val = $args['step'] ?? 0.1;
						$suffix   = $args['suffix'] ?? '';
						$display  = str_replace( '.', ',', (string) $value );
						?>
						<div style="display:flex;align-items:center;gap:12px;max-width:400px;">
							<span style="font-size:12px;color:#999;min-width:28px;text-align:right;"><?php echo esc_html( str_replace( '.', ',', (string) $min_val ) ); ?></span>
							<input type="range" id="<?php echo esc_attr( $id ); ?>_range"
								value="<?php echo esc_attr( $value ); ?>"
								min="<?php echo esc_attr( $min_val ); ?>" max="<?php echo esc_attr( $max_val ); ?>"
								step="<?php echo esc_attr( $step_val ); ?>"
								style="flex:1;">
							<span style="font-size:12px;color:#999;min-width:28px;"><?php echo esc_html( str_replace( '.', ',', (string) $max_val ) ); ?></span>
							<strong id="<?php echo esc_attr( $id ); ?>_display" style="min-width:50px;text-align:center;font-size:14px;">
								<?php echo esc_html( $display . $suffix ); ?>
							</strong>
							<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
								value="<?php echo esc_attr( $value ); ?>">
						</div>
						<?php
						break;

					case 'range':
						$min = $args['min'] ?? 0;
						$max = $args['max'] ?? 100;
						?>
						<div style="display:flex;align-items:center;gap:12px;max-width:400px;">
							<input type="range" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
								value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>"
								style="flex:1;">
							<span id="<?php echo esc_attr( $id ); ?>_val" style="min-width:40px;text-align:right;font-weight:600;">
								<?php echo esc_html( $value ); ?>
							</span>
						</div>
						<?php
						break;

					case 'select':
						printf( '<select id="%s" name="%s" class="alvobot-input">', esc_attr( $id ), esc_attr( $name ) );
						foreach ( ( $args['options'] ?? array() ) as $opt_val => $opt_label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $opt_val ),
								selected( $value, $opt_val, false ),
								esc_html( $opt_label )
							);
						}
						echo '</select>';
						break;

					case 'checkbox':
						printf(
							'<label class="alvobot-checkbox-label">
								<input type="hidden" name="%1$s" value="0">
								<input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s>
								%4$s
							</label>',
							esc_attr( $name ),
							esc_attr( $id ),
							checked( $value, true, false ),
							esc_html( $label )
						);
						break;
				}

				if ( ! empty( $args['description'] ) ) :
					?>
					<p class="alvobot-description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_color_field( $key, $label, $options ) {
		$value = isset( $options[ $key ] ) ? $options[ $key ] : '#000000';
		$name  = $this->option_name . '[' . $key . ']';
		$id    = 'mini_preloader_' . $key;
		?>
		<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
			<input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>" style="width:48px;height:48px;padding:2px;border:1px solid #ddd;border-radius:8px;cursor:pointer;">
			<span style="font-size:12px;color:#666;"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	// ─── Live Preview Script ─────────────────────────────────

	private function render_live_preview_script() {
		$animation_types = array( 'infinity', 'spinner', 'dots', 'pulse', 'bars' );
		$svg_map         = array();
		foreach ( $animation_types as $type ) {
			$svg_map[ $type ] = $this->get_animation_svg( $type, '{{COLOR}}' );
		}
		?>
		<script>
		(function(){
			var svgMap = <?php echo wp_json_encode( $svg_map, JSON_HEX_TAG ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_TAG is safe for script context. ?>;
			var optName = <?php echo wp_json_encode( $this->option_name, JSON_HEX_TAG ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

			var overlay  = document.getElementById('preview-overlay');
			var animBox  = document.getElementById('preview-animation');
			var msgEl    = document.getElementById('preview-message');
			if (!overlay || !animBox || !msgEl) return;

			function field(key) {
				return document.querySelector('[name="' + optName + '[' + key + ']"]');
			}

			function val(key) {
				var el = field(key);
				if (!el) return '';
				if (el.type === 'checkbox') return el.checked;
				return el.value;
			}

			function hexToRgba(hex, opacity) {
				hex = hex.replace('#', '');
				if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
				var r = parseInt(hex.substring(0,2), 16);
				var g = parseInt(hex.substring(2,4), 16);
				var b = parseInt(hex.substring(4,6), 16);
				return 'rgba(' + r + ',' + g + ',' + b + ',' + Math.max(0, Math.min(1, opacity / 100)) + ')';
			}

			function update() {
				// Background
				overlay.style.backgroundColor = hexToRgba(val('bg_color') || '#ffffff', parseInt(val('bg_opacity')) || 50);

				// Blur
				var blur = (parseInt(val('blur')) || 0) + 'px';
				overlay.style.backdropFilter = 'blur(' + blur + ')';
				overlay.style.webkitBackdropFilter = 'blur(' + blur + ')';

				// Animation SVG
				var anim  = val('animation') || 'infinity';
				var color = val('accent_color') || '#FF0000';
				if (svgMap[anim]) {
					animBox.innerHTML = svgMap[anim].replace(/\{\{COLOR\}\}/g, color);
				}

				// Message text + color
				msgEl.textContent = val('message') || '';
				msgEl.style.color = val('text_color') || '#000000';
			}

			// Range value display
			document.querySelectorAll('input[type="range"]').forEach(function(el) {
				var display = document.getElementById(el.id + '_val');
				if (display) {
					el.addEventListener('input', function() { display.textContent = el.value; });
				}
			});

			// Listen to all relevant fields.
			var fields = ['message','animation','bg_color','bg_opacity','text_color','accent_color','blur'];
			fields.forEach(function(key) {
				var el = field(key);
				if (!el) return;
				el.addEventListener('input', update);
				el.addEventListener('change', update);
			});

			// ── Range decimal sliders — sync range ↔ hidden input ↔ display ──
			document.querySelectorAll('input[type="range"][id$="_range"]').forEach(function(rangeEl) {
				var baseId  = rangeEl.id.replace('_range', '');
				var hidden  = document.getElementById(baseId);
				var display = document.getElementById(baseId + '_display');
				if (!hidden || !display) return;

				// Read suffix from display text (e.g. "2,5s" → "s").
				var suffix = display.textContent.trim().replace(/^[\d,.\s]+/, '');

				rangeEl.addEventListener('input', function() {
					hidden.value = rangeEl.value;
					display.textContent = String(parseFloat(rangeEl.value).toFixed(1)).replace('.', ',') + suffix;
				});
			});
		})();
		</script>
		<?php
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'alvobot-mini-preloader' ) === false ) {
			return;
		}

		// Global AlvoBot admin styles.
		wp_enqueue_style(
			'alvobot-pro-styles',
			ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
			array(),
			$this->version
		);
	}

	// ─── Frontend Output ──────────────────────────────────────

	public function render_preloader() {
		if ( is_admin() ) {
			return;
		}

		$options = $this->get_options();

		if ( ! $this->should_display( $options ) ) {
			return;
		}

		$bg_rgba   = esc_attr( $this->hex_to_rgba( $options['bg_color'], $options['bg_opacity'] ) );
		$blur      = intval( $options['blur'] );
		$animation = $this->get_animation_svg( $options['animation'], $options['accent_color'] );
		$message   = esc_html( $options['message'] );
		$text_clr  = esc_attr( $options['text_color'] );
		$fade_dur_ms = round( floatval( $options['fade_duration'] ) * 1000 );

		// JS config — seconds → ms. JSON_HEX_TAG prevents </script> breakout.
		$js_config = wp_json_encode( array(
			'minTime'    => round( floatval( $options['min_display_time'] ) * 1000 ),
			'maxTime'    => round( floatval( $options['max_display_time'] ) * 1000 ),
			'fadeDur'    => $fade_dur_ms,
			'waitForAds' => (bool) $options['wait_for_ads'],
		), JSON_HEX_TAG );

		?>
		<div id="alvobot-preloader" role="progressbar" aria-label="<?php esc_attr_e( 'Carregando página', 'alvobot-pro' ); ?>" style="
			position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;
			background-color:<?php echo $bg_rgba; ?>;
			backdrop-filter:blur(<?php echo esc_attr( $blur ); ?>px);
			-webkit-backdrop-filter:blur(<?php echo esc_attr( $blur ); ?>px);
			transition:opacity <?php echo $fade_dur_ms; ?>ms ease-out;
		">
			<div style="width:120px;height:60px;">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded markup; interpolated color is escaped with esc_attr() inside get_animation_svg().
				echo $animation;
				?>
			</div>
			<?php if ( $message ) : ?>
				<b style="color:<?php echo esc_attr( $text_clr ); ?>;font-weight:bold;"><?php echo esc_html( $message ); ?></b>
			<?php endif; ?>
		</div>
		<noscript><style>#alvobot-preloader{display:none!important}</style></noscript>
		<script>
		(function(){
			var cfg = <?php echo $js_config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_TAG is safe for script context. ?>;
			var el = document.getElementById('alvobot-preloader');
			if (!el) return;

			var done = false;
			function hide() {
				if (done) return;
				done = true;
				el.style.opacity = '0';
				setTimeout(function(){ el.remove(); }, cfg.fadeDur);
			}

			// Google Publisher Tag (AdSense, Ad Manager, Google Ads) injects
			// data-google-query-id once an ad slot renders.
			// avCustomConfig.skipAds is an optional global that themes/plugins
			// can set to bypass the ads-wait logic (e.g. pages with no ad slots).
			function adsReady() {
				return document.querySelector('[data-google-query-id]') !== null
					|| (typeof avCustomConfig === 'object' && avCustomConfig.skipAds === true);
			}

			// After minTime, check if we can hide.
			setTimeout(function(){
				if (!cfg.waitForAds || adsReady()) { hide(); return; }
				// Poll for ads, but only until maxTime (handled below).
				var poll = setInterval(function(){
					if (adsReady() || done) { clearInterval(poll); hide(); }
				}, 300);
			}, cfg.minTime);

			// Absolute safety net — force hide after maxTime no matter what.
			setTimeout(hide, cfg.maxTime);
		})();
		</script>
		<?php
	}

	// ─── Helpers ──────────────────────────────────────────────

	private function should_display( $options ) {
		switch ( $options['show_on'] ) {
			case 'homepage':
				return is_front_page() || is_home();
			case 'posts':
				return is_single();
			case 'pages':
				return is_page();
			default:
				return true;
		}
	}

	private function hex_to_rgba( $hex, $opacity ) {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$a = max( 0, min( 1, $opacity / 100 ) );
		return "rgba({$r},{$g},{$b},{$a})";
	}

	private function get_animation_svg( $type, $color ) {
		$color = esc_attr( $color );

		switch ( $type ) {
			case 'spinner':
				return '<svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
					<circle cx="25" cy="25" r="20" fill="none" stroke="' . $color . '" stroke-width="4" stroke-linecap="round" stroke-dasharray="80 120" stroke-dashoffset="0">
						<animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
					</circle>
				</svg>';

			case 'dots':
				return '<svg viewBox="0 0 80 20" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
					<circle cx="15" cy="10" r="6" fill="' . $color . '"><animate attributeName="opacity" values="1;0.3;1" dur="1.2s" repeatCount="indefinite" begin="0s"/></circle>
					<circle cx="40" cy="10" r="6" fill="' . $color . '"><animate attributeName="opacity" values="1;0.3;1" dur="1.2s" repeatCount="indefinite" begin="0.2s"/></circle>
					<circle cx="65" cy="10" r="6" fill="' . $color . '"><animate attributeName="opacity" values="1;0.3;1" dur="1.2s" repeatCount="indefinite" begin="0.4s"/></circle>
				</svg>';

			case 'pulse':
				return '<svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
					<circle cx="25" cy="25" r="15" fill="' . $color . '" opacity="0.6">
						<animate attributeName="r" values="10;20;10" dur="1.5s" repeatCount="indefinite"/>
						<animate attributeName="opacity" values="0.8;0.2;0.8" dur="1.5s" repeatCount="indefinite"/>
					</circle>
					<circle cx="25" cy="25" r="8" fill="' . $color . '"/>
				</svg>';

			case 'bars':
				return '<svg viewBox="0 0 80 40" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
					<rect x="5"  y="10" width="10" height="20" rx="3" fill="' . $color . '"><animate attributeName="height" values="20;35;20" dur="1s" repeatCount="indefinite" begin="0s"/><animate attributeName="y" values="10;2;10" dur="1s" repeatCount="indefinite" begin="0s"/></rect>
					<rect x="22" y="10" width="10" height="20" rx="3" fill="' . $color . '"><animate attributeName="height" values="20;35;20" dur="1s" repeatCount="indefinite" begin="0.15s"/><animate attributeName="y" values="10;2;10" dur="1s" repeatCount="indefinite" begin="0.15s"/></rect>
					<rect x="39" y="10" width="10" height="20" rx="3" fill="' . $color . '"><animate attributeName="height" values="20;35;20" dur="1s" repeatCount="indefinite" begin="0.3s"/><animate attributeName="y" values="10;2;10" dur="1s" repeatCount="indefinite" begin="0.3s"/></rect>
					<rect x="56" y="10" width="10" height="20" rx="3" fill="' . $color . '"><animate attributeName="height" values="20;35;20" dur="1s" repeatCount="indefinite" begin="0.45s"/><animate attributeName="y" values="10;2;10" dur="1s" repeatCount="indefinite" begin="0.45s"/></rect>
				</svg>';

			case 'infinity':
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150" style="width:100%;height:100%;">
					<path fill="none" stroke="' . $color . '" stroke-width="15" stroke-linecap="round" stroke-dasharray="300 385" stroke-dashoffset="0"
						d="M275 75c0 31-27 50-50 50-58 0-92-100-150-100-28 0-50 22-50 50s23 50 50 50c58 0 92-100 150-100 24 0 50 19 50 50Z">
						<animate attributeName="stroke-dashoffset" calcMode="spline" dur="2" values="685;-685" keySplines="0 0 1 1" repeatCount="indefinite"/>
					</path>
				</svg>';
		}
	}
}
