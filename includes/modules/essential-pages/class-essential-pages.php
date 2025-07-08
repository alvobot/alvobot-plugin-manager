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
        $this->base_dir = dirname(__FILE__) . '/';
        // Removido: não precisamos adicionar o menu aqui, pois já é adicionado pela classe principal
        // add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_shortcode( 'alvobot_contact_form', [ $this, 'render_contact_form' ] );

        // Debug para verificar o caminho dos templates
        AlvoBotPro::debug_log('essential_pages', 'base_dir: ' . $this->base_dir);
    }

    /**
     * Registra e enfileira os estilos do admin
     *
     * @param string $hook_suffix O sufixo do hook da página atual
     * @return void
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Só carrega os estilos na página de configurações do módulo
        if ( !isset($_GET['page']) || 'alvobot-pro-essential-pages' !== $_GET['page'] ) {
            return;
        }

        AlvoBotPro::debug_log('essential_pages', 'Enfileirando assets do Essential Pages');
        AlvoBotPro::debug_log('essential_pages', 'CSS path: ' . plugin_dir_url( __FILE__ ) . 'css/admin.css');
        AlvoBotPro::debug_log('essential_pages', 'JS path: ' . plugin_dir_url( __FILE__ ) . 'js/admin.js');

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
            ['jquery'],
            $this->plugin_version,
            true
        );
        
        // Localiza o script para traduções
        wp_localize_script(
            'alvobot-essential-pages-admin',
            'alvobotEssentialPages',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alvobot_essential_pages_nonce'),
                'strings' => [
                    'confirmCreate' => __('Isto criará ou recriará todas as páginas essenciais. Continuar?', 'alvobot-pro'),
                    'confirmDelete' => __('Tem certeza que deseja excluir esta página?', 'alvobot-pro'),
                    'confirmDeleteAll' => __('Tem certeza que deseja excluir todas as páginas essenciais?', 'alvobot-pro'),
                    'loading' => __('Processando...', 'alvobot-pro'),
                    'operationSuccess' => __('Operação realizada com sucesso!', 'alvobot-pro'),
                    'operationError' => __('Ocorreu um erro ao realizar a operação.', 'alvobot-pro')
                ],
                'pageNames' => [
                    'terms' => __('Termos de Uso', 'alvobot-pro'),
                    'privacy' => __('Política de Privacidade', 'alvobot-pro'),
                    'contact' => __('Contato', 'alvobot-pro')
                ]
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
        
        AlvoBotPro::debug_log('essential_pages', 'render_settings_page chamado');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AlvoBotPro::debug_log('essential_pages', 'Método POST detectado');
            AlvoBotPro::debug_log('essential_pages', 'POST data = ' . print_r($_POST, true));
        }

        // Processar ações do formulário
        $action_complete = '';
        $action_status = 'success';
        
        // Processa ações enviadas (ações globais ou individuais)
        if ( isset( $_POST['alvobot_action'] ) && check_admin_referer( 'alvobot_essential_pages_nonce', 'alvobot_nonce' ) ) {
            $action = sanitize_text_field( $_POST['alvobot_action'] );
            
            // Executar a ação solicitada
            switch ( $action ) {
                case 'create_all':
                    AlvoBotPro::debug_log('essential_pages', 'Iniciando criação de todas as páginas');
                    $result = $this->create_essential_pages( true );
                    
                    // Gravar resultado para exibição 
                    if ($result) {
                        $action_complete = 'all_created';
                    } else {
                        $action_complete = 'error';
                        $action_status = 'error';
                    }
                    
                    AlvoBotPro::debug_log('essential_pages', 'Resultado da criação: ' . ($result ? 'sucesso' : 'falha'));
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
                            $result = $this->create_essential_page( $page_key, true );
                            $action_complete = $result ? 'created' : 'error';
                            $action_status = $result ? 'success' : 'error';
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
            if ($action_complete) {
                // Armazenar a mensagem na opção temporária
                update_option('alvobot_essential_pages_message', [
                    'action' => $action_complete,
                    'status' => $action_status,
                    'time' => time()
                ]);
                
                // Redireciona para mesma página - não use exit() para evitar tela em branco
                echo "<meta http-equiv='refresh' content='0;url=" . 
                    esc_url(add_query_arg(['page' => 'alvobot-pro-essential-pages'], admin_url('admin.php'))) . "'>";
                echo "<p>Redirecionando...</p>";
                return; // Apenas retorne, não encerre a execução
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
        <div class="alvobot-admin-wrap">
            <div class="alvobot-admin-container">
                <div class="alvobot-admin-header">
                    <h1><?php esc_html_e( 'Páginas Essenciais', 'alvobot-pro' ); ?></h1>
                    <p><?php esc_html_e( 'Crie e gerencie páginas essenciais como Termos de Uso, Política de Privacidade e Contato de forma automática.', 'alvobot-pro' ); ?></p>
                </div>

                <div class="alvobot-notice-container">
                    <?php 
                    // Exibir mensagens de notificação
                    $message_data = get_option('alvobot_essential_pages_message');
                    if ($message_data && isset($message_data['time']) && (time() - $message_data['time'] < 60)) {
                        $action = $message_data['action'];
                        $status_class = ($message_data['status'] === 'success') ? 'updated' : 'error';
                        $message = '';
                        
                        switch($action) {
                            case 'created':
                                $message = __('Página criada com sucesso!', 'alvobot-pro');
                                break;
                            case 'deleted':
                                $message = __('Página excluída com sucesso!', 'alvobot-pro');
                                break;
                            case 'all_created':
                                $message = __('Todas as páginas foram criadas com sucesso e adicionadas ao Footer Menu! Adicione esse menu ao rodapé do seu tema.', 'alvobot-pro');
                                break;
                            case 'all_deleted':
                                $message = __('Todas as páginas foram excluídas com sucesso!', 'alvobot-pro');
                                break;
                            case 'error':
                                $message = __('Ocorreu um erro ao processar a ação solicitada.', 'alvobot-pro');
                                break;
                            default:
                                $message = __('Operação concluída com sucesso!', 'alvobot-pro');
                        }
                        
                        echo '<div class="notice ' . $status_class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
                        delete_option('alvobot_essential_pages_message');
                    }
                    settings_errors( 'alvobot_essential_pages' ); 
                    ?>
                </div>

                <div class="alvobot-grid alvobot-grid-auto">
    <?php foreach ( $this->essential_pages as $key => $page ):
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
            <?php if ( $status['exists'] ): ?>
              <span class="alvobot-badge alvobot-badge-success">
                <span class="alvobot-status-indicator success"></span>
                <?php esc_html_e( 'Publicado', 'alvobot-pro' ); ?>
              </span>
            <?php else: ?>
              <span class="alvobot-badge alvobot-badge-error">
                <span class="alvobot-status-indicator error"></span>
                <?php esc_html_e( 'Não Criado', 'alvobot-pro' ); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="alvobot-card-footer">
          <div class="alvobot-btn-group">
          <?php if ( $status['exists'] ): 
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
          <?php else: ?>
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
            AlvoBotPro::debug_log('essential-pages', sprintf( 'Template file not found: %s', $template_file ));
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
     * Adiciona páginas ao menu do rodapé - se o menu não existir, cria um novo
     *
     * @param array $page_ids Array com os IDs das páginas a serem adicionadas ao menu
     * @return void
     */
    private function add_pages_to_footer_menu( array $page_ids ): void {
        AlvoBotPro::debug_log('essential_pages', 'Adicionando páginas ao menu do rodapé. IDs: ' . implode(',', $page_ids));
        
        // Vamos primeiro verificar se existe um menu de rodapé
        $footer_menu_locations = array('footer', 'footer-menu', 'menu-footer', 'bottom', 'bottom-menu');
        $footer_menu_id = 0;
        
        // Verifica se há algum menu já associado a uma localização de rodapé
        $menu_locations = get_nav_menu_locations();
        foreach ($footer_menu_locations as $location) {
            if (isset($menu_locations[$location]) && $menu_locations[$location] > 0) {
                $footer_menu_id = $menu_locations[$location];
                break;
            }
        }
        
        // Se não encontramos um menu de rodapé, vamos procurar um menu com "footer" ou "rodape" no nome
        if (!$footer_menu_id) {
            $all_menus = wp_get_nav_menus();
            foreach ($all_menus as $menu) {
                if (stripos($menu->name, 'footer') !== false || stripos($menu->name, 'rodape') !== false || 
                    stripos($menu->name, 'rodapé') !== false || stripos($menu->name, 'bottom') !== false) {
                    $footer_menu_id = $menu->term_id;
                    break;
                }
            }
        }
        
        // Se ainda não encontramos um menu, vamos criar um novo
        if (!$footer_menu_id) {
            $menu_name = __('Footer Menu', 'alvobot-pro');
            $menu_id = wp_create_nav_menu($menu_name);
            
            if (is_wp_error($menu_id)) {
                AlvoBotPro::debug_log('essential_pages', 'Erro ao criar o menu do rodapé - ' . $menu_id->get_error_message());
                return;
            }
            
            $footer_menu_id = $menu_id;
            
            // Associar o menu a uma localização do tema, se disponível
            $theme_locations = get_registered_nav_menus();
            $location_to_use = '';
            
            foreach ($footer_menu_locations as $location) {
                if (isset($theme_locations[$location])) {
                    $location_to_use = $location;
                    break;
                }
            }
            
            if ($location_to_use) {
                $menu_locations[$location_to_use] = $footer_menu_id;
                set_theme_mod('nav_menu_locations', $menu_locations);
                
                AlvoBotPro::debug_log('essential_pages', 'Menu do rodapé criado e associado à localização: ' . $location_to_use);
            }
        }
        
        if ($footer_menu_id) {
            // Obter itens existentes para não duplicar
            $existing_items = wp_get_nav_menu_items($footer_menu_id);
            $existing_page_ids = [];
            
            if ($existing_items) {
                foreach ($existing_items as $item) {
                    if ($item->object === 'page') {
                        $existing_page_ids[] = $item->object_id;
                    }
                }
            }
            
            // Adicionar as páginas ao menu
            $added_count = 0;
            foreach ($page_ids as $page_id) {
                if (!in_array($page_id, $existing_page_ids)) {
                    $page_data = get_post($page_id);
                    if ($page_data) {
                        $item_id = wp_update_nav_menu_item($footer_menu_id, 0, [
                            'menu-item-title' => get_the_title($page_id),
                            'menu-item-object' => 'page',
                            'menu-item-object-id' => $page_id,
                            'menu-item-type' => 'post_type',
                            'menu-item-status' => 'publish'
                        ]);
                        
                        if (!is_wp_error($item_id)) {
                            $added_count++;
                        }
                    }
                }
            }
            
            AlvoBotPro::debug_log('essential_pages', $added_count . ' páginas adicionadas ao menu do rodapé');
        }
    }
    
    /**
     * Cria ou recria todas as páginas essenciais
     *
     * @param bool $force_recreate Se true, exclui as páginas existentes antes de recriar.
     * @return bool Retorna true se todas as páginas foram criadas com sucesso
     */
    public function create_essential_pages( $force_recreate = false ) {
        AlvoBotPro::debug_log('essential_pages', 'Iniciando create_essential_pages com force_recreate=' . ($force_recreate ? 'true' : 'false'));
        
        $created_page_ids = [];
        
        foreach ( $this->essential_pages as $key => $page_data ) {
            AlvoBotPro::debug_log('essential_pages', 'Processando página ' . $key . ' (slug: ' . $page_data['slug'] . ')');
            
            if ( $force_recreate ) {
                $existing = get_page_by_path( $page_data['slug'] );
                if ( $existing ) {
                    AlvoBotPro::debug_log('essential_pages', 'Excluindo página existente ID=' . $existing->ID);
                    wp_delete_post( $existing->ID, true );
                }
            }
            
            $existing = get_page_by_path( $page_data['slug'] );
            if ( ! $existing ) {
                AlvoBotPro::debug_log('essential_pages', 'Obtendo conteúdo do template ' . $key);
                $content = $this->get_template_content( $key );
                
                AlvoBotPro::debug_log('essential_pages', 'Template carregado com ' . strlen($content) . ' caracteres');
                if (empty($content)) {
                    AlvoBotPro::debug_log('essential_pages', 'ERRO - Template vazio para ' . $key);
                }
                
                $content = $this->process_content( $content );
                
                AlvoBotPro::debug_log('essential_pages', 'Conteúdo processado com ' . strlen($content) . ' caracteres');
                
                
                $page_id = wp_insert_post( [
                    'post_title'   => $this->get_page_title( $key ),
                    'post_name'    => $page_data['slug'],
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => get_current_user_id()
                ] );
                
                if (is_wp_error($page_id)) {
                    AlvoBotPro::debug_log('essential_pages', 'ERRO ao criar página - ' . $page_id->get_error_message());
                } else {
                    AlvoBotPro::debug_log('essential_pages', 'Página criada com sucesso ID=' . $page_id);
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
        if (!empty($created_page_ids)) {
            $this->add_pages_to_footer_menu($created_page_ids);
        }
        
        AlvoBotPro::debug_log('essential_pages', 'Resultado final - criou ' . count($created_page_ids) . ' páginas');
        
        // Se pelo menos uma página foi criada, consideramos sucesso
        return !empty($created_page_ids);
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
            AlvoBotPro::debug_log('essential-pages', sprintf( 'Empty content for page: %s', $key ));
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