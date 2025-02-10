<?php

class AlvoBotPro_AuthorBox_Setup {
    private static $instance = null;
    private $plugin_path;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/';
        add_action('alvobot_pro_module_activated_author_box', array($this, 'activate'));
        add_action('alvobot_pro_module_deactivated_author_box', array($this, 'deactivate'));
    }

    public function activate() {
        require_once $this->plugin_path . 'php/settings-defaults.php';

        $default_settings = array(
            'display_on_posts' => true,
            'display_on_pages' => false,
            'hide_wordpress_authorbox' => false,
            'font' => 'inherit',
            'show_shadow' => true,
            'show_border' => true,
            'border_color' => '#dddddd',
            'border_size' => 1,
            'avatar_size' => 96,
            'circle_avatar' => true,
            'headline' => __('About', 'alvobot-pro'),
            'fontsize_headline' => 18,
            'fontsize_position' => 14,
            'fontsize_bio' => 14,
            'fontsize_links' => 14,
            'display_authors_archive' => true
        );

        update_option('ab_settings', $default_settings);
    }

    public function deactivate() {
        delete_option('ab_settings');

        // Limpar metadados dos usuários
        $users = get_users();
        foreach ($users as $user) {
            delete_user_meta($user->ID, 'ab_setting_position');
            delete_user_meta($user->ID, 'ab_custom_avatar');
            delete_user_meta($user->ID, 'ab_setting_homepage_linktext');
            delete_user_meta($user->ID, 'ab_setting_homepage_linkurl');
        }
    }
}

// Initialize the setup
AlvoBotPro_AuthorBox_Setup::get_instance();

?>