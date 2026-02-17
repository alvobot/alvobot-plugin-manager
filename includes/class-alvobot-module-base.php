<?php
/**
 * Classe base para todos os módulos do AlvoBot Pro
 * Padroniza estrutura, UX e funcionalidades comuns
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AlvoBotPro_Module_Base {

	protected $module_id;
	protected $module_name;
	protected $module_description;
	protected $module_icon;
	protected $module_settings;
	protected $plugin_url;
	protected $plugin_dir;
	protected $version;

	public function __construct() {
		$this->plugin_url = ALVOBOT_PRO_PLUGIN_URL;
		$this->plugin_dir = ALVOBOT_PRO_PLUGIN_DIR;
		$this->version    = ALVOBOT_PRO_VERSION;

		// Define propriedades específicas do módulo
		$this->define_module_properties();

		// Inicializa apenas se o módulo estiver ativo
		if ( $this->is_module_active() ) {
			$this->init();
		}

		// Hooks comuns para todos os módulos
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . $this->module_id . '_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	/**
	 * Define as propriedades específicas do módulo
	 * Deve ser implementada por cada módulo
	 */
	abstract protected function define_module_properties();

	/**
	 * Inicialização específica do módulo
	 * Deve ser implementada por cada módulo
	 */
	abstract protected function init();

	/**
	 * Verifica se o módulo está ativo
	 */
	public function is_module_active() {
		$active_modules = get_option( 'alvobot_pro_active_modules', array() );
		return isset( $active_modules[ $this->module_id ] ) && $active_modules[ $this->module_id ];
	}

	/**
	 * Obtém as configurações do módulo
	 */
	public function get_settings() {
		if ( ! $this->module_settings ) {
			$this->module_settings = get_option( 'alvobot_pro_' . $this->module_id . '_settings', array() );
		}
		return $this->module_settings;
	}

	/**
	 * Salva as configurações do módulo
	 */
	public function save_settings( $settings ) {
		$sanitized_settings = $this->sanitize_settings( $settings );
		update_option( 'alvobot_pro_' . $this->module_id . '_settings', $sanitized_settings );
		$this->module_settings = $sanitized_settings;
		return true;
	}

	/**
	 * Sanitiza as configurações do módulo
	 * Pode ser sobrescrita por cada módulo
	 */
	protected function sanitize_settings( $settings ) {
		return $settings;
	}

	/**
	 * Enfileira assets do admin
	 */
	public function enqueue_admin_assets( $hook ) {
		// Verifica se estamos na página correta
		if ( strpos( $hook, 'alvobot-pro-' . str_replace( '_', '-', $this->module_id ) ) === false ) {
			return;
		}

		// CSS unificado sempre carregado
		wp_enqueue_style(
			'alvobot-pro-styles',
			$this->plugin_url . 'assets/css/styles.css',
			array(),
			$this->version
		);

		// CSS específico do módulo (se existir)
		$module_css = $this->get_module_asset_path( 'css', 'admin.css' );
		if ( $module_css ) {
			wp_enqueue_style(
				'alvobot-pro-' . $this->module_id,
				$module_css,
				array( 'alvobot-pro-styles' ),
				$this->version
			);
		}

		// JavaScript específico do módulo (se existir)
		$module_js = $this->get_module_asset_path( 'js', 'admin.js' );
		if ( $module_js ) {
			wp_enqueue_script(
				'alvobot-pro-' . $this->module_id,
				$module_js,
				array( 'jquery' ),
				$this->version,
				true
			);

			// Localização padrão
			$js_var_name = 'alvobot_' . str_replace( '-', '_', $this->module_id );
			wp_localize_script(
				'alvobot-pro-' . $this->module_id,
				$js_var_name,
				array(
					'ajaxurl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( $this->module_id . '_nonce' ),
					'module_id' => $this->module_id,
				)
			);
		}
	}

	/**
	 * Obtém o caminho do asset do módulo
	 */
	protected function get_module_asset_path( $type, $filename ) {
		$possible_paths = array(
			$this->plugin_dir . 'includes/modules/' . str_replace( '_', '-', $this->module_id ) . '/assets/' . $type . '/' . $filename,
			$this->plugin_dir . 'includes/modules/' . str_replace( '_', '-', $this->module_id ) . '/' . $type . '/' . $filename,
		);

		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) ) {
				return str_replace( $this->plugin_dir, $this->plugin_url, $path );
			}
		}

		return false;
	}

	/**
	 * Renderiza a página de configurações do módulo
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'alvobot-pro' ) );
		}

		// Processa formulário se enviado
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by wp_verify_nonce in same condition.
		if ( isset( $_POST['submit'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $this->module_id . '_settings' ) ) {
			$this->process_settings_form();
		}

		$this->render_settings_template();
	}

	/**
	 * Processa o formulário de configurações.
	 *
	 * IMPORTANT: This passes raw POST data to save_settings(). Modules with
	 * multi-tab forms or fields not present in every POST must either:
	 * - Override this method to merge POST with existing settings, or
	 * - Have their sanitize_settings() fall back to existing stored values
	 *   for keys absent from the input array.
	 * Failure to do so will cause missing POST keys to reset to defaults,
	 * silently wiping data saved from other tabs or internal API calls.
	 */
	protected function process_settings_form() {
		$settings = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render_settings_page before calling this method.
		unset( $settings['submit'], $settings['_wpnonce'], $settings['_wp_http_referer'] );

		if ( $this->save_settings( $settings ) ) {
			$this->add_admin_notice( __( 'Configurações salvas com sucesso!', 'alvobot-pro' ), 'success' );
		} else {
			$this->add_admin_notice( __( 'Erro ao salvar configurações.', 'alvobot-pro' ), 'error' );
		}
	}

	/**
	 * Renderiza o template de configurações
	 */
	protected function render_settings_template() {
		$settings = $this->get_settings();
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div class="alvobot-admin-header">
					<?php if ( $this->module_icon ) : ?>
					<div class="alvobot-header-icon">
						<i data-lucide="<?php echo esc_attr( $this->module_icon ); ?>" class="alvobot-icon"></i>
					</div>
					<?php endif; ?>
					<div class="alvobot-header-content">
						<h1><?php echo esc_html( $this->module_name ); ?></h1>
						<p><?php echo esc_html( $this->module_description ); ?></p>
					</div>
				</div>

				<?php $this->render_admin_notices(); ?>

				<div class="alvobot-card">
					<form method="post" action="" class="alvobot-module-form">
						<?php wp_nonce_field( $this->module_id . '_settings' ); ?>
						
						<div class="alvobot-form-sections">
							<?php $this->render_settings_sections( $settings ); ?>
						</div>

						<div class="alvobot-card-footer">
							<div class="alvobot-btn-group alvobot-btn-group-right">
								<button type="button" class="alvobot-btn alvobot-btn-outline" onclick="window.history.back()">
									Voltar
								</button>
								<button type="submit" name="submit" class="alvobot-btn alvobot-btn-primary">
									Salvar Configurações
								</button>
							</div>
						</div>
					</form>
				</div>

				<?php $this->render_additional_content( $settings ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza as seções de configurações
	 * Deve ser implementada por cada módulo
	 */
	abstract protected function render_settings_sections( $settings );

	/**
	 * Renderiza conteúdo adicional (opcional)
	 */
	protected function render_additional_content( $settings ) {
		// Pode ser sobrescrita pelos módulos
	}

	/**
	 * Adiciona uma notificação administrativa
	 */
	protected function add_admin_notice( $message, $type = 'info' ) {
		// Usa transients ao invés de sessões para evitar problemas com headers
		$notices = get_transient( 'alvobot_admin_notices_' . get_current_user_id() );
		if ( ! $notices ) {
			$notices = array();
		}

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( 'alvobot_admin_notices_' . get_current_user_id(), $notices, 60 );
	}

	/**
	 * Renderiza as notificações administrativas
	 */
	protected function render_admin_notices() {
		$notices = get_transient( 'alvobot_admin_notices_' . get_current_user_id() );

		if ( $notices ) {
			foreach ( $notices as $notice ) {
				printf(
					'<div class="alvobot-notice alvobot-notice-%s"><p>%s</p></div>',
					esc_attr( $notice['type'] ),
					esc_html( $notice['message'] )
				);
			}
			delete_transient( 'alvobot_admin_notices_' . get_current_user_id() );
		}
	}

	/**
	 * Renderiza um campo de formulário padronizado
	 */
	protected function render_form_field( $args ) {
		$defaults = array(
			'type'        => 'text',
			'id'          => '',
			'name'        => '',
			'value'       => '',
			'label'       => '',
			'description' => '',
			'options'     => array(),
			'class'       => '',
			'placeholder' => '',
			'required'    => false,
		);

		$args = wp_parse_args( $args, $defaults );

		?>
		<div class="alvobot-form-field">
			<?php if ( $args['label'] ) : ?>
				<label for="<?php echo esc_attr( $args['id'] ); ?>" class="alvobot-form-label">
					<?php echo esc_html( $args['label'] ); ?>
					<?php if ( $args['required'] ) : ?>
						<span class="alvobot-required">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
			
			<div class="alvobot-form-control">
				<?php $this->render_form_input( $args ); ?>
				
				<?php if ( $args['description'] ) : ?>
					<p class="alvobot-description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza o input do formulário baseado no tipo
	 */
	protected function render_form_input( $args ) {
		$class        = 'alvobot-input ' . $args['class'];
		$common_attrs = sprintf(
			'id="%s" name="%s" class="%s" placeholder="%s" %s',
			esc_attr( $args['id'] ),
			esc_attr( $args['name'] ),
			esc_attr( trim( $class ) ),
			esc_attr( $args['placeholder'] ),
			$args['required'] ? 'required' : ''
		);

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $common_attrs is built entirely from esc_attr() calls above.
		switch ( $args['type'] ) {
			case 'textarea':
				printf(
					'<textarea %s rows="4">%s</textarea>',
					$common_attrs,
					esc_textarea( $args['value'] )
				);
				break;

			case 'select':
				printf( '<select %s>', $common_attrs );
				foreach ( $args['options'] as $value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $value ),
						selected( $args['value'], $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label class="alvobot-checkbox-label"><input type="checkbox" %s value="1" %s> %s</label>',
					$common_attrs,
					checked( $args['value'], '1', false ),
					esc_html( $args['label'] )
				);
				break;

			case 'color':
				printf(
					'<input type="text" %s value="%s" class="alvobot-color-picker">',
					$common_attrs,
					esc_attr( $args['value'] )
				);
				break;

			case 'number':
				printf(
					'<input type="number" %s value="%s">',
					$common_attrs,
					esc_attr( $args['value'] )
				);
				break;

			default:
				printf(
					'<input type="%s" %s value="%s">',
					esc_attr( $args['type'] ),
					$common_attrs,
					esc_attr( $args['value'] )
				);
				break;
		}
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * AJAX para salvar configurações
	 */
	public function ajax_save_settings() {
		check_ajax_referer( $this->module_id . '_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada' );
		}

		$settings = $_POST;
		unset( $settings['action'], $settings['nonce'] );

		if ( $this->save_settings( $settings ) ) {
			wp_send_json_success( __( 'Configurações salvas com sucesso!', 'alvobot-pro' ) );
		} else {
			wp_send_json_error( __( 'Erro ao salvar configurações.', 'alvobot-pro' ) );
		}
	}

	/**
	 * Obtém informações do módulo para o dashboard
	 */
	public function get_module_info() {
		return array(
			'id'           => $this->module_id,
			'name'         => $this->module_name,
			'description'  => $this->module_description,
			'icon'         => $this->module_icon,
			'active'       => $this->is_module_active(),
			'settings_url' => admin_url( 'admin.php?page=alvobot-pro-' . str_replace( '_', '-', $this->module_id ) ),
		);
	}

	/**
	 * Renderiza o preview do módulo (opcional)
	 */
	public function render_preview() {
		return '<p>Preview não disponível para este módulo.</p>';
	}

	/**
	 * Valida as dependências do módulo
	 */
	public function validate_dependencies() {
		return array(
			'valid'    => true,
			'messages' => array(),
		);
	}

	/**
	 * Ativa o módulo
	 */
	public function activate_module() {
		$active_modules                     = get_option( 'alvobot_pro_active_modules', array() );
		$active_modules[ $this->module_id ] = true;
		update_option( 'alvobot_pro_active_modules', $active_modules );

		// Hook específico de ativação
		do_action( 'alvobot_pro_module_activated', $this->module_id );

		return true;
	}

	/**
	 * Desativa o módulo
	 */
	public function deactivate_module() {
		$active_modules                     = get_option( 'alvobot_pro_active_modules', array() );
		$active_modules[ $this->module_id ] = false;
		update_option( 'alvobot_pro_active_modules', $active_modules );

		// Hook específico de desativação
		do_action( 'alvobot_pro_module_deactivated', $this->module_id );

		return true;
	}
}