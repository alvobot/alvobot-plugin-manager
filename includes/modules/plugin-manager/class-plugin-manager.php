<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_PluginManager {
	private $namespace = 'alvobot-pro/v1';

	private $blocked_plugin_patterns = array(
		'hostinger',
	);

	/** Máximo de requisições permitidas na janela de tempo */
	private $rate_limit_max = 30;

	/** Janela de tempo em segundos para rate limiting */
	private $rate_limit_window = 60;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_notices', array( $this, 'show_connection_warnings' ) );
		add_action( 'admin_notices', array( $this, 'show_blocked_plugins_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_retry_connection' ) );
		add_action( 'admin_init', array( $this, 'auto_deactivate_blocked_plugins' ), 1 );
		add_action( 'wp_ajax_alvobot_fix_htaccess_auth', array( $this, 'ajax_fix_htaccess_auth' ) );
		// Defer init to plugins_loaded so wp_generate_password() is available
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		// Generate site token if not exists
		if ( ! get_option( 'alvobot_site_token' ) ) {
			$token = wp_generate_password( 32, false );
			update_option( 'alvobot_site_token', $token );
			AlvoBotPro::debug_log( 'plugin-manager', 'Token de site gerado com sucesso.' );
		} else {
			AlvoBotPro::debug_log( 'plugin-manager', 'Token de site já existe.' );
		}

		// Register REST API endpoints
		// add_action('rest_api_init', array($this, 'register_rest_routes'));
	}

	public function show_connection_warnings() {
		// Só mostra em páginas admin
		if ( ! is_admin() ) {
			return;
		}

		// Verifica o status da conexão
		$status = get_option( 'alvobot_connection_status' );

		// Se não há status ou está conectado, não mostra nada
		if ( ! $status || ! is_array( $status ) || $status['status'] !== 'error' ) {
			return;
		}

		// Monta a mensagem baseada no tipo de erro
		$error_type = isset( $status['error_type'] ) ? $status['error_type'] : 'unknown';
		$message    = isset( $status['message'] ) ? $status['message'] : 'Erro desconhecido na conexão.';

		$extra_info   = '';
		$retry_button = '';

		// Mensagens específicas por tipo de erro
		if ( $error_type === 'app_password_failed' ) {
			$extra_info   = '<p><strong>O que fazer:</strong></p>';
			$extra_info  .= '<ul style="margin-left: 20px;">';
			$extra_info  .= '<li>Verifique se há plugins de segurança ativos (como Wordfence, iThemes Security, All In One WP Security)</li>';
			$extra_info  .= '<li>Verifique se Application Passwords estão habilitados no WordPress (requer HTTPS)</li>';
			$extra_info  .= '<li>Desative temporariamente plugins de segurança e tente novamente</li>';
			$extra_info  .= '<li>Verifique se seu site está usando HTTPS (Application Passwords requerem SSL)</li>';
			$extra_info  .= '</ul>';
			$retry_button = '<p><a href="' . wp_nonce_url( admin_url( 'admin.php?page=alvobot-pro&retry_connection=1' ), 'alvobot_retry_connection' ) . '" class="button button-primary">Tentar Novamente</a></p>';
		} elseif ( $error_type === 'registration_failed' ) {
			$http_status = isset( $status['http_status'] ) ? $status['http_status'] : null;

			$extra_info  = '<p><strong>O que fazer:</strong></p>';
			$extra_info .= '<ul style="margin-left: 20px;">';

			if ( $http_status == 404 ) {
				$extra_info .= '<li><strong>Este site não está cadastrado no painel AlvoBot</strong></li>';
				$extra_info .= '<li>Acesse <a href="https://app.alvobot.com" target="_blank">https://app.alvobot.com</a> e cadastre este domínio</li>';
				$extra_info .= '<li>Domínio atual: <code>' . esc_html( get_site_url() ) . '</code></li>';
				$extra_info .= '<li>Após cadastrar, clique em "Tentar Novamente" abaixo</li>';
			} elseif ( $http_status == 401 ) {
				$extra_info .= '<li>Application Password gerado pode estar inválido</li>';
				$extra_info .= '<li>Verifique se há plugins bloqueando autenticação</li>';
				$extra_info .= '<li>Tente novamente após verificar as configurações</li>';
			} else {
				$extra_info .= '<li><strong>Plugins de segurança podem estar bloqueando a conexão</strong></li>';
				$extra_info .= '<li>Verifique se há plugins como Wordfence Security, iThemes Security ou similar ativos</li>';
				$extra_info .= '<li>Desative temporariamente plugins de segurança e tente novamente</li>';
				$extra_info .= '<li>Verifique sua conexão com a internet</li>';
				$extra_info .= '<li>Verifique se o firewall não está bloqueando conexões externas</li>';
				if ( $http_status ) {
					$extra_info .= '<li>Código HTTP: <code>' . esc_html( $http_status ) . '</code></li>';
				}
			}

			$extra_info  .= '</ul>';
			$retry_button = '<p><a href="' . wp_nonce_url( admin_url( 'admin.php?page=alvobot-pro&retry_connection=1' ), 'alvobot_retry_connection' ) . '" class="button button-primary">Tentar Novamente</a></p>';
		} elseif ( $error_type === 'user_creation_failed' ) {
			$extra_info   = '<p><strong>O que fazer:</strong></p>';
			$extra_info  .= '<ul style="margin-left: 20px;">';
			$extra_info  .= '<li>Verifique se há conflitos com outros plugins</li>';
			$extra_info  .= '<li>Verifique as permissões do banco de dados</li>';
			$extra_info  .= '</ul>';
			$retry_button = '<p><a href="' . wp_nonce_url( admin_url( 'admin.php?page=alvobot-pro&retry_connection=1' ), 'alvobot_retry_connection' ) . '" class="button button-primary">Tentar Novamente</a></p>';
		}

		// Add diagnostic link to all error types
		$diagnostic_link = '<p style="margin-top: 8px;"><a href="' . esc_url( admin_url( 'admin.php?page=alvobot-pro' ) ) . '">Abrir Diagnóstico do Sistema</a> para uma verificação completa de todos os pré-requisitos.</p>';

		// Exibe o aviso com estilo de erro
		?>
		<div class="notice notice-error is-dismissible" style="border-left-color: #dc3232;">
			<h3 style="margin-top: 10px;"><i data-lucide="alert-triangle" class="alvobot-icon" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;color:#dc3232;"></i> AlvoBot Pro - Falha na Conexão</h3>
			<p><strong><?php echo esc_html( $message ); ?></strong></p>
			<?php echo wp_kses_post( $extra_info ); ?>
			<?php echo wp_kses_post( $retry_button ); ?>
			<?php echo wp_kses_post( $diagnostic_link ); ?>
			<p style="margin-bottom: 10px;"><small>Erro detectado em: <?php echo esc_html( gmdate( 'd/m/Y H:i:s', $status['timestamp'] ) ); ?></small></p>
		</div>
		<?php
	}

	public function handle_retry_connection() {
		// Verifica se foi solicitado retry
		if ( ! isset( $_GET['retry_connection'] ) || sanitize_text_field( wp_unslash( $_GET['retry_connection'] ) ) != '1' ) {
			return;
		}

		// Verifica permissões
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verifica nonce para proteção CSRF
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'alvobot_retry_connection' ) ) {
			return;
		}

		// Executa o activate novamente
		AlvoBotPro::debug_log( 'plugin-manager', 'Tentativa manual de reconexão iniciada' );
		$this->activate();

		// Redireciona para limpar a URL
		wp_safe_redirect( admin_url( 'admin.php?page=alvobot-pro' ) );
		exit;
	}

	public function auto_deactivate_blocked_plugins() {
		$active_plugins = get_option( 'active_plugins', array() );

		if ( empty( $active_plugins ) ) {
			return;
		}

		$plugins_to_deactivate = array();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $active_plugins as $plugin ) {
			if ( stripos( $plugin, 'alvobot' ) !== false ) {
				continue;
			}

			foreach ( $this->blocked_plugin_patterns as $pattern ) {
				if ( stripos( $plugin, $pattern ) !== false ) {
					$plugins_to_deactivate[] = $plugin;
					AlvoBotPro::debug_log( 'plugin-manager', sprintf( 'Plugin incompatível detectado: %s (padrão: %s)', $plugin, $pattern ) );
					break;
				}
			}
		}

		if ( ! empty( $plugins_to_deactivate ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$deactivated_names = array();
			foreach ( $plugins_to_deactivate as $plugin ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
				$name        = $plugin;
				if ( file_exists( $plugin_file ) ) {
					$data = get_plugin_data( $plugin_file, false, false );
					if ( ! empty( $data['Name'] ) ) {
						$name = $data['Name'];
					}
				}
				deactivate_plugins( $plugin, true );
				$deactivated_names[] = $name;
				AlvoBotPro::debug_log( 'plugin-manager', sprintf( 'Plugin desativado automaticamente: %s', $plugin ) );
			}

			// Store which plugins were deactivated so we can show a notice
			update_option( 'alvobot_blocked_plugins_notice', $deactivated_names );
		}
	}

	/**
	 * Shows admin notice when blocked plugins have been deactivated.
	 * Notice is dismissible and only shows once after deactivation.
	 */
	public function show_blocked_plugins_notice() {
		$deactivated = get_option( 'alvobot_blocked_plugins_notice' );
		if ( empty( $deactivated ) || ! is_array( $deactivated ) ) {
			return;
		}

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin_list = implode( ', ', array_map( 'esc_html', $deactivated ) );
		?>
		<div class="notice notice-warning is-dismissible" data-alvobot-dismiss="blocked-plugins">
			<h3 style="margin-top: 10px;">
				<i data-lucide="shield-alert" class="alvobot-icon" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;color:#dba617;"></i>
				AlvoBot Pro - Plugins Incompatíveis Desativados
			</h3>
			<p>
				<?php
				printf(
					/* translators: %s: comma-separated list of plugin names */
					esc_html__( 'Os seguintes plugins foram desativados automaticamente por serem incompatíveis com o AlvoBot Pro: %s', 'alvobot-pro' ),
					'<strong>' . wp_kses_post( $plugin_list ) . '</strong>'
				);
				?>
			</p>
			<p style="color: #666; font-size: 12px;">
				<?php esc_html_e( 'Esses plugins interferem no gerenciamento remoto e no registro do site. Se precisar reativá-los, desative o AlvoBot Pro primeiro.', 'alvobot-pro' ); ?>
			</p>
		</div>
		<?php
		// Clear the notice after showing it once
		delete_option( 'alvobot_blocked_plugins_notice' );
	}

	public function activate() {
		AlvoBotPro::debug_log( 'plugin-manager', 'Iniciando ativação do Plugin Manager' );

		// Limpa status de erro anterior
		delete_option( 'alvobot_connection_status' );

		// Create alvobot user
		$user = $this->create_alvobot_user();

		if ( $user && ! is_wp_error( $user ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Usuário alvobot criado com sucesso' );
			// Generate app password
			$app_password = $this->generate_alvobot_app_password( $user );

			if ( $app_password ) {
				AlvoBotPro::debug_log( 'plugin-manager', 'Senha de aplicativo gerada, iniciando registro no servidor' );
				// Register site with the central server
				$result = $this->register_site( $app_password );
				if ( $result ) {
					AlvoBotPro::debug_log( 'plugin-manager', 'Registro no servidor concluído com sucesso' );
					// Marca como conectado com sucesso
					update_option(
						'alvobot_connection_status',
						array(
							'status'    => 'connected',
							'timestamp' => time(),
							'message'   => 'Conexão estabelecida com sucesso',
						)
					);
				} else {
					AlvoBotPro::debug_log( 'plugin-manager', 'ERRO: Falha no registro do servidor' );
					update_option(
						'alvobot_connection_status',
						array(
							'status'     => 'error',
							'error_type' => 'registration_failed',
							'timestamp'  => time(),
							'message'    => 'Falha ao registrar no servidor central. Verifique sua conexão com a internet.',
						)
					);
				}
			} else {
				AlvoBotPro::debug_log( 'plugin-manager', 'ERRO: Falha ao gerar senha de aplicativo' );
				update_option(
					'alvobot_connection_status',
					array(
						'status'     => 'error',
						'error_type' => 'app_password_failed',
						'timestamp'  => time(),
						'message'    => 'Falha ao gerar Application Password. Verifique o Diagnóstico do Sistema para mais detalhes.',
					)
				);
			}
		} else {
			AlvoBotPro::debug_log( 'plugin-manager', 'ERRO: Falha ao criar usuário alvobot' );
			if ( is_wp_error( $user ) ) {
				AlvoBotPro::debug_log( 'plugin-manager', 'Erro WP: ' . $user->get_error_message() );
			}
			update_option(
				'alvobot_connection_status',
				array(
					'status'     => 'error',
					'error_type' => 'user_creation_failed',
					'timestamp'  => time(),
					'message'    => 'Falha ao criar usuário alvobot. ' . ( is_wp_error( $user ) ? $user->get_error_message() : 'Erro desconhecido' ),
				)
			);
		}
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alvobot-pro' ) );
		}

		// Get current status
		$alvobot_user      = get_user_by( 'login', 'alvobot' );
		$site_token        = get_option( 'alvobot_site_token' );
		$connection_status = get_option( 'alvobot_connection_status' );

		// Check if Application Password exists
		$has_app_password   = false;
		$app_password_count = 0;
		if ( $alvobot_user ) {
			if ( ! class_exists( 'WP_Application_Passwords' ) ) {
				require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
			}
			$passwords          = WP_Application_Passwords::get_user_application_passwords( $alvobot_user->ID );
			$app_password_count = count( $passwords );
			$has_app_password   = $app_password_count > 0;
		}

		// Check if we need to trigger activation manually
		if ( isset( $_POST['action'] ) ) {
			$pm_post_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			if ( $pm_post_action === 'activate_plugin_manager' ) {
				check_admin_referer( 'activate_plugin_manager' );
				$this->activate();
			} elseif ( $pm_post_action === 'retry_registration' ) {
				check_admin_referer( 'alvobot_retry_registration', 'alvobot_retry_registration_nonce' );
				AlvoBotPro::debug_log( 'plugin-manager', ' Iniciando processo de refazer registro' );

				// Gerar nova senha de aplicativo e registrar novamente
				if ( $alvobot_user ) {
					AlvoBotPro::debug_log( 'plugin-manager', ' Usuário alvobot encontrado, gerando nova senha de aplicativo' );
					$app_password = $this->generate_alvobot_app_password( $alvobot_user );
					if ( $app_password ) {
						AlvoBotPro::debug_log( 'plugin-manager', ' Nova senha de aplicativo gerada, registrando no servidor' );
						$result = $this->register_site( $app_password );
						if ( $result ) {
							AlvoBotPro::debug_log( 'plugin-manager', ' Refazer registro: SUCESSO' );
							add_action(
								'admin_notices',
								function () {
									echo '<div class="notice notice-success"><p>' . esc_html__( 'Registro refeito com sucesso!', 'alvobot-pro' ) . '</p></div>';
								}
							);
						} else {
							AlvoBotPro::debug_log( 'plugin-manager', ' Refazer registro: ERRO no servidor' );
							add_action(
								'admin_notices',
								function () {
									echo '<div class="notice notice-error"><p>' . esc_html__( 'Erro ao refazer o registro. Verifique os logs para mais detalhes.', 'alvobot-pro' ) . '</p></div>';
								}
							);
						}
					} else {
						AlvoBotPro::debug_log( 'plugin-manager', ' Refazer registro: ERRO ao gerar senha de aplicativo' );
						add_action(
							'admin_notices',
							function () {
								echo '<div class="notice notice-error"><p>' . esc_html__( 'Erro ao gerar nova senha de aplicativo.', 'alvobot-pro' ) . '</p></div>';
							}
						);
					}
				} else {
					AlvoBotPro::debug_log( 'plugin-manager', ' Refazer registro: ERRO - usuário alvobot não encontrado' );
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-error"><p>' . esc_html__( 'Usuário alvobot não encontrado. Execute a inicialização primeiro.', 'alvobot-pro' ) . '</p></div>';
						}
					);
				}
			}

			// Refresh status after any action
			$alvobot_user      = get_user_by( 'login', 'alvobot' );
			$site_token        = get_option( 'alvobot_site_token' );
			$connection_status = get_option( 'alvobot_connection_status' );

			// Recalculate Application Password status
			$has_app_password   = false;
			$app_password_count = 0;
			if ( $alvobot_user ) {
				if ( ! class_exists( 'WP_Application_Passwords' ) ) {
					require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
				}
				$passwords          = WP_Application_Passwords::get_user_application_passwords( $alvobot_user->ID );
				$app_password_count = count( $passwords );
				$has_app_password   = $app_password_count > 0;
			}
		}

		// Debug: Verify variables exist
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				'[Plugin Manager] Variables: has_app_password=' . ( $has_app_password ? 'true' : 'false' ) .
					', app_password_count=' . $app_password_count .
					', connection_status=' . ( $connection_status ? 'set' : 'not set' )
			);
		}

		// Include the settings page template
		include plugin_dir_path( __FILE__ ) . 'templates/plugin-manager-settings.php';
	}

	private function create_alvobot_user() {
		$username = 'alvobot';
		$email    = 'alvobot@alvobot.com.br';

		// Check if user exists
		$existing_user = get_user_by( 'login', $username );

		if ( $existing_user ) {
			// Check if admin
			if ( ! in_array( 'administrator', $existing_user->roles ) ) {
				$existing_user->set_role( 'administrator' );
			}
			return $existing_user;
		}

		// Check if email is already in use by another user
		$email_user = get_user_by( 'email', $email );
		if ( $email_user ) {
			AlvoBotPro::debug_log( 'plugin-manager', sprintf( 'Email %s já em uso pelo usuário %s (ID: %d). Usando email alternativo.', $email, $email_user->user_login, $email_user->ID ) );
			$email = 'alvobot-' . wp_generate_password( 6, false, false ) . '@alvobot.com.br';
		}

		// Create new user
		$userdata = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		);

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Erro ao criar usuário: ' . $user_id->get_error_message() );
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_lookup_failed', 'Usuário criado mas não encontrado no banco de dados.' );
		}

		return $user;
	}

	public function generate_alvobot_app_password( $user ) {
		if ( ! $user ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: Usuário não encontrado para gerar senha de aplicativo.' );
			return false;
		}

		// Check WordPress version supports Application Passwords (5.6+)
		global $wp_version;
		if ( version_compare( $wp_version, '5.6', '<' ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: WordPress ' . $wp_version . ' não suporta Application Passwords (requer 5.6+).' );
			update_option(
				'alvobot_connection_status',
				array(
					'status'     => 'error',
					'error_type' => 'app_password_failed',
					'timestamp'  => time(),
					'message'    => 'WordPress ' . $wp_version . ' não suporta Application Passwords. Atualize para a versão 5.6 ou superior.',
				)
			);
			return false;
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
		}

		// Check if Application Passwords are available (can be disabled by plugins or non-HTTPS)
		if ( ! wp_is_application_passwords_available() ) {
			$reason = 'Motivo desconhecido.';
			if ( ! is_ssl() && ! wp_is_application_passwords_available_for_user( $user ) ) {
				$reason = 'O site não usa HTTPS. Application Passwords requerem SSL.';
			} elseif ( ! wp_is_application_passwords_available_for_user( $user ) ) {
				$reason = 'Application Passwords estão desabilitados para este usuário (possivelmente por um plugin de segurança).';
			} else {
				$reason = 'Application Passwords estão desabilitados (possivelmente por um plugin de segurança ou configuração do servidor).';
			}
			AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: Application Passwords não disponíveis. ' . $reason );
			update_option(
				'alvobot_connection_status',
				array(
					'status'     => 'error',
					'error_type' => 'app_password_failed',
					'timestamp'  => time(),
					'message'    => 'Application Passwords não disponíveis. ' . $reason,
				)
			);
			return false;
		}

		// Remove existing passwords
		WP_Application_Passwords::delete_all_application_passwords( $user->ID );

		// Create new password
		$app_pass = WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array(
				'name'   => 'AlvoBot Plugin Manager',
				'app_id' => 'alvobot-plugin-manager',
			)
		);

		if ( is_wp_error( $app_pass ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: Erro ao gerar senha de aplicativo: ' . $app_pass->get_error_message() );
			return false;
		}

		if ( ! isset( $app_pass[0] ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: Senha de aplicativo não foi retornada corretamente.' );
			return false;
		}

		AlvoBotPro::debug_log( 'plugin-manager', 'Plugin Manager: Senha de aplicativo gerada com sucesso' );
		return $app_pass;
	}

	public function register_site( $app_password = null ) {
		AlvoBotPro::debug_log( 'plugin-manager', ' Iniciando registro do site no servidor central' );

		if ( ! defined( 'ALVOBOT_SERVER_URL' ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', ' ERRO: ALVOBOT_SERVER_URL não está definida' );
			return false;
		}

		AlvoBotPro::debug_log( 'plugin-manager', ' ALVOBOT_SERVER_URL definida: ' . ALVOBOT_SERVER_URL );

		// Carrega as funções necessárias do WordPress
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins        = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$site_token = get_option( 'alvobot_site_token' );
		$data       = array(
			'action'     => 'register_site',
			'site_url'   => get_site_url(),
			'token'      => $site_token,
			'wp_version' => get_bloginfo( 'version' ),
			'plugins'    => array_keys( $plugins ),
		);

		AlvoBotPro::debug_log( 'plugin-manager', ' Token do site: ' . ( $site_token ? 'PRESENTE' : 'VAZIO' ) );

		if ( $app_password ) {
			// Pegar apenas a string da senha (primeiro elemento do array)
			if ( is_array( $app_password ) && ! empty( $app_password[0] ) ) {
				$data['app_password'] = $app_password[0];
			} else {
				$data['app_password'] = $app_password;
			}
			AlvoBotPro::debug_log( 'plugin-manager', ' Senha de aplicativo incluída nos dados' );
		}

		AlvoBotPro::debug_log( 'plugin-manager', ' Dados a serem enviados: ' . wp_json_encode( $this->sanitize_log_data( $data ) ) );
		AlvoBotPro::debug_log( 'plugin-manager', ' URL do servidor: ' . ALVOBOT_SERVER_URL );

		$args = array(
			'body'      => wp_json_encode( $data ),
			'headers'   => array(
				'Content-Type' => 'application/json',
			),
			'timeout'   => 30, // Aumentado para 30 segundos
			'sslverify' => true,
		);

		AlvoBotPro::debug_log( 'plugin-manager', ' Enviando requisição POST para o servidor' );
		$response = wp_remote_post( ALVOBOT_SERVER_URL, $args );

		if ( is_wp_error( $response ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', ' ERRO ao registrar site: ' . $response->get_error_message() );
			AlvoBotPro::debug_log( 'plugin-manager', ' Código do erro: ' . $response->get_error_code() );

			update_option(
				'alvobot_connection_status',
				array(
					'status'     => 'error',
					'error_type' => 'registration_failed',
					'timestamp'  => time(),
					'message'    => 'Erro de conexão: ' . $response->get_error_message(),
				)
			);

			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		AlvoBotPro::debug_log( 'plugin-manager', ' Resposta do servidor:' );
		AlvoBotPro::debug_log( 'plugin-manager', ' - Status: ' . $status_code );
		AlvoBotPro::debug_log( 'plugin-manager', ' - Headers: ' . wp_json_encode( $this->sanitize_log_data( (array) $headers ) ) );
		AlvoBotPro::debug_log( 'plugin-manager', ' - Body: ' . $this->truncate_log_string( $body, 800 ) );

		// Decodifica a resposta para obter detalhes do erro
		$json_response = null;
		try {
			$json_response = json_decode( $body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				AlvoBotPro::debug_log( 'plugin-manager', ' AVISO: Resposta não é um JSON válido: ' . json_last_error_msg() );
			} else {
				AlvoBotPro::debug_log( 'plugin-manager', ' Resposta decodificada: ' . wp_json_encode( $this->sanitize_log_data( $json_response ) ) );
			}
		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'plugin-manager', ' AVISO ao decodificar resposta: ' . $e->getMessage() );
		}

		// Verifica erros de status HTTP
		if ( $status_code < 200 || $status_code >= 300 ) {
			AlvoBotPro::debug_log( 'plugin-manager', ' ERRO: Resposta inválida do servidor (Status ' . $status_code . ')' );

			// Salva detalhes do erro
			$error_message = 'Falha no registro com servidor central';
			$error_details = '';

			if ( $json_response && isset( $json_response['error'] ) ) {
				$error_details = $json_response['error'];

				// Erros específicos
				if ( $status_code == 404 ) {
					$error_message  = 'Projeto não encontrado no servidor AlvoBot';
					$error_details .= ' - Verifique se o domínio está cadastrado no painel AlvoBot.';
				} elseif ( $status_code == 401 ) {
					$error_message  = 'Autenticação falhou';
					$error_details .= ' - Application Password pode estar inválido.';
				} elseif ( $status_code == 400 ) {
					$error_message = 'Dados inválidos enviados';
				}
			}

			update_option(
				'alvobot_connection_status',
				array(
					'status'          => 'error',
					'error_type'      => 'registration_failed',
					'timestamp'       => time(),
					'http_status'     => $status_code,
					'message'         => $error_message . ( $error_details ? ': ' . $error_details : '' ),
					'server_response' => $body,
				)
			);

			return false;
		}

		// Verifica se há erro na resposta mesmo com status 200
		if ( $json_response && isset( $json_response['error'] ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', ' ERRO na resposta: ' . $json_response['error'] );

			update_option(
				'alvobot_connection_status',
				array(
					'status'          => 'error',
					'error_type'      => 'registration_failed',
					'timestamp'       => time(),
					'http_status'     => $status_code,
					'message'         => 'Erro do servidor: ' . $json_response['error'],
					'server_response' => $body,
				)
			);

			return false;
		}

		return true;
	}

	public function register_rest_routes() {
		// Endpoint para comandos de plugins
		register_rest_route(
			$this->namespace,
			'/plugins/commands',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_command' ),
				'permission_callback' => array( $this, 'verify_token' ),
			)
		);

		// Endpoint para health check diagnóstico
		register_rest_route(
			$this->namespace,
			'/health-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_health_check' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Endpoint para testar se o Authorization header chega ao PHP
		register_rest_route(
			$this->namespace,
			'/auth-header-test',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_auth_header_test' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST endpoint that echoes whether the Authorization header was received.
	 * Used by the health check to verify header passthrough.
	 */
	public function rest_auth_header_test( $request ) {
		$auth_header = $request->get_header( 'authorization' );

		return new WP_REST_Response(
			array(
				'received'              => ! empty( $auth_header ),
				'header_value'          => $auth_header ? substr( $auth_header, 0, 20 ) . '...' : null,
				'http_authorization'    => isset( $_SERVER['HTTP_AUTHORIZATION'] ),
				'redirect_authorization' => isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ),
			),
			200
		);
	}

	/**
	 * REST endpoint for health check (admin-only).
	 */
	public function rest_health_check() {
		return new WP_REST_Response( $this->run_health_check(), 200 );
	}

	/**
	 * Runs a comprehensive health check of all prerequisites for the AlvoBot connection.
	 *
	 * @return array Associative array of check results.
	 */
	public function run_health_check() {
		$checks = array();

		// 1. WordPress version
		global $wp_version;
		$checks['wp_version'] = array(
			'label'  => 'WordPress 5.6+',
			'status' => version_compare( $wp_version, '5.6', '>=' ) ? 'ok' : 'error',
			'value'  => $wp_version,
			'help'   => version_compare( $wp_version, '5.6', '<' ) ? 'Atualize o WordPress para 5.6+ para suportar Application Passwords.' : '',
		);

		// 2. HTTPS
		$is_ssl = is_ssl() || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) === 'https' );
		$checks['https'] = array(
			'label'  => 'HTTPS Ativo',
			'status' => $is_ssl ? 'ok' : 'warning',
			'value'  => $is_ssl ? 'Sim' : 'Não',
			'help'   => ! $is_ssl ? 'Application Passwords requerem HTTPS. Ative SSL no seu hosting.' : '',
		);

		// 3. Application Passwords available
		$app_pass_available = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
		$checks['app_passwords'] = array(
			'label'  => 'Application Passwords Disponíveis',
			'status' => $app_pass_available ? 'ok' : 'error',
			'value'  => $app_pass_available ? 'Sim' : 'Não',
			'help'   => ! $app_pass_available ? 'Verifique se plugins de segurança não estão desabilitando Application Passwords.' : '',
		);

		// 4. Alvobot user exists
		$alvobot_user = get_user_by( 'login', 'alvobot' );
		$checks['user'] = array(
			'label'  => 'Usuário AlvoBot',
			'status' => $alvobot_user ? 'ok' : 'error',
			'value'  => $alvobot_user ? 'ID: ' . $alvobot_user->ID : 'Não existe',
			'help'   => ! $alvobot_user ? 'Clique em "Inicializar Sistema" ou "Refazer Registro" para criar.' : '',
		);

		// 5. App Password exists for user
		$has_app_pass = false;
		if ( $alvobot_user ) {
			if ( ! class_exists( 'WP_Application_Passwords' ) ) {
				require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
			}
			$passwords    = WP_Application_Passwords::get_user_application_passwords( $alvobot_user->ID );
			$has_app_pass = count( $passwords ) > 0;
		}
		$checks['app_password_exists'] = array(
			'label'  => 'Application Password Gerada',
			'status' => $has_app_pass ? 'ok' : 'error',
			'value'  => $has_app_pass ? 'Sim' : 'Não',
			'help'   => ! $has_app_pass ? 'Clique em "Refazer Registro" para gerar uma nova.' : '',
		);

		// 6. Site token exists
		$site_token = get_option( 'alvobot_site_token' );
		$checks['token'] = array(
			'label'  => 'Token do Site',
			'status' => ! empty( $site_token ) ? 'ok' : 'error',
			'value'  => ! empty( $site_token ) ? 'Configurado' : 'Ausente',
			'help'   => empty( $site_token ) ? 'O token será gerado automaticamente na próxima inicialização.' : '',
		);

		// 7. REST API accessible
		$rest_url    = get_rest_url( null, 'wp/v2/' );
		$rest_check  = wp_remote_get(
			$rest_url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);
		$rest_status = 'ok';
		$rest_value  = 'Acessível';
		$rest_help   = '';
		if ( is_wp_error( $rest_check ) ) {
			$rest_status = 'error';
			$rest_value  = 'Erro: ' . $rest_check->get_error_message();
			$rest_help   = 'A REST API não está acessível. Verifique se não há plugins bloqueando ou permalinks desabilitados.';
		} elseif ( wp_remote_retrieve_response_code( $rest_check ) >= 400 ) {
			$rest_status = 'warning';
			$rest_value  = 'HTTP ' . wp_remote_retrieve_response_code( $rest_check );
			$rest_help   = 'A REST API retornou erro. Verifique plugins de segurança ou configurações do servidor.';
		}
		$checks['rest_api'] = array(
			'label'  => 'REST API Acessível',
			'status' => $rest_status,
			'value'  => $rest_value,
			'help'   => $rest_help,
		);

		// 8. Outgoing HTTP connections (can reach Supabase)
		$outgoing_check = wp_remote_get(
			'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/',
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);
		$outgoing_ok = ! is_wp_error( $outgoing_check );
		$checks['outgoing_http'] = array(
			'label'  => 'Conexão com Servidor AlvoBot',
			'status' => $outgoing_ok ? 'ok' : 'error',
			'value'  => $outgoing_ok ? 'Conectado' : 'Falha',
			'help'   => ! $outgoing_ok ? 'O servidor não consegue conectar ao AlvoBot. Verifique firewall, proxy ou restrições do hosting. Erro: ' . ( is_wp_error( $outgoing_check ) ? $outgoing_check->get_error_message() : '' ) : '',
		);

		// 9. Authorization header passthrough (real self-test)
		$auth_test_result = $this->test_auth_header_passthrough();
		$checks['auth_header'] = $auth_test_result;

		// 10. Check for known conflicting plugins
		$conflicting_plugins = $this->detect_conflicting_plugins();
		$has_conflicts       = ! empty( $conflicting_plugins );
		$checks['conflicting_plugins'] = array(
			'label'  => 'Plugins de Segurança',
			'status' => $has_conflicts ? 'warning' : 'ok',
			'value'  => $has_conflicts ? implode( ', ', $conflicting_plugins ) : 'Nenhum detectado',
			'help'   => $has_conflicts ? 'Esses plugins podem interferir com Application Passwords e REST API. Se houver problemas de conexão, tente desativá-los temporariamente.' : '',
		);

		// 11. Connection status
		$conn_status = get_option( 'alvobot_connection_status' );
		$conn_ok     = is_array( $conn_status ) && isset( $conn_status['status'] ) && $conn_status['status'] === 'connected';
		$checks['connection'] = array(
			'label'  => 'Status da Conexão',
			'status' => $conn_ok ? 'ok' : ( $conn_status ? 'error' : 'warning' ),
			'value'  => $conn_ok ? 'Conectado' : ( $conn_status && is_array( $conn_status ) ? ( $conn_status['message'] ?? 'Erro' ) : 'Não inicializado' ),
			'help'   => '',
		);

		// 12. Site URL consistency check
		$site_url = get_site_url();
		$home_url = get_home_url();
		$urls_match = untrailingslashit( $site_url ) === untrailingslashit( $home_url );
		$checks['url_consistency'] = array(
			'label'  => 'Consistência de URL',
			'status' => $urls_match ? 'ok' : 'warning',
			'value'  => $urls_match ? $site_url : "site_url: {$site_url} | home_url: {$home_url}",
			'help'   => ! $urls_match ? 'site_url e home_url são diferentes. Isso pode causar problemas na validação do domínio.' : '',
		);

		return $checks;
	}

	/**
	 * Detects known security plugins that may interfere with Application Passwords or REST API.
	 *
	 * @return array List of conflicting plugin names.
	 */
	private function detect_conflicting_plugins() {
		$active_plugins = get_option( 'active_plugins', array() );
		$known_conflicts = array(
			'wordfence'          => 'Wordfence Security',
			'better-wp-security' => 'iThemes Security',
			'ithemes-security'   => 'iThemes Security',
			'all-in-one-wp-security' => 'All In One WP Security',
			'sucuri-scanner'     => 'Sucuri Security',
			'disable-json-api'   => 'Disable REST API',
			'disable-wp-rest-api' => 'Disable WP REST API',
			'really-simple-ssl'  => 'Really Simple SSL',
			'shield-security'    => 'Shield Security',
			'cerber'             => 'WP Cerber Security',
		);

		$found = array();
		foreach ( $active_plugins as $plugin ) {
			$plugin_lower = strtolower( $plugin );
			foreach ( $known_conflicts as $pattern => $name ) {
				if ( strpos( $plugin_lower, $pattern ) !== false ) {
					$found[] = $name;
					break;
				}
			}
		}

		return array_unique( $found );
	}

	/**
	 * Tests whether the Authorization header actually reaches PHP by making a self-request
	 * to the auth-header-test endpoint with a dummy Authorization header.
	 *
	 * @return array Health check result array.
	 */
	private function test_auth_header_passthrough() {
		$test_url = get_rest_url( null, $this->namespace . '/auth-header-test' );
		$response = wp_remote_get(
			$test_url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Basic ' . base64_encode( 'alvobot-test:health-check-probe' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'label'  => 'Authorization Header',
				'status' => 'warning',
				'value'  => 'Não foi possível testar',
				'help'   => 'Erro ao testar: ' . $response->get_error_message(),
				'fixable' => false,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return array(
				'label'  => 'Authorization Header',
				'status' => 'warning',
				'value'  => 'Teste inconclusivo (HTTP ' . $status_code . ')',
				'help'   => 'O endpoint de teste retornou status ' . $status_code . '. Verifique se a REST API está acessível.',
				'fixable' => false,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['received'] ) ) {
			return array(
				'label'  => 'Authorization Header',
				'status' => 'ok',
				'value'  => 'Funcionando',
				'help'   => '',
				'fixable' => false,
			);
		}

		// Header NOT received — check if we can fix it
		$htaccess_path = $this->get_htaccess_path();
		$can_fix       = $htaccess_path && is_writable( $htaccess_path );

		return array(
			'label'  => 'Authorization Header',
			'status' => 'error',
			'value'  => 'Bloqueado pelo servidor',
			'help'   => $can_fix
				? 'O servidor está removendo o header Authorization. Clique em "Corrigir" para adicionar a regra necessária ao .htaccess automaticamente.'
				: 'O servidor está removendo o header Authorization. Adicione manualmente ao .htaccess: RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
			'fixable' => $can_fix,
		);
	}

	/**
	 * Returns the path to the .htaccess file if available.
	 *
	 * @return string|false Path to .htaccess or false.
	 */
	private function get_htaccess_path() {
		$htaccess = ABSPATH . '.htaccess';
		return file_exists( $htaccess ) ? $htaccess : false;
	}

	/**
	 * Fixes the .htaccess to pass Authorization headers to PHP.
	 * Uses WordPress's insert_with_markers() for safe, idempotent insertion.
	 *
	 * @return true|WP_Error
	 */
	public function fix_htaccess_auth() {
		$htaccess_path = $this->get_htaccess_path();

		if ( ! $htaccess_path ) {
			// .htaccess doesn't exist yet — try to create it
			$htaccess_path = ABSPATH . '.htaccess';
		}

		if ( file_exists( $htaccess_path ) && ! is_writable( $htaccess_path ) ) {
			return new WP_Error(
				'htaccess_not_writable',
				'O arquivo .htaccess não tem permissão de escrita. Altere as permissões ou adicione a regra manualmente.'
			);
		}

		$rules = array(
			'RewriteEngine On',
			'RewriteCond %{HTTP:Authorization} ^(.*)',
			'RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]',
		);

		$result = insert_with_markers( $htaccess_path, 'AlvoBot Authorization Fix', $rules );

		if ( ! $result ) {
			return new WP_Error(
				'htaccess_write_failed',
				'Falha ao escrever no .htaccess. Verifique as permissões do arquivo.'
			);
		}

		AlvoBotPro::debug_log( 'plugin-manager', 'Authorization header fix applied to .htaccess successfully.' );

		return true;
	}

	/**
	 * AJAX handler for fixing .htaccess Authorization header passthrough.
	 */
	public function ajax_fix_htaccess_auth() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada.' ) );
		}

		if ( ! check_ajax_referer( 'alvobot_fix_htaccess', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ) );
		}

		$result = $this->fix_htaccess_auth();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Re-test to confirm the fix worked
		$retest = $this->test_auth_header_passthrough();

		wp_send_json_success(
			array(
				'message'    => 'Regra adicionada ao .htaccess com sucesso!',
				'new_status' => $retest['status'],
			)
		);
	}

	public function verify_token( $request ) {
		$header_token = $request->get_header( 'token' );
		$params       = $request->get_json_params();
		$body_token   = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';

		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Token verification - Header Token: ' . ( $header_token ? 'Present' : 'Missing' ) );
		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Token verification - Body Token: ' . ( $body_token ? 'Present' : 'Missing' ) );
		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Token verification - Stored Token: ' . ( get_option( 'alvobot_site_token' ) ? 'Present' : 'Missing' ) );

		// Aceita o token tanto no header quanto no body
		$token = ! empty( $header_token ) ? $header_token : $body_token;

		if ( empty( $token ) || $token !== get_option( 'alvobot_site_token' ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Token verification failed' );
			return new WP_Error( 'unauthorized', 'Token inválido', array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Verifica rate limiting por IP usando transients do WordPress.
	 *
	 * @return true|WP_Error
	 */
	private function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'alvobot_rl_' . md5( $ip );

		$data = get_transient( $key );

		if ( $data === false ) {
			set_transient(
				$key,
				array(
					'count' => 1,
					'start' => time(),
				),
				$this->rate_limit_window
			);
			return true;
		}

		if ( $data['count'] >= $this->rate_limit_max ) {
			$retry_after = $this->rate_limit_window - ( time() - $data['start'] );
			AlvoBotPro::debug_log(
				'plugin-manager',
				sprintf(
					'[Rate Limit] IP %s excedeu %d requisições em %ds. Retry-After: %ds',
					$ip,
					$this->rate_limit_max,
					$this->rate_limit_window,
					max( 1, $retry_after )
				)
			);
			return new WP_Error(
				'rate_limit_exceeded',
				'Limite de requisições excedido. Tente novamente em breve.',
				array( 'status' => 429 )
			);
		}

		++$data['count'];
		set_transient( $key, $data, $this->rate_limit_window - ( time() - $data['start'] ) );
		return true;
	}

	public function handle_command( $request ) {
		// Rate limiting
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $rate_check->get_error_message(),
				),
				429
			);
		}

		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Starting command execution at ' . date( 'Y-m-d H:i:s' ) );

		$params = $request->get_json_params();
		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Received parameters: ' . wp_json_encode( $this->sanitize_log_data( $params ) ) );

		$command = isset( $params['command'] ) ? sanitize_text_field( $params['command'] ) : '';
		AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Command received: ' . $command );

		if ( empty( $command ) ) {
			AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Error: No command provided' );
			return new WP_Error( 'missing_command', 'Comando não fornecido.' );
		}

		// Processa o comando
		switch ( $command ) {
			case 'install_plugin':
				$plugin_slug = isset( $params['plugin_slug'] ) ? sanitize_text_field( $params['plugin_slug'] ) : '';
				$plugin_url  = isset( $params['plugin_url'] ) ? esc_url_raw( $params['plugin_url'] ) : '';

				AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Install request - Slug: ' . $plugin_slug . ', URL: ' . $this->sanitize_log_url( $plugin_url ) );

				// Carrega as funções necessárias
				if ( ! function_exists( 'show_message' ) ) {
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Loading WordPress admin functions' );
					require_once ABSPATH . 'wp-admin/includes/admin.php';
				}

				if ( ! function_exists( 'plugins_api' ) ) {
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Loading plugins API' );
					require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				}

				if ( ! class_exists( 'Plugin_Upgrader' ) ) {
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Loading upgrader classes' );
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				}

				if ( ! function_exists( 'get_plugins' ) ) {
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Loading plugin functions' );
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				if ( ! empty( $plugin_slug ) ) {
					// Instalação a partir do repositório WordPress
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Installing from WordPress repository: ' . $plugin_slug );

					// Check if plugin is already installed
					$existing_plugin_file = $this->find_plugin_file_by_slug( $plugin_slug );
					if ( $existing_plugin_file ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Plugin already installed: ' . $existing_plugin_file );

						// Check if already active
						if ( is_plugin_active( $existing_plugin_file ) ) {
							AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Plugin already active' );
							return new WP_REST_Response(
								array(
									'success'     => true,
									'message'     => 'Plugin já está instalado e ativo',
									'plugin_file' => $existing_plugin_file,
									'data'        => array( 'status' => 'already_active' ),
								)
							);
						}

						// Activate existing plugin
						$activate = activate_plugin( $existing_plugin_file );
						if ( is_wp_error( $activate ) ) {
							AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Activation Error: ' . $activate->get_error_message() );
							return $activate;
						}

						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Existing plugin activated: ' . $existing_plugin_file );
						return new WP_REST_Response(
							array(
								'success'     => true,
								'message'     => 'Plugin ativado com sucesso',
								'plugin_file' => $existing_plugin_file,
								'data'        => array( 'status' => 'already_active' ),
							)
						);
					}

					$api = plugins_api(
						'plugin_information',
						array(
							'slug'   => $plugin_slug,
							'fields' => array(
								'short_description' => false,
								'sections'          => false,
								'requires'          => false,
								'rating'            => false,
								'ratings'           => false,
								'downloaded'        => false,
								'last_updated'      => false,
								'added'             => false,
								'tags'              => false,
								'compatibility'     => false,
								'homepage'          => false,
								'donate_link'       => false,
							),
						)
					);

					if ( is_wp_error( $api ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] API Error: ' . $api->get_error_message() );
						return $api;
					}

					$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$installed = $upgrader->install( $api->download_link );

					if ( is_wp_error( $installed ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Installation Error: ' . $installed->get_error_message() );
						return $installed;
					}

					$plugin_file = $upgrader->plugin_info();

					// If plugin_info() returns null, try to find the plugin file manually
					if ( ! $plugin_file ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] plugin_info() returned null, trying to find plugin file manually' );
						$plugin_file = $this->find_plugin_file_by_slug( $plugin_slug );
					}

					if ( ! $plugin_file ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Error: Could not determine plugin file after installation' );
						return new WP_Error( 'plugin_error', 'Não foi possível determinar o arquivo do plugin após a instalação' );
					}

					// Ativa o plugin
					$activate = activate_plugin( $plugin_file );

					if ( is_wp_error( $activate ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Activation Error: ' . $activate->get_error_message() );
						return $activate;
					}

					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Plugin installed and activated successfully: ' . $plugin_file );
					return new WP_REST_Response(
						array(
							'success'     => true,
							'message'     => 'Plugin instalado e ativado com sucesso',
							'plugin_file' => $plugin_file,
						)
					);
				} elseif ( ! empty( $plugin_url ) ) {
					// Instalação a partir de uma URL
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Installing from URL: ' . $plugin_url );

					// Valida a URL
					if ( ! filter_var( $plugin_url, FILTER_VALIDATE_URL ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Invalid URL provided: ' . $plugin_url );
						return new WP_Error( 'invalid_url', 'URL inválida' );
					}

					// Captura a lista de plugins antes da instalação
					$plugins_before = get_plugins();

					// Baixa o arquivo temporariamente
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Downloading plugin from URL' );
					$download_file = download_url( $plugin_url );

					if ( is_wp_error( $download_file ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Download Error: ' . $download_file->get_error_message() );
						return new WP_Error( 'download_failed', 'Erro ao baixar o plugin: ' . $download_file->get_error_message() );
					}

					// Instala o plugin
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Installing downloaded plugin' );
					$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$installed = $upgrader->install( $download_file );

					// Remove o arquivo temporário
					@unlink( $download_file );

					if ( is_wp_error( $installed ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Installation Error: ' . $installed->get_error_message() );
						return new WP_Error( 'install_failed', 'Falha na instalação do plugin: ' . $installed->get_error_message() );
					}

					// Captura a lista de plugins após a instalação
					$plugins_after = get_plugins();
					$new_plugins   = array_diff_key( $plugins_after, $plugins_before );

					if ( empty( $new_plugins ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] No new plugins found after installation' );
						return new WP_Error( 'plugin_not_found', 'Nenhum novo plugin encontrado após instalação' );
					}

					// Obtém o primeiro plugin recém-instalado
					$plugin_file = key( $new_plugins );
					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] New plugin detected: ' . $plugin_file );

					// Ativa o plugin
					$result = activate_plugin( $plugin_file );

					if ( is_wp_error( $result ) ) {
						AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Activation Error: ' . $result->get_error_message() );
						return new WP_Error( 'activation_failed', 'Falha na ativação do plugin: ' . $result->get_error_message() );
					}

					AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Successfully installed and activated plugin from URL: ' . $plugin_file );
					return new WP_REST_Response(
						array(
							'success'     => true,
							'message'     => 'Plugin instalado e ativado com sucesso a partir da URL',
							'plugin_file' => $plugin_file,
						)
					);
				}

				return new WP_Error( 'missing_source', 'Nenhuma fonte de plugin fornecida (slug ou URL).' );

			case 'activate_plugin':
				if ( isset( $params['plugin'] ) ) {
					$result = activate_plugin( $params['plugin'] );
					return new WP_REST_Response(
						array(
							'success' => ! is_wp_error( $result ),
							'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Plugin activated',
						)
					);
				}
				break;

			case 'deactivate_plugin':
				if ( isset( $params['plugin'] ) ) {
					deactivate_plugins( $params['plugin'] );
					return new WP_REST_Response(
						array(
							'success' => true,
							'message' => 'Plugin deactivated',
						)
					);
				}
				break;

			case 'delete_plugin':
				if ( ! function_exists( 'delete_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugin_file = isset( $params['plugin'] ) ? $params['plugin'] : '';
				if ( empty( $plugin_file ) ) {
					return new WP_Error( 'missing_plugin', 'Plugin não especificado' );
				}

				// Desativa o plugin antes de deletar
				deactivate_plugins( $plugin_file );

				// Deleta o plugin
				$deleted = delete_plugins( array( $plugin_file ) );

				if ( is_wp_error( $deleted ) ) {
					return $deleted;
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => 'Plugin deletado com sucesso',
					)
				);

			case 'get_plugins':
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$plugins        = get_plugins();
				$active_plugins = get_option( 'active_plugins', array() );

				$formatted_plugins = array();
				foreach ( $plugins as $plugin_file => $plugin_data ) {
					$formatted_plugins[] = array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'],
						'version'     => $plugin_data['Version'],
						'description' => $plugin_data['Description'],
						'author'      => $plugin_data['Author'],
						'active'      => in_array( $plugin_file, $active_plugins ),
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'plugins' => $formatted_plugins,
					)
				);

			case 'reset':
				return $this->handle_reset( $request );

			default:
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Unknown command',
					),
					400
				);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Invalid parameters',
			),
			400
		);
	}

	/**
	 * Executa o reset completo do plugin
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	private function handle_reset( $request ) {
		try {
			// Remove todas as opções do plugin
			delete_option( 'alvobot_site_token' );
			delete_option( 'alvobot_connection_status' );
			// Legados (caso existam)
			delete_option( 'grp_site_token' );
			delete_option( 'grp_site_code' );
			delete_option( 'grp_registered' );
			delete_option( 'grp_app_password' );

			// Remove o usuário alvobot
			$user = get_user_by( 'login', 'alvobot' );
			if ( $user ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user->ID );
			}

			// Cria um novo usuário alvobot
			$new_user = $this->create_alvobot_user();
			if ( is_wp_error( $new_user ) ) {
				throw new Exception( 'Falha ao criar usuário alvobot: ' . $new_user->get_error_message() );
			}
			if ( ! $new_user ) {
				throw new Exception( 'Falha ao criar usuário alvobot' );
			}

			// Gera uma nova senha de app
			$app_password_data = $this->generate_alvobot_app_password( $new_user );
			if ( ! $app_password_data ) {
				throw new Exception( 'Falha ao gerar senha de aplicativo' );
			}

			// Gera e salva um novo token
			$new_token = wp_generate_password( 32, false );
			update_option( 'alvobot_site_token', $new_token );

			// Registra o site com a nova senha de app (envia credenciais ao Supabase internamente)
			$register_result = $this->register_site( $app_password_data );

			return new WP_REST_Response(
				array(
					'success'            => true,
					'message'            => 'Plugin resetado com sucesso',
					'new_token'          => $new_token,
					'registration_ok'    => (bool) $register_result,
					'user'               => array(
						'id'    => $new_user->ID,
						'login' => $new_user->user_login,
					),
				)
			);
		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'plugin-manager', '[AlvoBot Debug] Reset error: ' . $e->getMessage() );
			return new WP_Error(
				'reset_failed',
				'Erro ao resetar o plugin: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Sanitiza dados antes do log para evitar exposição de segredos e payloads muito grandes.
	 */
	private function sanitize_log_data( $data, $depth = 0 ) {
		if ( $depth > 4 ) {
			return '[max-depth]';
		}

		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$normalized_key = is_string( $key ) ? strtolower( $key ) : '';
				if ( $this->is_sensitive_log_key( $normalized_key ) ) {
					$sanitized[ $key ] = '[redacted]';
					continue;
				}
				$sanitized[ $key ] = $this->sanitize_log_data( $value, $depth + 1 );
			}
			return $sanitized;
		}

		if ( is_object( $data ) ) {
			if ( $data instanceof Traversable ) {
				return $this->sanitize_log_data( iterator_to_array( $data ), $depth + 1 );
			}
			if ( method_exists( $data, 'get_error_message' ) && method_exists( $data, 'get_error_code' ) ) {
				return array(
					'error_code'    => $data->get_error_code(),
					'error_message' => $this->truncate_log_string( $data->get_error_message() ),
				);
			}
			return '[object ' . get_class( $data ) . ']';
		}

		if ( is_string( $data ) ) {
			return $this->truncate_log_string( $data, 500 );
		}

		return $data;
	}

	/**
	 * Define quais chaves são sensíveis para logs.
	 */
	private function is_sensitive_log_key( $key ) {
		$sensitive_fragments = array(
			'token',
			'password',
			'app_password',
			'authorization',
			'api_key',
			'secret',
		);

		foreach ( $sensitive_fragments as $fragment ) {
			if ( strpos( $key, $fragment ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Trunca strings para evitar logs extensos.
	 */
	private function truncate_log_string( $value, $max_length = 300 ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$length = strlen( $value );
		if ( $length <= $max_length ) {
			return $value;
		}

		return substr( $value, 0, $max_length ) . '...[truncated ' . ( $length - $max_length ) . ' chars]';
	}

	/**
	 * Remove query string/fragment de URLs antes de registrar em log.
	 */
	private function sanitize_log_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return $this->truncate_log_string( $url, 200 );
		}

		$path = isset( $parsed['path'] ) ? $parsed['path'] : '';
		return $parsed['scheme'] . '://' . $parsed['host'] . $path;
	}

	private function find_plugin_file_by_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		foreach ( $all_plugins as $file => $plugin_data ) {
			// Extrai o diretório do plugin
			$plugin_dir = dirname( $file );

			if ( $plugin_dir === '.' ) {
				// Plugin está na raiz da pasta de plugins
				if ( $slug === basename( $file, '.php' ) ) {
					return $file;
				}
			} elseif ( $plugin_dir === $slug ) {
					return $file;
			}
		}

		return false;
	}
}
