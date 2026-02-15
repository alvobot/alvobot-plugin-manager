<?php
/**
 * Classe para manipular requisições AJAX do AlvoBot Pro
 */
class AlvoBotPro_Ajax {
	/**
	 * Inicializa os hooks AJAX
	 */
	public function __construct() {
		add_action( 'wp_ajax_alvobot_pro_toggle_module', array( $this, 'toggle_module' ) );
		add_action( 'wp_ajax_alvobot_retry_registration', array( $this, 'retry_registration' ) );
	}

	/**
	 * Ativa/desativa um módulo
	 */
	public function toggle_module() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'alvobot_pro_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		$module  = isset( $_POST['module'] ) ? sanitize_text_field( wp_unslash( $_POST['module'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? filter_var( wp_unslash( $_POST['enabled'] ), FILTER_VALIDATE_BOOLEAN ) : false;

		if ( empty( $module ) ) {
			wp_send_json_error( array( 'message' => 'Módulo não especificado' ) );
		}

		// Obtém o estado atual
		$current_modules = get_option( 'alvobot_pro_active_modules', array() );

		// Converte os valores atuais para booleano
		$active_modules = array();
		foreach ( $current_modules as $key => $value ) {
			$active_modules[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}

		// Define os valores padrão se necessário
		$default_modules = array(
			'logo_generator'  => true,
			'author_box'      => true,
			'plugin-manager'  => true,
			'pre-article'     => true,
			'essential_pages' => true,
			'multi-languages' => true,
			'temporary-login' => true,
			'quiz-builder'    => true,
			'cta-cards'       => true,
		);

		// Mescla com os valores padrão
		$active_modules = wp_parse_args( $active_modules, $default_modules );

		// Atualiza o estado do módulo específico
		$active_modules[ $module ] = $enabled;

		// Garante que o plugin-manager sempre esteja ativo
		$active_modules['plugin-manager'] = true;

		// Força a atualização da opção (terceiro parâmetro true força update mesmo se o valor for o mesmo)
		delete_option( 'alvobot_pro_active_modules' );
		$updated = add_option( 'alvobot_pro_active_modules', $active_modules );

		// Se já existia, atualiza
		if ( ! $updated ) {
			$updated = update_option( 'alvobot_pro_active_modules', $active_modules );
		}

		// Limpa todos os caches possíveis
		wp_cache_delete( 'alvobot_pro_active_modules', 'options' );
		wp_cache_delete( 'alvobot_pro_active_modules', 'alloptions' );
		wp_cache_flush();

		// Verifica o estado final
		$final_state   = get_option( 'alvobot_pro_active_modules', array() );
		$final_enabled = isset( $final_state[ $module ] ) ? filter_var( $final_state[ $module ], FILTER_VALIDATE_BOOLEAN ) : false;

		wp_send_json_success(
			array(
				'message'        => $final_enabled ? 'Módulo ativado com sucesso' : 'Módulo desativado com sucesso',
				'active_modules' => $final_state,
				'module_state'   => array(
					'module'  => $module,
					'enabled' => $final_enabled,
				),
			)
		);
	}

	/**
	 * Refaz o registro do site no AlvoBot
	 */
	public function retry_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'alvobot_retry_registration', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		AlvoBotPro::debug_log( 'core', '[AJAX] Iniciando processo de refazer registro via AJAX' );

		// Obtém o usuário alvobot
		$alvobot_user = get_user_by( 'login', 'alvobot' );
		if ( ! $alvobot_user ) {
			AlvoBotPro::debug_log( 'core', '[AJAX] Refazer registro: ERRO - usuário alvobot não encontrado' );
			wp_send_json_error( array( 'message' => 'Usuário alvobot não encontrado. Execute a inicialização primeiro.' ) );
		}

		$plugin_manager = new AlvoBotPro_PluginManager();

		AlvoBotPro::debug_log( 'core', '[AJAX] Usuário alvobot encontrado, gerando nova senha de aplicativo' );
		$app_password = $plugin_manager->generate_alvobot_app_password( $alvobot_user );

		if ( ! $app_password ) {
			AlvoBotPro::debug_log( 'core', '[AJAX] Refazer registro: ERRO ao gerar senha de aplicativo' );
			wp_send_json_error( array( 'message' => 'Erro ao gerar nova senha de aplicativo.' ) );
		}

		AlvoBotPro::debug_log( 'core', '[AJAX] Nova senha de aplicativo gerada, registrando no servidor' );
		$result = $plugin_manager->register_site( $app_password );

		if ( $result ) {
			AlvoBotPro::debug_log( 'core', '[AJAX] Refazer registro: SUCESSO' );

			// Atualiza o status de conexão para 'connected'
			update_option(
				'alvobot_connection_status',
				array(
					'status'    => 'connected',
					'timestamp' => time(),
					'message'   => 'Conexão estabelecida com sucesso',
				)
			);

			wp_send_json_success( array( 'message' => 'Registro refeito com sucesso!' ) );
		} else {
			AlvoBotPro::debug_log( 'core', '[AJAX] Refazer registro: ERRO no servidor' );

			// Se register_site falhou mas não salvou status (ex: WP_Error de rede),
			// garante que o status de erro seja registrado
			$current_status = get_option( 'alvobot_connection_status' );
			if ( ! $current_status || ! is_array( $current_status ) || $current_status['status'] !== 'error' ) {
				update_option(
					'alvobot_connection_status',
					array(
						'status'     => 'error',
						'error_type' => 'registration_failed',
						'timestamp'  => time(),
						'message'    => 'Falha ao conectar com o servidor central. Verifique sua conexão com a internet.',
					)
				);
			}

			$error_msg = 'Erro ao refazer o registro.';
			$status    = get_option( 'alvobot_connection_status' );
			if ( is_array( $status ) && ! empty( $status['message'] ) ) {
				$error_msg .= ' ' . $status['message'];
			}

			wp_send_json_error( array( 'message' => $error_msg ) );
		}
	}
}
