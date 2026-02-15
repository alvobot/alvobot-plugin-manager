<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_MultiLanguages_Admin_Controller' ) ) {
	return;
}

/**
 * Controller para funcionalidades administrativas do módulo Multi Languages
 *
 * Gerencia menus, páginas de configuração e assets do admin
 */
class AlvoBotPro_MultiLanguages_Admin_Controller {

	/** @var string Namespace para REST API */
	private $namespace = 'alvobot-pro/v1';

	/** @var AlvoBotPro_Translation_Queue Sistema de fila */
	private $translation_queue;

	public function __construct( $translation_queue = null ) {
		$this->translation_queue = $translation_queue;

		$this->register_admin_hooks();
	}

	/**
	 * Registra hooks administrativos
	 */
	private function register_admin_hooks() {
		// Assets administrativos
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Desabilitado - botão "Traduzir" já é adicionado pela classe principal
		// add_filter('post_row_actions', array($this, 'add_translate_row_action'), 10, 2);
		// add_filter('page_row_actions', array($this, 'add_translate_row_action'), 10, 2);

		// Suporte a tradução de menus
		add_action( 'admin_init', array( $this, 'register_nav_menu_translation_support' ) );

		// REST API
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'rest_api_init', array( $this, 'register_post_translations_field' ) );

		// Logs de sistema
		add_action( 'alvobot_multi_languages_log', array( $this, 'handle_log_entry' ) );

		// Agendamentos de cron personalizados
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		AlvoBotPro::debug_log( 'multi-languages', 'Admin Controller: Hooks registrados' );
	}

	/**
	 * Define instância do serviço de fila
	 */
	public function set_translation_queue( $translation_queue ) {
		$this->translation_queue = $translation_queue;
	}

	/**
	 * Carrega assets do admin
	 */
	public function enqueue_admin_assets( $hook ) {
		// Carrega apenas na página de configurações do Multi Languages
		if ( strpos( $hook, 'alvobot-pro-multi-languages' ) === false ) {
			return;
		}

		AlvoBotPro::debug_log( 'multi-languages', 'Carregando assets do Multi Languages - Hook: ' . $hook );

		$module_url = plugin_dir_url( __FILE__ ) . '../';

		// CSS
		wp_enqueue_style(
			'alvobot-multi-languages-admin',
			$module_url . 'assets/css/translation-modal.css',
			array(),
			ALVOBOT_PRO_VERSION
		);

		// JavaScript - usa apenas translation-interface.js unificado
		// Removido carregamento duplicado - translation-interface.js já é carregado pela classe principal

		wp_enqueue_script(
			'alvobot-multi-languages-queue',
			$module_url . 'assets/js/translation-queue.js',
			array( 'jquery' ),
			ALVOBOT_PRO_VERSION . '.2',
			true
		);

		// Localização para o script de fila
		wp_localize_script(
			'alvobot-multi-languages-queue',
			'alvobotMultiLanguages',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'restUrl'      => rest_url( $this->namespace ),
				'nonce'        => wp_create_nonce( 'alvobot_nonce' ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'translations' => array(
					'translating'    => __( 'Traduzindo...', 'alvobot-pro' ),
					'error'          => __( 'Erro na tradução', 'alvobot-pro' ),
					'success'        => __( 'Tradução concluída', 'alvobot-pro' ),
					'confirm_delete' => __( 'Tem certeza que deseja excluir?', 'alvobot-pro' ),
					'processing'     => __( 'Processando...', 'alvobot-pro' ),
				),
			)
		);
	}

	/**
	 * Adiciona ação "Traduzir" nas listas de posts
	 */
	public function add_translate_row_action( $actions, $post ) {
		// Verifica se é um tipo de post suportado
		if ( ! in_array( $post->post_type, array( 'post', 'page' ) ) ) {
			return $actions;
		}

		// Verifica se o usuário tem permissão
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		// Verifica se o Polylang está ativo
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return $actions;
		}

		$translate_url = admin_url( 'admin.php?page=alvobot-pro-multi-languages&action=translate&post_id=' . $post->ID );

		$actions['alvobot_translate'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $translate_url ),
			esc_attr__( 'Traduzir este post com AlvoBot', 'alvobot-pro' ),
			esc_html__( 'Traduzir', 'alvobot-pro' )
		);

		return $actions;
	}

	/**
	 * Registra suporte a tradução de menus
	 */
	public function register_nav_menu_translation_support() {
		if ( function_exists( 'pll_register_string' ) ) {
			// Registra strings de menu para tradução automática
			$menus = wp_get_nav_menus();
			foreach ( $menus as $menu ) {
				$menu_items = wp_get_nav_menu_items( $menu->term_id );
				if ( $menu_items ) {
					foreach ( $menu_items as $item ) {
						pll_register_string( 'menu-' . $item->ID, $item->title, 'AlvoBot Menu Translations' );
					}
				}
			}
		}
	}

	/**
	 * Registra rotas da REST API
	 */
	public function register_rest_routes() {
		$admin_permission_callback = array( $this, 'can_edit_posts_permission' );

		// Rota para traduzir conteúdo
		register_rest_route(
			$this->namespace,
			'/admin/translate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_translate_content' ),
				'permission_callback' => $admin_permission_callback,
				'args'                => array(
					'text'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'source_lang' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'target_lang' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Rota para status da fila
		register_rest_route(
			$this->namespace,
			'/admin/queue/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_queue_status' ),
				'permission_callback' => $admin_permission_callback,
			)
		);

		// Rota para adicionar à fila
		register_rest_route(
			$this->namespace,
			'/admin/queue/add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_add_to_queue' ),
				'permission_callback' => $admin_permission_callback,
			)
		);
	}

	/**
	 * Callback de permissão padronizado para endpoints administrativos REST.
	 */
	public function can_edit_posts_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Adiciona campo de traduções aos posts via REST API
	 */
	public function register_post_translations_field() {
		$post_types = array( 'post', 'page' );

		foreach ( $post_types as $post_type ) {
			register_rest_field(
				$post_type,
				'pll_post_translations',
				array(
					'get_callback' => array( $this, 'get_post_translations_for_rest' ),
					'schema'       => array(
						'description' => __( 'Traduções do post via Polylang', 'alvobot-pro' ),
						'type'        => 'object',
					),
				)
			);
		}
	}

	/**
	 * Callback para obter traduções via REST
	 */
	public function get_post_translations_for_rest( $post ) {
		if ( function_exists( 'pll_get_post_translations' ) ) {
			return pll_get_post_translations( $post['id'] );
		}
		return array();
	}

	/**
	 * Handler REST para traduzir conteúdo
	 */
	public function rest_translate_content( $request ) {
		$text        = $request->get_param( 'text' );
		$source_lang = $request->get_param( 'source_lang' );
		$target_lang = $request->get_param( 'target_lang' );

		// Implementar tradução via REST API
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'REST API translation not yet implemented',
			),
			501
		);
	}

	/**
	 * Handler REST para status da fila
	 */
	public function rest_get_queue_status( $request ) {
		if ( ! $this->translation_queue ) {
			return new WP_Error( 'no_queue', 'Sistema de fila não disponível', array( 'status' => 500 ) );
		}

		$status = $this->translation_queue->get_queue_status();
		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Handler REST para adicionar à fila
	 */
	public function rest_add_to_queue( $request ) {
		if ( ! $this->translation_queue ) {
			return new WP_Error( 'no_queue', 'Sistema de fila não disponível', array( 'status' => 500 ) );
		}

		// Implementar adição à fila via REST API
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'REST API queue add not yet implemented',
			),
			501
		);
	}

	/**
	 * Manipula entradas de log
	 */
	public function handle_log_entry( $log_entry ) {
		if ( ! is_array( $log_entry ) || ! isset( $log_entry['message'] ) ) {
			return;
		}

		$level   = $log_entry['level'] ?? 'info';
		$message = $log_entry['message'];
		$context = $log_entry['context'] ?? array();

		// Log para debug do WordPress
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			$formatted_message = sprintf(
				'[AlvoBot Multi-Languages] [%s] %s',
				strtoupper( $level ),
				$message
			);

			if ( ! empty( $context ) ) {
				$formatted_message .= ' - Context: ' . wp_json_encode( $context );
			}

			AlvoBotPro::debug_log( 'multi-languages', $formatted_message );
		}

		// Armazena logs em transient para exibição no admin
		$logs = get_transient( 'alvobot_multi_languages_logs' ) ?: array();

		$log_entry['id']        = uniqid();
		$log_entry['timestamp'] = $log_entry['timestamp'] ?? current_time( 'mysql' );

		array_unshift( $logs, $log_entry );

		// Limita quantidade de logs
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, 0, 100 );
		}

		set_transient( 'alvobot_multi_languages_logs', $logs, DAY_IN_SECONDS );
	}

	/**
	 * Adiciona agendamentos de cron personalizados
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'A cada minuto', 'alvobot-pro' ),
		);

		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'A cada 5 minutos', 'alvobot-pro' ),
		);

		return $schedules;
	}

	/**
	 * Renderiza página de configurações
	 */
	public function render_settings_page() {
		AlvoBotPro::debug_log( 'multi-languages', 'Iniciando render_settings_page' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation display logic, no data modification.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		// Redirect legacy 'openai' tab to 'credits'
		if ( $current_tab === 'openai' ) {
			$current_tab = 'credits';
		}
		$valid_tabs = array( 'settings', 'credits', 'queue', 'api-docs' );

		if ( ! in_array( $current_tab, $valid_tabs ) ) {
			$current_tab = 'settings';
		}

		// Inicializa a fila se necessário
		if ( $current_tab === 'queue' && $this->translation_queue ) {
			$this->translation_queue->create_table();
		}

		$this->render_tab_navigation( $current_tab );
		$this->render_tab_content( $current_tab );
	}

	/**
	 * Renderiza navegação das abas
	 */
	private function render_tab_navigation( $current_tab ) {
		$tabs = array(
			'settings' => array(
				'label' => __( 'Configurações', 'alvobot-pro' ),
				'icon'  => 'settings',
			),
			'credits'  => array(
				'label' => __( 'Créditos IA', 'alvobot-pro' ),
				'icon'  => 'ticket',
			),
			'queue'    => array(
				'label' => __( 'Fila de Traduções', 'alvobot-pro' ),
				'icon'  => 'list-ordered',
			),
			'api-docs' => array(
				'label' => __( 'API Docs', 'alvobot-pro' ),
				'icon'  => 'book-open',
			),
		);

		echo '<div class="alvobot-admin-wrap">';
		echo '<div class="alvobot-admin-container">';

		// Header compartilhado (acima das tabs)
		echo '<div class="alvobot-admin-header">';
		echo '<div class="alvobot-header-icon"><i data-lucide="languages" class="alvobot-icon"></i></div>';
		echo '<div class="alvobot-header-content">';
		echo '<h1>' . esc_html__( 'Gerenciamento Multilíngue', 'alvobot-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Gerencie conteúdo multilíngue usando o plugin Polylang e tradução automática avançada.', 'alvobot-pro' ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $tab_key => $tab_data ) {
			$active_class = $current_tab === $tab_key ? ' nav-tab-active' : '';
			$tab_url      = admin_url( 'admin.php?page=alvobot-pro-multi-languages&tab=' . $tab_key );

			printf(
				'<a href="%s" class="nav-tab%s"><i data-lucide="%s" class="alvobot-icon"></i>%s</a>',
				esc_url( $tab_url ),
				esc_attr( $active_class ),
				esc_attr( $tab_data['icon'] ),
				esc_html( $tab_data['label'] )
			);
		}

		echo '</nav>';
	}

	/**
	 * Renderiza conteúdo da aba
	 */
	private function render_tab_content( $current_tab ) {
		$template_dir = __DIR__ . '/../templates/';
		$template_map = array(
			'settings' => 'multi-languages-settings.php',
			'credits'  => 'multi-languages-credits-settings.php',
			'queue'    => 'translation-queue.php',
			'api-docs' => 'multi-languages-api-docs.php',
		);

		if ( isset( $template_map[ $current_tab ] ) ) {
			$template_path = $template_dir . $template_map[ $current_tab ];

			if ( file_exists( $template_path ) ) {
				include_once $template_path;
				AlvoBotPro::debug_log( 'multi-languages', "Template carregado com sucesso para aba: {$current_tab}" );
			} else {
				echo '<div class="wrap"><h1>' . esc_html__( 'Erro', 'alvobot-pro' ) . '</h1>';
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Template não encontrado:', 'alvobot-pro' ) . ' ' . esc_html( $template_path ) . '</p></div>';
				echo '</div>';            }
		}

		echo '</div>'; // Fecha .alvobot-admin-container
		echo '</div>'; // Fecha .alvobot-admin-wrap
	}
}
