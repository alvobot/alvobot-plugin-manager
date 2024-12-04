<?php
/**
 * Gerenciador de atualizações via GitHub
 *
 * @package Alvobot_Plugin_Manager
 */

declare(strict_types=1);

use YahnisElsts\PluginUpdateChecker\v5p5\PucFactory;

class Alvobot_Plugin_Manager_Updater {
    /**
     * O caminho completo do arquivo principal do plugin
     *
     * @var string
     */
    private string $plugin_file;

    /**
     * O nome base do plugin (diretório/arquivo)
     *
     * @var string
     */
    private string $plugin_basename;

    /**
     * O slug do plugin
     *
     * @var string
     */
    private string $plugin_slug;

    /**
     * Instância do Plugin Update Checker
     *
     * @var \YahnisElsts\PluginUpdateChecker\v5p5\PucFactory
     */
    private $update_checker;

    /**
     * Constructor
     *
     * @param string $plugin_file Caminho completo do arquivo principal do plugin
     */
    public function __construct(string $plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        
        // Inclui o arquivo de carregamento do Plugin Update Checker
        require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/load-v5p5.php';
        
        $this->init();
    }

    /**
     * Inicializa o sistema de atualizações
     */
    private function init(): void {
        // Configura o atualizador
        $this->update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/alvobot/alvobot-plugin-manager/',
            $this->plugin_file,
            'alvobot-plugin-manager'
        );

        // Configura para usar releases do GitHub
        if ($this->update_checker instanceof \YahnisElsts\PluginUpdateChecker\v5p5\Vcs\PluginUpdateChecker) {
            $this->update_checker->getVcsApi()->enableReleaseAssets();
        }

        // Configura o branch a ser usado (main/master)
        $this->update_checker->setBranch('main');

        // Adiciona filtros e ações
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'add_action_links']);
        add_action('admin_init', [$this, 'handle_manual_check']);
        add_action('admin_footer', [$this, 'add_manual_check_script']);
    }

    /**
     * Adiciona o link "Atualizar" e o código de verificação na lista de plugins
     *
     * @param array $links Links de ação do plugin.
     * @return array Links modificados
     */
    public function add_action_links(array $links): array {
        if (current_user_can('update_plugins')) {
            $check_update_link = sprintf(
                '<a href="#" class="alvobot-check-updates" data-plugin="%s" data-nonce="%s">%s</a>',
                esc_attr($this->plugin_slug),
                esc_attr(wp_create_nonce('alvobot_check_updates')),
                esc_html__('Verificar Atualização', 'alvobot-plugin-manager')
            );
            
            array_unshift($links, $check_update_link);
        }
        return $links;
    }

    /**
     * Adiciona o script para verificação manual de atualizações
     */
    public function add_manual_check_script(): void {
        // Só adiciona na página de plugins
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'plugins') {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.alvobot-check-updates').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                var plugin = $button.data('plugin');
                var nonce = $button.data('nonce');
                
                // Desativa o botão e mostra loading
                $button.text('<?php echo esc_js(__('Verificando...', 'alvobot-plugin-manager')); ?>').addClass('disabled');
                
                // Faz a requisição AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alvobot_check_updates',
                        plugin: plugin,
                        _ajax_nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Recarrega a página para mostrar atualizações
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(__('Erro ao verificar atualizações.', 'alvobot-plugin-manager')); ?>');
                            $button.text(originalText).removeClass('disabled');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Erro ao verificar atualizações.', 'alvobot-plugin-manager')); ?>');
                        $button.text(originalText).removeClass('disabled');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Manipula a verificação manual de atualizações
     */
    public function handle_manual_check(): void {
        // Adiciona endpoint AJAX para verificação de atualizações
        add_action('wp_ajax_alvobot_check_updates', [$this, 'ajax_check_updates']);
    }

    /**
     * Verifica atualizações via AJAX
     */
    public function ajax_check_updates(): void {
        // Verifica nonce e permissões
        check_ajax_referer('alvobot_check_updates');
        if (!current_user_can('update_plugins')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'alvobot-plugin-manager')]);
        }

        try {
            // Força o Update Checker a verificar novamente
            $this->update_checker->checkForUpdates();
            
            wp_send_json_success(['message' => __('Verificação concluída.', 'alvobot-plugin-manager')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
