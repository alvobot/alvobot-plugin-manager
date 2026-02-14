<?php
/**
 * Classe para manipular requisições AJAX do AlvoBot Pro
 */
class AlvoBotPro_Ajax {
	/**
	 * Inicializa os hooks AJAX
	 */
	public function __construct() {
		// Handlers para usuários logados
		add_action( 'wp_ajax_alvobot_pro_toggle_module', array( $this, 'toggle_module' ) );
		add_action( 'wp_ajax_grp_register_site', array( $this, 'register_site' ) );
		add_action( 'wp_ajax_grp_retry_registration', array( $this, 'retry_registration' ) );
		add_action( 'wp_ajax_grp_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_grp_reset_plugin', array( $this, 'reset_plugin' ) );
		add_action( 'wp_ajax_grp_get_activity_log', array( $this, 'get_activity_log' ) );
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

		$module  = isset( $_POST['module'] ) ? sanitize_text_field( $_POST['module'] ) : '';
		$enabled = isset( $_POST['enabled'] ) ? filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN ) : false;

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
	 * Registra o site no AlvoBot
	 */
	public function register_site() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'grp_register_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		$app_password = isset( $_POST['app_password'] ) ? sanitize_text_field( $_POST['app_password'] ) : '';
		if ( empty( $app_password ) ) {
			wp_send_json_error( array( 'message' => 'Senha do aplicativo não fornecida' ) );
		}

		$plugin_manager = new AlvoBotPro_PluginManager();
		$result         = $plugin_manager->register_site( $app_password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Site registrado com sucesso' ) );
	}

	/**
	 * Refaz o registro do site no AlvoBot
	 */
	public function retry_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'retry_registration_nonce', 'nonce', false ) ) {
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
			wp_send_json_success( array( 'message' => 'Registro refeito com sucesso!' ) );
		} else {
			AlvoBotPro::debug_log( 'core', '[AJAX] Refazer registro: ERRO no servidor' );
			wp_send_json_error( array( 'message' => 'Erro ao refazer o registro. Verifique os logs para mais detalhes.' ) );
		}
	}

	/**
	 * Salva as configurações do plugin
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'grp_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		if ( empty( $settings ) ) {
			wp_send_json_error( array( 'message' => 'Nenhuma configuração fornecida' ) );
		}

		parse_str( $settings, $parsed_settings );
		$sanitized_settings = array(
			'auto_update'   => isset( $parsed_settings['grp_settings']['auto_update'] ),
			'allow_install' => isset( $parsed_settings['grp_settings']['allow_install'] ),
			'allow_delete'  => isset( $parsed_settings['grp_settings']['allow_delete'] ),
		);

		update_option( 'grp_settings', $sanitized_settings );
		wp_send_json_success( array( 'message' => 'Configurações salvas com sucesso' ) );
	}

	/**
	 * Reseta o plugin para as configurações padrão
	 */
	public function reset_plugin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'grp_reset_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		// Remove todas as opções do plugin
		delete_option( 'grp_site_token' );
		delete_option( 'grp_registration_status' );
		delete_option( 'grp_settings' );
		delete_option( 'grp_activity_log' );

		// Remove o usuário alvobot se existir
		$user = get_user_by( 'login', 'alvobot' );
		if ( $user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user->ID );
		}

		wp_send_json_success( array( 'message' => 'Plugin resetado com sucesso' ) );
	}

	/**
	 * Retorna o log de atividades
	 */
	public function get_activity_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissão negada' ) );
		}

		if ( ! check_ajax_referer( 'grp_log_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
		}

		$log = get_option( 'grp_activity_log', array() );
		wp_send_json_success( array( 'log' => $log ) );
	}
}
