<?php

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;
    private $github_repo = 'alvobot/alvobot-plugin-manager';
    private $github_api = 'https://api.github.com/repos/';

    public function __construct($file) {
        $this->file = $file;
        $this->basename = plugin_basename($file);
        
        add_action('admin_init', array($this, 'init'));
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        add_filter('plugin_action_links_' . $this->basename, array($this, 'add_action_links'));
        add_action('wp_ajax_alvobotpro_manual_check_update', array($this, 'handle_manual_check'));
        add_action('admin_footer', array($this, 'add_manual_check_script'));
    }

    public function init() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->active = is_plugin_active($this->basename);
    }

    public function add_action_links($links) {
        $check_link = sprintf(
            '<a href="#" class="alvobotpro-check-update" data-nonce="%s">%s</a>',
            wp_create_nonce('alvobotpro_check_update'),
            __('Verificar Atualizações', 'alvobot-pro')
        );
        array_unshift($links, $check_link);
        return $links;
    }

    public function handle_manual_check() {
        check_ajax_referer('alvobotpro_check_update');
        
        if (!current_user_can('update_plugins')) {
            wp_send_json_error('Permissão negada');
        }

        delete_site_transient('update_plugins');
        wp_clean_plugins_cache(true);
        wp_update_plugins();

        wp_send_json_success([
            'message' => __('Verificação de atualizações concluída!', 'alvobot-pro')
        ]);
    }

    public function add_manual_check_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.alvobotpro-check-update').on('click', function(e) {
                e.preventDefault();
                const button = $(this);
                const originalText = button.text();
                
                button.text('<?php echo esc_js(__('Verificando...', 'alvobot-pro')); ?>').addClass('disabled');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alvobotpro_manual_check_update',
                        _ajax_nonce: button.data('nonce')
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php echo esc_js(__('Erro ao verificar atualizações.', 'alvobot-pro')); ?>');
                            button.text(originalText).removeClass('disabled');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Erro ao verificar atualizações.', 'alvobot-pro')); ?>');
                        button.text(originalText).removeClass('disabled');
                    }
                });
            });
        });
        </script>
        <?php
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $request_uri = $this->github_api . $this->github_repo . '/releases/latest';
            $response = wp_remote_get($request_uri, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version')
                )
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return false;
            }

            $response = json_decode(wp_remote_retrieve_body($response));
            if (empty($response)) {
                return false;
            }

            $this->github_response = $response;
        }
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_repository_info();
        if (is_null($this->github_response)) {
            return $transient;
        }

        $latest_version = ltrim($this->github_response->tag_name, 'v');
        $current_version = ALVOBOT_PRO_VERSION;
        
        // Debug
        AlvoBotPro::debug_log('core', "Current version: " . $current_version);
        AlvoBotPro::debug_log('core', "Latest version: " . $latest_version);
        
        $doUpdate = version_compare($latest_version, $current_version, 'gt');
        
        if ($doUpdate) {
            $package = $this->github_response->zipball_url;

            $plugin = array(
                'slug' => dirname($this->basename),
                'plugin' => $this->basename,
                'new_version' => $latest_version,
                'url' => 'https://github.com/' . $this->github_repo,
                'package' => $package,
                'icons' => array(),
                'tested' => '6.4',
                'requires_php' => '7.4'
            );

            $transient->response[$this->basename] = (object) $plugin;
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->basename)) {
            return $result;
        }

        $this->get_repository_info();
        if (is_null($this->github_response)) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->file);

        $plugin_info = array(
            'name' => $plugin_data['Name'],
            'slug' => dirname($this->basename),
            'version' => ltrim($this->github_response->tag_name, 'v'),
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'last_updated' => $this->github_response->published_at,
            'homepage' => $plugin_data['PluginURI'],
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => nl2br($this->github_response->body)
            ),
            'download_link' => $this->github_response->zipball_url,
            'requires' => '5.8',
            'tested' => '6.4',
            'requires_php' => '7.4'
        );

        return (object) $plugin_info;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Verifica se é nosso plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $result;
        }

        // Obtém o nome correto da pasta do plugin
        $plugin_folder_name = dirname($this->basename);
        $plugin_folder = WP_PLUGIN_DIR . '/' . $plugin_folder_name;
        
        // Se o destino já é a pasta correta, não precisa mover
        if ($result['destination'] === $plugin_folder) {
            return $result;
        }
        
        // Remove a pasta antiga se existir (com verificação de permissão)
        if ($wp_filesystem->exists($plugin_folder)) {
            if (!$wp_filesystem->delete($plugin_folder, true)) {
                // Se não conseguir deletar, tenta renomear temporariamente
                $temp_folder = $plugin_folder . '_old_' . time();
                $wp_filesystem->move($plugin_folder, $temp_folder);
            }
        }
        
        // Move para o local correto
        $move_result = $wp_filesystem->move($result['destination'], $plugin_folder);
        
        if ($move_result) {
            $result['destination'] = $plugin_folder;
            
            // Remove pasta temporária se existir
            if (isset($temp_folder) && $wp_filesystem->exists($temp_folder)) {
                $wp_filesystem->delete($temp_folder, true);
            }
            
            // Reativa o plugin se estava ativo
            if ($this->active) {
                activate_plugin($this->basename);
            }
        }

        return $result;
    }
}
