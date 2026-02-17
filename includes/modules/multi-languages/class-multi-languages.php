<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclaração da classe
if ( class_exists( 'AlvoBotPro_MultiLanguages' ) ) {
	return;
}

/**
 * Coordenador principal do módulo Multi Languages
 * Versão consolidada - apenas coordenação e inicialização
 */
class AlvoBotPro_MultiLanguages {

	/** @var AlvoBotPro_Translation_Service Serviço unificado de tradução */
	private $translation_service;

	/** @var AlvoBotPro_Rest_Api_Service Serviço de API REST */
	private $rest_api_service;

	/** @var AlvoBotPro_MultiLanguages_Ajax_Controller Controller AJAX */
	private $ajax_controller;

	/** @var AlvoBotPro_MultiLanguages_Admin_Controller Controller Admin */
	private $admin_controller;

	/** @var AlvoBotPro_MultiLanguages Instância única da classe */
	private static $instance = null;

	/**
	 * Retorna a instância única da classe
	 */
	private static $hooks_registered = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			if ( ! self::$hooks_registered ) {
				self::$instance->register_hooks_once();
				self::$hooks_registered = true;
			}
		}
		return self::$instance;
	}

	/**
	 * Construtor protegido para garantir padrão Singleton
	 */
	protected function __construct() {
		// Inicializa autoloader apenas uma vez
		static $autoloader_initialized = false;
		if ( ! $autoloader_initialized ) {
			$this->init_autoloader();
			$autoloader_initialized = true;
		}
	}

	/**
	 * Registra hooks apenas uma vez
	 */
	private function register_hooks_once() {
		// Inicializa serviços e controllers após Polylang estar carregado
		add_action( 'plugins_loaded', array( $this, 'init_services' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'init_controllers' ), 25 );
		add_action( 'plugins_loaded', array( $this, 'connect_services' ), 30 );

		// Registra hooks principais
		$this->register_hooks();
	}

	/**
	 * Registra hooks principais
	 */
	private function register_hooks() {
		// Adiciona suporte a REST API para posts
		add_action( 'init', array( $this, 'register_post_translations_field' ) );

		// Adiciona páginas de administração
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );

		// Adiciona ações nas linhas de posts
		add_filter( 'post_row_actions', array( $this, 'add_translate_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_translate_row_action' ), 10, 2 );

		// Assets administrativos
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handlers AJAX centralizados no Ajax Controller
		// Removido: add_action('wp_ajax_alvobot_translate_and_create_post') - agora no Ajax Controller

		// Cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Inicializa serviços unificados
	 */
	private static $services_initialized    = false;
	private static $controllers_initialized = false;
	private static $services_connected      = false;

	public function init_services() {
		if ( self::$services_initialized ) {
			return;
		}

		// Verifica se o Polylang oficial está disponível (evita conflito com AutoPoly)
		if ( ! function_exists( 'PLL' ) || ! PLL()->model || class_exists( 'Automatic_Polylang' ) ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Polylang não disponível ou AutoPoly detectado - pulando inicialização' );
			return;
		}

		self::$services_initialized = true;

		try {
			// Inicializa serviço unificado de tradução
			if ( class_exists( 'AlvoBotPro_Translation_Service' ) ) {
				$this->translation_service = AlvoBotPro_Translation_Service::get_instance();
			}

			// Inicializa serviço de API REST
			if ( class_exists( 'AlvoBotPro_Rest_Api_Service' ) ) {
				$this->rest_api_service = new AlvoBotPro_Rest_Api_Service();
			}
		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Erro ao inicializar serviços: ' . $e->getMessage() );
		}
	}

	/**
	 * Inicializa controllers
	 */
	public function init_controllers() {
		if ( self::$controllers_initialized ) {
			return;
		}
		self::$controllers_initialized = true;

		try {
			// Inicializa controller Admin SEMPRE (para mostrar interface de instalação)
			if ( class_exists( 'AlvoBotPro_MultiLanguages_Admin_Controller' ) ) {
				$this->admin_controller = new AlvoBotPro_MultiLanguages_Admin_Controller();
				AlvoBotPro::debug_log( 'multi-languages', 'Admin Controller inicializado (independente do Polylang)' );
			}

			// Inicializa controller AJAX apenas se Polylang estiver ativo
			if ( self::$services_initialized && class_exists( 'AlvoBotPro_MultiLanguages_Ajax_Controller' ) ) {
				$this->ajax_controller = new AlvoBotPro_MultiLanguages_Ajax_Controller();
				AlvoBotPro::debug_log( 'multi-languages', 'AJAX Controller inicializado (Polylang ativo)' );
			}
		} catch ( Exception $e ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Erro ao inicializar controllers: ' . $e->getMessage() );
		}
	}

	/**
	 * Conecta serviços aos controllers e entre si
	 */
	public function connect_services() {
		if ( self::$services_connected || ! self::$controllers_initialized ) {
			return;
		}
		self::$services_connected = true;

		// Conecta translation service ao REST API service
		if ( $this->rest_api_service && $this->translation_service ) {
			$this->rest_api_service->set_translation_service( $this->translation_service );
		}

		// Conecta serviços ao AJAX controller
		if ( $this->ajax_controller && $this->translation_service ) {
			$translation_engine = $this->translation_service->get_translation_engine();
			$translation_queue  = $this->translation_service->get_translation_queue();

			if ( $translation_engine && $translation_queue ) {
				$this->ajax_controller->set_services( $translation_engine, $translation_queue );
				AlvoBotPro::debug_log( 'multi-languages', 'Serviços conectados ao AJAX Controller' );
			}
		}

		// Conecta serviços ao Admin controller (se disponível)
		if ( $this->admin_controller && $this->translation_service ) {
			$translation_queue = $this->translation_service->get_translation_queue();
			if ( $translation_queue ) {
				$this->admin_controller->set_translation_queue( $translation_queue );
				AlvoBotPro::debug_log( 'multi-languages', 'Translation Queue conectado ao Admin Controller' );
			}
		} elseif ( $this->admin_controller ) {
			AlvoBotPro::debug_log( 'multi-languages', 'Admin Controller ativo sem Translation Service (Polylang inativo)' );
		}
	}

	// Método removido - centralizado no Ajax Controller

	// Método removido - centralizado no Ajax Controller

	/**
	 * Adiciona botão "Traduzir" nas ações das linhas de posts/páginas
	 */
	public function add_translate_row_action( $actions, $post ) {
		// Verifica se o Polylang oficial está ativo (evita conflito com AutoPoly)
		if ( ! function_exists( 'PLL' ) || ! PLL()->model || class_exists( 'Automatic_Polylang' ) ) {
			return $actions;
		}

		// Verifica se o post type está configurado para tradução no Polylang
		$translated_post_types = PLL()->model->get_translated_post_types();
		if ( ! array_key_exists( $post->post_type, $translated_post_types ) ) {
			return $actions;
		}

		// Verifica permissões
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		// Verifica se há idiomas configurados
		$languages = PLL()->model->get_languages_list();
		if ( empty( $languages ) || count( $languages ) < 2 ) {
			return $actions;
		}

		// Adiciona o botão "Traduzir"
		$source_lang_slug = '';
		if ( function_exists( 'pll_get_post_language' ) && PLL()->model->is_translated_post_type( $post->post_type ) ) {
			$source_lang_slug = pll_get_post_language( $post->ID, 'slug' );
		}
		$source_lang_slug = $source_lang_slug ? $source_lang_slug : '';

		$actions['alvobot_translate'] = sprintf(
			'<a href="#" class="alvobot-translate-btn" data-post-id="%d" data-post-title="%s" data-post-type="%s" data-source-lang="%s"><i data-lucide="languages" class="alvobot-icon" style="margin-right: 5px;"></i>%s</a>',
			$post->ID,
			esc_attr( $post->post_title ),
			esc_attr( $post->post_type ),
			esc_attr( $source_lang_slug ),
			__( 'Traduzir', 'alvobot-pro' )
		);

		return $actions;
	}

	/**
	 * Enfileira scripts e estilos do admin
	 */
	public function enqueue_admin_assets( $hook ) {
		// Páginas de edição de conteúdo que usam o modal de tradução
		$allowed_hooks  = array( 'edit.php', 'post.php', 'post-new.php' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection for asset loading, no data modification.
		$is_module_page = ( isset( $_GET['page'] ) && $_GET['page'] === 'alvobot-pro-multi-languages' );
		$is_editor_page = in_array( $hook, $allowed_hooks, true );

		if ( ! $is_editor_page && ! $is_module_page ) {
			return;
		}

		if ( $is_editor_page ) {
			// CSS/JS do modal de tradução para listas/edição de conteúdo.
			wp_enqueue_style(
				'alvobot-translation-modal',
				plugin_dir_url( __FILE__ ) . 'assets/css/translation-modal.css',
				array(),
				'1.0.1'
			);

			wp_enqueue_script(
				'alvobot-translation-interface',
				plugin_dir_url( __FILE__ ) . 'assets/js/translation-interface.js',
				array( 'jquery', 'wp-util' ),
				'2.0.0',
				true
			);

			$this->localize_script_data();
		}

		// Delega para o Admin Controller para páginas específicas do módulo
		if ( $is_module_page && $this->admin_controller && method_exists( $this->admin_controller, 'enqueue_admin_assets' ) ) {
			$this->admin_controller->enqueue_admin_assets( $hook );
		}
	}

	/**
	 * Localiza dados do script
	 */
	private function localize_script_data() {
		$languages = array();

		// Obtém idiomas do Polylang se disponível
		if ( function_exists( 'PLL' ) && PLL()->model ) {
			$pll_languages = PLL()->model->get_languages_list();
			foreach ( $pll_languages as $language ) {
				$languages[] = array(
					'slug'        => $language->slug,
					'name'        => $language->name,
					'native_name' => isset( $language->native_name ) ? $language->native_name : $language->name,
					'locale'      => $language->locale,
					'flag'        => isset( $language->flag_url ) ? $language->flag_url : '',
					'is_default'  => isset( $language->is_default ) ? $language->is_default : false,
				);
			}
		}

		wp_localize_script(
			'alvobot-translation-interface',
			'alvobotTranslation',
			array(
				'nonce'         => wp_create_nonce( 'alvobot_nonce' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'adminUrl'      => admin_url(),
				'postId'        => get_the_ID(),
				'languages'     => $languages,
				'languagesList' => $languages, // Para compatibilidade
				'strings'       => array(
					'noLanguagesConfigured' => __( 'Nenhum idioma configurado no Polylang.', 'alvobot-pro' ),
					'selectLanguage'        => __( 'Selecione o idioma de destino:', 'alvobot-pro' ),
					'cancel'                => __( 'Cancelar', 'alvobot-pro' ),
					'translate'             => __( 'Traduzir', 'alvobot-pro' ),
					'translating'           => __( 'Traduzindo...', 'alvobot-pro' ),
					'success'               => __( 'Tradução realizada com sucesso!', 'alvobot-pro' ),
					'error'                 => __( 'Erro ao traduzir:', 'alvobot-pro' ),
				),
			)
		);
	}

	/**
	 * Adiciona agendamentos de cron personalizados
	 */
	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['every_minute'] ) ) {
			$schedules['every_minute'] = array(
				'interval' => 60,
				'display'  => __( 'A cada minuto', 'alvobot-pro' ),
			);
		}

		if ( ! isset( $schedules['every_five_minutes'] ) ) {
			$schedules['every_five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'A cada 5 minutos', 'alvobot-pro' ),
			);
		}

		return $schedules;
	}

	/**
	 * Registra as páginas de administração do módulo
	 */
	public function register_admin_pages() {
		// Menu já registrado pela classe principal AlvoBotPro
		// Esta função é mantida vazia para compatibilidade
	}

	/**
	 * Renderiza página de configurações - delega para Admin Controller
	 */
	public function render_settings_page() {
		if ( $this->admin_controller ) {
			$this->admin_controller->render_settings_page();
			return;
		}

		// Fallback básico
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Multi Languages', 'alvobot-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Módulo Multi Languages carregado.', 'alvobot-pro' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Registra o campo pll_post_translations para posts e páginas na API REST
	 */
	public function register_post_translations_field() {
		register_rest_field(
			[ 'post', 'page' ],
			'pll_post_translations',
			[
				'get_callback'    => function ( $object ) {
					if ( function_exists( 'pll_get_post_translations' ) ) {
						return pll_get_post_translations( $object['id'] );
					}
					return [];
				},
				'update_callback' => function ( $value, $post ) {
					if ( function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_set_post_language' ) ) {
						$current_lang = pll_get_post_language( $post->ID );

						if ( ! $current_lang && isset( $value['lang'] ) ) {
							pll_set_post_language( $post->ID, $value['lang'] );
						}

						$translations = [];
						foreach ( $value as $lang => $translated_post_id ) {
							if ( get_post( $translated_post_id ) ) {
								$translations[ $lang ] = (int) $translated_post_id;
							}
						}

						$post_lang = pll_get_post_language( $post->ID );
						if ( $post_lang ) {
							$translations[ $post_lang ] = $post->ID;
						}

						pll_save_post_translations( $translations );
					}
				},
				'schema'          => [
					'description' => __( 'Polylang translations for the post.', 'alvobot-pro' ),
					'type'        => 'object',
				],
			]
		);
	}

	/**
	 * Retorna instância do Translation Service
	 */
	public function get_translation_service() {
		return $this->translation_service;
	}

	/**
	 * Retorna instância do REST API Service
	 */
	public function get_rest_api_service() {
		return $this->rest_api_service;
	}

	/**
	 * Métodos de compatibilidade para manter funcionalidade existente
	 */
	public function get_translation_engine() {
		return $this->translation_service ? $this->translation_service->get_translation_engine() : null;
	}

	public function get_translation_queue() {
		return $this->translation_service ? $this->translation_service->get_translation_queue() : null;
	}

	// Métodos básicos para ativação/desativação
	public function activate() {
		// Ações de ativação se necessário
	}

	public function deactivate() {
		// Ações de desativação se necessário
	}

	/**
	 * Inicializa o autoloader do módulo
	 */
	private function init_autoloader() {
		$autoloader_path = __DIR__ . '/class-autoloader.php';

		if ( file_exists( $autoloader_path ) ) {
			require_once $autoloader_path;

			AlvoBotPro_MultiLanguages_Autoloader::init();
			AlvoBotPro_MultiLanguages_Autoloader::load_interfaces();
			AlvoBotPro_MultiLanguages_Autoloader::load_core_classes();

			AlvoBotPro::debug_log( 'multi-languages', 'Autoloader inicializado com sucesso' );
		}
	}
}
