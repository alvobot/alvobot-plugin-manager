<?php
/**
 * Plugin Name: AlvoBot Essential Pages
 * Description: Cria páginas essenciais (Termos de Uso, Política de Privacidade e Contato) e fornece um painel de gerenciamento com ações individuais e globais. Inclui também um formulário de contato via shortcode.
 * Version:     1.1
 * Author:      Seu Nome
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Segurança
}

/**
 * -----------------------------------------------------------------
 * 1) SHORTCODE DO FORMULÁRIO DE CONTATO
 * -----------------------------------------------------------------
 */
function alvobot_contact_form_shortcode() {
	$feedback = '';

	// Processa o envio do formulário, se submetido e nonce válido
	if ( isset( $_POST['contact_submit'] ) && check_admin_referer( 'contact_form_nonce', 'contact_nonce' ) ) {
		$to      = get_option( 'admin_email' );
		$subject = sanitize_text_field( $_POST['contact_subject'] );
		$name    = sanitize_text_field( $_POST['contact_name'] );
		$email   = sanitize_email( $_POST['contact_email'] );
		$phone   = sanitize_text_field( $_POST['contact_phone'] );
		$message = sanitize_textarea_field( $_POST['contact_message'] );

		$body = sprintf(
			"Nome: %s\nE-mail: %s\nTelefone: %s\n\nMensagem:\n%s",
			$name,
			$email,
			$phone,
			$message
		);

		$headers = [ "From: {$name} <{$email}>" ];

		if ( wp_mail( $to, $subject, $body, $headers ) ) {
			$feedback = '<div class="success">' . esc_html__( 'Mensagem enviada com sucesso!', 'alvobot-pro' ) . '</div>';
		} else {
			$feedback = '<div class="error">' . esc_html__( 'Erro ao enviar mensagem. Tente novamente.', 'alvobot-pro' ) . '</div>';
		}
	}

	ob_start();
	?>
	<div class="contact-form-wrapper">
		<?php echo $feedback; ?>
		<form action="" method="post" id="contact-form" class="contact-form">
			<?php wp_nonce_field( 'contact_form_nonce', 'contact_nonce' ); ?>
			<div class="form-group">
				<label for="name"><?php esc_html_e( 'Nome *', 'alvobot-pro' ); ?></label>
				<input type="text" name="contact_name" id="name" required>
			</div>
			<div class="form-group">
				<label for="email"><?php esc_html_e( 'E-mail *', 'alvobot-pro' ); ?></label>
				<input type="email" name="contact_email" id="email" required>
			</div>
			<div class="form-group">
				<label for="phone"><?php esc_html_e( 'Telefone', 'alvobot-pro' ); ?></label>
				<input type="tel" name="contact_phone" id="phone">
			</div>
			<div class="form-group">
				<label for="subject"><?php esc_html_e( 'Assunto *', 'alvobot-pro' ); ?></label>
				<input type="text" name="contact_subject" id="subject" required>
			</div>
			<div class="form-group">
				<label for="message"><?php esc_html_e( 'Mensagem *', 'alvobot-pro' ); ?></label>
				<textarea name="contact_message" id="message" rows="5" required></textarea>
			</div>
			<div class="form-group">
				<button type="submit" name="contact_submit" class="button button-primary"><?php esc_html_e( 'Enviar Mensagem', 'alvobot-pro' ); ?></button>
			</div>
		</form>
	</div>
	<style>
		.contact-form-wrapper {
			background: #fff;
			padding: 20px;
			border-radius: 8px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			margin-bottom: 20px;
		}
		.contact-form .form-group {
			margin-bottom: 1rem;
		}
		.contact-form input[type="text"],
		.contact-form input[type="email"],
		.contact-form input[type="tel"],
		.contact-form textarea {
			width: 100%;
			padding: 8px;
			margin: 4px 0;
			border: 1px solid #ddd;
			border-radius: 4px;
			box-sizing: border-box;
		}
		.contact-form label {
			display: block;
			font-weight: 600;
			margin-bottom: 4px;
		}
		.contact-form button {
			background: #0073aa;
			color: white;
			padding: 10px 20px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			width: 100%;
			font-size: 16px;
		}
		.contact-form button:hover {
			background: #005177;
		}
		.success, .error {
			padding: 10px;
			margin: 10px 0;
			border-radius: 4px;
		}
		.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
	</style>
	<?php
	return ob_get_clean();
}
add_shortcode( 'alvobot_contact_form', 'alvobot_contact_form_shortcode' );


/**
 * -----------------------------------------------------------------
 * 2) CLASSE PRINCIPAL: CRIA E GERENCIA AS PÁGINAS ESSENCIAIS
 * -----------------------------------------------------------------
 */
class AlvoBotPro_EssentialPages {
	private $plugin_version = '1.1';

	/**
	 * Páginas essenciais e suas configurações
	 *
	 * @var array
	 */
	private array $essential_pages = [
		'terms'   => [
			'title' => 'Termos de Uso',
			'slug'  => 'termos-de-uso',
		],
		'privacy' => [
			'title' => 'Política de Privacidade',
			'slug'  => 'politica-de-privacidade',
		],
		'contact' => [
			'title' => 'Contato',
			'slug'  => 'contato',
		],
		'about'   => [
			'title' => 'Sobre Nós',
			'slug'  => 'sobre-nos',
		],
	];

	/**
	 * Diretório base do módulo
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->base_dir = __DIR__ . '/';
		// Removido: não precisamos adicionar o menu aqui, pois já é adicionado pela classe principal
		// add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_shortcode( 'alvobot_contact_form', [ $this, 'render_contact_form' ] );

		// Debug para verificar o caminho dos templates
		AlvoBotPro::debug_log( 'essential_pages', 'base_dir: ' . $this->base_dir );

		// Inicializar suporte multi-idioma
	}

	/**
	 * Registra e enfileira os estilos do admin
	 *
	 * @param string $hook_suffix O sufixo do hook da página atual
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Só carrega os estilos na página de configurações do módulo
		if ( ! isset( $_GET['page'] ) || 'alvobot-pro-essential-pages' !== $_GET['page'] ) {
			return;
		}

		AlvoBotPro::debug_log( 'essential_pages', 'Enfileirando assets do Essential Pages' );
		AlvoBotPro::debug_log( 'essential_pages', 'CSS path: ' . plugin_dir_url( __FILE__ ) . 'css/admin.css' );
		AlvoBotPro::debug_log( 'essential_pages', 'JS path: ' . plugin_dir_url( __FILE__ ) . 'js/admin.js' );

		// Enfileira CSS minificado para melhor performance
		wp_enqueue_style(
			'alvobot-essential-pages-admin',
			plugin_dir_url( __FILE__ ) . 'css/admin.css',
			[],
			$this->plugin_version
		);

		// Enfileira o JavaScript minificado com melhorias de UX
		wp_enqueue_script(
			'alvobot-essential-pages-admin',
			plugin_dir_url( __FILE__ ) . 'js/admin.js',
			[ 'jquery' ],
			$this->plugin_version,
			true
		);

		// Localiza o script para traduções
		wp_localize_script(
			'alvobot-essential-pages-admin',
			'alvobotEssentialPages',
			[
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'alvobot_essential_pages_nonce' ),
				'strings'   => [
					'confirmCreate'    => __( 'Isto criará ou recriará todas as páginas essenciais. Continuar?', 'alvobot-pro' ),
					'confirmDelete'    => __( 'Tem certeza que deseja excluir esta página?', 'alvobot-pro' ),
					'confirmDeleteAll' => __( 'Tem certeza que deseja excluir todas as páginas essenciais?', 'alvobot-pro' ),
					'loading'          => __( 'Processando...', 'alvobot-pro' ),
					'operationSuccess' => __( 'Operação realizada com sucesso!', 'alvobot-pro' ),
					'operationError'   => __( 'Ocorreu um erro ao realizar a operação.', 'alvobot-pro' ),
				],
				'pageNames' => [
					'terms'   => __( 'Termos de Uso', 'alvobot-pro' ),
					'privacy' => __( 'Política de Privacidade', 'alvobot-pro' ),
					'contact' => __( 'Contato', 'alvobot-pro' ),
					'about'   => __( 'Sobre Nós', 'alvobot-pro' ),
				],
			]
		);
	}

	/**
	 * Registra a página de configurações no menu de Opções
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'AlvoBot Essential Pages', 'alvobot-pro' ),
			__( 'AlvoBot Pages', 'alvobot-pro' ),
			'manage_options',
			'alvobot-essential-pages',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Renderiza o painel de configurações
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		AlvoBotPro::debug_log( 'essential_pages', 'render_settings_page chamado' );
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Método POST detectado' );
			AlvoBotPro::debug_log( 'essential_pages', 'POST data = ' . print_r( $_POST, true ) );
		}

		// Processar ações do formulário
		$action_complete = '';
		$action_status   = 'success';

		// Processa ações enviadas (ações globais ou individuais)
		if ( isset( $_POST['alvobot_action'] ) && check_admin_referer( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ) ) {
			$action = sanitize_text_field( $_POST['alvobot_action'] );

			// Executar a ação solicitada
			switch ( $action ) {
				case 'create_all':
					AlvoBotPro::debug_log( 'essential_pages', 'Iniciando criação de todas as páginas' );
					$result = $this->create_essential_pages( true );

					// Gravar resultado para exibição
					if ( $result ) {
						$action_complete = 'all_created';
					} else {
						$action_complete = 'error';
						$action_status   = 'error';
					}

					AlvoBotPro::debug_log( 'essential_pages', 'Resultado da criação: ' . ( $result ? 'sucesso' : 'falha' ) );
					break;

				case 'delete_all':
					$this->delete_essential_pages();
					$action_complete = 'all_deleted';
					break;

				case 'create_page':
					if ( isset( $_POST['page_key'] ) ) {
						$page_key = sanitize_text_field( $_POST['page_key'] );
						if ( isset( $this->essential_pages[ $page_key ] ) ) {
							// Exclui a página existente (caso exista) e cria uma nova
							$result          = $this->create_essential_page( $page_key, true );
							$action_complete = $result ? 'created' : 'error';
							$action_status   = $result ? 'success' : 'error';
						}
					}
					break;

				case 'delete_page':
					if ( isset( $_POST['page_key'] ) ) {
						$page_key = sanitize_text_field( $_POST['page_key'] );
						if ( isset( $this->essential_pages[ $page_key ] ) ) {
							$this->delete_essential_page( $page_key );
							$action_complete = 'deleted';
						}
					}
					break;
			}

			// Define mensagens para exibir na página após o processamento
			if ( $action_complete ) {
				// Armazenar a mensagem na opção temporária
				update_option(
					'alvobot_essential_pages_message',
					[
						'action' => $action_complete,
						'status' => $action_status,
						'time'   => time(),
					]
				);

				// Redireciona para mesma página - não use exit() para evitar tela em branco
				echo "<meta http-equiv='refresh' content='0;url=" .
					esc_url( add_query_arg( [ 'page' => 'alvobot-pro-essential-pages' ], admin_url( 'admin.php' ) ) ) . "'>";
				echo '<p>Redirecionando...</p>';
				return; // Apenas retorne, não encerre a execução
			}
		}

		// Obter status de cada página essencial
		$pages_status = [];
		foreach ( $this->essential_pages as $key => $data ) {
			$existing             = get_page_by_path( $data['slug'] );
			$pages_status[ $key ] = [
				'exists' => ( $existing && $existing->post_status === 'publish' ),
				'id'     => ( $existing ) ? $existing->ID : 0,
			];
		}
		?>
		<div class="alvobot-admin-wrap">
			<div class="alvobot-admin-container">
				<div class="alvobot-admin-header">
					<div class="alvobot-header-icon">
						<i data-lucide="file-check" class="alvobot-icon"></i>
					</div>
					<div class="alvobot-header-content">
						<h1><?php esc_html_e( 'Páginas Essenciais', 'alvobot-pro' ); ?></h1>
						<p><?php esc_html_e( 'Crie e gerencie páginas essenciais como Termos de Uso, Política de Privacidade e Contato de forma automática.', 'alvobot-pro' ); ?></p>
					</div>
				</div>

				<div class="alvobot-notice-container">
					<?php
					// Exibir mensagens de notificação
					$message_data = get_option( 'alvobot_essential_pages_message' );
					if ( $message_data && isset( $message_data['time'] ) && ( time() - $message_data['time'] < 60 ) ) {
						$action       = $message_data['action'];
						$status_class = ( $message_data['status'] === 'success' ) ? 'updated' : 'error';
						$message      = '';

						switch ( $action ) {
							case 'created':
								$message = __( 'Página criada com sucesso!', 'alvobot-pro' );
								break;
							case 'deleted':
								$message = __( 'Página excluída com sucesso!', 'alvobot-pro' );
								break;
							case 'all_created':
								$message = __( 'Todas as páginas foram criadas com sucesso e adicionadas ao Footer Menu! Adicione esse menu ao rodapé do seu tema.', 'alvobot-pro' );
								break;
							case 'all_deleted':
								$message = __( 'Todas as páginas foram excluídas com sucesso!', 'alvobot-pro' );
								break;
							case 'error':
								$message = __( 'Ocorreu um erro ao processar a ação solicitada.', 'alvobot-pro' );
								break;
							default:
								$message = __( 'Operação concluída com sucesso!', 'alvobot-pro' );
						}

						echo '<div class="notice ' . $status_class . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
						delete_option( 'alvobot_essential_pages_message' );
					}
					settings_errors( 'alvobot_essential_pages' );
					?>
				</div>

				<div class="alvobot-grid alvobot-grid-auto">
		<?php
		foreach ( $this->essential_pages as $key => $page ) :
			$status = $pages_status[ $key ];
			?>
		<div class="alvobot-card <?php echo $status['exists'] ? 'module-enabled' : ''; ?>">
		<div class="alvobot-card-header">
			<div>
			<h2 class="alvobot-card-title">
				<?php echo esc_html( $this->get_page_title( $key ) ); ?>
			</h2>
			<p class="alvobot-card-subtitle">
				<?php
				printf(
					/* translators: %s: page slug */
					esc_html__( 'Slug: %s', 'alvobot-pro' ),
					esc_html( $page['slug'] )
				);
				?>
			</p>
			</div>
			<div>
				<?php if ( $status['exists'] ) : ?>
				<span class="alvobot-badge alvobot-badge-success">
				<span class="alvobot-status-indicator success"></span>
					<?php esc_html_e( 'Publicado', 'alvobot-pro' ); ?>
				</span>
			<?php else : ?>
				<span class="alvobot-badge alvobot-badge-error">
				<span class="alvobot-status-indicator error"></span>
				<?php esc_html_e( 'Não Criado', 'alvobot-pro' ); ?>
				</span>
			<?php endif; ?>
			</div>
		</div>

		<div class="alvobot-card-footer">
			<div class="alvobot-btn-group">
				<?php
				if ( $status['exists'] ) :
					$view_link = get_permalink( $status['id'] );
					$edit_link = get_edit_post_link( $status['id'] );
					?>
			<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" class="alvobot-btn alvobot-btn-outline alvobot-btn-sm">
					<?php esc_html_e( 'Ver Página', 'alvobot-pro' ); ?>
			</a>
			<a href="<?php echo esc_url( $edit_link ); ?>" class="alvobot-btn alvobot-btn-secondary alvobot-btn-sm">
					<?php esc_html_e( 'Editar', 'alvobot-pro' ); ?>
			</a>
			<form method="post" style="display:inline-block;">
					<?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
				<input type="hidden" name="alvobot_action" value="delete_page">
				<input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
				<button type="submit" class="alvobot-btn alvobot-btn-danger alvobot-btn-sm">
					<?php esc_html_e( 'Excluir', 'alvobot-pro' ); ?>
				</button>
			</form>
				<?php else : ?>
			<form method="post" style="display:inline-block;">
					<?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
				<input type="hidden" name="alvobot_action" value="create_page">
				<input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
				<button type="submit" class="alvobot-btn alvobot-btn-primary">
					<?php esc_html_e( 'Criar Página', 'alvobot-pro' ); ?>
				</button>
			</form>
			<?php endif; ?>
			</div>
		</div>
		</div>
		<?php endforeach; ?>
	</div>

				<!-- Cartão para Ações Globais -->
				<div class="alvobot-card">
					<div class="alvobot-card-header">
						<div>
							<h2 class="alvobot-card-title"><?php esc_html_e( 'Ações Globais', 'alvobot-pro' ); ?></h2>
							<p class="alvobot-card-subtitle"><?php esc_html_e( 'Gerencie todas as páginas essenciais de uma vez', 'alvobot-pro' ); ?></p>
						</div>
					</div>
					<div class="alvobot-card-footer">
						<form method="post" class="alvobot-w-full">
							<?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
							<div class="alvobot-btn-group alvobot-btn-group-centered">
								<button type="submit" name="alvobot_action" value="create_all" class="alvobot-btn alvobot-btn-primary">
									<?php esc_html_e( 'Criar/Recriar Todas', 'alvobot-pro' ); ?>
								</button>
								<button type="submit" name="alvobot_action" value="delete_all" class="alvobot-btn alvobot-btn-danger">
									<?php esc_html_e( 'Excluir Todas', 'alvobot-pro' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Obtém o conteúdo do template
	 *
	 * @param string $template Nome do template
	 * @return string
	 */
	private function get_template_content( string $template ): string {
		$template_file = $this->base_dir . 'templates/' . $template . '.php';

		if ( ! file_exists( $template_file ) ) {
			AlvoBotPro::debug_log( 'essential-pages', sprintf( 'Template file not found: %s', $template_file ) );
			return '';
		}

		ob_start();
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Obtém as informações básicas do site
	 *
	 * @return array
	 */
	private function get_company_info(): array {
		$site_name   = get_bloginfo( 'name' );
		$admin_email = get_option( 'admin_email' );

		// Informações mínimas baseadas apenas nos dados do WordPress
		return [
			'name'                   => $site_name,
			'legal_name'             => $site_name,
			'email'                  => $admin_email,
			'document'               => '',
			'address'                => '',
			'city'                   => '',
			'state'                  => '',
			'zip'                    => '',
			'country'                => '',
			'phone'                  => '',
			'whatsapp'               => '',
			'support_email'          => '',
			'sales_email'            => '',
			'working_hours'          => '',
			'working_hours_extended' => '',
			'support_hours'          => '',
			'emergency_phone'        => '',
			'full_address'           => '',
			'social_media'           => [
				'facebook'  => '',
				'instagram' => '',
				'linkedin'  => '',
				'youtube'   => '',
			],
			'legal_info'             => [
				'founded'               => '',
				'legal_representative'  => '',
				'technical_responsible' => '',
			],
		];
	}

	/**
	 * Processa o conteúdo substituindo os placeholders
	 *
	 * @param string $content Conteúdo a ser processado
	 * @return string
	 */
	private function process_content( string $content ): string {
		$company      = $this->get_company_info();
		$current_year = date( 'Y' );
		$current_date = wp_date( get_option( 'date_format' ) );

		$replacements = [
			// Informações básicas do site
			'[site_name]'          => get_bloginfo( 'name' ),
			'[site_url]'           => home_url(),
			'[current_year]'       => $current_year,
			'[terms_date]'         => $current_date,
			'[privacy_date]'       => $current_date,

			// Informações da empresa (simplificadas)
			'[company_name]'       => $company['name'],
			'[company_legal_name]' => $company['legal_name'],
			'[contact_email]'      => $company['email'],

			// Placeholders essenciais
			'[site_description]'   => get_bloginfo( 'description' ) ?: 'informações e serviços de qualidade',
			'[minimum_age]'        => '18',
			'[privacy_policy_url]' => home_url( '/politica-de-privacidade' ),
			'[contact_url]'        => home_url( '/contato' ),
			'[terms_url]'          => home_url( '/termos-de-uso' ),
			'[dpo_email]'          => 'dpo@' . parse_url( home_url(), PHP_URL_HOST ),

			// Seção legal minimalista
			'[legal_info_section]' => $this->get_legal_info_section( $company ),

			// Mapa com cidade aleatória
			'[random_city_map]'    => $this->get_random_city_map(),
		];

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Gera seção de informações legais de forma completamente condicional
	 * Estilo minimalista como Dratune - só mostra o que for relevante
	 */
	private function get_legal_info_section( array $company ): string {
		$has_any_legal_info = false;
		$legal_parts        = [];
		$contact_parts      = [];

		// Verifica se há informações corporativas significativas
		$document         = preg_replace( '/\D/', '', $company['document'] ?? '' );
		$has_company_info = ! empty( $document );
		$has_address_info = ! empty( $company['address'] ) && ! empty( $company['city'] );

		// Se não tem nenhuma info corporativa, usa formato minimalista estilo Dratune
		if ( ! $has_company_info && ! $has_address_info ) {
			return '
    <!-- wp:paragraph {"className":"simple-contact"} -->
    <p class="simple-contact">
        ' . __( 'Para dúvidas sobre estes Termos de Uso, entre em contato através do formulário disponível em', 'alvobot-pro' ) . ' <a href="[contact_url]">[contact_url]</a>.
    </p>
    <!-- /wp:paragraph -->';
		}

		// Seção minimalista - apenas responsável e site
		$legal_parts[]      = '<strong>' . __( 'Responsável:', 'alvobot-pro' ) . '</strong> ' . ( $company['legal_name'] ?: $company['name'] );
		$legal_parts[]      = '<strong>' . __( 'Site:', 'alvobot-pro' ) . '</strong> <a href="' . home_url() . '">' . home_url() . '</a>';
		$has_any_legal_info = true;

		$result = '
    <!-- wp:paragraph {"className":"legal-info"} -->
    <p class="legal-info">
        ' . implode( '<br>', $legal_parts ) . '
    </p>
    <!-- /wp:paragraph -->';

		// DPO apenas para empresas (CNPJ) com dados pessoais
		if ( strlen( $document ) === 14 && $has_any_legal_info ) {
			$dpo_email = 'dpo@' . parse_url( home_url(), PHP_URL_HOST );

			$result .= '

    <!-- wp:paragraph {"className":"dpo-info"} -->
    <p class="dpo-info">
        <strong>' . __( 'Encarregado de Proteção de Dados (DPO):', 'alvobot-pro' ) . '</strong> ' . $dpo_email . '
    </p>
    <!-- /wp:paragraph -->';
		}

		return $result;
	}

	/**
	 * Gera descrição do conteúdo baseada no tipo de site
	 */
	private function get_content_description( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( empty( $description ) ) {
			return 'análises detalhadas, recomendações especializadas e conteúdo de qualidade';
		}

		return $description;
	}

	/**
	 * Gera seção de conteúdo de IA de forma condicional
	 */
	private function get_ai_content_section( array $company ): string {
		// Verifica se o site usa IA (pode ser configurável no futuro)
		$uses_ai = apply_filters( 'alvobot_site_uses_ai', false );

		if ( ! $uses_ai ) {
			return '';
		}

		return '
    <!-- wp:heading {"level":2} -->
    <h2>' . __( 'Conteúdo Gerado por IA', 'alvobot-pro' ) . '</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>' . __( 'Para melhorar a experiência do usuário e agilizar a criação de conteúdo, utilizamos inteligência artificial para gerar algumas das imagens e elementos visuais que aparecem em nosso site. Essa tecnologia nos permite desenvolver conteúdo de forma mais dinâmica e eficiente, oferecendo uma apresentação visual inovadora e acessível.', 'alvobot-pro' ) . '</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>' . __( 'Embora a IA desempenhe um papel na geração de conteúdo, mantemos controles editoriais rigorosos para garantir que todo o material publicado atenda aos nossos padrões de qualidade, precisão e integridade. Estamos comprometidos com a transparência e acreditamos que a IA deve ser usada com responsabilidade.', 'alvobot-pro' ) . '</p>
    <!-- /wp:paragraph -->';
	}

	/**
	 * Gera seções adicionais de compliance baseadas na localização
	 */
	private function get_compliance_sections( array $company ): string {
		// Para empresas, pode incluir seções específicas por região
		$document   = preg_replace( '/\D/', '', $company['document'] ?? '' );
		$is_company = strlen( $document ) === 14;

		if ( ! $is_company ) {
			return '';
		}

		return '
    <!-- wp:heading {"level":3} -->
    <h3>' . __( 'Outras Regulamentações', 'alvobot-pro' ) . '</h3>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>' . __( 'Além da LGPD e GDPR, também cumprimos outras regulamentações de proteção de dados aplicáveis, incluindo leis estaduais e municipais que possam incidir sobre nossas atividades. Monitoramos continuamente as mudanças legislativas para garantir compliance contínuo.', 'alvobot-pro' ) . '</p>
    <!-- /wp:paragraph -->';
	}

	/**
	 * Gera detalhes sobre transferências internacionais de dados
	 */
	private function get_transfer_details( array $company ): string {
		return 'Utilizamos fornecedores de serviços confiáveis que podem estar localizados nos Estados Unidos, União Europeia ou outros países com adequado nível de proteção de dados. Todas as transferências são protegidas por cláusulas contratuais padrão aprovadas pela Autoridade Nacional de Proteção de Dados (ANPD).';
	}

	/**
	 * Gera seção legal específica para Política de Privacidade
	 */
	private function get_privacy_legal_info_section( array $company ): string {
		$has_any_legal_info = false;
		$legal_parts        = [];

		// Verifica se há informações corporativas significativas
		$document         = preg_replace( '/\D/', '', $company['document'] ?? '' );
		$has_company_info = ! empty( $document );
		$has_address_info = ! empty( $company['address'] ) && ! empty( $company['city'] );

		// Se não tem nenhuma info corporativa, usa formato minimalista
		if ( ! $has_company_info && ! $has_address_info ) {
			return '
    <!-- wp:paragraph {"className":"simple-contact"} -->
    <p class="simple-contact">
        ' . __( 'Para exercer seus direitos de proteção de dados ou esclarecer dúvidas sobre esta política, entre em contato através do formulário disponível em', 'alvobot-pro' ) . ' <a href="[contact_url]">[contact_url]</a>.
    </p>
    <!-- /wp:paragraph -->';
		}

		// Seção minimalista - apenas responsável e site
		$legal_parts[]      = '<strong>' . __( 'Responsável pelo Tratamento:', 'alvobot-pro' ) . '</strong> ' . ( $company['legal_name'] ?: $company['name'] );
		$legal_parts[]      = '<strong>' . __( 'Site:', 'alvobot-pro' ) . '</strong> <a href="' . home_url() . '">' . home_url() . '</a>';
		$has_any_legal_info = true;

		$result = '
    <!-- wp:paragraph {"className":"legal-info"} -->
    <p class="legal-info">
        ' . implode( '<br>', $legal_parts ) . '
    </p>
    <!-- /wp:paragraph -->';

		// DPO sempre presente se há informações legais
		if ( $has_any_legal_info ) {
			$dpo_email = 'dpo@' . parse_url( home_url(), PHP_URL_HOST );

			$result .= '

    <!-- wp:paragraph {"className":"dpo-info"} -->
    <p class="dpo-info">
        <strong>' . __( 'Encarregado de Proteção de Dados (DPO):', 'alvobot-pro' ) . '</strong> ' . $dpo_email . '
    </p>
    <!-- /wp:paragraph -->';
		}

		return $result;
	}

	/**
	 * Adiciona páginas ao menu do rodapé - se o menu não existir, cria um novo
	 *
	 * @param array $page_ids Array com os IDs das páginas a serem adicionadas ao menu
	 * @return void
	 */
	private function add_pages_to_footer_menu( array $page_ids ): void {
		AlvoBotPro::debug_log( 'essential_pages', 'Adicionando páginas ao menu do rodapé. IDs: ' . implode( ',', $page_ids ) );

		// Vamos primeiro verificar se existe um menu de rodapé
		$footer_menu_locations = array( 'footer', 'footer-menu', 'menu-footer', 'bottom', 'bottom-menu' );
		$footer_menu_id        = 0;

		// Verifica se há algum menu já associado a uma localização de rodapé
		$menu_locations = get_nav_menu_locations();
		foreach ( $footer_menu_locations as $location ) {
			if ( isset( $menu_locations[ $location ] ) && $menu_locations[ $location ] > 0 ) {
				$footer_menu_id = $menu_locations[ $location ];
				break;
			}
		}

		// Se não encontramos um menu de rodapé, vamos procurar um menu com "footer" ou "rodape" no nome
		if ( ! $footer_menu_id ) {
			$all_menus = wp_get_nav_menus();
			foreach ( $all_menus as $menu ) {
				if ( stripos( $menu->name, 'footer' ) !== false || stripos( $menu->name, 'rodape' ) !== false ||
					stripos( $menu->name, 'rodapé' ) !== false || stripos( $menu->name, 'bottom' ) !== false ) {
					$footer_menu_id = $menu->term_id;
					break;
				}
			}
		}

		// Se ainda não encontramos um menu, vamos criar um novo
		if ( ! $footer_menu_id ) {
			$menu_name = __( 'Footer Menu', 'alvobot-pro' );
			$menu_id   = wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				AlvoBotPro::debug_log( 'essential_pages', 'Erro ao criar o menu do rodapé - ' . $menu_id->get_error_message() );
				return;
			}

			$footer_menu_id = $menu_id;

			// Associar o menu a uma localização do tema, se disponível
			$theme_locations = get_registered_nav_menus();
			$location_to_use = '';

			foreach ( $footer_menu_locations as $location ) {
				if ( isset( $theme_locations[ $location ] ) ) {
					$location_to_use = $location;
					break;
				}
			}

			if ( $location_to_use ) {
				$menu_locations[ $location_to_use ] = $footer_menu_id;
				set_theme_mod( 'nav_menu_locations', $menu_locations );

				AlvoBotPro::debug_log( 'essential_pages', 'Menu do rodapé criado e associado à localização: ' . $location_to_use );
			}
		}

		if ( $footer_menu_id ) {
			// Obter itens existentes para não duplicar
			$existing_items    = wp_get_nav_menu_items( $footer_menu_id );
			$existing_page_ids = [];

			if ( $existing_items ) {
				foreach ( $existing_items as $item ) {
					if ( $item->object === 'page' ) {
						$existing_page_ids[] = $item->object_id;
					}
				}
			}

			// Adicionar as páginas ao menu
			$added_count = 0;
			foreach ( $page_ids as $page_id ) {
				if ( ! in_array( $page_id, $existing_page_ids ) ) {
					$page_data = get_post( $page_id );
					if ( $page_data ) {
						$item_id = wp_update_nav_menu_item(
							$footer_menu_id,
							0,
							[
								'menu-item-title'     => get_the_title( $page_id ),
								'menu-item-object'    => 'page',
								'menu-item-object-id' => $page_id,
								'menu-item-type'      => 'post_type',
								'menu-item-status'    => 'publish',
							]
						);

						if ( ! is_wp_error( $item_id ) ) {
							++$added_count;
						}
					}
				}
			}

			AlvoBotPro::debug_log( 'essential_pages', $added_count . ' páginas adicionadas ao menu do rodapé' );
		}
	}

	/**
	 * Cria ou recria todas as páginas essenciais
	 *
	 * @param bool $force_recreate Se true, exclui as páginas existentes antes de recriar.
	 * @return bool Retorna true se todas as páginas foram criadas com sucesso
	 */
	public function create_essential_pages( $force_recreate = false ) {
		AlvoBotPro::debug_log( 'essential_pages', 'Iniciando create_essential_pages com force_recreate=' . ( $force_recreate ? 'true' : 'false' ) );

		$created_page_ids = [];

		foreach ( $this->essential_pages as $key => $page_data ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Processando página ' . $key . ' (slug: ' . $page_data['slug'] . ')' );

			if ( $force_recreate ) {
				$existing = get_page_by_path( $page_data['slug'] );
				if ( $existing ) {
					AlvoBotPro::debug_log( 'essential_pages', 'Excluindo página existente ID=' . $existing->ID );
					wp_delete_post( $existing->ID, true );
				}
			}

			$existing = get_page_by_path( $page_data['slug'] );
			if ( ! $existing ) {
				AlvoBotPro::debug_log( 'essential_pages', 'Obtendo conteúdo do template ' . $key );
				$content = $this->get_template_content( $key );

				AlvoBotPro::debug_log( 'essential_pages', 'Template carregado com ' . strlen( $content ) . ' caracteres' );
				if ( empty( $content ) ) {
					AlvoBotPro::debug_log( 'essential_pages', 'ERRO - Template vazio para ' . $key );
				}

				$content = $this->process_content( $content );

				AlvoBotPro::debug_log( 'essential_pages', 'Conteúdo processado com ' . strlen( $content ) . ' caracteres' );

				$page_id = wp_insert_post(
					[
						'post_title'   => $this->get_page_title( $key ),
						'post_name'    => $page_data['slug'],
						'post_content' => $content,
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_author'  => get_current_user_id(),
					]
				);

				if ( is_wp_error( $page_id ) ) {
					AlvoBotPro::debug_log( 'essential_pages', 'ERRO ao criar página - ' . $page_id->get_error_message() );
				} else {
					AlvoBotPro::debug_log( 'essential_pages', 'Página criada com sucesso ID=' . $page_id );
				}

				if ( ! is_wp_error( $page_id ) ) {
					update_post_meta( $page_id, '_essential_page_type', $key );
					update_post_meta( $page_id, '_essential_page_version', $this->plugin_version );
					$created_page_ids[] = $page_id;

					// Se for a política de privacidade, atualiza a opção padrão do WordPress
					if ( 'privacy' === $key ) {
						update_option( 'wp_page_for_privacy_policy', $page_id );
					}
					// Se desejar, para termos, use uma opção customizada, ex.:
					if ( 'terms' === $key ) {
						update_option( 'wp_page_for_terms', $page_id );
					}
				}
			}
		}

		// Após criar todas as páginas, adiciona ao menu do rodapé
		if ( ! empty( $created_page_ids ) ) {
			$this->add_pages_to_footer_menu( $created_page_ids );
		}

		AlvoBotPro::debug_log( 'essential_pages', 'Resultado final - criou ' . count( $created_page_ids ) . ' páginas' );

		// Se pelo menos uma página foi criada, consideramos sucesso
		return ! empty( $created_page_ids );
	}

	/**
	 * Exclui todas as páginas essenciais
	 */
	public function delete_essential_pages() {
		foreach ( $this->essential_pages as $key => $page_data ) {
			$existing = get_page_by_path( $page_data['slug'] );
			if ( $existing ) {
				wp_delete_post( $existing->ID, true );
			}
		}
	}

	/**
	 * Cria uma única página essencial
	 *
	 * @param string $page_key Chave da página a ser criada (terms, privacy, contact, about)
	 * @param bool   $force_recreate Se true, exclui a página existente antes de recriar
	 * @return bool Retorna true se a página foi criada com sucesso
	 */
	public function create_essential_page( string $page_key, bool $force_recreate = false ): bool {
		if ( ! isset( $this->essential_pages[ $page_key ] ) ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Invalid page key: ' . $page_key );
			return false;
		}

		AlvoBotPro::debug_log( 'essential_pages', 'Creating page: ' . $page_key . ' (force_recreate: ' . ( $force_recreate ? 'yes' : 'no' ) . ')' );

		$page_data = $this->essential_pages[ $page_key ];

		// Se force_recreate, exclui a página existente
		if ( $force_recreate ) {
			$existing = get_page_by_path( $page_data['slug'] );
			if ( $existing ) {
				AlvoBotPro::debug_log( 'essential_pages', 'Deleting existing page ID: ' . $existing->ID );
				wp_delete_post( $existing->ID, true );
			}
		}

		// Verifica se a página já existe
		$existing = get_page_by_path( $page_data['slug'] );
		if ( $existing ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Page already exists ID: ' . $existing->ID );
			return false;
		}

		// Carrega e processa o template
		$content = $this->get_template_content( $page_key );

		if ( empty( $content ) ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Empty content for page: ' . $page_key );
			return false;
		}

		$content = $this->process_content( $content );

		// Cria a página
		$page_id = wp_insert_post(
			[
				'post_title'   => $this->get_page_title( $page_key ),
				'post_name'    => $page_data['slug'],
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			]
		);

		if ( is_wp_error( $page_id ) ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Error creating page: ' . $page_id->get_error_message() );
			return false;
		}

		// Adiciona metadados
		update_post_meta( $page_id, '_essential_page_type', $page_key );
		update_post_meta( $page_id, '_essential_page_version', $this->plugin_version );

		// Configura páginas especiais do WordPress
		if ( 'privacy' === $page_key ) {
			update_option( 'wp_page_for_privacy_policy', $page_id );
		}
		if ( 'terms' === $page_key ) {
			update_option( 'wp_page_for_terms', $page_id );
		}

		// Adiciona ao menu do rodapé
		$this->add_pages_to_footer_menu( [ $page_id ] );

		AlvoBotPro::debug_log( 'essential_pages', 'Page created successfully ID: ' . $page_id );
		return true;
	}

	/**
	 * Exclui uma única página essencial
	 *
	 * @param string $page_key Chave da página a ser excluída (terms, privacy, contact, about)
	 * @return bool Retorna true se a página foi excluída com sucesso
	 */
	public function delete_essential_page( string $page_key ): bool {
		if ( ! isset( $this->essential_pages[ $page_key ] ) ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Invalid page key for deletion: ' . $page_key );
			return false;
		}

		$page_data = $this->essential_pages[ $page_key ];
		$existing  = get_page_by_path( $page_data['slug'] );

		if ( ! $existing ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Page not found for deletion: ' . $page_key );
			return false;
		}

		$result = wp_delete_post( $existing->ID, true );

		if ( $result ) {
			AlvoBotPro::debug_log( 'essential_pages', 'Page deleted successfully: ' . $page_key . ' (ID: ' . $existing->ID . ')' );
			return true;
		}

		AlvoBotPro::debug_log( 'essential_pages', 'Failed to delete page: ' . $page_key );
		return false;
	}

	/**
	 * Cria uma única página essencial
	 *
	 * @param string $key Chave da página a ser criada
	 * @return void
	 */
	private function create_single_page( string $key ): void {
		if ( ! isset( $this->essential_pages[ $key ] ) ) {
			return;
		}

		$page_data = $this->essential_pages[ $key ];
		$content   = $this->get_template_content( $key );

		if ( empty( $content ) ) {
			AlvoBotPro::debug_log( 'essential-pages', sprintf( 'Empty content for page: %s', $key ) );
			return;
		}

		$content = $this->process_content( $content );

		$page = [
			'post_title'   => $this->get_page_title( $key ),
			'post_name'    => $page_data['slug'],
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		];

		wp_insert_post( $page );
	}

	/**
	 * Exclui uma página individual com base na chave
	 *
	 * @param string $key Chave da página a ser excluída
	 * @return void
	 */
	private function delete_single_page( string $key ): void {
		if ( ! isset( $this->essential_pages[ $key ] ) ) {
			return;
		}
		$page_data = $this->essential_pages[ $key ];
		$existing  = get_page_by_path( $page_data['slug'] );
		if ( $existing ) {
			wp_delete_post( $existing->ID, true );
		}
	}

	/**
	 * Retorna o título traduzido da página
	 *
	 * @param string $key Chave da página
	 * @return string
	 */
	private function get_page_title( string $key ): string {
		return __( $this->essential_pages[ $key ]['title'], 'alvobot-pro' );
	}

	/**
	 * Renderiza o formulário de contato
	 *
	 * @return string
	 */
	public function render_contact_form(): string {
		return alvobot_contact_form_shortcode();
	}

	/**
	 * About Us page placeholder methods
	 */
	private function get_target_description( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( ! empty( $description ) ) {
			return 'apaixonado por ' . $description;
		}

		return 'um entusiasta da área, um profissional experiente ou alguém que está começando a explorar este universo';
	}

	private function get_mission_statement( array $company ): string {
		return 'tomar decisões informadas e encontrar exatamente o que procura';
	}

	private function get_content_offerings( array $company ): string {
		return 'as melhores análises, comparações e recomendações';
	}

	private function get_business_sector( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( ! empty( $description ) ) {
			return 'do ' . $description;
		}

		return 'em constante evolução';
	}

	private function get_team_description( array $company ): string {
		$document   = preg_replace( '/\D/', '', $company['document'] ?? '' );
		$is_company = strlen( $document ) === 14;

		if ( $is_company ) {
			return 'uma equipe de especialistas';
		}

		return 'um profissional especializado';
	}

	private function get_field_expertise( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( ! empty( $description ) ) {
			return $description;
		}

		return 'as tendências e inovações do nosso setor';
	}

	private function get_content_approach( array $company ): string {
		return 'análises detalhadas';
	}

	private function get_content_type( array $company ): string {
		return 'guias práticos';
	}

	private function get_analysis_focus( array $company ): string {
		return 'as últimas tendências do mercado';
	}

	private function get_content_breakdown( array $company ): string {
		return 'explicando conceitos complexos de forma simples';
	}

	private function get_target_audience_detail( array $company ): string {
		return 'profissionais experientes';
	}

	private function get_journey_description( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( ! empty( $description ) ) {
			return 'a explorar o mundo do ' . $description;
		}

		return 'sua jornada de aprendizado';
	}

	private function get_content_qualities( array $company ): string {
		return 'confiável, atualizado e relevante';
	}

	private function get_service_title( array $company, int $number ): string {
		$services = [
			1 => 'Análises Detalhadas',
			2 => 'Comparações Objetivas',
			3 => 'Recomendações Personalizadas',
			4 => 'Conteúdo Atualizado',
			5 => 'Suporte Especializado',
		];

		return $services[ $number ] ?? 'Serviço ' . $number;
	}

	private function get_service_description( array $company, int $number ): string {
		$descriptions = [
			1 => 'Investigamos a fundo cada tópico para oferecer insights valiosos e bem fundamentados.',
			2 => 'Apresentamos comparações imparciais para ajudar você a fazer a melhor escolha.',
			3 => 'Sugestões personalizadas baseadas em suas necessidades específicas.',
			4 => 'Mantemos nosso conteúdo sempre atual com as últimas tendências e mudanças.',
			5 => 'Equipe especializada pronta para esclarecer dúvidas e oferecer orientação.',
		];

		return $descriptions[ $number ] ?? 'Descrição do serviço ' . $number;
	}

	private function get_additional_sections( array $company ): string {
		return '';
	}

	private function get_mission_detailed( array $company ): string {
		return 'empoderar nossos leitores';
	}

	private function get_content_attributes( array $company ): string {
		return 'preciso, confiável e atualizado';
	}

	private function get_user_goal( array $company ): string {
		return 'navegar em suas escolhas';
	}

	private function get_team_section( array $company ): string {
		$document   = preg_replace( '/\D/', '', $company['document'] ?? '' );
		$is_company = strlen( $document ) === 14;

		if ( ! $is_company ) {
			return '';
		}

		return '
    <!-- wp:heading {"level":2} -->
    <h2>' . __( 'Nossa Equipe', 'alvobot-pro' ) . '</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>' . __( 'Nossa equipe é composta por profissionais experientes e apaixonados pelo que fazem. Cada membro traz expertise única que contribui para a qualidade e diversidade do nosso conteúdo.', 'alvobot-pro' ) . '</p>
    <!-- /wp:paragraph -->';
	}

	private function get_disclaimer_section( array $company ): string {
		return '
    <!-- wp:heading {"level":2} -->
    <h2>' . __( 'Aviso Legal', 'alvobot-pro' ) . '</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>' . __( 'As informações fornecidas neste site são apenas para fins informativos. Embora nos esforcemos para manter as informações atualizadas e corretas, não fazemos representações ou garantias de qualquer tipo, expressas ou implícitas, sobre a integridade, precisão, confiabilidade, adequação ou disponibilidade do site ou das informações.', 'alvobot-pro' ) . '</p>
    <!-- /wp:paragraph -->';
	}

	private function get_user_journey( array $company ): string {
		$description = get_bloginfo( 'description' );

		if ( ! empty( $description ) ) {
			return 'sua jornada no ' . $description;
		}

		return 'sua experiência';
	}

	private function get_value_proposition( array $company ): string {
		return 'uma experiência';
	}

	private function get_social_links_section( array $company ): string {
		$social_links = [];

		if ( ! empty( $company['social_media']['facebook'] ) ) {
			$social_links[] = '<a href="' . esc_url( $company['social_media']['facebook'] ) . '" target="_blank">Facebook</a>';
		}

		if ( ! empty( $company['social_media']['instagram'] ) ) {
			$social_links[] = '<a href="' . esc_url( $company['social_media']['instagram'] ) . '" target="_blank">Instagram</a>';
		}

		if ( ! empty( $company['social_media']['linkedin'] ) ) {
			$social_links[] = '<a href="' . esc_url( $company['social_media']['linkedin'] ) . '" target="_blank">LinkedIn</a>';
		}

		if ( empty( $social_links ) ) {
			return '';
		}

		return '
    <!-- wp:heading {"level":2} -->
    <h2>' . __( 'Siga-nos', 'alvobot-pro' ) . '</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>' . __( 'Conecte-se conosco nas redes sociais: ', 'alvobot-pro' ) . implode( ' | ', $social_links ) . '</p>
    <!-- /wp:paragraph -->';
	}

	private function get_about_legal_info_section( array $company ): string {
		$legal_parts = [];

		// Sempre mostra pelo menos o responsável se há nome da empresa
		if ( ! empty( $company['name'] ) ) {
			$legal_parts[] = '<strong>' . __( 'Responsável:', 'alvobot-pro' ) . '</strong> ' . ( $company['legal_name'] ?: $company['name'] );
		}

		// Se não há nada para mostrar, retorna vazio
		if ( empty( $legal_parts ) ) {
			return '';
		}

		return '
    <!-- wp:paragraph {"className":"about-legal-info"} -->
    <p class="about-legal-info">
        ' . implode( '<br>', $legal_parts ) . '
    </p>
    <!-- /wp:paragraph -->';
	}

	/**
	 * Gera um mapa com cidade aleatória do Brasil
	 *
	 * @return string HTML do iframe do Google Maps
	 */
	private function get_random_city_map(): string {
		$cities = [
			'São Paulo'      => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3657.2!2d-46.6333!3d-23.5505!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce59c8da0aa315%3A0xd59f9431f2c9776a!2sS%C3%A3o%20Paulo%2C%20SP!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Rio de Janeiro' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3675.3!2d-43.2075!3d-22.9068!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x997e58a085b7af%3A0x4d11c63a4c2e6b7e!2sRio%20de%20Janeiro%2C%20RJ!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Brasília'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3839.6!2d-47.8825!3d-15.7942!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x935a3d18df9ae275%3A0x738470e469754a24!2sBras%C3%ADlia%2C%20DF!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Salvador'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.8!2d-38.5014!3d-12.9714!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7161b52f4200109b%3A0x1de415e4f8517d8a!2sSalvador%2C%20BA!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Belo Horizonte' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3751.9!2d-43.9378!3d-19.9167!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xa690cacacf2c33%3A0x1b7c6eece3a167!2sBelo%20Horizonte%2C%20MG!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Fortaleza'      => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3981.0!2d-38.5434!3d-3.7172!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7c748c0e415b6781%3A0xf6bdc9d6bb79ca89!2sFortaleza%2C%20CE!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Recife'         => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3950.5!2d-34.8813!3d-8.0476!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7ab196cdf682601%3A0x10c19eafeead67e8!2sRecife%2C%20PE!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Porto Alegre'   => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3454.1!2d-51.2177!3d-30.0346!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x951978567f17f28d%3A0x2c2c5272bacf4d3a!2sPorto%20Alegre%2C%20RS!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Curitiba'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3603.9!2d-49.2643!3d-25.4284!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94dce35351cdb3dd%3A0x6d2f6ba5bacbe809!2sCuritiba%2C%20PR!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
			'Manaus'         => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3984.2!2d-60.0253!3d-3.1190!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x926c05173da87e17%3A0x459c79a96c89f72!2sManaus%2C%20AM!5e0!3m2!1spt!2sbr!4v1642692000000!5m2!1spt!2sbr',
		];

		$city_names  = array_keys( $cities );
		$random_city = $city_names[ array_rand( $city_names ) ];
		$embed_url   = $cities[ $random_city ];

		return sprintf(
			'<iframe src="%s" width="100%%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa de %s"></iframe>',
			esc_url( $embed_url ),
			esc_html( $random_city )
		);
	}



	/**
	 * Adiciona interface de tradução no admin
	 */
	public function add_translation_interface() {
		$screen = get_current_screen();

		// Só adiciona na página do módulo Essential Pages
		if ( ! $screen || strpos( $screen->id, 'alvobot-essential-pages' ) === false ) {
			return;
		}

		// Verifica se Polylang está ativo
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return;
		}

		?>
		<div id="alvobot-translation-modal" style="display: none;">
			<div class="alvobot-modal-content">
				<div class="alvobot-modal-header">
					<h3><?php _e( 'Traduções Multi-idioma', 'alvobot-pro' ); ?></h3>
					<button type="button" class="alvobot-modal-close">&times;</button>
				</div>
				<div class="alvobot-modal-body">
					<div class="translation-options">
						<h4><?php _e( 'O que você deseja fazer?', 'alvobot-pro' ); ?></h4>

						<div class="option-card" data-option="translate-existing">
							<h5><?php _e( 'Traduzir Páginas Existentes', 'alvobot-pro' ); ?></h5>
							<p><?php _e( 'Traduzir o conteúdo das páginas já criadas para outros idiomas usando IA.', 'alvobot-pro' ); ?></p>
						</div>

						<div class="option-card" data-option="create-multilingual">
							<h5><?php _e( 'Criar Páginas Multi-idioma', 'alvobot-pro' ); ?></h5>
							<p><?php _e( 'Criar páginas essenciais já traduzidas para vários idiomas de uma vez.', 'alvobot-pro' ); ?></p>
						</div>
					</div>

					<div class="translation-step-2" style="display: none;">
						<h4><?php _e( 'Selecione os Idiomas', 'alvobot-pro' ); ?></h4>
						<div class="languages-grid" id="languages-selection">
							<!-- Idiomas serão carregados via AJAX -->
						</div>

						<div class="pages-selection" style="display: none;">
							<h4><?php _e( 'Selecione as Páginas', 'alvobot-pro' ); ?></h4>
							<div class="pages-grid">
								<?php foreach ( $this->essential_pages as $key => $page ) : ?>
									<label class="page-option">
										<input type="checkbox" name="pages[]" value="<?php echo esc_attr( $key ); ?>">
										<span><?php echo esc_html( $page['title'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="translation-progress" style="display: none;">
						<h4><?php _e( 'Progresso da Tradução', 'alvobot-pro' ); ?></h4>
						<div class="progress-bar">
							<div class="progress-fill" style="width: 0%"></div>
						</div>
						<div class="progress-text">0% concluído</div>
						<div class="progress-details"></div>
					</div>

					<div class="translation-results" style="display: none;">
						<h4><?php _e( 'Resultados', 'alvobot-pro' ); ?></h4>
						<div class="results-content">
							<!-- Resultados aparecerão aqui -->
						</div>
					</div>
				</div>
				<div class="alvobot-modal-footer">
					<button type="button" class="button cancel-btn"><?php _e( 'Cancelar', 'alvobot-pro' ); ?></button>
					<button type="button" class="button button-secondary back-btn" style="display: none;"><?php _e( 'Voltar', 'alvobot-pro' ); ?></button>
					<button type="button" class="button button-primary next-btn"><?php _e( 'Próximo', 'alvobot-pro' ); ?></button>
					<button type="button" class="button button-primary start-translation-btn" style="display: none;"><?php _e( 'Iniciar Tradução', 'alvobot-pro' ); ?></button>
				</div>
			</div>
		</div>

		<style>
		#alvobot-translation-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0,0,0,0.7);
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.alvobot-modal-content {
			background: white;
			border-radius: 8px;
			width: 90%;
			max-width: 700px;
			max-height: 80vh;
			overflow-y: auto;
			box-shadow: 0 4px 20px rgba(0,0,0,0.3);
		}
		.alvobot-modal-header {
			padding: 20px;
			border-bottom: 1px solid #ddd;
			display: flex;
			justify-content: space-between;
			align-items: center;
			background: #f8f9fa;
			border-radius: 8px 8px 0 0;
		}
		.alvobot-modal-header h3 {
			margin: 0;
			color: #2c3e50;
		}
		.alvobot-modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			color: #666;
		}
		.alvobot-modal-close:hover {
			color: #333;
		}
		.alvobot-modal-body {
			padding: 30px;
		}
		.option-card {
			border: 2px solid #e1e5e9;
			border-radius: 8px;
			padding: 20px;
			margin: 15px 0;
			cursor: pointer;
			transition: all 0.3s ease;
			background: #f8f9fa;
		}
		.option-card:hover, .option-card.selected {
			border-color: #0073aa;
			background: #e3f2fd;
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0,115,170,0.2);
		}
		.option-card h5 {
			margin: 0 0 10px 0;
			color: #2c3e50;
			font-size: 16px;
		}
		.option-card p {
			margin: 0;
			color: #666;
			font-size: 14px;
		}
		.languages-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
			gap: 15px;
			margin: 20px 0;
		}
		.language-option {
			border: 2px solid #e1e5e9;
			border-radius: 6px;
			padding: 15px;
			cursor: pointer;
			transition: all 0.2s ease;
			text-align: center;
			background: white;
		}
		.language-option:hover, .language-option.selected {
			border-color: #0073aa;
			background: #e3f2fd;
		}
		.language-option input {
			margin-right: 8px;
		}
		.pages-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
			gap: 15px;
			margin: 20px 0;
		}
		.page-option {
			border: 2px solid #e1e5e9;
			border-radius: 6px;
			padding: 15px;
			cursor: pointer;
			transition: all 0.2s ease;
			text-align: center;
			background: white;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.page-option:hover {
			border-color: #0073aa;
			background: #e3f2fd;
		}
		.page-option input:checked + span {
			font-weight: bold;
			color: #0073aa;
		}
		.progress-bar {
			width: 100%;
			height: 20px;
			background: #e1e5e9;
			border-radius: 10px;
			overflow: hidden;
			margin: 15px 0;
		}
		.progress-fill {
			height: 100%;
			background: linear-gradient(90deg, #0073aa, #005177);
			transition: width 0.3s ease;
		}
		.progress-text {
			text-align: center;
			font-weight: bold;
			color: #2c3e50;
		}
		.progress-details {
			margin-top: 15px;
			padding: 10px;
			background: #f8f9fa;
			border-radius: 5px;
			border-left: 4px solid #0073aa;
		}
		.alvobot-modal-footer {
			padding: 20px;
			border-top: 1px solid #ddd;
			text-align: right;
			background: #f8f9fa;
			border-radius: 0 0 8px 8px;
		}
		.alvobot-modal-footer .button {
			margin-left: 10px;
		}
		.results-content {
			max-height: 300px;
			overflow-y: auto;
		}
		.result-item {
			padding: 10px;
			margin: 5px 0;
			border-radius: 5px;
			border-left: 4px solid #28a745;
			background: #f8fff8;
		}
		.result-item.error {
			border-left-color: #dc3545;
			background: #fff8f8;
		}
		</style>
		<?php
	}

	/**
	 * Enqueue assets para tradução
	 */
	public function enqueue_translation_assets( $hook ) {
		// Carrega em páginas do admin relevantes
		if ( strpos( $hook, 'alvobot' ) === false && ! in_array( $hook, array( 'edit.php', 'post.php' ) ) ) {
			return;
		}

		// Verifica se Polylang está ativo
		if ( ! function_exists( 'PLL' ) || ! PLL()->model ) {
			return;
		}

		wp_enqueue_script(
			'alvobot-essential-pages-translation',
			plugin_dir_url( __FILE__ ) . 'assets/js/translation.js',
			array( 'jquery' ),
			$this->plugin_version,
			true
		);

		wp_localize_script(
			'alvobot-essential-pages-translation',
			'alvobotEssentialPagesTranslation',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'alvobot_essential_pages_translation' ),
				'strings' => array(
					'translating'         => __( 'Traduzindo...', 'alvobot-pro' ),
					'success'             => __( 'Sucesso!', 'alvobot-pro' ),
					'error'               => __( 'Erro:', 'alvobot-pro' ),
					'confirm_translate'   => __( 'Tem certeza que deseja traduzir esta página?', 'alvobot-pro' ),
					'select_languages'    => __( 'Selecione pelo menos um idioma.', 'alvobot-pro' ),
					'select_pages'        => __( 'Selecione pelo menos uma página.', 'alvobot-pro' ),
					'creating_page'       => __( 'Criando página', 'alvobot-pro' ),
					'translating_content' => __( 'Traduzindo conteúdo', 'alvobot-pro' ),
					'adding_to_menu'      => __( 'Adicionando ao menu', 'alvobot-pro' ),
					'completed'           => __( 'Concluído!', 'alvobot-pro' ),
					'pages_created'       => __( 'páginas criadas com sucesso!', 'alvobot-pro' ),
				),
			)
		);
	}

	/**
	 * AJAX handler para traduzir páginas
	 */
	public function ajax_translate_pages() {
		// Verifica nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'alvobot_essential_pages_translation' ) ) {
			wp_send_json_error( __( 'Acesso negado.', 'alvobot-pro' ) );
		}

		// Verifica permissões
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( __( 'Permissões insuficientes.', 'alvobot-pro' ) );
		}

		$page_id          = intval( $_POST['page_id'] );
		$target_languages = array_map( 'sanitize_text_field', $_POST['target_languages'] );

		if ( empty( $page_id ) || empty( $target_languages ) ) {
			wp_send_json_error( __( 'Parâmetros inválidos.', 'alvobot-pro' ) );
		}

		$post = get_post( $page_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Página não encontrada.', 'alvobot-pro' ) );
		}

		$page_type = get_post_meta( $page_id, '_essential_page_type', true );
		if ( ! $page_type ) {
			wp_send_json_error( __( 'Tipo de página não identificado.', 'alvobot-pro' ) );
		}

		$translated_pages = array();
		$errors           = array();

		foreach ( $target_languages as $lang_code ) {
			try {
				// Traduz o conteúdo
				$translated_content = $this->translate_page_content( $post->post_content, $lang_code );
				$translated_title   = $this->translate_text( $post->post_title, $lang_code );

				// Cria a página traduzida
				$translated_page = $this->create_translated_page( $post, $translated_title, $translated_content, $lang_code, $page_type );

				if ( ! is_wp_error( $translated_page ) ) {
					$translated_pages[ $lang_code ] = $translated_page;
				} else {
					$errors[ $lang_code ] = $translated_page->get_error_message();
				}
			} catch ( Exception $e ) {
				$errors[ $lang_code ] = $e->getMessage();
			}
		}

		wp_send_json_success(
			array(
				'translated_pages' => $translated_pages,
				'errors'           => $errors,
				'total'            => count( $target_languages ),
				'success_count'    => count( $translated_pages ),
			)
		);
	}

	/**
	 * AJAX handler para obter idiomas disponíveis
	 */
	public function ajax_get_languages() {
		// Verifica nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'alvobot_essential_pages_translation' ) ) {
			wp_send_json_error( __( 'Acesso negado.', 'alvobot-pro' ) );
		}

		$languages = array();

		// Primeiro tenta Polylang
		if ( function_exists( 'PLL' ) && PLL()->model ) {
			$pll_languages = PLL()->model->get_languages_list();
			foreach ( $pll_languages as $lang ) {
				$languages[ $lang->slug ] = array(
					'name'        => $lang->name,
					'native_name' => $this->get_language_native_name( $lang->slug ),
					'flag'        => $this->get_language_flag( $lang->slug ),
				);
			}
		}

		// Remove o idioma atual
		$current_lang = $this->get_current_language();
		unset( $languages[ $current_lang ] );

		wp_send_json_success(
			array(
				'languages' => $languages,
				'current'   => $current_lang,
			)
		);
	}

	/**
	 * AJAX handler para criar páginas traduzidas
	 */
	public function ajax_create_translated_pages() {
		// Verifica nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'alvobot_essential_pages_translation' ) ) {
			wp_send_json_error( __( 'Acesso negado.', 'alvobot-pro' ) );
		}

		// Verifica permissões
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( __( 'Permissões insuficientes.', 'alvobot-pro' ) );
		}

		$target_languages = array_map( 'sanitize_text_field', $_POST['target_languages'] );
		$selected_pages   = array_map( 'sanitize_text_field', $_POST['selected_pages'] );

		if ( empty( $target_languages ) || empty( $selected_pages ) ) {
			wp_send_json_error( __( 'Parâmetros inválidos.', 'alvobot-pro' ) );
		}

		$results           = array();
		$total_operations  = count( $target_languages ) * count( $selected_pages );
		$current_operation = 0;

		foreach ( $target_languages as $lang_code ) {
			$results[ $lang_code ] = array();

			foreach ( $selected_pages as $page_type ) {
				++$current_operation;

				try {
					// Carrega o template da página
					$template_content = $this->get_template_content( $page_type );

					// Traduz o conteúdo
					$translated_content = $this->translate_page_content( $template_content, $lang_code );
					$translated_title   = $this->translate_text( $this->essential_pages[ $page_type ]['title'], $lang_code );

					// Cria a página
					$page_data = array(
						'post_title'   => $translated_title,
						'post_content' => $translated_content,
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_name'    => $this->essential_pages[ $page_type ]['slug'] . '-' . $lang_code,
					);

					$page_id = wp_insert_post( $page_data );

					if ( ! is_wp_error( $page_id ) ) {
						// Adiciona metadados
						update_post_meta( $page_id, '_essential_page_type', $page_type );
						update_post_meta( $page_id, '_essential_page_language', $lang_code );
						update_post_meta( $page_id, '_essential_page_version', $this->plugin_version );

						// Se for Polylang, conecta as traduções
						if ( function_exists( 'pll_set_post_language' ) ) {
							pll_set_post_language( $page_id, $lang_code );
						}

						$results[ $lang_code ][ $page_type ] = array(
							'success'   => true,
							'page_id'   => $page_id,
							'title'     => $translated_title,
							'edit_link' => get_edit_post_link( $page_id ),
							'view_link' => get_permalink( $page_id ),
						);

						AlvoBotPro::debug_log( 'essential_pages', "Created translated page: {$page_type} in {$lang_code} (ID: {$page_id})" );
					} else {
						$results[ $lang_code ][ $page_type ] = array(
							'success' => false,
							'error'   => $page_id->get_error_message(),
						);
					}
				} catch ( Exception $e ) {
					$results[ $lang_code ][ $page_type ] = array(
						'success' => false,
						'error'   => $e->getMessage(),
					);
				}

				// Permite que o JavaScript atualize o progresso
				if ( $current_operation % 2 === 0 ) {
					$progress = ( $current_operation / $total_operations ) * 100;
					AlvoBotPro::debug_log( 'essential_pages', "Translation progress: {$progress}%" );
				}
			}
		}

		wp_send_json_success(
			array(
				'results'         => $results,
				'total_languages' => count( $target_languages ),
				'total_pages'     => count( $selected_pages ),
				'total_created'   => $this->count_successful_results( $results ),
			)
		);
	}

	/**
	 * Traduz conteúdo da página
	 */
	private function translate_page_content( $content, $target_language ) {
		// Processa placeholders primeiro
		$processed_content = $this->process_content( $content );

		// Se o módulo multi-languages está ativo, usa ele
		if ( class_exists( 'AlvoBotPro_MultiLanguages' ) ) {
			$multi_lang = AlvoBotPro_MultiLanguages::get_instance();

			if ( method_exists( $multi_lang, 'translate_text_simple' ) ) {
				return $multi_lang->translate_text_simple( $processed_content, $target_language );
			}
		}

		// Fallback: traduz por partes (parágrafos)
		return $this->translate_content_by_parts( $processed_content, $target_language );
	}

	/**
	 * Traduz texto simples
	 */
	private function translate_text( $text, $target_language ) {
		if ( class_exists( 'AlvoBotPro_MultiLanguages' ) ) {
			$multi_lang = AlvoBotPro_MultiLanguages::get_instance();

			if ( method_exists( $multi_lang, 'translate_text_simple' ) ) {
				return $multi_lang->translate_text_simple( $text, $target_language );
			}
		}

		// Fallback: adiciona prefixo do idioma
		return '[' . strtoupper( $target_language ) . '] ' . $text;
	}

	/**
	 * Traduz conteúdo por partes
	 */
	private function translate_content_by_parts( $content, $target_language ) {
		// Para fallback, simplesmente adiciona indicação do idioma
		$lang_comment = '<!-- Traduzido para: ' . strtoupper( $target_language ) . " -->\n";
		return $lang_comment . $content;
	}

	/**
	 * Cria página traduzida
	 */
	private function create_translated_page( $original_post, $translated_title, $translated_content, $lang_code, $page_type ) {
		$page_data = array(
			'post_title'   => $translated_title,
			'post_content' => $translated_content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => $original_post->post_name . '-' . $lang_code,
			'post_author'  => get_current_user_id(),
		);

		$page_id = wp_insert_post( $page_data );

		if ( ! is_wp_error( $page_id ) ) {
			// Adiciona metadados
			update_post_meta( $page_id, '_essential_page_type', $page_type );
			update_post_meta( $page_id, '_essential_page_language', $lang_code );
			update_post_meta( $page_id, '_essential_page_version', $this->plugin_version );
			update_post_meta( $page_id, '_essential_page_original', $original_post->ID );

			// Se for Polylang, conecta as traduções
			if ( function_exists( 'pll_set_post_language' ) ) {
				pll_set_post_language( $page_id, $lang_code );

				// Conecta com a página original se Polylang suportar
				if ( function_exists( 'pll_save_post_translations' ) ) {
					$translations               = pll_get_post_translations( $original_post->ID );
					$translations[ $lang_code ] = $page_id;
					pll_save_post_translations( $translations );
				}
			}

			return array(
				'page_id'   => $page_id,
				'title'     => $translated_title,
				'edit_link' => get_edit_post_link( $page_id ),
				'view_link' => get_permalink( $page_id ),
			);
		}

		return $page_id; // WP_Error
	}

	/**
	 * Obtém o idioma atual
	 */
	private function get_current_language() {
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language();
			if ( $lang ) {
				return $lang;
			}
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return ICL_LANGUAGE_CODE;
		}

		$locale = get_locale();
		return substr( $locale, 0, 2 );
	}

	/**
	 * Obtém nome nativo do idioma
	 */
	private function get_language_native_name( $lang_code ) {
		$native_names = array(
			'pt' => 'Português',
			'en' => 'English',
			'es' => 'Español',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'it' => 'Italiano',
			'ru' => 'Русский',
			'zh' => '中文',
			'ja' => '日本語',
			'ko' => '한국어',
			'ar' => 'العربية',
			'hi' => 'हिन्दी',
			'nl' => 'Nederlands',
			'tr' => 'Türkçe',
		);

		return isset( $native_names[ $lang_code ] ) ? $native_names[ $lang_code ] : $lang_code;
	}

	/**
	 * Obtém emoji da bandeira do país
	 */
	private function get_language_flag( $lang_code ) {
		$flags = array(
			'pt' => '🇧🇷',
			'en' => '🇺🇸',
			'es' => '🇪🇸',
			'fr' => '🇫🇷',
			'de' => '🇩🇪',
			'it' => '🇮🇹',
			'ru' => '🇷🇺',
			'zh' => '🇨🇳',
			'ja' => '🇯🇵',
			'ko' => '🇰🇷',
			'ar' => '🇸🇦',
			'hi' => '🇮🇳',
			'nl' => '🇳🇱',
			'tr' => '🇹🇷',
		);

		return isset( $flags[ $lang_code ] ) ? $flags[ $lang_code ] : '🌐';
	}

	/**
	 * Conta resultados bem-sucedidos
	 */
	private function count_successful_results( $results ) {
		$count = 0;
		foreach ( $results as $lang_results ) {
			foreach ( $lang_results as $page_result ) {
				if ( $page_result['success'] ) {
					++$count;
				}
			}
		}
		return $count;
	}
}

/**
 * Instancia a classe ao carregar o plugin
 */
add_action(
	'plugins_loaded',
	function () {
		new AlvoBotPro_EssentialPages();
	}
);