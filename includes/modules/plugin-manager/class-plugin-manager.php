<?php

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_PluginManager {
    private $namespace = 'alvobot-pro/v1';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        $this->init();
    }

    public function init() {
        // Generate site token if not exists
        if (!get_option('grp_site_token')) {
            $token = wp_generate_password(32, false);
            update_option('grp_site_token', $token);
            error_log('[Plugin Manager] Token de site gerado: ' . substr($token, 0, 8) . '...');
        } else {
            $existing_token = get_option('grp_site_token');
            error_log('[Plugin Manager] Token de site já existe: ' . substr($existing_token, 0, 8) . '...');
        }

        // Register REST API endpoints
        // add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function activate() {
        error_log('[Plugin Manager] Iniciando ativação do Plugin Manager');
        
        // Create alvobot user
        $user = $this->create_alvobot_user();
        
        if ($user && !is_wp_error($user)) {
            error_log('[Plugin Manager] Usuário alvobot criado com sucesso');
            // Generate app password
            $app_password = $this->generate_alvobot_app_password($user);
            
            if ($app_password) {
                error_log('[Plugin Manager] Senha de aplicativo gerada, iniciando registro no servidor');
                // Register site with the central server
                $result = $this->register_site($app_password);
                if ($result) {
                    error_log('[Plugin Manager] Registro no servidor concluído com sucesso');
                } else {
                    error_log('[Plugin Manager] ERRO: Falha no registro do servidor');
                }
            } else {
                error_log('[Plugin Manager] ERRO: Falha ao gerar senha de aplicativo');
            }
        } else {
            error_log('[Plugin Manager] ERRO: Falha ao criar usuário alvobot');
            if (is_wp_error($user)) {
                error_log('[Plugin Manager] Erro WP: ' . $user->get_error_message());
            }
        }
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get current status
        $alvobot_user = get_user_by('login', 'alvobot');
        $site_token = get_option('grp_site_token');

        // Check if we need to trigger activation manually
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'activate_plugin_manager') {
                check_admin_referer('activate_plugin_manager');
                $this->activate();
            } elseif ($_POST['action'] === 'retry_registration') {
                check_admin_referer('retry_registration');
                error_log('[Plugin Manager] Iniciando processo de refazer registro');
                
                // Gerar nova senha de aplicativo e registrar novamente
                if ($alvobot_user) {
                    error_log('[Plugin Manager] Usuário alvobot encontrado, gerando nova senha de aplicativo');
                    $app_password = $this->generate_alvobot_app_password($alvobot_user);
                    if ($app_password) {
                        error_log('[Plugin Manager] Nova senha de aplicativo gerada, registrando no servidor');
                        $result = $this->register_site($app_password);
                        if ($result) {
                            error_log('[Plugin Manager] Refazer registro: SUCESSO');
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success"><p>Registro refeito com sucesso!</p></div>';
                            });
                        } else {
                            error_log('[Plugin Manager] Refazer registro: ERRO no servidor');
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-error"><p>Erro ao refazer o registro. Verifique os logs para mais detalhes.</p></div>';
                            });
                        }
                    } else {
                        error_log('[Plugin Manager] Refazer registro: ERRO ao gerar senha de aplicativo');
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error"><p>Erro ao gerar nova senha de aplicativo.</p></div>';
                        });
                    }
                } else {
                    error_log('[Plugin Manager] Refazer registro: ERRO - usuário alvobot não encontrado');
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>Usuário alvobot não encontrado. Execute a inicialização primeiro.</p></div>';
                    });
                }
            }
            
            // Refresh status after any action
            $alvobot_user = get_user_by('login', 'alvobot');
            $site_token = get_option('grp_site_token');
        }

        // Include the settings page template
        include_once plugin_dir_path(__FILE__) . 'templates/plugin-manager-settings.php';
    }

    private function create_alvobot_user() {
        $username = 'alvobot';
        $email = 'alvobot@alvobot.com.br';

        // Check if user exists
        $existing_user = get_user_by('login', $username);
        
        if ($existing_user) {
            // Check if admin
            if (!in_array('administrator', $existing_user->roles)) {
                $existing_user->set_role('administrator');
            }
            return $existing_user;
        }

        // Create new user
        $userdata = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'role' => 'administrator'
        );

        $user_id = wp_insert_user($userdata);
        return $user_id ? get_user_by('id', $user_id) : false;
    }

    public function generate_alvobot_app_password($user) {
        if (!$user) {
            error_log('Plugin Manager: Usuário não encontrado para gerar senha de aplicativo.');
            return false;
        }

        if (!class_exists('WP_Application_Passwords')) {
            require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
        }

        // Remove existing passwords
        WP_Application_Passwords::delete_all_application_passwords($user->ID);

        // Create new password
        $app_pass = WP_Application_Passwords::create_new_application_password(
            $user->ID,
            array(
                'name' => 'AlvoBot Plugin Manager',
                'app_id' => 'alvobot-plugin-manager',
            )
        );

        if (is_wp_error($app_pass)) {
            error_log('Plugin Manager: Erro ao gerar senha de aplicativo: ' . $app_pass->get_error_message());
            return false;
        }

        if (!isset($app_pass[0])) {
            error_log('Plugin Manager: Senha de aplicativo não foi retornada corretamente.');
            return false;
        }

        error_log('Plugin Manager: Senha de aplicativo gerada com sucesso');
        return $app_pass; // Return the plain text password
    }

    public function register_site($app_password = null) {
        error_log('[Plugin Manager] Iniciando registro do site no servidor central');
        
        if (!defined('GRP_SERVER_URL')) {
            error_log('[Plugin Manager] ERRO: GRP_SERVER_URL não está definida');
            return false;
        }
        
        error_log('[Plugin Manager] GRP_SERVER_URL definida: ' . GRP_SERVER_URL);
        
        // Carrega as funções necessárias do WordPress
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        $site_token = get_option('grp_site_token');
        $data = array(
            'action' => 'register_site',
            'site_url' => get_site_url(),
            'token' => $site_token,
            'wp_version' => get_bloginfo('version'),
            'plugins' => array_keys($plugins),
        );
        
        error_log('[Plugin Manager] Token do site: ' . ($site_token ? substr($site_token, 0, 8) . '...' : 'VAZIO'));

        if ($app_password) {
            // Pegar apenas a string da senha (primeiro elemento do array)
            if (is_array($app_password) && !empty($app_password[0])) {
                $data['app_password'] = $app_password[0];
            } else {
                $data['app_password'] = $app_password;
            }
            error_log('[Plugin Manager] Senha de aplicativo incluída nos dados');
        }

        error_log('[Plugin Manager] Dados a serem enviados: ' . print_r($data, true));
        error_log('[Plugin Manager] URL do servidor: ' . GRP_SERVER_URL);

        $args = array(
            'body' => wp_json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30, // Aumentado para 30 segundos
            'sslverify' => true,
        );

        error_log('[Plugin Manager] Enviando requisição POST para o servidor');
        $response = wp_remote_post(GRP_SERVER_URL, $args);

        if (is_wp_error($response)) {
            error_log('[Plugin Manager] ERRO ao registrar site: ' . $response->get_error_message());
            error_log('[Plugin Manager] Código do erro: ' . $response->get_error_code());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        error_log('[Plugin Manager] Resposta do servidor:');
        error_log('[Plugin Manager] - Status: ' . $status_code);
        error_log('[Plugin Manager] - Headers: ' . print_r($headers, true));
        error_log('[Plugin Manager] - Body: ' . $body);

        if ($status_code < 200 || $status_code >= 300) {
            error_log('[Plugin Manager] ERRO: Resposta inválida do servidor (Status ' . $status_code . ')');
            return false;
        }

        try {
            $json_response = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[Plugin Manager] ERRO: Resposta não é um JSON válido: ' . json_last_error_msg());
                return false;
            }
            error_log('[Plugin Manager] Resposta decodificada: ' . print_r($json_response, true));
        } catch (Exception $e) {
            error_log('[Plugin Manager] ERRO ao decodificar resposta: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function register_rest_routes() {
        // Endpoint para comandos de plugins
        register_rest_route($this->namespace, '/plugins/commands', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_command'),
            'permission_callback' => array($this, 'verify_token'),
        ));
    }

    public function verify_token($request) {
        $header_token = $request->get_header('token');
        $params = $request->get_json_params();
        $body_token = isset($params['token']) ? sanitize_text_field($params['token']) : '';
        
        error_log('[GRP Debug] Token verification - Header Token: ' . ($header_token ? 'Present' : 'Missing'));
        error_log('[GRP Debug] Token verification - Body Token: ' . ($body_token ? 'Present' : 'Missing'));
        error_log('[GRP Debug] Token verification - Stored Token: ' . (get_option('grp_site_token') ? 'Present' : 'Missing'));
        
        // Aceita o token tanto no header quanto no body
        $token = !empty($header_token) ? $header_token : $body_token;
        
        if (empty($token) || $token !== get_option('grp_site_token')) {
            error_log('[GRP Debug] Token verification failed - Token: ' . $token);
            error_log('[GRP Debug] Token verification failed - Stored Token: ' . get_option('grp_site_token'));
            return new WP_Error('unauthorized', 'Token inválido', array('status' => 401));
        }
        return true;
    }

    public function handle_command($request) {
        error_log('[GRP Debug] Starting command execution at ' . date('Y-m-d H:i:s'));

        $params = $request->get_json_params();
        error_log('[GRP Debug] Received parameters: ' . print_r($params, true));

        $command = isset($params['command']) ? sanitize_text_field($params['command']) : '';
        error_log('[GRP Debug] Command received: ' . $command);

        if (empty($command)) {
            error_log('[GRP Debug] Error: No command provided');
            return new WP_Error('missing_command', 'Comando não fornecido.');
        }

        // Processa o comando
        switch ($command) {
            case 'install_plugin':
                $plugin_slug = isset($params['plugin_slug']) ? sanitize_text_field($params['plugin_slug']) : '';
                $plugin_url = isset($params['plugin_url']) ? esc_url_raw($params['plugin_url']) : '';

                error_log('[GRP Debug] Install request - Slug: ' . $plugin_slug . ', URL: ' . $plugin_url);

                // Carrega as funções necessárias
                if (!function_exists('show_message')) {
                    error_log('[GRP Debug] Loading WordPress admin functions');
                    require_once(ABSPATH . 'wp-admin/includes/admin.php');
                }

                if (!function_exists('plugins_api')) {
                    error_log('[GRP Debug] Loading plugins API');
                    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
                }

                if (!class_exists('Plugin_Upgrader')) {
                    error_log('[GRP Debug] Loading upgrader classes');
                    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
                }

                if (!function_exists('get_plugins')) {
                    error_log('[GRP Debug] Loading plugin functions');
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                if (!empty($plugin_slug)) {
                    // Instalação a partir do repositório WordPress
                    error_log('[GRP Debug] Installing from WordPress repository: ' . $plugin_slug);

                    $api = plugins_api('plugin_information', array(
                        'slug' => $plugin_slug,
                        'fields' => array(
                            'short_description' => false,
                            'sections' => false,
                            'requires' => false,
                            'rating' => false,
                            'ratings' => false,
                            'downloaded' => false,
                            'last_updated' => false,
                            'added' => false,
                            'tags' => false,
                            'compatibility' => false,
                            'homepage' => false,
                            'donate_link' => false,
                        ),
                    ));

                    if (is_wp_error($api)) {
                        error_log('[GRP Debug] API Error: ' . $api->get_error_message());
                        return $api;
                    }

                    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
                    $installed = $upgrader->install($api->download_link);

                    if (is_wp_error($installed)) {
                        error_log('[GRP Debug] Installation Error: ' . $installed->get_error_message());
                        return $installed;
                    }

                    $plugin_file = $upgrader->plugin_info();

                    if (!$plugin_file) {
                        error_log('[GRP Debug] Error: Could not determine plugin file after installation');
                        return new WP_Error('plugin_error', 'Não foi possível determinar o arquivo do plugin após a instalação');
                    }

                    // Ativa o plugin
                    $activate = activate_plugin($plugin_file);

                    if (is_wp_error($activate)) {
                        error_log('[GRP Debug] Activation Error: ' . $activate->get_error_message());
                        return $activate;
                    }

                    error_log('[GRP Debug] Plugin installed and activated successfully: ' . $plugin_file);
                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => 'Plugin instalado e ativado com sucesso',
                        'plugin_file' => $plugin_file
                    ));
                } elseif (!empty($plugin_url)) {
                    // Instalação a partir de uma URL
                    error_log('[GRP Debug] Installing from URL: ' . $plugin_url);

                    // Valida a URL
                    if (!filter_var($plugin_url, FILTER_VALIDATE_URL)) {
                        error_log('[GRP Debug] Invalid URL provided: ' . $plugin_url);
                        return new WP_Error('invalid_url', 'URL inválida');
                    }

                    // Captura a lista de plugins antes da instalação
                    $plugins_before = get_plugins();

                    // Baixa o arquivo temporariamente
                    error_log('[GRP Debug] Downloading plugin from URL');
                    $download_file = download_url($plugin_url);

                    if (is_wp_error($download_file)) {
                        error_log('[GRP Debug] Download Error: ' . $download_file->get_error_message());
                        return new WP_Error('download_failed', 'Erro ao baixar o plugin: ' . $download_file->get_error_message());
                    }

                    // Instala o plugin
                    error_log('[GRP Debug] Installing downloaded plugin');
                    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
                    $installed = $upgrader->install($download_file);

                    // Remove o arquivo temporário
                    @unlink($download_file);

                    if (is_wp_error($installed)) {
                        error_log('[GRP Debug] Installation Error: ' . $installed->get_error_message());
                        return new WP_Error('install_failed', 'Falha na instalação do plugin: ' . $installed->get_error_message());
                    }

                    // Captura a lista de plugins após a instalação
                    $plugins_after = get_plugins();
                    $new_plugins = array_diff_key($plugins_after, $plugins_before);

                    if (empty($new_plugins)) {
                        error_log('[GRP Debug] No new plugins found after installation');
                        return new WP_Error('plugin_not_found', 'Nenhum novo plugin encontrado após instalação');
                    }

                    // Obtém o primeiro plugin recém-instalado
                    $plugin_file = key($new_plugins);
                    error_log('[GRP Debug] New plugin detected: ' . $plugin_file);

                    // Ativa o plugin
                    $result = activate_plugin($plugin_file);

                    if (is_wp_error($result)) {
                        error_log('[GRP Debug] Activation Error: ' . $result->get_error_message());
                        return new WP_Error('activation_failed', 'Falha na ativação do plugin: ' . $result->get_error_message());
                    }

                    error_log('[GRP Debug] Successfully installed and activated plugin from URL: ' . $plugin_file);
                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => 'Plugin instalado e ativado com sucesso a partir da URL',
                        'plugin_file' => $plugin_file
                    ));
                }

                return new WP_Error('missing_source', 'Nenhuma fonte de plugin fornecida (slug ou URL).');

            case 'activate_plugin':
                if (isset($params['plugin'])) {
                    $result = activate_plugin($params['plugin']);
                    return new WP_REST_Response(array(
                        'success' => !is_wp_error($result),
                        'message' => is_wp_error($result) ? $result->get_error_message() : 'Plugin activated'
                    ));
                }
                break;

            case 'deactivate_plugin':
                if (isset($params['plugin'])) {
                    deactivate_plugins($params['plugin']);
                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => 'Plugin deactivated'
                    ));
                }
                break;

            case 'delete_plugin':
                if (!function_exists('delete_plugins')) {
                    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                }

                $plugin_file = isset($params['plugin']) ? $params['plugin'] : '';
                if (empty($plugin_file)) {
                    return new WP_Error('missing_plugin', 'Plugin não especificado');
                }

                // Desativa o plugin antes de deletar
                deactivate_plugins($plugin_file);

                // Deleta o plugin
                $deleted = delete_plugins(array($plugin_file));

                if (is_wp_error($deleted)) {
                    return $deleted;
                }

                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Plugin deletado com sucesso'
                ));

            case 'get_plugins':
                if (!function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $plugins = get_plugins();
                $active_plugins = get_option('active_plugins', array());

                $formatted_plugins = array();
                foreach ($plugins as $plugin_file => $plugin_data) {
                    $formatted_plugins[] = array(
                        'file' => $plugin_file,
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'description' => $plugin_data['Description'],
                        'author' => $plugin_data['Author'],
                        'active' => in_array($plugin_file, $active_plugins)
                    );
                }

                return new WP_REST_Response(array(
                    'success' => true,
                    'plugins' => $formatted_plugins
                ));

            case 'reset':
                return $this->handle_reset($request);

            default:
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Unknown command'
                ), 400);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid parameters'
        ), 400);
    }

    /**
     * Executa o reset completo do plugin
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    private function handle_reset($request) {
        try {
            // Guarda o token atual antes de deletar
            $current_token = get_option('grp_site_token');
            
            // Remove todas as opções do plugin
            delete_option('grp_site_token');
            delete_option('grp_site_code');
            delete_option('grp_registered');
            delete_option('grp_app_password');

            // Remove o usuário alvobot
            $user = get_user_by('login', 'alvobot');
            if ($user) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($user->ID);
            }

            // Cria um novo usuário alvobot
            $new_user = $this->create_alvobot_user();
            if (!$new_user) {
                throw new Exception('Falha ao criar usuário alvobot');
            }

            // Gera uma nova senha de app
            $app_password_data = $this->generate_alvobot_app_password($new_user);
            if (!$app_password_data) {
                throw new Exception('Falha ao gerar senha de aplicativo');
            }

            // Gera e salva um novo token
            $new_token = wp_generate_password(32, false);
            update_option('grp_site_token', $new_token);

            // Registra o site com a nova senha de app
            $this->register_site($app_password_data[0]);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Plugin resetado com sucesso',
                'new_token' => $new_token,
                'app_password' => $app_password_data[0],
                'user' => array(
                    'id' => $new_user->ID,
                    'login' => $new_user->user_login,
                    'email' => $new_user->user_email
                )
            ));
        } catch (Exception $e) {
            error_log('[GRP Debug] Reset error: ' . $e->getMessage());
            return new WP_Error(
                'reset_failed',
                'Erro ao resetar o plugin: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    private function find_plugin_file_by_slug($slug) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ($all_plugins as $file => $plugin_data) {
            // Extrai o diretório do plugin
            $plugin_dir = dirname($file);

            if ($plugin_dir === '.') {
                // Plugin está na raiz da pasta de plugins
                if ($slug === basename($file, '.php')) {
                    return $file;
                }
            } else {
                if ($plugin_dir === $slug) {
                    return $file;
                }
            }
        }

        return false;
    }
}
