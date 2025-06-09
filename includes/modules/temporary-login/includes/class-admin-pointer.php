<?php
/**
 * Admin Pointer for Temporary Login
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlvoBotPro_TemporaryLogin_AdminPointer {

    const CURRENT_POINTER_SLUG = 'alvobot-temporary-login-pointer';

    /**
     * Register pointer hooks
     */
    public static function add_hooks() {
        add_action('admin_print_footer_scripts-index.php', [__CLASS__, 'admin_print_script']);
        add_action('wp_ajax_alvobot_temporary_login_pointer_dismissed', [__CLASS__, 'ajax_dismiss_pointer']);
    }

    /**
     * Print pointer script
     */
    public static function admin_print_script() {
        if (!current_user_can(AlvoBotPro_TemporaryLogin_Ajax::USER_CAPABILITY)) {
            return;
        }

        if (self::is_dismissed()) {
            return;
        }

        wp_enqueue_script('wp-pointer');
        wp_enqueue_style('wp-pointer');

        $pointer_content = '<h3>' . esc_html__('Login Temporário', 'alvobot-pro') . '</h3>';
        $pointer_content .= '<p>' . esc_html__('Acesse o módulo de Login Temporário para criar acessos seguros e temporários ao painel administrativo do seu site.', 'alvobot-pro') . '</p>';

        $pointer_content .= sprintf(
            '<p><a class="button button-primary" href="%s">%s</a></p>',
            admin_url('users.php?page=alvobot-temporary-login'),
            esc_html__('Ver agora', 'alvobot-pro')
        );

        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#menu-users').pointer({
                    content: '<?php echo wp_kses_post($pointer_content); ?>',
                    position: {
                        edge: <?php echo is_rtl() ? "'right'" : "'left'"; ?>,
                        align: 'center'
                    },
                    close: function() {
                        wp.ajax.post('alvobot_temporary_login_pointer_dismissed', {
                            data: {
                                pointer: '<?php echo esc_attr(self::CURRENT_POINTER_SLUG); ?>',
                            },
                            nonce: '<?php echo esc_attr(wp_create_nonce(self::CURRENT_POINTER_SLUG . '-dismissed')); ?>',
                        });
                    }
                }).pointer('open');
            });
        </script>
        <?php
    }

    /**
     * Check if pointer is dismissed
     *
     * @return bool
     */
    public static function is_dismissed(): bool {
        $dismissed = get_user_meta(get_current_user_id(), '_alvobot_temporary_login_pointer_dismissed', true);
        return !empty($dismissed);
    }

    /**
     * Handle AJAX dismiss pointer
     */
    public static function ajax_dismiss_pointer() {
        check_ajax_referer(self::CURRENT_POINTER_SLUG . '-dismissed', 'nonce');

        if (empty($_POST['data']['pointer']) || self::CURRENT_POINTER_SLUG !== $_POST['data']['pointer']) {
            wp_send_json_error('Invalid pointer');
        }

        self::dismiss();

        wp_send_json_success();
    }

    /**
     * Dismiss pointer
     */
    public static function dismiss() {
        update_user_meta(get_current_user_id(), '_alvobot_temporary_login_pointer_dismissed', 1);
    }
}
