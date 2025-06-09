<?php
/**
 * Classe do módulo Temporary Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_TemporaryLogin extends AlvoBotPro_Module_Base {
    private $namespace = 'alvobot-pro/v1';

    public function __construct() {
        parent::__construct();
    }

    protected function define_module_properties() {
        $this->module_id = 'temporary-login';
        $this->module_name = 'Login Temporário';
        $this->module_description = 'Crie links de login temporário para acesso seguro ao painel administrativo.';
        $this->module_icon = '';
    }

    protected function init() {
        // Carrega os arquivos necessários
        require_once plugin_dir_path(__FILE__) . 'includes/class-options.php';
        
        // Garante que as opções padrão existam
        $settings = $this->get_settings();
        if (empty($settings)) {
            $this->save_settings($this->get_default_options());
        }

        // Registra os endpoints da API REST
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Hook para processar login temporário
        add_action('init', array($this, 'handle_temporary_login'));
        
        // Hook para limpar usuários expirados
        add_action('wp_loaded', array($this, 'cleanup_expired_users'));
    }

    private function get_default_options() {
        return array(
            'expiration_days' => 15,
            'auto_delete_expired' => true,
            'temp_user_expiration' => 0 // Timestamp de expiração
        );
    }

    protected function render_settings_sections($settings) {
        ?>
        <div class="alvobot-form-section">
            <h3 class="alvobot-section-title"><?php _e('Status dos Logins Temporários', 'alvobot-pro'); ?></h3>
            <div id="temporary-login-status">
                <div class="alvobot-loading">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Carregando status...', 'alvobot-pro'); ?>
                </div>
            </div>
        </div>

        <div class="alvobot-form-section">
            <h3 class="alvobot-section-title"><?php _e('Criar Novo Login Temporário', 'alvobot-pro'); ?></h3>
            <p class="alvobot-description">
                <?php _e('Gere um link de acesso temporário para permitir que outras pessoas acessem o painel administrativo por tempo limitado.', 'alvobot-pro'); ?>
            </p>
            
            <div class="alvobot-temp-login-creator">
                <div class="alvobot-temp-login-action">
                    <button type="button" id="create-temp-login" class="alvobot-btn alvobot-btn-primary alvobot-btn-lg">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Criar Login Temporário', 'alvobot-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        </div>
        <?php
    }
    
    public function handle_temporary_login() {
        if (!isset($_GET['temp-login-token']) || !isset($_GET['tl-site'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['temp-login-token']);
        $site_token = sanitize_text_field($_GET['tl-site']);
        
        // Verifica se o site token é válido
        $stored_token = get_option('grp_site_token');
        if ($site_token !== $stored_token) {
            wp_die(__('Token do site inválido.', 'alvobot-pro'));
        }
        
        // Verifica se o token de login é válido
        if (!get_transient('alvobot_temp_login_token_' . $token)) {
            wp_die(__('Token de login inválido ou expirado.', 'alvobot-pro'));
        }
        
        // Verifica se o login temporário está ativo
        if (!$this->is_temp_login_active()) {
            wp_die(__('Este link de login expirou.', 'alvobot-pro'));
        }
        
        // Obtém o usuário temporário
        $user = get_user_by('login', 'alvobot_temp');
        if (!$user) {
            wp_die(__('Usuário temporário não encontrado.', 'alvobot-pro'));
        }
        
        // Remove o token (uso único)
        delete_transient('alvobot_temp_login_token_' . $token);
        
        // Faz login do usuário
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        // Redireciona para o admin
        wp_redirect(admin_url());
        exit;
    }
    
    public function cleanup_expired_users() {
        $settings = $this->get_settings();
        if (!empty($settings['auto_delete_expired'])) {
            $this->check_and_cleanup_temp_user();
        }
    }

    /**
     * Verifica se o usuário temporário expirou e o remove se necessário
     */
    private function check_and_cleanup_temp_user() {
        $settings = $this->get_settings();
        $expiration = isset($settings['temp_user_expiration']) ? $settings['temp_user_expiration'] : 0;
        
        if ($expiration > 0 && time() > $expiration) {
            $this->remove_temp_user();
        }
    }

    /**
     * Cria ou obtém o usuário temporário fixo
     */
    private function get_or_create_temp_user() {
        $username = 'alvobot_temp';
        $user = get_user_by('login', $username);
        
        if (!$user) {
            // Cria o usuário temporário
            $user_id = wp_create_user(
                $username,
                wp_generate_password(20, true, true),
                'temp@alvobot.local'
            );
            
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            
            // Define como administrador
            $user = new WP_User($user_id);
            $user->set_role('administrator');
            
            // Marca como usuário temporário
            update_user_meta($user_id, 'alvobot_temp_user', true);
            update_user_meta($user_id, 'alvobot_created_by', get_current_user_id());
            
            return $user_id;
        }
        
        return $user->ID;
    }

    /**
     * Remove o usuário temporário e transfere conteúdo para admin
     */
    private function remove_temp_user() {
        $username = 'alvobot_temp';
        $user = get_user_by('login', $username);
        
        if ($user) {
            // Transfere todos os posts para o admin
            $admin_id = get_current_user_id() ?: 1;
            
            $posts = get_posts(array(
                'author' => $user->ID,
                'post_type' => 'any',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            
            foreach ($posts as $post) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_author' => $admin_id
                ));
            }
            
            // Remove o usuário
            wp_delete_user($user->ID, $admin_id);
            
            // Limpa a expiração das configurações
            $settings = $this->get_settings();
            $settings['temp_user_expiration'] = 0;
            $this->save_settings($settings);
        }
    }

    /**
     * Verifica se existe um login temporário ativo
     */
    private function is_temp_login_active() {
        $settings = $this->get_settings();
        $expiration = isset($settings['temp_user_expiration']) ? $settings['temp_user_expiration'] : 0;
        
        if ($expiration == 0) {
            return false;
        }
        
        // Verifica se não expirou
        if (time() > $expiration) {
            return false;
        }
        
        // Verifica se o usuário ainda existe
        $user = get_user_by('login', 'alvobot_temp');
        return $user !== false;
    }

    /**
     * Gera o link de login para o usuário temporário
     */
    private function generate_temp_login_url() {
        $token = wp_generate_password(32, false);
        $site_token = get_option('grp_site_token');
        
        // Salva o token temporariamente (30 minutos)
        set_transient('alvobot_temp_login_token_' . $token, true, 30 * MINUTE_IN_SECONDS);
        
        return add_query_arg(array(
            'temp-login-token' => $token,
            'tl-site' => $site_token
        ), home_url());
    }

    public function register_rest_routes() {
        // Endpoint interno para status
        register_rest_route($this->namespace, '/temporary-login/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        // Endpoint interno para criar usuário temporário
        register_rest_route($this->namespace, '/temporary-login/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_temporary_user'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        // Endpoint interno para revogar usuários
        register_rest_route($this->namespace, '/temporary-login/revoke', array(
            'methods' => 'POST',
            'callback' => array($this, 'revoke_temporary_users'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        // Endpoint principal para API externa (usando token de autenticação)
        register_rest_route($this->namespace, '/temporary-login/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_generate_login_link'),
            'permission_callback' => array($this, 'verify_api_token'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Token de autenticação do site'
                ),
                'expiration_hours' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 24,
                    'description' => 'Horas até expiração (máximo 168 = 7 dias)'
                ),
                'description' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Descrição do acesso temporário'
                )
            )
        ));
    }

    public function verify_api_token($request) {
        $json = $request->get_json_params();
        $token = isset($json['token']) ? $json['token'] : null;
        
        if (!$token) {
            return new WP_Error(
                'missing_token',
                'Token não fornecido',
                array('status' => 401)
            );
        }
        
        // Verifica se o token é válido consultando a opção do WordPress
        $stored_token = get_option('grp_site_token');
        
        if (!$stored_token || $token !== $stored_token) {
            return new WP_Error(
                'invalid_token',
                'Token inválido',
                array('status' => 401)
            );
        }

        return true;
    }

    public function get_status($request) {
        if ($this->is_temp_login_active()) {
            $settings = $this->get_settings();
            $expiration = $settings['temp_user_expiration'];
            
            $data = array(
                'status' => 'active',
                'login_url' => $this->generate_temp_login_url(),
                'expiration_human' => human_time_diff(time(), $expiration) . ' remaining',
                'expires_at' => date('Y-m-d H:i:s', $expiration),
                'username' => 'alvobot_temp'
            );
        } else {
            $data = array(
                'status' => 'inactive'
            );
        }

        return new WP_REST_Response($data, 200);
    }


    public function api_generate_login_link($request) {
        $params = $request->get_json_params();
        
        $expiration_hours = isset($params['expiration_hours']) ? absint($params['expiration_hours']) : 24;
        $description = isset($params['description']) ? sanitize_text_field($params['description']) : '';
        
        // Força 15 dias (360 horas) como padrão
        $expiration_hours = 360; // 15 dias fixo
        
        // Cria ou obtém o usuário temporário
        $user_id = $this->get_or_create_temp_user();
        
        if (is_wp_error($user_id)) {
            return new WP_Error(
                'user_creation_failed',
                $user_id->get_error_message(),
                array('status' => 500)
            );
        }
        
        // Define a expiração nas configurações
        $expiration_timestamp = time() + ($expiration_hours * HOUR_IN_SECONDS);
        $settings = $this->get_settings();
        $settings['temp_user_expiration'] = $expiration_timestamp;
        $this->save_settings($settings);
        
        // Gera o link de login
        $login_url = $this->generate_temp_login_url();
        
        $response = array(
            'login_url' => $login_url,
            'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
            'expires_in_hours' => $expiration_hours,
            'description' => $description,
            'username' => 'alvobot_temp',
            'message' => __('Link de login temporário gerado com sucesso.', 'alvobot-pro')
        );
        
        return new WP_REST_Response($response, 200);
    }

    public function create_temporary_user($request) {
        $settings = $this->get_settings();
        $expiration_days = isset($settings['expiration_days']) ? absint($settings['expiration_days']) : 15;
        
        // Cria ou obtém o usuário temporário
        $user_ID = $this->get_or_create_temp_user();
        if (is_wp_error($user_ID)) {
            return new WP_Error(
                'user_creation_failed',
                $user_ID->get_error_message(),
                array('status' => 500)
            );
        }

        // Define a expiração (15 dias padrão)
        $expiration_timestamp = time() + ($expiration_days * DAY_IN_SECONDS);
        $settings['temp_user_expiration'] = $expiration_timestamp;
        $this->save_settings($settings);
        
        // Gera o link de login
        $login_url = $this->generate_temp_login_url();

        $response = array(
            'login_url' => $login_url,
            'expires_at' => date('Y-m-d H:i:s', $expiration_timestamp),
            'username' => 'alvobot_temp',
            'message' => __('Usuário temporário criado com sucesso', 'alvobot-pro')
        );

        return new WP_REST_Response($response, 200);
    }

    public function revoke_temporary_users() {
        // Remove o usuário temporário
        $this->remove_temp_user();

        $response = array(
            'message' => __('Acesso temporário revogado', 'alvobot-pro')
        );

        return new WP_REST_Response($response, 200);
    }

    public function extend_temporary_user($request) {
        if (!$this->is_temp_login_active()) {
            return new WP_Error(
                'no_active_login',
                __('Nenhum login temporário ativo encontrado', 'alvobot-pro'),
                array('status' => 404)
            );
        }

        // Estende por mais 15 dias
        $settings = $this->get_settings();
        $current_expiration = $settings['temp_user_expiration'];
        $new_expiration = $current_expiration + (15 * DAY_IN_SECONDS);
        
        $settings['temp_user_expiration'] = $new_expiration;
        $this->save_settings($settings);

        $response = array(
            'message' => __('Acesso estendido com sucesso', 'alvobot-pro'),
            'new_expiration' => date('Y-m-d H:i:s', $new_expiration),
            'expires_in' => human_time_diff(time(), $new_expiration)
        );

        return new WP_REST_Response($response, 200);
    }

    protected function sanitize_settings($settings) {
        $sanitized = array();
        
        $sanitized['expiration_days'] = isset($settings['expiration_days']) ? 
            max(1, min(30, absint($settings['expiration_days']))) : 15;
            
        $sanitized['auto_delete_expired'] = true; // Sempre ativo
        
        $sanitized['temp_user_expiration'] = isset($settings['temp_user_expiration']) ? 
            absint($settings['temp_user_expiration']) : 0;
            
        return $sanitized;
    }

    protected function render_additional_content($settings) {
        // Inclui o conteúdo adicional do template
        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }
}