<?php

class AlvoBotPro_AuthorBox_Settings {
    private static $instance = null;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option('ab_settings', array());
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'alvobot-pro',
            __('Author Box Settings', 'alvobot-pro'),
            __('Author Box', 'alvobot-pro'),
            'manage_options',
            'alvobot-pro-author-box',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('ab_settings', 'ab_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // General Settings
        add_settings_section(
            'ab_general_section',
            __('Display Settings', 'alvobot-pro'),
            null,
            'ab_settings'
        );

        add_settings_field(
            'display_on_posts',
            __('Display on Posts', 'alvobot-pro'),
            array($this, 'render_checkbox_field'),
            'ab_settings',
            'ab_general_section',
            array('id' => 'display_on_posts', 'default' => true)
        );

        add_settings_field(
            'display_on_pages',
            __('Display on Pages', 'alvobot-pro'),
            array($this, 'render_checkbox_field'),
            'ab_settings',
            'ab_general_section',
            array('id' => 'display_on_pages', 'default' => false)
        );

        add_settings_field(
            'hide_wordpress_authorbox',
            __('Hide WordPress Author Box', 'alvobot-pro'),
            array($this, 'render_checkbox_field'),
            'ab_settings',
            'ab_general_section',
            array('id' => 'hide_wordpress_authorbox', 'default' => false)
        );

        // Layout Settings
        add_settings_section(
            'ab_layout_section',
            __('Layout Settings', 'alvobot-pro'),
            null,
            'ab_settings'
        );

        $this->add_layout_settings_fields();
    }

    private function add_layout_settings_fields() {
        $layout_fields = array(
            'font' => array(
                'title' => __('Font Family', 'alvobot-pro'),
                'type' => 'select',
                'options' => array(
                    'inherit' => __('Default', 'alvobot-pro'),
                    'Arial' => 'Arial',
                    'Helvetica' => 'Helvetica',
                    'Georgia' => 'Georgia',
                    'Times New Roman' => 'Times New Roman'
                ),
                'default' => 'inherit'
            ),
            'show_shadow' => array(
                'title' => __('Show Shadow', 'alvobot-pro'),
                'type' => 'checkbox',
                'default' => true
            ),
            'show_border' => array(
                'title' => __('Show Border', 'alvobot-pro'),
                'type' => 'checkbox',
                'default' => true
            ),
            'border_color' => array(
                'title' => __('Border Color', 'alvobot-pro'),
                'type' => 'color',
                'default' => '#dddddd'
            ),
            'border_size' => array(
                'title' => __('Border Size (px)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 1
            ),
            'avatar_size' => array(
                'title' => __('Avatar Size (px)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 96
            ),
            'circle_avatar' => array(
                'title' => __('Circle Avatar', 'alvobot-pro'),
                'type' => 'checkbox',
                'default' => true
            ),
            'headline' => array(
                'title' => __('Headline Text', 'alvobot-pro'),
                'type' => 'text',
                'default' => __('About', 'alvobot-pro')
            ),
            'fontsize_headline' => array(
                'title' => __('Fontsize of Headline (em)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 1
            ),
            'fontsize_position' => array(
                'title' => __('Fontsize of author\'s Position (em)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 0.7
            ),
            'fontsize_bio' => array(
                'title' => __('Fontsize of Biography (em)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 1
            ),
            'fontsize_links' => array(
                'title' => __('Fontsize of Links (em)', 'alvobot-pro'),
                'type' => 'number',
                'default' => 0.7
            ),
            'display_authors_archive' => array(
                'title' => __('Display a link to the author\'s archive', 'alvobot-pro'),
                'type' => 'checkbox',
                'default' => true
            )
        );

        foreach ($layout_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                array($this, 'render_' . $field['type'] . '_field'),
                'ab_settings',
                'ab_layout_section',
                array('id' => $id, 'default' => $field['default'], 'options' => isset($field['options']) ? $field['options'] : null)
            );
        }
    }

    public function render_checkbox_field($args) {
        $id = $args['id'];
        $value = isset($this->settings[$id]) ? $this->settings[$id] : $args['default'];
        ?>
        <input type="checkbox" id="<?php echo esc_attr($id); ?>" 
               name="ab_settings[<?php echo esc_attr($id); ?>]" 
               value="1" <?php checked($value, true); ?>>
        <?php
    }

    public function render_text_field($args) {
        $id = $args['id'];
        $value = isset($this->settings[$id]) ? $this->settings[$id] : $args['default'];
        ?>
        <input type="text" id="<?php echo esc_attr($id); ?>" 
               name="ab_settings[<?php echo esc_attr($id); ?>]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_number_field($args) {
        $id = $args['id'];
        $value = isset($this->settings[$id]) ? $this->settings[$id] : $args['default'];
        ?>
        <input type="number" id="<?php echo esc_attr($id); ?>" 
               name="ab_settings[<?php echo esc_attr($id); ?>]" 
               value="<?php echo esc_attr($value); ?>" class="small-text">
        <?php
    }

    public function render_color_field($args) {
        $id = $args['id'];
        $value = isset($this->settings[$id]) ? $this->settings[$id] : $args['default'];
        ?>
        <input type="text" id="<?php echo esc_attr($id); ?>" 
               name="ab_settings[<?php echo esc_attr($id); ?>]" 
               value="<?php echo esc_attr($value); ?>" class="color-picker">
        <?php
    }

    public function render_select_field($args) {
        $id = $args['id'];
        $value = isset($this->settings[$id]) ? $this->settings[$id] : $args['default'];
        ?>
        <select id="<?php echo esc_attr($id); ?>" 
                name="ab_settings[<?php echo esc_attr($id); ?>]">
            <?php foreach ($args['options'] as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" 
                        <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('ab_settings');
                do_settings_sections('ab_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        // General settings
        $sanitized['display_on_posts'] = isset($input['display_on_posts']);
        $sanitized['display_on_pages'] = isset($input['display_on_pages']);
        $sanitized['hide_wordpress_authorbox'] = isset($input['hide_wordpress_authorbox']);
        
        // Layout settings
        $sanitized['font'] = sanitize_text_field($input['font']);
        $sanitized['show_shadow'] = isset($input['show_shadow']);
        $sanitized['show_border'] = isset($input['show_border']);
        $sanitized['border_color'] = sanitize_hex_color($input['border_color']);
        $sanitized['border_size'] = absint($input['border_size']);
        $sanitized['avatar_size'] = absint($input['avatar_size']);
        $sanitized['circle_avatar'] = isset($input['circle_avatar']);
        $sanitized['headline'] = sanitize_text_field($input['headline']);
        $sanitized['fontsize_headline'] = absint($input['fontsize_headline']);
        $sanitized['fontsize_position'] = absint($input['fontsize_position']);
        $sanitized['fontsize_bio'] = absint($input['fontsize_bio']);
        $sanitized['fontsize_links'] = absint($input['fontsize_links']);
        $sanitized['display_authors_archive'] = isset($input['display_authors_archive']);
        
        return $sanitized;
    }
}

// Initialize the settings
AlvoBotPro_AuthorBox_Settings::get_instance();