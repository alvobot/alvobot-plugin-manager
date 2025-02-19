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
        'terms' => [
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
        ]
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
        $this->base_dir = plugin_dir_path( __FILE__ );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_shortcode( 'alvobot_contact_form', [ $this, 'render_contact_form' ] );
    }

    /**
     * Registra e enfileira os estilos do admin
     *
     * @param string $hook_suffix O sufixo do hook da página atual
     * @return void
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Só carrega os estilos na página de configurações do módulo
        if ( 'settings_page_alvobot-essential-pages' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'alvobot-essential-pages-admin',
            plugin_dir_url( __FILE__ ) . 'css/admin.css',
            [],
            $this->plugin_version
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

        // Processa ações enviadas (ações globais ou individuais)
        if ( isset( $_POST['alvobot_action'] ) && check_admin_referer( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ) ) {
            $action = sanitize_text_field( $_POST['alvobot_action'] );
            if ( 'create_all' === $action ) {
                $this->create_essential_pages( true );
                add_settings_error(
                    'alvobot_essential_pages',
                    'pages_created',
                    __( 'Páginas essenciais criadas/recriadas com sucesso.', 'alvobot-pro' ),
                    'success'
                );
            } elseif ( 'delete_all' === $action ) {
                $this->delete_essential_pages();
                add_settings_error(
                    'alvobot_essential_pages',
                    'pages_deleted',
                    __( 'Todas as páginas essenciais foram excluídas.', 'alvobot-pro' ),
                    'success'
                );
            } elseif ( 'create_page' === $action && isset( $_POST['page_key'] ) ) {
                $page_key = sanitize_text_field( $_POST['page_key'] );
                $this->create_single_page( $page_key );
                add_settings_error(
                    'alvobot_essential_pages',
                    'page_created',
                    sprintf(
                        /* translators: %s: page title */
                        __( 'Página %s criada com sucesso.', 'alvobot-pro' ),
                        '<strong>' . esc_html( $this->get_page_title( $page_key ) ) . '</strong>'
                    ),
                    'success'
                );
            } elseif ( 'delete_page' === $action && isset( $_POST['page_key'] ) ) {
                $page_key = sanitize_text_field( $_POST['page_key'] );
                $this->delete_single_page( $page_key );
                add_settings_error(
                    'alvobot_essential_pages',
                    'page_deleted',
                    sprintf(
                        /* translators: %s: page title */
                        __( 'Página %s excluída com sucesso.', 'alvobot-pro' ),
                        '<strong>' . esc_html( $this->get_page_title( $page_key ) ) . '</strong>'
                    ),
                    'success'
                );
            }
        }

        // Obter status de cada página essencial
        $pages_status = [];
        foreach ( $this->essential_pages as $key => $data ) {
            $existing = get_page_by_path( $data['slug'] );
            $pages_status[ $key ] = [
                'exists' => ( $existing && $existing->post_status === 'publish' ),
                'id'     => ( $existing ) ? $existing->ID : 0,
            ];
        }
        ?>
        <div class="alvobot-pro-wrap">
  <div class="alvobot-pro-header">
    <h1><?php esc_html_e( 'AlvoBot Essential Pages', 'alvobot-pro' ); ?></h1>
    <p><?php esc_html_e( 'Gerencie as páginas essenciais do site. Veja abaixo o status atual e utilize as ações disponíveis para cada página.', 'alvobot-pro' ); ?></p>
  </div>

  <?php settings_errors( 'alvobot_essential_pages' ); ?>

  <div class="alvobot-pro-modules">
    <?php foreach ( $this->essential_pages as $key => $page ):
      $status = $pages_status[ $key ];
    ?>
      <div class="alvobot-pro-module-card <?php echo $status['exists'] ? 'module-enabled' : ''; ?>">
        <div class="alvobot-pro-module-header">
          <h2 class="alvobot-pro-module-title">
            <?php echo esc_html( $this->get_page_title( $key ) ); ?>
          </h2>
          <span class="status-indicator <?php echo $status['exists'] ? 'status-registered' : 'status-unregistered'; ?>"></span>
        </div>
        
        <p class="alvobot-pro-module-description">
          <?php 
            printf(
              /* translators: %s: page slug */
              esc_html__( 'Slug: %s', 'alvobot-pro' ),
              esc_html( $page['slug'] )
            );
          ?>
          <br>
          <?php esc_html_e( 'Status:', 'alvobot-pro' ); ?> 
          <span class="<?php echo $status['exists'] ? 'text-success' : 'text-error'; ?>">
            <?php echo $status['exists'] 
              ? esc_html__( 'Publicado', 'alvobot-pro' ) 
              : esc_html__( 'Não Criado', 'alvobot-pro' ); ?>
          </span>
        </p>

        <div class="alvobot-pro-module-actions">
          <?php if ( $status['exists'] ): 
            $view_link = get_permalink( $status['id'] );
            $edit_link = get_edit_post_link( $status['id'] );
          ?>
            <a href="<?php echo esc_url( $view_link ); ?>" target="_blank" class="button button-secondary">
              <?php esc_html_e( 'Ver', 'alvobot-pro' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_link ); ?>" class="button button-secondary">
              <?php esc_html_e( 'Editar', 'alvobot-pro' ); ?>
            </a>
            <form method="post" style="display:inline-block;">
              <?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
              <input type="hidden" name="alvobot_action" value="delete_page">
              <input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
              <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Tem certeza que deseja excluir esta página?', 'alvobot-pro' ); ?>');">
                <?php esc_html_e( 'Excluir', 'alvobot-pro' ); ?>
              </button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline-block;">
              <?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
              <input type="hidden" name="alvobot_action" value="create_page">
              <input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
              <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Criar', 'alvobot-pro' ); ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Cartão extra para Ações Globais -->
  <div class="alvobot-pro-module-card" style="margin-top: 2em;">
    <div class="alvobot-pro-module-header">
      <h2 class="alvobot-pro-module-title"><?php esc_html_e( 'Ações Globais', 'alvobot-pro' ); ?></h2>
    </div>
    <div class="alvobot-pro-module-actions" style="margin-top: 1em;">
      <form method="post" style="display: inline-block;">
        <?php wp_nonce_field( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ); ?>
        <button type="submit" name="alvobot_action" value="create_all" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Isto criará ou recriará todas as páginas essenciais. Continuar?', 'alvobot-pro' ); ?>');">
          <?php esc_html_e( 'Criar/Recriar Todas', 'alvobot-pro' ); ?>
        </button>
        <button type="submit" name="alvobot_action" value="delete_all" class="button" onclick="return confirm('<?php esc_attr_e( 'Tem certeza que deseja excluir TODAS as páginas essenciais?', 'alvobot-pro' ); ?>');">
          <?php esc_html_e( 'Excluir Todas', 'alvobot-pro' ); ?>
        </button>
      </form>
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
            error_log( sprintf( 'Template file not found: %s', $template_file ) );
            return '';
        }

        ob_start();
        include $template_file;
        return ob_get_clean();
    }

    /**
     * Obtém as informações da empresa
     *
     * @return array
     */
    private function get_company_info(): array {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $admin_email = get_option('admin_email');

        // Informações padrão da empresa (podem ser sobrescritas por opções salvas)
        $default_info = [
            'name' => $site_name,
            'legal_name' => $site_name . ' Tecnologia LTDA',
            'document' => '12.345.678/0001-90',
            'state_document' => '123.456.789.000',
            'address' => 'Av. Paulista, 1000, 15º andar',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip' => '01310-100',
            'country' => 'Brasil',
            'phone' => '+55 (11) 4858-9900',
            'whatsapp' => '+55 (11) 98765-4321',
            'email' => $admin_email,
            'support_email' => 'suporte@' . parse_url($site_url, PHP_URL_HOST),
            'sales_email' => 'vendas@' . parse_url($site_url, PHP_URL_HOST),
            'working_hours' => 'Segunda a Sexta, das 9h às 18h',
            'working_hours_extended' => 'Segunda a Sexta: 9h às 18h | Sábado: 9h às 13h',
            'social_media' => [
                'facebook' => 'https://facebook.com/' . sanitize_title($site_name),
                'instagram' => 'https://instagram.com/' . sanitize_title($site_name),
                'linkedin' => 'https://linkedin.com/company/' . sanitize_title($site_name),
                'youtube' => 'https://youtube.com/@' . sanitize_title($site_name),
            ],
            'legal_info' => [
                'founded' => '2020',
                'registration' => 'JUCESP nº 1234567890-1',
                'legal_representative' => 'João Silva',
                'technical_responsible' => 'Maria Santos - CRA-SP 123456',
            ],
            'support_hours' => 'Segunda a Sexta: 8h às 20h | Sábado: 9h às 15h',
            'emergency_phone' => '+55 (11) 98888-7777',
        ];

        // Obtém as opções salvas e mescla com as informações padrão
        $saved_options = get_option('alvobot_company_info', []);
        return wp_parse_args($saved_options, $default_info);
    }

    /**
     * Processa o conteúdo substituindo os placeholders
     *
     * @param string $content Conteúdo a ser processado
     * @return string
     */
    private function process_content(string $content): string {
        $company = $this->get_company_info();
        $current_year = date('Y');
        $current_date = wp_date(get_option('date_format'));

        $replacements = [
            // Informações básicas do site
            '[site_name]' => get_bloginfo('name'),
            '[site_url]' => home_url(),
            '[current_year]' => $current_year,
            '[terms_date]' => $current_date,
            '[privacy_date]' => $current_date,
            
            // Informações da empresa
            '[company_name]' => $company['name'],
            '[company_legal_name]' => $company['legal_name'],
            '[company_document]' => $company['document'],
            '[company_state_document]' => $company['state_document'],
            '[company_address]' => $company['address'],
            '[company_neighborhood]' => $company['neighborhood'],
            '[company_city]' => $company['city'],
            '[company_state]' => $company['state'],
            '[company_zip]' => $company['zip'],
            '[company_country]' => $company['country'],
            '[company_full_address]' => sprintf('%s, %s - %s/%s, CEP %s, %s',
                $company['address'],
                $company['neighborhood'],
                $company['city'],
                $company['state'],
                $company['zip'],
                $company['country']
            ),
            
            // Contatos
            '[company_phone]' => $company['phone'],
            '[company_whatsapp]' => $company['whatsapp'],
            '[contact_email]' => $company['email'],
            '[support_email]' => $company['support_email'],
            '[sales_email]' => $company['sales_email'],
            
            // Horários
            '[working_hours]' => $company['working_hours'],
            '[working_hours_extended]' => $company['working_hours_extended'],
            '[support_hours]' => $company['support_hours'],
            
            // Redes sociais
            '[facebook_url]' => $company['social_media']['facebook'],
            '[instagram_url]' => $company['social_media']['instagram'],
            '[linkedin_url]' => $company['social_media']['linkedin'],
            '[youtube_url]' => $company['social_media']['youtube'],
            
            // Informações legais
            '[company_founded]' => $company['legal_info']['founded'],
            '[company_registration]' => $company['legal_info']['registration'],
            '[legal_representative]' => $company['legal_info']['legal_representative'],
            '[technical_responsible]' => $company['legal_info']['technical_responsible'],
            '[emergency_phone]' => $company['emergency_phone'],
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Cria ou recria todas as páginas essenciais
     *
     * @param bool $force_recreate Se true, exclui as páginas existentes antes de recriar.
     */
    public function create_essential_pages( $force_recreate = false ) {
        foreach ( $this->essential_pages as $key => $page_data ) {
            if ( $force_recreate ) {
                $existing = get_page_by_path( $page_data['slug'] );
                if ( $existing ) {
                    wp_delete_post( $existing->ID, true );
                }
            }
            $existing = get_page_by_path( $page_data['slug'] );
            if ( ! $existing ) {
                $content = $this->get_template_content( $key );
                $content = $this->process_content( $content );
                $page_id = wp_insert_post( [
                    'post_title'   => $this->get_page_title( $key ),
                    'post_name'    => $page_data['slug'],
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => get_current_user_id()
                ] );
                if ( ! is_wp_error( $page_id ) ) {
                    update_post_meta( $page_id, '_essential_page_type', $key );
                    update_post_meta( $page_id, '_essential_page_version', $this->plugin_version );
                    // Se for a política de privacidade, atualiza a opção padrão do WordPress
                    if ( 'privacy' === $key ) {
                        update_option( 'wp_page_for_privacy', $page_id );
                    }
                    // Se desejar, para termos, use uma opção customizada, ex.:
                    if ( 'terms' === $key ) {
                        update_option( 'wp_page_for_terms', $page_id );
                    }
                }
            }
        }
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
     * @param string $key Chave da página a ser criada
     * @return void
     */
    private function create_single_page( string $key ): void {
        if ( ! isset( $this->essential_pages[$key] ) ) {
            return;
        }

        $page_data = $this->essential_pages[$key];
        $content = $this->get_template_content( $key );
        
        if ( empty( $content ) ) {
            error_log( sprintf( 'Empty content for page: %s', $key ) );
            return;
        }

        $content = $this->process_content( $content );

        $page = [
            'post_title'    => $this->get_page_title( $key ),
            'post_name'     => $page_data['slug'],
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
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
        if ( ! isset( $this->essential_pages[$key] ) ) {
            return;
        }
        $page_data = $this->essential_pages[$key];
        $existing = get_page_by_path( $page_data['slug'] );
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
        return __( $this->essential_pages[$key]['title'], 'alvobot-pro' );
    }

    /**
     * Renderiza o formulário de contato
     *
     * @return string
     */
    public function render_contact_form(): string {
        return alvobot_contact_form_shortcode();
    }
}

/**
 * Instancia a classe ao carregar o plugin
 */
add_action( 'plugins_loaded', function() {
    new AlvoBotPro_EssentialPages();
} );