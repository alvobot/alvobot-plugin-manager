<?php
/**
 * Plugin Name: AlvoBot Plugin Manager
 * Plugin URI: https://github.com/alvobot/alvobot-plugin-manager
 * Description: Permite a gestão remota de plugins utilizando a plataforma AlvoBot.
 * Version: 1.4.0
 * Author: Alvobot - Cris Franklin
 * Author URI: https://github.com/alvobot
 * Text Domain: alvobot-plugin
 * Domain Path: /languages
 * License: GPL2
 */

// Bloqueia o acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define a URL do servidor central
if (!defined('GRP_SERVER_URL')) {
    define('GRP_SERVER_URL', 'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/api_plugin');
}

// Gera uma chave única para o site (pode ser melhorada para incluir autenticação robusta)
if ( ! get_option( 'grp_site_token' ) ) {
    $token = wp_generate_password( 32, false );
    update_option( 'grp_site_token', $token );
} else {
    $token = get_option( 'grp_site_token' );
}

/**
 * Função para registrar o site no servidor central
 *
 * @param string $app_password Senha de aplicativo gerada para 'alvobot'.
 * @return bool Retorna true em sucesso ou false em falha.
 */
function grp_register_site( $app_password = null ) {
    $data = array(
        'action'     => 'register_site',
        'site_url'   => get_site_url(),
        'token'      => get_option( 'grp_site_token' ),
        'wp_version' => get_bloginfo( 'version' ),
        'plugins'    => array_keys( get_plugins() ),
    );

    if ( $app_password ) {
        $data['app_password'] = $app_password;
    }

    // Adiciona informações para depuração
    error_log( 'Dados a serem enviados para o webhook: ' . print_r( $data, true ) );

    $args = array(
        'body'        => wp_json_encode( $data ),
        'headers'     => array(
            'Content-Type' => 'application/json',
        ),
        'timeout'     => 15,
        'sslverify'   => true, // Defina como false temporariamente para depuração, se necessário
    );

    $response = wp_remote_post( GRP_SERVER_URL, $args );

    if ( is_wp_error( $response ) ) {
        error_log( 'Erro ao registrar o site: ' . $response->get_error_message() );
        return false;
    } else {
        // Log do código de status da resposta
        $status_code   = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        error_log( 'Resposta do webhook: Código de Status ' . $status_code );
        error_log( 'Resposta do webhook: ' . $response_body );

        if ( $status_code >= 200 && $status_code < 300 ) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Função para criar o usuário 'alvobot'
 *
 * @return WP_User|false Retorna o objeto WP_User ou false em falha.
 */
function grp_create_alvobot_user() {
    $username = 'alvobot';
    $email    = 'alvobot@alvobot.com.br';

    // Verifica se o usuário já existe
    $existing_user = get_user_by('login', $username);
    
    if ($existing_user) {
        error_log("Usuário 'alvobot' já existe. Verificando permissões...");
        
        // Verifica se é administrador
        if (!in_array('administrator', $existing_user->roles)) {
            error_log("Atualizando papel do usuário 'alvobot' para administrador");
            $existing_user->set_role('administrator');
        }
        
        return $existing_user;
    }

    // Cria novo usuário se não existir
    $password = wp_generate_password(12, false);
    $user_id  = wp_create_user($username, $password, $email);

    if (!is_wp_error($user_id)) {
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        error_log("Usuário 'alvobot' criado com sucesso. Senha: " . $password);
        return $user;
    }

    error_log("Erro ao criar o usuário 'alvobot': " . $user_id->get_error_message());
    return false;
}

/**
 * Função para gerar a senha de aplicativo para 'alvobot' e retornar a senha gerada
 *
 * @param WP_User $user Objeto do usuário 'alvobot'.
 * @return string|false Retorna a senha de aplicativo ou false em falha.
 */
function grp_generate_alvobot_app_password( $user ) {
    if ( ! $user ) {
        error_log( "Usuário 'alvobot' não encontrado para gerar senha de aplicativo." );
        return false;
    }

    if ( ! class_exists( 'WP_Application_Passwords' ) ) {
        require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
    }

    $app_name = 'AlvoBot App Integration';
    
    // Verifica se já existe uma senha de aplicativo com este nome
    $existing_passwords = WP_Application_Passwords::get_user_application_passwords( $user->ID );
    
    foreach ( $existing_passwords as $existing ) {
        if ( $existing['name'] === $app_name ) {
            error_log( "Removendo senha de aplicativo existente para 'AlvoBot App Integration'" );
            WP_Application_Passwords::delete_application_password( $user->ID, $existing['uuid'] );
            break;
        }
    }

    // Cria nova senha de aplicativo
    $app_password_args = array(
        'name' => $app_name,
    );

    error_log( "Gerando nova senha de aplicativo para 'AlvoBot App Integration'" );
    $application_password = WP_Application_Passwords::create_new_application_password( $user->ID, $app_password_args );

    if ( is_wp_error( $application_password ) ) {
        error_log( "Falha na geração da senha de aplicativo: " . $application_password->get_error_message() );
        return false;
    }

    if ( ! isset( $application_password[0] ) ) {
        error_log( "Senha de aplicativo não foi retornada corretamente." );
        return false;
    }

    $plain_password = $application_password[0];
    error_log( "Nova senha de aplicativo gerada com sucesso" );

    return $plain_password;
}

/**
 * Hook de ativação para registrar o site, criar o usuário 'alvobot', gerar a senha de aplicativo e o código
 *
 * @return void
 */
register_activation_hook( __FILE__, 'grp_on_activation' );

function grp_on_activation() {
    error_log( "Iniciando ativação do plugin Gestão Remota de Plugins." );

    // Cria ou recupera o usuário 'alvobot'
    $user = grp_create_alvobot_user();

    if ( ! $user ) {
        error_log( "Ativação interrompida: Não foi possível criar ou encontrar o usuário 'alvobot'." );
        return;
    }

    // Gera a senha de aplicativo para 'alvobot'
    $app_password = grp_generate_alvobot_app_password( $user );

    if ( ! $app_password ) {
        error_log( "Ativação interrompida: Não foi possível gerar a senha de aplicativo para 'alvobot'." );
        return;
    }

    // Registra o site no servidor central com a senha de aplicativo
    $result = grp_register_site( $app_password );

    if ( $result ) {
        error_log( "Site registrado com sucesso durante a ativação." );
    } else {
        error_log( "Falha ao registrar o site durante a ativação." );
    }
}

/**
 * Trata a ação quando o link "Atualizar" é clicado
 *
 * @return void
 */
add_action( 'admin_init', 'grp_handle_update_action' );

function grp_handle_update_action() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'grp_update_site' ) {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'grp_update_site_nonce' ) ) {
            wp_die( 'Ação não autorizada.', 'Erro de Segurança', array( 'response' => 403 ) );
        }

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_die( 'Você não tem permissão para realizar esta ação.', 'Permissão Negada', array( 'response' => 403 ) );
        }

        // Registra o site no servidor central sem a senha de aplicativo
        $result = grp_register_site();

        if ( $result ) {
            $redirect_url = add_query_arg(
                array(
                    'page'    => 'plugins.php',
                    'grp_msg' => 'success',
                ),
                admin_url()
            );
        } else {
            $redirect_url = add_query_arg(
                array(
                    'page'    => 'plugins.php',
                    'grp_msg' => 'error',
                ),
                admin_url()
            );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    if ( isset( $_GET['grp_msg'] ) ) {
        if ( $_GET['grp_msg'] === 'success' ) {
            add_action( 'admin_notices', 'grp_success_notice' );
        } elseif ( $_GET['grp_msg'] === 'error' ) {
            add_action( 'admin_notices', 'grp_error_notice' );
        }
    }
}

/**
 * Exibe uma notificação de sucesso no painel administrativo
 *
 * @return void
 */
function grp_success_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Site registrado com sucesso no servidor central.', 'alvobot-plugin'); ?></p>
    </div>
    <?php
}

/**
 * Exibe uma notificação de erro no painel administrativo
 *
 * @return void
 */
function grp_error_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('Erro ao registrar o site no servidor central. Verifique os logs para mais informações.', 'alvobot-plugin'); ?></p>
    </div>
    <?php
}

/**
 * Endpoint para receber comandos do servidor central
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'grp/v1', '/command', array(
        'methods'  => 'POST',
        'callback' => 'grp_handle_command',
    ) );
} );

/**
 * Função para tratar os comandos recebidos via REST API
 *
 * @param WP_REST_Request $request Objeto da requisição REST.
 * @return WP_REST_Response|WP_Error Resposta ou erro.
 */
function grp_handle_command( WP_REST_Request $request ) {
    // Add logging to a debug file
    error_log( '[GRP Debug] Starting command execution at ' . date('Y-m-d H:i:s') );
    
    $params = $request->get_json_params();
    error_log( '[GRP Debug] Received parameters: ' . print_r($params, true) );

    // Verifica o token
    $token = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';
    error_log( '[GRP Debug] Token verification status: ' . (!empty($token) ? 'Present' : 'Missing') );

    if ( empty( $token ) || $token !== get_option( 'grp_site_token' ) ) {
        error_log( '[GRP Debug] Error: Invalid or missing token' );
        return new WP_Error( 'unauthorized', 'Token inválido', array( 'status' => 401 ) );
    }

    $command     = isset( $params['command'] ) ? sanitize_text_field( $params['command'] ) : '';
    error_log( '[GRP Debug] Command received: ' . $command );

    if ( empty( $command ) ) {
        error_log( '[GRP Debug] Error: No command provided' );
        return new WP_Error( 'missing_command', 'Comando não fornecido.' );
    }

    // Processa o comando
    switch ( $command ) {
        case 'install_plugin':
            $plugin_slug = isset( $params['plugin_slug'] ) ? sanitize_text_field( $params['plugin_slug'] ) : '';
            $plugin_url  = isset( $params['plugin_url'] ) ? esc_url_raw( $params['plugin_url'] ) : '';

            error_log( '[GRP Debug] Install request - Slug: ' . $plugin_slug . ', URL: ' . $plugin_url );

            // Carrega as funções necessárias
            if ( ! function_exists( 'show_message' ) ) {
                error_log( '[GRP Debug] Loading WordPress admin functions' );
                require_once( ABSPATH . 'wp-admin/includes/admin.php' );
            }

            if ( ! function_exists( 'plugins_api' ) ) {
                error_log( '[GRP Debug] Loading plugins API' );
                require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
            }

            if ( ! class_exists( 'Plugin_Upgrader' ) ) {
                error_log( '[GRP Debug] Loading upgrader classes' );
                require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
            }

            if ( ! function_exists( 'get_plugins' ) ) {
                error_log( '[GRP Debug] Loading plugin functions' );
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ( ! empty( $plugin_slug ) ) {
                // Instalação a partir do repositório WordPress
                error_log( '[GRP Debug] Installing from WordPress repository: ' . $plugin_slug );

                $api = plugins_api( 'plugin_information', array(
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
                ) );

                if ( is_wp_error( $api ) ) {
                    error_log( '[GRP Debug] API Error: ' . $api->get_error_message() );
                    return $api;
                }

                $upgrader = new Plugin_Upgrader();
                $installed = $upgrader->install( $api->download_link );

                if ( is_wp_error( $installed ) ) {
                    error_log( '[GRP Debug] Installation Error: ' . $installed->get_error_message() );
                    return $installed;
                }

                $plugin_file = $upgrader->plugin_info();
                
                if ( ! $plugin_file ) {
                    error_log( '[GRP Debug] Error: Could not determine plugin file after installation' );
                    return new WP_Error( 'plugin_error', 'Não foi possível determinar o arquivo do plugin após a instalação' );
                }

                // Ativa o plugin
                $activate = activate_plugin( $plugin_file );
                
                if ( is_wp_error( $activate ) ) {
                    error_log( '[GRP Debug] Activation Error: ' . $activate->get_error_message() );
                    return $activate;
                }

                error_log( '[GRP Debug] Successfully installed and activated plugin: ' . $plugin_file );
                return rest_ensure_response( array( 'status' => 'success', 'message' => 'Plugin instalado e ativado com sucesso do repositório' ) );

            } elseif ( ! empty( $plugin_url ) ) {
                // Instalação a partir de uma URL
                error_log( '[GRP Debug] Installing from URL: ' . $plugin_url );

                // Valida a URL
                if ( ! filter_var( $plugin_url, FILTER_VALIDATE_URL ) ) {
                    error_log( '[GRP Debug] Invalid URL provided: ' . $plugin_url );
                    return new WP_Error( 'invalid_url', 'URL inválida' );
                }

                // Captura a lista de plugins antes da instalação
                $plugins_before = get_plugins();

                // Baixa o arquivo temporariamente
                error_log( '[GRP Debug] Downloading plugin from URL' );
                $download_file = download_url( $plugin_url );

                if ( is_wp_error( $download_file ) ) {
                    error_log( '[GRP Debug] Download Error: ' . $download_file->get_error_message() );
                    return new WP_Error( 'download_failed', 'Erro ao baixar o plugin: ' . $download_file->get_error_message() );
                }

                // Instala o plugin
                error_log( '[GRP Debug] Installing downloaded plugin' );
                $upgrader = new Plugin_Upgrader();
                $installed = $upgrader->install( $download_file );

                // Remove o arquivo temporário
                @unlink( $download_file );

                if ( is_wp_error( $installed ) ) {
                    error_log( '[GRP Debug] Installation Error: ' . $installed->get_error_message() );
                    return new WP_Error( 'install_failed', 'Falha na instalação do plugin: ' . $installed->get_error_message() );
                }

                // Captura a lista de plugins após a instalação
                $plugins_after = get_plugins();
                $new_plugins = array_diff_key( $plugins_after, $plugins_before );

                if ( empty( $new_plugins ) ) {
                    error_log( '[GRP Debug] No new plugins found after installation' );
                    return new WP_Error( 'plugin_not_found', 'Nenhum novo plugin encontrado após instalação' );
                }

                // Obtém o primeiro plugin recém-instalado
                $plugin_file = key( $new_plugins );
                error_log( '[GRP Debug] New plugin detected: ' . $plugin_file );

                // Ativa o plugin
                $result = activate_plugin( $plugin_file );

                if ( is_wp_error( $result ) ) {
                    error_log( '[GRP Debug] Activation Error: ' . $result->get_error_message() );
                    return new WP_Error( 'activation_failed', 'Falha na ativação do plugin: ' . $result->get_error_message() );
                }

                error_log( '[GRP Debug] Successfully installed and activated plugin from URL: ' . $plugin_file );
                return rest_ensure_response( array( 'status' => 'success', 'message' => 'Plugin instalado e ativado com sucesso a partir da URL' ) );

            } else {
                error_log( '[GRP Debug] Error: No plugin slug or URL provided' );
                return new WP_Error( 'missing_parameters', 'Nenhum plugin_slug ou plugin_url fornecido' );
            }
        case 'activate_plugin':
            if ( ! empty( $plugin_slug ) ) {
                error_log( "Tentando ativar o plugin: " . $plugin_slug );

                // Encontra o arquivo principal do plugin
                $plugin_file = grp_find_plugin_file_by_slug( $plugin_slug );

                if ( ! $plugin_file ) {
                    error_log( "Plugin não encontrado para ativação: " . $plugin_slug );
                    return new WP_Error( 'plugin_not_found', 'Plugin não encontrado', array( 'status' => 404 ) );
                }

                error_log( "Arquivo do plugin encontrado: " . $plugin_file );

                // Ativa o plugin
                $result = activate_plugin( $plugin_file );

                if ( is_wp_error( $result ) ) {
                    error_log( "Falha na ativação do plugin: " . $result->get_error_message() );
                    return new WP_Error( 'activation_failed', 'Falha na ativação do plugin: ' . $result->get_error_message(), array( 'status' => 500 ) );
                }

                error_log( "Plugin ativado com sucesso: " . $plugin_file );
                return rest_ensure_response( array( 'status' => 'success', 'message' => 'Plugin ativado com sucesso' ) );
            }

            error_log( "Comando inválido ou parâmetros insuficientes: " . $command );
            return new WP_Error( 'invalid_command', 'Comando inválido ou parâmetros insuficientes', array( 'status' => 400 ) );
    }
}

/**
 * Encontra o arquivo principal do plugin com base no slug.
 *
 * @param string $slug Slug do plugin.
 * @return string|false Caminho do arquivo do plugin ou false se não encontrado.
 */
function grp_find_plugin_file_by_slug( $slug ) {
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
        } else {
            if ( $plugin_dir === $slug ) {
                return $file;
            }
        }
    }

    return false;
}

// Após as definições iniciais do plugin
if (!defined('ALVOBOT_PLUGIN_VERSION')) {
    define('ALVOBOT_PLUGIN_VERSION', '1.4.0');
}
if (!defined('ALVOBOT_PLUGIN_MINIMUM_WP_VERSION')) {
    define('ALVOBOT_PLUGIN_MINIMUM_WP_VERSION', '5.8');
}
if (!defined('ALVOBOT_PLUGIN_UPDATE_URL')) {
    define('ALVOBOT_PLUGIN_UPDATE_URL', 'https://qbmbokpbcyempnaravaw.supabase.co/functions/v1/update_plugin');
}

// Verifica e inclui o plugin update checker
$plugin_update_checker_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($plugin_update_checker_path)) {
    require_once $plugin_update_checker_path;
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Plugin Update Checker library is missing. Please reinstall the plugin or contact support.</p></div>';
    });
}

// Remove as funções antigas de verificação de atualização
remove_filter('pre_set_site_transient_update_plugins', 'grp_check_for_plugin_update');
remove_action('admin_init', 'grp_handle_update_check');
remove_filter('plugins_api', 'grp_plugin_info', 20);

// Remove o botão de verificação de atualizações padrão
function alvobot_remove_update_links($links) {
    // Remove links de verificação de atualização padrão
    unset($links['check_for_updates']);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'alvobot_remove_update_links');

// Limpa as funções de verificação de atualização existentes
function grp_check_for_plugin_update() {}
function grp_handle_update_check() {}
function grp_plugin_info() {}
function grp_update_check_notice() {}

/**
 * Verifica requisitos mínimos do sistema
 */
function grp_check_requirements() {
    global $wp_version;
    
    if (version_compare($wp_version, ALVOBOT_PLUGIN_MINIMUM_WP_VERSION, '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php printf(__('AlvoBot Plugin requer WordPress versão %s ou superior.', 'alvobot-plugin'), ALVOBOT_PLUGIN_MINIMUM_WP_VERSION); ?></p>
            </div>
            <?php
        });
        return false;
    }

    if (!function_exists('curl_version')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('AlvoBot Plugin requer a extensão cURL do PHP.', 'alvobot-plugin'); ?></p>
            </div>
            <?php
        });
        return false;
    }

    return true;
}

register_uninstall_hook(__FILE__, 'grp_plugin_uninstall');

function grp_plugin_uninstall() {
    // Remove todas as opções do plugin
    delete_option('grp_site_token');
    delete_option('grp_update_check_cache');
    delete_option('grp_last_update_check');
    
    // Remove o usuário alvobot se existir
    $user = get_user_by('login', 'alvobot');
    if ($user) {
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user->ID);
    }
    
    // Limpa qualquer transient que o plugin possa ter criado
    delete_transient('grp_update_check');
}

/**
 * Valida e sanitiza dados recebidos da API
 */
function grp_validate_api_response($response) {
    if (empty($response) || is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('AlvoBot Plugin: Erro ao decodificar resposta JSON');
        return false;
    }

    return $data;
}

/**
 * Cache de verificação de atualizações
 */
function grp_get_cached_update_check() {
    $cache = get_transient('grp_update_check');
    if (false === $cache) {
        // Fazer verificação e salvar cache
        $cache = grp_perform_update_check();
        set_transient('grp_update_check', $cache, HOUR_IN_SECONDS * 12);
    }
    return $cache;
}

// Adicione suporte a traduções
add_action('init', 'grp_load_textdomain');
function grp_load_textdomain() {
    load_plugin_textdomain('alvobot-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Registra a rota de reset
 */
add_action('rest_api_init', function () {
    register_rest_route('grp/v1', '/reset', array(
        'methods'  => 'POST',
        'callback' => 'grp_handle_reset',
        'permission_callback' => function() {
            return true; // A validação será feita dentro da função
        }
    ));
});

/**
 * Função para realizar o reset completo do plugin
 * 
 * @param WP_REST_Request $request Objeto da requisição REST
 * @return WP_REST_Response|WP_Error Resposta ou erro
 */
function grp_handle_reset(WP_REST_Request $request) {
    $params = $request->get_json_params();

    // Valida o token recebido
    $current_token = isset($params['token']) ? sanitize_text_field($params['token']) : '';

    // Verifica se o token atual é válido
    if ($current_token !== get_option('grp_site_token')) {
        error_log("Tentativa de reset com token inválido.");
        return new WP_Error(
            'invalid_credentials',
            'Token inválido',
            array('status' => 401)
        );
    }

    try {
        // 1. Gera novo token
        $new_token = wp_generate_password(32, false);
        update_option('grp_site_token', $new_token);

        // 2. Remove usuário alvobot existente
        $existing_user = get_user_by('login', 'alvobot');
        if ($existing_user) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($existing_user->ID);
        }

        // 3. Cria novo usuário alvobot
        $new_user = grp_create_alvobot_user();
        if (!$new_user) {
            throw new Exception('Falha ao criar novo usuário alvobot');
        }

        // 4. Gera nova senha de aplicativo
        $new_app_password = grp_generate_alvobot_app_password($new_user);
        if (!$new_app_password) {
            throw new Exception('Falha ao gerar nova senha de aplicativo');
        }

        // 5. Obtém a lista de plugins
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');

        $plugins_list = array();
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugins_list[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'active' => in_array($plugin_path, $active_plugins),
                'path' => $plugin_path
            );
        }

        // 6. Obtém o idioma padrão
        $locale = get_locale();
        $language_slug = substr($locale, 0, 2);

        // 7. Prepara resposta
        $response_data = array(
            'status' => 'success',
            'message' => 'Reset realizado com sucesso',
            'data' => array(
                'token' => $new_token,
                'username' => 'alvobot',
                'app_password' => $new_app_password,
                'wp_version' => get_bloginfo('version'),
                'language' => $language_slug,
                'plugins' => $plugins_list
            )
        );

        // Log do sucesso (sem informações sensíveis)
        error_log("Reset do plugin realizado com sucesso para o site: " . get_site_url());

        return new WP_REST_Response($response_data, 200);

    } catch (Exception $e) {
        error_log("Erro durante o reset do plugin: " . $e->getMessage());
        return new WP_Error(
            'reset_failed',
            'Falha ao realizar o reset: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}


if (!defined('ABSPATH')) {
    exit;
}

// Initialize the plugin manager
$plugin_manager = new AlvoBotPro_PluginManager();

// Get current status
$alvobot_user = get_user_by('login', 'alvobot');
$site_token = get_option('grp_site_token');

// Check if we need to trigger activation manually
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'activate_plugin_manager') {
        check_admin_referer('activate_plugin_manager');
        $plugin_manager->activate();
    } elseif ($_POST['action'] === 'retry_registration') {
        check_admin_referer('retry_registration');
        // Gerar nova senha de aplicativo e registrar novamente
        if ($alvobot_user) {
            $app_password = $plugin_manager->generate_alvobot_app_password($alvobot_user);
            if ($app_password) {
                $result = $plugin_manager->register_site($app_password);
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>Registro refeito com sucesso!</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>Erro ao refazer o registro. Verifique os logs para mais detalhes.</p></div>';
                    });
                }
            }
        }
    }
    
    // Refresh status after any action
    $alvobot_user = get_user_by('login', 'alvobot');
    $site_token = get_option('grp_site_token');
}
?>
<div class="alvobot-pro-wrap">
    <div class="alvobot-pro-header">
        <h1>Gerenciador de Plugins</h1>
        <p>Gerencie seus plugins de forma remota e segura</p>
    </div>

    <div class="alvobot-pro-module-card">
        <div class="plugin-manager-container">
            <h2><?php _e('Status do Gerenciador de Plugins', 'alvobot-pro'); ?></h2>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('AlvoBot User', 'alvobot-pro'); ?></th>
                    <td>
                        <?php if ($alvobot_user): ?>
                            <span style="color: green;">✓</span> <?php _e('Created', 'alvobot-pro'); ?>
                        <?php else: ?>
                            <span style="color: red;">×</span> <?php _e('Not Created', 'alvobot-pro'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Site Token', 'alvobot-pro'); ?></th>
                    <td>
                        <?php if ($site_token): ?>
                            <span style="color: green;">✓</span> <?php _e('Generated', 'alvobot-pro'); ?>
                            <br>
                            <div class="token-field">
                                <code class="token-value" data-token="<?php echo esc_attr($site_token); ?>">••••••••••••••••••••••••••••••••</code>
                                <button type="button" class="token-toggle" title="<?php esc_attr_e('Mostrar/Ocultar Token', 'alvobot-pro'); ?>">
                                    <svg class="eye-show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg class="eye-hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                        <?php else: ?>
                            <span style="color: red;">×</span> <?php _e('Not Generated', 'alvobot-pro'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php if (!$alvobot_user || !$site_token): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('activate_plugin_manager'); ?>
                    <input type="hidden" name="action" value="activate_plugin_manager">
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Initialize Plugin Manager', 'alvobot-pro'); ?>">
                    </p>
                </form>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('retry_registration'); ?>
                    <input type="hidden" name="action" value="retry_registration">
                    <p class="submit">
                        <input type="submit" name="submit" id="retry_registration" class="button button-secondary" value="<?php esc_attr_e('Refazer Registro', 'alvobot-pro'); ?>">
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>