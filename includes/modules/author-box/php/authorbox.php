<?php

class AlvoBotPro_AuthorBox_Display {
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
        $this->init();
    }

    public function init() {
        add_shortcode('authorbox', array($this, 'shortcode_authorbox'));
        add_filter('the_content', array($this, 'add_authorbox'));
    }

    public function shortcode_authorbox() {
        return $this->get_authorbox();
    }

    public function add_authorbox($content) {
        $display_on_posts = isset($this->settings['display_on_posts']) ? $this->settings['display_on_posts'] : true;
        $display_on_pages = isset($this->settings['display_on_pages']) ? $this->settings['display_on_pages'] : false;
        
        if (is_single() && $display_on_posts) {
            $content .= $this->get_authorbox();
        }
        else if (is_page() && $display_on_pages && !is_front_page() && !is_home() && !is_privacy_policy()) {
            $content .= $this->get_authorbox();
        }
        return $content;
    }

    public function get_authorbox() {
        $author_id = get_the_author_meta('ID');
        
        // Get settings with defaults
        $settings = wp_parse_args($this->settings, array(
            'avatar_size' => 96,
            'circle_avatar' => true,
            'font' => 'inherit',
            'show_shadow' => true,
            'show_border' => true,
            'border_color' => '#dddddd',
            'border_size' => 1,
            'font_size_headline' => 18,
            'font_size_position' => 14,
            'font_size_bio' => 14,
            'font_size_links' => 14,
            'display_authors_archive' => true,
            'headline' => __('About', 'alvobot-pro')
        ));
        
        $circle_avatar_class = $settings['circle_avatar'] ? "class='ab_circle'" : '';
        
        $author_link_text = get_the_author_meta('ab_setting_homepage_linktext');
        $author_link_url = get_the_author_meta('ab_setting_homepage_linkurl');
        
        // Build HTML
        $html = '<div class="ab_author_box" style="font-family: ' . esc_attr($settings['font']) . ';">';
        
        if ($settings['show_shadow']) {
            $html .= '<style>.ab_author_box { box-shadow: 0 0 10px rgba(0,0,0,0.1); }</style>';
        }
        
        if ($settings['show_border']) {
            $html .= '<style>.ab_author_box { border: ' . esc_attr($settings['border_size']) . 'px solid ' . esc_attr($settings['border_color']) . '; }</style>';
        }
        
        // Avatar
        $html .= '<div class="ab_author_image">';
        $html .= get_avatar($author_id, $settings['avatar_size'], '', '', array('class' => $circle_avatar_class));
        $html .= '</div>';
        
        // Author info
        $html .= '<div class="ab_author_info">';
        $html .= '<div class="ab_name" style="font-size: ' . esc_attr($settings['font_size_headline']) . 'px;">';
        $html .= $settings['headline'] . ' ' . get_the_author();
        $html .= '</div>';
        
        // Position/title if set
        $position = get_the_author_meta('ab_setting_position');
        if (!empty($position)) {
            $html .= '<div class="ab_position" style="font-size: ' . esc_attr($settings['font_size_position']) . 'px;">';
            $html .= esc_html($position);
            $html .= '</div>';
        }
        
        // Bio
        $html .= '<div class="ab_description" style="font-size: ' . esc_attr($settings['font_size_bio']) . 'px;">';
        $html .= wpautop(get_the_author_meta('description'));
        $html .= '</div>';
        
        // Links
        $html .= '<div class="ab_links" style="font-size: ' . esc_attr($settings['font_size_links']) . 'px;">';
        
        // Author archive link
        if ($settings['display_authors_archive']) {
            $html .= '<a href="' . esc_url(get_author_posts_url($author_id)) . '">';
            $html .= sprintf(__('Read more posts by %s', 'alvobot-pro'), get_the_author());
            $html .= '</a>';
        }
        
        // Custom homepage link
        if (!empty($author_link_url) && !empty($author_link_text)) {
            $html .= ' | <a href="' . esc_url($author_link_url) . '" target="_blank" rel="nofollow">';
            $html .= esc_html($author_link_text);
            $html .= '</a>';
        }
        
        $html .= '</div>'; // .ab_links
        $html .= '</div>'; // .ab_author_info
        $html .= '</div>'; // .ab_author_box
        
        return $html;
    }
}

// Initialize the class
AlvoBotPro_AuthorBox_Display::get_instance();

?>