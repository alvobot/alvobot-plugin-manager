<?php

class AlvoBotPro_AuthorBox_UserSettings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Adiciona os campos ao perfil do usuário
        add_action('show_user_profile', array($this, 'add_user_fields'));
        add_action('edit_user_profile', array($this, 'add_user_fields'));
        
        // Salva os campos do perfil do usuário
        add_action('personal_options_update', array($this, 'save_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_fields'));
        
        // Carrega os scripts e estilos necessários
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        if ('profile.php' !== $hook && 'user-edit.php' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'ab-custom-avatar',
            plugins_url('js/custom-avatar.js', dirname(__FILE__)),
            array('jquery'),
            ALVOBOT_PRO_VERSION,
            true
        );
    }

    public function add_user_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        ?>
        <div class="wrap alvobot-pro-wrap">
            <div class="alvobot-pro-header">
                <h1><?php _e('Author Box Settings', 'alvobot-pro'); ?></h1>
                <p><?php _e('Customize how your author information appears in the Author Box.', 'alvobot-pro'); ?></p>
            </div>

            <!-- Preview Card -->
            <div class="alvobot-pro-preview-card">
                <h3><?php _e('Live Preview', 'alvobot-pro'); ?></h3>
                <div class="ab-avatar-preview" style="margin-bottom: 10px;">
                    <?php 
                    $custom_avatar_id = get_user_meta($user->ID, 'ab_custom_avatar_id', true);
                    $avatar_url = $custom_avatar_id ? wp_get_attachment_image_url($custom_avatar_id, 'thumbnail') : get_avatar_url($user->ID);
                    ?>
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" />
                </div>

            </div>

            <!-- Settings Card -->
            <div class="alvobot-pro-card">
                <div class="alvobot-pro-card-header">
                    <h2><?php _e('Author Box Configuration', 'alvobot-pro'); ?></h2>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th>
                            <label for="ab_custom_avatar"><?php _e('Author Box Avatar', 'alvobot-pro'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" name="ab_custom_avatar_id" id="ab_custom_avatar_id" 
                                   value="<?php echo esc_attr($custom_avatar_id); ?>" />
                            <input type="button" class="button" id="ab_upload_avatar_button" 
                                   value="<?php _e('Select Image', 'alvobot-pro'); ?>" />
                            <?php if ($avatar_url) : ?>
                                <input type="button" class="button" id="ab_remove_avatar_button" 
                                       value="<?php _e('Remove Image', 'alvobot-pro'); ?>" />
                            <?php endif; ?>
                            <p class="description">
                                <?php _e('This image will be used in the Author Box instead of your Gravatar.', 'alvobot-pro'); ?>
                            </p>
                        </td>
                    </tr>


                </table>
            </div>
        </div>
        <?php
    }

    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        update_user_meta($user_id, 'ab_setting_position', 
            sanitize_text_field($_POST['ab_setting_position']));
        
        update_user_meta($user_id, 'ab_custom_avatar_id',
            sanitize_text_field($_POST['ab_custom_avatar_id']));
        
        update_user_meta($user_id, 'ab_setting_homepage_linktext',
            sanitize_text_field($_POST['ab_setting_homepage_linktext']));
        
        update_user_meta($user_id, 'ab_setting_homepage_linkurl',
            esc_url_raw($_POST['ab_setting_homepage_linkurl']));
    }
}

// Inicializa a classe de configurações do usuário
add_action('init', array('AlvoBotPro_AuthorBox_UserSettings', 'get_instance'));

?>