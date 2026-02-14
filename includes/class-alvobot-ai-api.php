<?php
/**
 * Classe para comunicacao com a Edge Function de AI do AlvoBot
 * Gerencia creditos, custos e geracoes AI para todos os modulos.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_AI_API {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_alvobot_ai_get_credits', array($this, 'ajax_get_credits'));
        add_action('wp_ajax_alvobot_ai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_alvobot_ai_apply_author', array($this, 'ajax_apply_author'));
    }

    /**
     * Chamada generica ao Edge Function ai_plugin
     */
    public function call($action, $params = array()) {
        $token = get_option('grp_site_token');
        if (empty($token)) {
            return new WP_Error('no_token', 'Plugin não conectado ao AlvoBot. Registre o site primeiro.');
        }

        $body = array(
            'site_url' => get_site_url(),
            'token'    => $token,
            'action'   => $action,
            'params'   => $params,
        );

        $response = wp_remote_post(ALVOBOT_AI_URL, array(
            'body'      => wp_json_encode($body),
            'headers'   => array('Content-Type' => 'application/json'),
            'timeout'   => 60,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            AlvoBotPro::debug_log('ai-api', 'Erro na chamada: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            AlvoBotPro::debug_log('ai-api', 'Resposta JSON invalida: ' . $body_raw);
            return new WP_Error('invalid_json', 'Resposta inválida do servidor.');
        }

        if ($status_code >= 400) {
            $error_msg = isset($data['error']) ? $data['error'] : 'Erro desconhecido';
            $error_code = isset($data['error_code']) ? $data['error_code'] : 'api_error';
            AlvoBotPro::debug_log('ai-api', "Erro {$status_code}: {$error_msg}");
            $wp_error = new WP_Error($error_code, $error_msg);
            if (isset($data['credits'])) {
                $wp_error->add_data(array('credits' => $data['credits']));
            }
            return $wp_error;
        }

        return $data;
    }

    /**
     * Busca creditos do workspace (cached em transient 5min)
     */
    public function get_credits($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient('alvobot_ai_credits');
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = $this->call('get_credits');

        if (is_wp_error($result)) {
            return array(
                'total_available'   => 0,
                'monthly_limit'     => 0,
                'monthly_used'      => 0,
                'monthly_remaining' => 0,
                'extra_available'   => 0,
                'has_active_plan'   => false,
                'error'             => $result->get_error_message(),
            );
        }

        $credits = isset($result['data']) ? $result['data'] : array();
        set_transient('alvobot_ai_credits', $credits, 5 * MINUTE_IN_SECONDS);
        return $credits;
    }

    /**
     * Busca custos das acoes AI (cached em transient 1h)
     */
    public function get_costs($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient('alvobot_ai_costs');
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = $this->call('get_costs');

        if (is_wp_error($result)) {
            // Fallback hardcoded
            return array('generate_author' => 3);
        }

        $costs = isset($result['data']) ? $result['data'] : array('generate_author' => 3);
        set_transient('alvobot_ai_costs', $costs, HOUR_IN_SECONDS);
        return $costs;
    }

    /**
     * Retorna custo de uma acao especifica
     */
    public function get_action_cost($action_name) {
        $costs = $this->get_costs();
        return isset($costs[$action_name]) ? intval($costs[$action_name]) : null;
    }

    /**
     * Renderiza badge de creditos (HTML reutilizavel)
     */
    public static function render_credit_badge() {
        $api = self::get_instance();
        $credits = $api->get_credits();
        $token = get_option('grp_site_token');

        if (empty($token)) {
            echo '<div class="alvobot-credits-badge alvobot-credits-disconnected">';
            echo '<i data-lucide="alert-triangle" class="alvobot-icon"></i> ';
            echo 'Site não conectado ao AlvoBot';
            echo '</div>';
            return;
        }

        $total = isset($credits['total_available']) ? intval($credits['total_available']) : 0;
        $limit = isset($credits['monthly_limit']) ? intval($credits['monthly_limit']) : 0;
        $has_plan = !empty($credits['has_active_plan']);
        $has_error = isset($credits['error']);

        if ($has_error) {
            $color_class = 'alvobot-credits-error';
        } elseif (!$has_plan) {
            $color_class = 'alvobot-credits-noplan';
        } elseif ($limit > 0 && $total <= ($limit * 0.1)) {
            $color_class = 'alvobot-credits-critical';
        } elseif ($limit > 0 && $total <= ($limit * 0.25)) {
            $color_class = 'alvobot-credits-low';
        } else {
            $color_class = 'alvobot-credits-ok';
        }

        echo '<div class="alvobot-credits-badge ' . esc_attr($color_class) . '" id="alvobot-credits-badge">';
        echo '<i data-lucide="ticket" class="alvobot-icon"></i> ';
        if ($has_error) {
            echo 'Erro ao carregar créditos';
        } elseif (!$has_plan) {
            echo 'Sem plano ativo';
        } else {
            echo '<strong>' . esc_html($total) . '</strong> créditos disponíveis';
        }
        echo ' <button type="button" class="alvobot-credits-refresh" title="Atualizar créditos">';
        echo '<i data-lucide="refresh-cw" class="alvobot-icon"></i>';
        echo '</button>';
        echo '</div>';
    }

    /**
     * AJAX: Busca creditos
     */
    public function ajax_get_credits() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }
        check_ajax_referer('alvobot_ai_nonce', 'nonce');

        $credits = $this->get_credits(true);
        wp_send_json_success($credits);
    }

    /**
     * AJAX: Gera conteudo AI (generico)
     */
    public function ajax_generate() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }
        check_ajax_referer('alvobot_ai_nonce', 'nonce');

        $generation_type = isset($_POST['generation_type']) ? sanitize_text_field($_POST['generation_type']) : '';
        $params_raw = isset($_POST['params']) ? $_POST['params'] : array();

        if (empty($generation_type)) {
            wp_send_json_error(array('message' => 'Tipo de geração não especificado'));
        }

        // Sanitize params
        $params = array();
        if (is_array($params_raw)) {
            foreach ($params_raw as $key => $value) {
                $params[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }

        $result = $this->call($generation_type, $params);

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array(
                'message'    => $result->get_error_message(),
                'error_code' => $result->get_error_code(),
                'credits'    => isset($error_data['credits']) ? $error_data['credits'] : null,
            ));
        }

        // Invalidate credits cache after generation
        delete_transient('alvobot_ai_credits');

        wp_send_json_success($result);
    }

    /**
     * AJAX: Aplica autor gerado ao WordPress
     */
    public function ajax_apply_author() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }
        check_ajax_referer('alvobot_ai_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $avatar_url = isset($_POST['avatar_url']) ? esc_url_raw($_POST['avatar_url']) : '';
        $lang_code = isset($_POST['lang_code']) ? sanitize_key($_POST['lang_code']) : '';

        if (empty($user_id) || !get_user_by('ID', $user_id)) {
            wp_send_json_error(array('message' => 'Usuário inválido'));
        }

        $updated = array();

        // Update display name
        if (!empty($display_name)) {
            $result = wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
            if (!is_wp_error($result)) {
                $updated[] = 'display_name';
            }
        }

        // Update description (per-language if lang_code provided)
        if (!empty($description)) {
            if (!empty($lang_code) && preg_match('/^[a-z]{2}$/', $lang_code)) {
                update_user_meta($user_id, 'ab_bio_' . $lang_code, $description);
                $updated[] = 'description_' . $lang_code;

                // Also update default WP description if this is the site's primary language
                $site_lang = substr(get_locale(), 0, 2);
                if ($lang_code === $site_lang) {
                    update_user_meta($user_id, 'description', $description);
                    $updated[] = 'description';
                }
            } else {
                update_user_meta($user_id, 'description', $description);
                $updated[] = 'description';
            }
        }

        // Download and set avatar
        if (!empty($avatar_url)) {
            $avatar_result = $this->download_and_set_avatar($user_id, $avatar_url);
            if (!is_wp_error($avatar_result)) {
                $updated[] = 'avatar';
            }
        }

        wp_send_json_success(array(
            'message' => 'Autor atualizado com sucesso',
            'updated' => $updated,
        ));
    }

    /**
     * Faz download de imagem de URL e salva como avatar do usuario
     */
    private function download_and_set_avatar($user_id, $image_url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url, 30);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = array(
            'name'     => 'alvobot-author-avatar-' . $user_id . '.jpg',
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload($file_array, 0, 'AlvoBot Author Avatar');

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $attachment_id;
        }

        update_user_meta($user_id, 'ab_custom_avatar_id', $attachment_id);
        return $attachment_id;
    }
}
