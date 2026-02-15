<?php
/**
 * Classe principal do plugin AlvoBot Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro {
	private static $instance = null;

	private $version;
	private $modules        = [];
	private $active_modules = [];

	private static $debug_settings_cache = null;
	private static $active_modules_cache = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->version = ALVOBOT_PRO_VERSION;
		$this->load_dependencies();
		// Migra options legadas ANTES de init_modules (PluginManager precisa de alvobot_site_token)
		$this->migrate_legacy_options();
		$this->init_modules();
	}

	private function load_dependencies() {
		// Carrega a classe base dos módulos
		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-module-base.php';

		// Carrega os módulos
		$module_files = array(
			'logo_generator'       => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/class-logo-generator.php',
			'author_box'           => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/class-author-box.php',
			'plugin-manager'       => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/plugin-manager/class-plugin-manager.php',
			'pre-article'          => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pre-article/pre-article.php',
			'essential_pages'      => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/essential-pages/class-essential-pages.php',
			'multi-languages'      => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/multi-languages/class-multi-languages.php',
			'temporary-login'      => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/temporary-login/class-temporary-login.php',
			'quiz-builder'         => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/quiz-builder/class-quiz-builder.php',
			'cta-cards'            => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/cta-cards/class-cta-cards.php',
			'smart-internal-links' => ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/class-smart-internal-links.php',
		);

		foreach ( $module_files as $module => $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		// Inicializa o AJAX handler
		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-ajax.php';
		new AlvoBotPro_Ajax();

		// Inicializa a API de AI
		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-ai-api.php';
		AlvoBotPro_AI_API::get_instance();
	}

	public function init() {
		// Adiciona menus administrativos
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Adiciona scripts e estilos
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Adiciona autenticação customizada para todas as rotas REST API
		add_filter( 'determine_current_user', array( $this, 'authenticate_rest_api_request' ), 20 );
	}

	private function init_modules() {
		self::debug_log( 'plugin-manager', 'Iniciando init_modules' );

		// Carrega módulos ativos das opções com valores padrão
		$default_modules = array(
			'logo_generator'       => true,
			'author_box'           => true,
			'plugin-manager'       => true,
			'pre-article'          => true,
			'essential_pages'      => true,
			'multi-languages'      => true,
			'temporary-login'      => true,
			'quiz-builder'         => true,
			'cta-cards'            => true,
			'smart-internal-links' => true,
		);

		// Obtém os módulos ativos do banco de dados
		$saved_modules = get_option( 'alvobot_pro_active_modules' );
		self::debug_log( 'plugin-manager', 'Módulos salvos: ' . wp_json_encode( $saved_modules ) );

		// Se não existir no banco, cria com os valores padrão
		if ( false === $saved_modules ) {
			update_option( 'alvobot_pro_active_modules', $default_modules );
			self::$active_modules_cache = $default_modules;
			$this->active_modules       = $default_modules;
			self::debug_log( 'plugin-manager', 'Criando módulos padrão' );
		} else {
			// Mescla os módulos salvos com os padrões para garantir que todos os módulos existam
			$this->active_modules = wp_parse_args( $saved_modules, $default_modules );
			// Garante que o plugin_manager esteja sempre ativo
			$this->active_modules['plugin-manager'] = true;
			// Atualiza a opção no banco de dados
			update_option( 'alvobot_pro_active_modules', $this->active_modules );
			self::$active_modules_cache = $this->active_modules;
			self::debug_log( 'plugin-manager', 'Módulos ativos atualizados: ' . wp_json_encode( $this->active_modules ) );
		}

		// Mapeia os módulos para suas classes
		$module_classes = array(
			'logo_generator'       => 'AlvoBotPro_LogoGenerator',
			'author_box'           => 'AlvoBotPro_AuthorBox',
			'plugin-manager'       => 'AlvoBotPro_PluginManager',
			'pre-article'          => 'Alvobot_Pre_Article',
			'essential_pages'      => 'AlvoBotPro_EssentialPages',
			'multi-languages'      => 'AlvoBotPro_MultiLanguages',
			'temporary-login'      => 'AlvoBotPro_TemporaryLogin',
			'quiz-builder'         => 'AlvoBotPro_QuizBuilder',
			'cta-cards'            => 'AlvoBotPro_CTACards',
			'smart-internal-links' => 'AlvoBotPro_Smart_Internal_Links',
		);

		// Instancia apenas os módulos ativos
		foreach ( $module_classes as $module_id => $class_name ) {
			self::debug_log( 'plugin-manager', "Verificando módulo {$module_id} ({$class_name})" );
			if (
				isset( $this->active_modules[ $module_id ] ) &&
				$this->active_modules[ $module_id ] &&
				class_exists( $class_name )
			) {
				self::debug_log( 'plugin-manager', "Instanciando módulo {$module_id}" );
				if ( $class_name === 'AlvoBotPro_MultiLanguages' ) {
					$this->modules[ $module_id ] = AlvoBotPro_MultiLanguages::get_instance();
				} else {
					$this->modules[ $module_id ] = new $class_name();
				}
			} else {
				self::debug_log( 'plugin-manager', "Módulo {$module_id} não ativo ou classe não existe" );
				if ( ! class_exists( $class_name ) ) {
					self::debug_log( 'plugin-manager', "Classe {$class_name} não existe" );
				}
			}
		}
	}

	/**
	 * Obtém o estado atual dos módulos
	 */
	public function get_active_modules() {
		return $this->active_modules;
	}

	/**
	 * Retorna os nomes amigáveis dos módulos (fonte única para dashboard e debug).
	 *
	 * @return array<string, string> Mapeamento module_id => nome para exibição
	 */
	public function get_module_names() {
		return array(
			'logo_generator'       => __( 'Gerador de Logo', 'alvobot-pro' ),
			'author_box'           => __( 'Author Box', 'alvobot-pro' ),
			'pre-article'          => __( 'Pre Article', 'alvobot-pro' ),
			'essential_pages'      => __( 'Páginas Essenciais', 'alvobot-pro' ),
			'multi-languages'      => __( 'Multi Languages', 'alvobot-pro' ),
			'temporary-login'      => __( 'Login Temporário', 'alvobot-pro' ),
			'quiz-builder'         => __( 'Quiz Builder', 'alvobot-pro' ),
			'cta-cards'            => __( 'CTA Cards', 'alvobot-pro' ),
			'smart-internal-links' => __( 'Smart Internal Links', 'alvobot-pro' ),
			'plugin-manager'       => __( 'Plugin Manager', 'alvobot-pro' ),
			'updater'              => __( 'Sistema de Atualizações', 'alvobot-pro' ),
			'auth'                 => __( 'Autenticação REST API', 'alvobot-pro' ),
		);
	}

	/**
	 * Salva as configurações de debug dos módulos
	 */
	private function save_debug_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_admin_referer in render_dashboard_page before calling this method.
		$debug_modules = isset( $_POST['debug_modules'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['debug_modules'] ) ) : array();

		$debug_settings = array();
		$module_ids     = array_keys( $this->get_module_names() );

		foreach ( $module_ids as $module_id ) {
			$debug_settings[ $module_id ] = isset( $debug_modules[ $module_id ] ) && $debug_modules[ $module_id ] == '1';
		}

		update_option( 'alvobot_pro_debug_modules', $debug_settings );
		self::clear_options_cache();

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>Configurações de debug salvas com sucesso!</p></div>';
			}
		);
	}

	/**
	 * Limpa os caches estáticos de debug/active modules.
	 * Deve ser chamado quando as opções são salvas.
	 */
	public static function clear_options_cache() {
		self::$debug_settings_cache = null;
		self::$active_modules_cache = null;
	}

	/**
	 * Verifica se o debug está habilitado para um módulo específico
	 */
	public static function is_debug_enabled( $module_id ) {
		if ( self::$debug_settings_cache === null ) {
			self::$debug_settings_cache = get_option( 'alvobot_pro_debug_modules', array() );
		}
		return isset( self::$debug_settings_cache[ $module_id ] ) && self::$debug_settings_cache[ $module_id ];
	}

	/**
	 * Função auxiliar para log de debug condicional
	 */
	public static function debug_log( $module_id, $message ) {
		// Verifica se WP_DEBUG está ativo
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// Verifica se o debug está habilitado para este módulo específico
		if ( ! self::is_debug_enabled( $module_id ) ) {
			return;
		}

		// Verifica se o módulo está ativo (exceto para core/plugin-manager/updater/auth que sempre devem logar)
		if ( $module_id !== 'core' && $module_id !== 'plugin-manager' && $module_id !== 'updater' && $module_id !== 'auth' ) {
			if ( self::$active_modules_cache === null ) {
				self::$active_modules_cache = get_option( 'alvobot_pro_active_modules', array() );
			}
			$active_modules = self::$active_modules_cache;
			if ( ! empty( $active_modules ) && ( ! isset( $active_modules[ $module_id ] ) || ! $active_modules[ $module_id ] ) ) {
				return;
			}
		}

		error_log( "[AlvoBot Pro - {$module_id}] " . $message );
	}

	/**
	 * Autentica requisições REST API usando tokens customizados
	 * Permite usar tokens AlvoBot em TODAS as rotas REST (nativas e customizadas)
	 */
	public function authenticate_rest_api_request( $user_id ) {
		// Se já temos um usuário autenticado, não faz nada
		if ( $user_id ) {
			return $user_id;
		}

		// Verifica se é uma requisição REST API de forma mais robusta
		$is_rest_request = false;

		// Método 1: Verifica constante REST_REQUEST (pode não estar definida ainda)
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$is_rest_request = true;
		}

		// Método 2: Verifica URL contém /wp-json/
		if ( ! $is_rest_request && isset( $_SERVER['REQUEST_URI'] ) ) {
			if ( strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/wp-json/' ) !== false ) {
				$is_rest_request = true;
			}
		}

		// Método 3: Verifica query var rest_route
		if ( ! $is_rest_request && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$is_rest_request = true;
		}

		if ( ! $is_rest_request ) {
			return $user_id;
		}

		// Verifica se existe header de autorização
		$auth_header = null;
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth_header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( ! $auth_header ) {
			return $user_id;
		}

		// Verifica se é Basic Auth
		if ( ! preg_match( '/^Basic\s+(.*)$/i', $auth_header, $matches ) ) {
			return $user_id;
		}

		// Decodifica as credenciais
		$credentials = base64_decode( $matches[1] );
		if ( ! $credentials || strpos( $credentials, ':' ) === false ) {
			self::debug_log( 'auth', 'Falha ao decodificar credenciais Basic Auth' );
			return $user_id;
		}

		list($username, $token) = explode( ':', $credentials, 2 );

		// Tenta autenticar com o token
		$user = self::authenticate_with_token( $username, $token );

		if ( is_wp_error( $user ) ) {
			self::debug_log( 'auth', sprintf( 'Falha na autenticação para usuário "%s": %s', $username, $user->get_error_message() ) );
			return $user_id;
		}

		if ( $user && $user->ID ) {
			self::debug_log( 'auth', sprintf( '[OK] Usuário %s (ID: %d) autenticado com sucesso via token customizado', $username, $user->ID ) );
			// Define o usuário atual para esta requisição
			wp_set_current_user( $user->ID );
			return $user->ID;
		}

		return $user_id;
	}

	/**
	 * Autentica um usuário usando o token do site (alvobot_site_token)
	 * Usa o MESMO token que já existe no sistema
	 */
	public static function authenticate_with_token( $username, $token ) {
		self::debug_log( 'auth', sprintf( 'Tentando autenticar com token: %s...', substr( $token, 0, 10 ) ) );

		// Obtém o token do site (o mesmo usado para comunicação com GRP)
		$site_token = get_option( 'alvobot_site_token' );

		if ( empty( $site_token ) ) {
			self::debug_log( 'auth', '✗ Token do site (alvobot_site_token) não existe' );
			return new WP_Error( 'no_site_token', __( 'Token do site não configurado.', 'alvobot-pro' ) );
		}

		self::debug_log( 'auth', sprintf( 'Token do site: %s...', substr( $site_token, 0, 10 ) ) );

		// Verifica se o token fornecido corresponde ao token do site
		if ( $token !== $site_token ) {
			self::debug_log(
				'auth',
				sprintf(
					'✗ Token inválido. Fornecido: %s... / Esperado: %s...',
					substr( $token, 0, 10 ),
					substr( $site_token, 0, 10 )
				)
			);
			return new WP_Error( 'invalid_token', __( 'Token inválido.', 'alvobot-pro' ) );
		}

		self::debug_log( 'auth', '[OK] Token validado com sucesso' );

		// Busca o usuário pelo nome (se fornecido) ou usa alvobot como padrão
		$user = get_user_by( 'login', $username );

		// Se o usuário não existe, tenta usar 'alvobot'
		if ( ! $user && $username !== 'alvobot' ) {
			self::debug_log( 'auth', sprintf( 'Usuário "%s" não encontrado, tentando usuário "alvobot"', $username ) );
			$user = get_user_by( 'login', 'alvobot' );
		}

		if ( ! $user ) {
			self::debug_log( 'auth', '✗ Nenhum usuário válido encontrado' );
			return new WP_Error( 'user_not_found', __( 'Usuário não encontrado.', 'alvobot-pro' ) );
		}

		self::debug_log( 'auth', sprintf( '[OK] Usuário %s (ID: %d) autenticado com sucesso', $user->user_login, $user->ID ) );
		return $user;
	}

	public function add_admin_menu() {
		self::debug_log( 'plugin-manager', 'Iniciando add_admin_menu' );

		// Adiciona menu principal
		add_menu_page(
			'AlvoBot Pro - Dashboard',
			'Alvobot',
			'manage_options',
			'alvobot-pro',
			array( $this, 'render_dashboard_page' ),
			ALVOBOT_PRO_PLUGIN_URL . 'assets/images/icon-alvobot-app.svg',
			2
		);

		// Adiciona submenu "Configurações" apontando para a página principal
		add_submenu_page(
			'alvobot-pro',
			'AlvoBot Pro - Configurações',
			'Configurações',
			'manage_options',
			'alvobot-pro',
			array( $this, 'render_dashboard_page' )
		);

		// 1. Criador de Logos - Ferramenta principal e visual
		if ( isset( $this->modules['logo_generator'] ) ) {
			self::debug_log( 'plugin-manager', 'Adicionando submenu Logo Generator' );
			add_submenu_page(
				'alvobot-pro',
				'Criador de Logos',
				'Criador de Logos',
				'manage_options',
				'alvobot-pro-logo',
				array( $this->modules['logo_generator'], 'render_settings_page' )
			);
		}

		// 2. Caixa de Autor - Funcionalidade de conteúdo
		if ( isset( $this->modules['author_box'] ) ) {
			self::debug_log( 'plugin-manager', 'Adicionando submenu Author Box' );
			add_submenu_page(
				'alvobot-pro',
				'Caixa de Autor',
				'Caixa de Autor',
				'manage_options',
				'alvobot-pro-author-box',
				array( $this->modules['author_box'], 'render_settings_page' )
			);
		}

		// 3. Pré-Artigos - Funcionalidade de conteúdo
		if ( isset( $this->modules['pre-article'] ) ) {
			self::debug_log( 'plugin-manager', 'Adicionando submenu Pre Article' );
			add_submenu_page(
				'alvobot-pro',
				'Páginas de Pré-Artigo',
				'Pré-Artigos',
				'manage_options',
				'alvobot-pro-pre-article',
				array( $this->modules['pre-article'], 'render_settings_page' )
			);
		} else {
			self::debug_log( 'plugin-manager', 'Módulo Pre Article não está ativo ou não existe' );
			self::debug_log( 'plugin-manager', 'Módulos ativos: ' . wp_json_encode( array_keys( $this->modules ) ) );
		}

		// 4. Páginas Essenciais - Configuração básica do site
		if ( isset( $this->modules['essential_pages'] ) ) {
			self::debug_log( 'plugin-manager', 'Adicionando submenu Essential Pages' );
			add_submenu_page(
				'alvobot-pro',
				'Páginas Essenciais',
				'Páginas Essenciais',
				'manage_options',
				'alvobot-pro-essential-pages',
				array( $this->modules['essential_pages'], 'render_settings_page' )
			);
		}

		// 5. Multilíngue - Funcionalidade avançada
		if ( isset( $this->modules['multi-languages'] ) ) {
			self::debug_log( 'plugin-manager', 'Adicionando submenu Multi Languages' );
			add_submenu_page(
				'alvobot-pro',
				'Gerenciamento Multilíngue',
				'Multilíngue',
				'manage_options',
				'alvobot-pro-multi-languages',
				array( $this->modules['multi-languages'], 'render_settings_page' )
			);
		}

		// 6. Quiz Builder - Menu é adicionado pelo próprio módulo

		// 7. CTA Cards - Menu é adicionado pelo próprio módulo

		// 8. Configurações - Por último, configurações gerais (remove submenu duplicado)
		// O menu principal já aponta para render_dashboard_page
	}

	public function render_dashboard_page() {
		// Processa ações do Plugin Manager na página de configurações
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_admin_referer for each specific action below.
		if ( isset( $_POST['action'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_admin_referer for each specific action below.
			$post_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			if ( $post_action === 'activate_plugin_manager' && isset( $this->modules['plugin-manager'] ) ) {
				check_admin_referer( 'activate_plugin_manager' );
				$this->modules['plugin-manager']->activate();
			} elseif ( $post_action === 'retry_registration' && isset( $this->modules['plugin-manager'] ) ) {
				check_admin_referer( 'alvobot_retry_registration', 'alvobot_retry_registration_nonce' );
				$alvobot_user = get_user_by( 'login', 'alvobot' );
				if ( $alvobot_user ) {
					$app_password = $this->modules['plugin-manager']->generate_alvobot_app_password( $alvobot_user );
					if ( $app_password ) {
						$result = $this->modules['plugin-manager']->register_site( $app_password );
						if ( $result ) {
							add_action(
								'admin_notices',
								function () {
									echo '<div class="notice notice-success"><p>Registro refeito com sucesso!</p></div>';
								}
							);
						} else {
							add_action(
								'admin_notices',
								function () {
									echo '<div class="notice notice-error"><p>Erro ao refazer o registro. Verifique os logs para mais detalhes.</p></div>';
								}
							);
						}
					}
				}
			} elseif ( $post_action === 'save_debug_settings' ) {
				check_admin_referer( 'alvobot_debug_settings' );
				$this->save_debug_settings();
			}
		}

		// Obtém o estado atual dos módulos
		$active_modules = $this->get_active_modules();

		// Obtém o usuário e token do AlvoBot
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

		$module_names = $this->get_module_names();
		include ALVOBOT_PRO_PLUGIN_DIR . 'assets/templates/dashboard.php';
	}

	public function enqueue_admin_assets( $hook ) {
		// Admin menu styles — must load on ALL admin pages so the sidebar icon renders correctly.
		wp_register_style( 'alvobot-pro-menu', false, array(), ALVOBOT_PRO_VERSION );
		wp_enqueue_style( 'alvobot-pro-menu' );
		wp_add_inline_style(
			'alvobot-pro-menu',
			':root{--alvobot-primary:#fbbf24;--alvobot-secondary:#0E100D;--alvobot-white:#fff;--alvobot-gray-100:#F9FAFB;--alvobot-transition-normal:200ms ease}'
			. '#adminmenu li.toplevel_page_alvobot-pro{background:var(--alvobot-secondary)!important;border-top:1px solid rgba(251,191,36,.2);border-bottom:1px solid rgba(251,191,36,.2)}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-submenu{background:var(--alvobot-secondary)!important;box-shadow:0 2px 8px rgba(0,0,0,.15)}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-menu-image{opacity:1!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-menu-image img{width:20px;height:20px;padding:7px 0;opacity:1!important;filter:none!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro>a{color:var(--alvobot-primary)!important;background:var(--alvobot-secondary)!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro>a:hover{color:var(--alvobot-white)!important;background:rgba(251,191,36,.1)!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro.wp-has-current-submenu>a.wp-has-current-submenu,#adminmenu li.toplevel_page_alvobot-pro.current>a.current{background:var(--alvobot-primary)!important;color:var(--alvobot-secondary)!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro>a.menu-top{font-weight:bold!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-submenu a{color:var(--alvobot-primary)!important;padding:8px 20px!important;background:transparent!important;transition:all var(--alvobot-transition-normal)}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-submenu a:hover{background:rgba(251,191,36,.1)!important;color:var(--alvobot-white)!important}'
			. '#adminmenu li.toplevel_page_alvobot-pro .wp-submenu .current a.current{background:var(--alvobot-gray-100)!important;color:var(--alvobot-secondary)!important;font-weight:600}'
		);

		// Verifica se estamos em uma página do plugin
		// Todos os hooks de páginas AlvoBot contêm 'alvobot' (ex: toplevel_page_alvobot-pro, alvobot_page_*)
		$is_plugin_page = ( strpos( $hook, 'alvobot' ) !== false );

		if ( $is_plugin_page ) {
			// Fonte Inter (alinhada com o App Design System)
			wp_enqueue_style(
				'alvobot-pro-inter-font',
				'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
				array(),
				null
			);

			// Estilos do plugin
			wp_enqueue_style(
				'alvobot-pro-styles',
				ALVOBOT_PRO_PLUGIN_URL . 'assets/css/styles.css',
				array( 'alvobot-pro-inter-font' ),
				$this->version
			);
			// Lucide Icons (mesma CDN do Logo Generator)
			wp_enqueue_script(
				'lucide-icons',
				'https://unpkg.com/lucide@0.564.0/dist/umd/lucide.min.js',
				array(),
				'0.564.0',
				true
			);

			// Auto-init Lucide icons (initial + dynamic DOM changes via MutationObserver)
			// Uses readyState check so it works even if DOMContentLoaded already fired
			wp_add_inline_script(
				'lucide-icons',
				'
				(function() {
					function alvobotInitLucide() {
						if (typeof lucide === "undefined") return;
						lucide.createIcons();
						var t;
						new MutationObserver(function(m) {
							if (m.some(function(r) {
								return Array.from(r.addedNodes).some(function(n) {
									return n.nodeType === 1 && (n.matches && n.matches("[data-lucide]") || n.querySelector && n.querySelector("[data-lucide]"));
								});
							})) { clearTimeout(t); t = setTimeout(function() { lucide.createIcons(); }, 50); }
						}).observe(document.body, { childList: true, subtree: true });
					}
					if (document.readyState === "loading") {
						document.addEventListener("DOMContentLoaded", alvobotInitLucide);
					} else {
						alvobotInitLucide();
					}
				})();
			'
			);

			wp_enqueue_script(
				'alvobot-pro-token-visibility',
				ALVOBOT_PRO_PLUGIN_URL . 'assets/js/token-visibility.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_enqueue_script(
				'alvobot-pro-admin',
				ALVOBOT_PRO_PLUGIN_URL . 'assets/js/alvobot-pro-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_localize_script(
				'alvobot-pro-admin',
				'alvobotPro',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'alvobot_pro_nonce' ),
				)
			);

			// AI API JS (disponivel em todas as paginas AlvoBot)
			wp_enqueue_script(
				'alvobot-ai',
				ALVOBOT_PRO_PLUGIN_URL . 'assets/js/alvobot-ai.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			$ai_api = AlvoBotPro_AI_API::get_instance();
			wp_localize_script(
				'alvobot-ai',
				'alvobotAI',
				array(
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'alvobot_ai_nonce' ),
					'costs'      => $ai_api->get_costs(),
					'credits'    => $ai_api->get_credits(),
					'site_title' => get_bloginfo( 'name' ),
				)
			);
		}
	}

	/**
	 * Método chamado quando o plugin é ativado
	 */
	public function activate() {
		// Migra options legadas grp_* → alvobot_*
		$this->migrate_legacy_options();

		$default_modules = array(
			'logo_generator'       => true,
			'author_box'           => true,
			'plugin-manager'       => true,
			'pre-article'          => true,
			'essential_pages'      => true,
			'multi-languages'      => true,
			'temporary-login'      => true,
			'quiz-builder'         => true,
			'cta-cards'            => true,
			'smart-internal-links' => true,
		);
		if ( ! get_option( 'alvobot_pro_active_modules' ) ) {
			update_option( 'alvobot_pro_active_modules', $default_modules );
		}

		// Ativa cada módulo
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'activate' ) ) {
				$module->activate();
			}
		}

		// Atualiza a versão do plugin
		update_option( 'alvobot_pro_version', $this->version );
	}

	/**
	 * Migra options legadas com prefixo grp_* para alvobot_*
	 * Executa uma única vez (flag alvobot_options_migrated).
	 */
	private function migrate_legacy_options() {
		if ( get_option( 'alvobot_options_migrated' ) ) {
			return;
		}

		$migrations = array(
			'grp_site_token'   => 'alvobot_site_token',
			'grp_settings'     => 'alvobot_settings',
			'grp_activity_log' => 'alvobot_activity_log',
		);

		foreach ( $migrations as $old_key => $new_key ) {
			$old_value = get_option( $old_key );
			if ( false !== $old_value && false === get_option( $new_key ) ) {
				update_option( $new_key, $old_value );
				delete_option( $old_key );
				self::debug_log( 'core', "Migrada option {$old_key} → {$new_key}" );
			} elseif ( false !== $old_value ) {
				// New key already exists, just clean up old
				delete_option( $old_key );
			}
		}

		// Remove options legadas que não têm correspondente novo
		$legacy_only = array( 'grp_registration_status', 'grp_site_code', 'grp_app_password', 'grp_registered' );
		foreach ( $legacy_only as $old_key ) {
			delete_option( $old_key );
		}

		update_option( 'alvobot_options_migrated', true );
		self::debug_log( 'core', 'Migração de options legadas concluída' );
	}

	/**
	 * Método chamado quando o plugin é desativado
	 */
	public function deactivate() {
		// Desativa cada módulo
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'deactivate' ) ) {
				$module->deactivate();
			}
		}
	}
}
