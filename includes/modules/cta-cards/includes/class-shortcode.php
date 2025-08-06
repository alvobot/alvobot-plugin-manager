<?php
/**
 * CTA Cards Shortcode Handler
 *
 * @package AlvoBotPro
 * @subpackage Modules/CTACards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the [cta_card] shortcode functionality
 */
class AlvoBotPro_CTACards_Shortcode {
    
    /**
     * Available templates
     *
     * @var array
     */
    private $templates = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_templates();
        add_shortcode('cta_card', array($this, 'render_cta_card'));
    }
    
    /**
     * Initialize available templates
     */
    private function init_templates() {
        $this->templates = array(
            'vertical' => 'render_vertical_template',
            'horizontal' => 'render_horizontal_template',
            'minimal' => 'render_minimal_template',
            'banner' => 'render_banner_template',
            'simple' => 'render_simple_template',
            'pulse' => 'render_pulse_template',
            'multi-button' => 'render_multi_button_template',
            'led-border' => 'render_led_border_template'
        );
    }
    
    /**
     * Render the CTA card shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_cta_card($atts) {
        // Default attributes
        $defaults = array(
            'template' => 'vertical',
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'button' => __('Saiba Mais', 'alvobot-pro'),
            'url' => '#',
            'target' => '_self',
            'image' => '',
            'icon' => '',
            'tag' => '',
            'background' => '',
            'color_primary' => '#2271b1',
            'color_button' => '#2271b1',
            'color_text' => '#333333',
            'color_bg' => '#ffffff',
            'align' => 'center',
            'class' => '',
            // Multi-button parameters
            'button2' => '',
            'url2' => '',
            'target2' => '_self',
            'color_button2' => '#666666',
            'button3' => '',
            'url3' => '',
            'target3' => '_self',
            'color_button3' => '#999999',
            // Pulse template specific
            'pulse_color' => '#ff6b6b',
            'pulse_text' => __('AO VIVO', 'alvobot-pro'),
            // LED Border template specific
            'led_colors' => '#ff0080,#00ff80,#8000ff,#ff8000',
            'led_speed' => '2s'
        );
        
        $atts = shortcode_atts($defaults, $atts, 'cta_card');
        
        // Validate template
        if (!isset($this->templates[$atts['template']])) {
            $atts['template'] = 'vertical';
        }
        
        // Sanitize attributes
        $atts = $this->sanitize_attributes($atts);
        
        // Call the appropriate template renderer
        $method = $this->templates[$atts['template']];
        if (method_exists($this, $method)) {
            return $this->$method($atts);
        }
        
        return '';
    }
    
    /**
     * Sanitize shortcode attributes
     *
     * @param array $atts Raw attributes
     * @return array Sanitized attributes
     */
    private function sanitize_attributes($atts) {
        $atts['title'] = sanitize_text_field($atts['title']);
        $atts['subtitle'] = sanitize_text_field($atts['subtitle']);
        $atts['description'] = wp_kses_post($atts['description']);
        $atts['button'] = sanitize_text_field($atts['button']);
        $atts['url'] = esc_url($atts['url']);
        $atts['target'] = in_array($atts['target'], array('_blank', '_self')) ? $atts['target'] : '_self';
        $atts['image'] = esc_url($atts['image']);
        $atts['icon'] = $this->sanitize_icon($atts['icon']);
        $atts['tag'] = sanitize_text_field($atts['tag']);
        $atts['background'] = esc_url($atts['background']);
        $atts['align'] = in_array($atts['align'], array('left', 'center', 'right')) ? $atts['align'] : 'center';
        $atts['class'] = sanitize_html_class($atts['class']);
        
        // Sanitize colors
        $atts['color_primary'] = $this->sanitize_hex_color($atts['color_primary']);
        $atts['color_button'] = $this->sanitize_hex_color($atts['color_button']);
        $atts['color_text'] = $this->sanitize_hex_color($atts['color_text']);
        $atts['color_bg'] = $this->sanitize_hex_color($atts['color_bg']);
        
        // Sanitize multi-button attributes
        $atts['button2'] = sanitize_text_field($atts['button2']);
        $atts['url2'] = esc_url($atts['url2']);
        $atts['target2'] = in_array($atts['target2'], array('_blank', '_self')) ? $atts['target2'] : '_self';
        $atts['color_button2'] = $this->sanitize_hex_color($atts['color_button2']);
        
        $atts['button3'] = sanitize_text_field($atts['button3']);
        $atts['url3'] = esc_url($atts['url3']);
        $atts['target3'] = in_array($atts['target3'], array('_blank', '_self')) ? $atts['target3'] : '_self';
        $atts['color_button3'] = $this->sanitize_hex_color($atts['color_button3']);
        
        // Sanitize pulse attributes
        $atts['pulse_color'] = $this->sanitize_hex_color($atts['pulse_color']);
        $atts['pulse_text'] = sanitize_text_field($atts['pulse_text']);
        
        // Sanitize LED border attributes
        $atts['led_colors'] = sanitize_text_field($atts['led_colors']);
        $atts['led_speed'] = sanitize_text_field($atts['led_speed']);
        
        return $atts;
    }
    
    /**
     * Sanitize hex color
     *
     * @param string $color Hex color
     * @return string Sanitized hex color
     */
    private function sanitize_hex_color($color) {
        if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            return $color;
        }
        return '#2271b1'; // Default WordPress blue
    }
    
    /**
     * Sanitize icon (preserves emojis and dashicons)
     *
     * @param string $icon Icon string
     * @return string Sanitized icon
     */
    private function sanitize_icon($icon) {
        // Remove any dangerous HTML tags but preserve emojis and text
        $icon = strip_tags($icon);
        
        // Check if it's a dashicon (starts with dashicons- and contains only valid characters)
        if (preg_match('/^dashicons-[a-z0-9\-]+$/i', $icon)) {
            return $icon;
        }
        
        // Check if it's an emoji or special character - allow most unicode characters
        // This regex allows emojis, symbols, and other non-dangerous characters
        if (preg_match('/^[\p{L}\p{N}\p{S}\p{M}\p{Sk}\p{So}]+$/u', $icon)) {
            return $icon;
        }
        
        // If it doesn't match safe patterns, return empty
        return '';
    }
    
    /**
     * Get inline styles
     *
     * @param array $atts Attributes
     * @return string Inline style attribute
     */
    private function get_inline_styles($atts, $properties = array()) {
        $styles = array();
        
        if (in_array('background', $properties) && $atts['color_bg']) {
            $styles[] = 'background-color: ' . $atts['color_bg'];
        }
        
        if (in_array('color', $properties) && $atts['color_text']) {
            $styles[] = 'color: ' . $atts['color_text'];
        }
        
        if (in_array('text-align', $properties) && $atts['align']) {
            $styles[] = 'text-align: ' . $atts['align'];
        }
        
        return !empty($styles) ? ' style="' . esc_attr(implode('; ', $styles)) . '"' : '';
    }
    
    /**
     * Render vertical template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_vertical_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-vertical ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background', 'text-align')) . '>';
        
        // Image
        if (!empty($atts['image'])) {
            $output .= '<div class="cta-image">';
            $output .= '<img src="' . esc_url($atts['image']) . '" alt="' . esc_attr($atts['title']) . '">';
            $output .= '</div>';
        }
        
        // Content
        $output .= '<div class="cta-content">';
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h3 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h3>';
        }
        
        // Subtitle
        if (!empty($atts['subtitle'])) {
            $output .= '<p class="cta-subtitle">' . esc_html($atts['subtitle']) . '</p>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $button_style = 'background-color: ' . $atts['color_button'] . '; color: #ffffff;';
            $output .= '<div class="cta-button-wrapper">';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button" style="' . esc_attr($button_style) . '"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render horizontal template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_horizontal_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-horizontal ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background')) . '>';
        
        // Image
        if (!empty($atts['image'])) {
            $output .= '<div class="cta-image">';
            $output .= '<img src="' . esc_url($atts['image']) . '" alt="' . esc_attr($atts['title']) . '">';
            $output .= '</div>';
        }
        
        // Content
        $output .= '<div class="cta-content">';
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h3 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h3>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $button_style = 'background-color: ' . $atts['color_button'] . '; color: #ffffff;';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button" style="' . esc_attr($button_style) . '"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . '</a>';
        }
        
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render minimal template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_minimal_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-minimal ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background')) . '>';
        
        // Tag
        if (!empty($atts['tag'])) {
            $tag_style = 'background-color: ' . $atts['color_primary'] . '; color: #ffffff;';
            $output .= '<span class="cta-tag" style="' . esc_attr($tag_style) . '">' . 
                       esc_html($atts['tag']) . '</span>';
        }
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h4 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h4>';
        }
        
        // Subtitle
        if (!empty($atts['subtitle'])) {
            $output .= '<p class="cta-subtitle">' . esc_html($atts['subtitle']) . '</p>';
        }
        
        // Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button-minimal"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . ' →</a>';
        }
        
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render banner template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_banner_template($atts) {
        $banner_style = '';
        if (!empty($atts['background'])) {
            $banner_style = 'background-image: url(' . esc_url($atts['background']) . ');';
        }
        
        $output = '<div class="alvobot-cta-card alvobot-cta-banner ' . esc_attr($atts['class']) . '" style="' . esc_attr($banner_style) . '">';
        $output .= '<div class="cta-overlay">';
        $output .= '<div class="cta-content"' . $this->get_inline_styles($atts, array('text-align')) . '>';
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h2 class="cta-title">' . esc_html($atts['title']) . '</h2>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $button_style = 'background-color: ' . $atts['color_button'] . '; color: #ffffff;';
            $output .= '<div class="cta-button-wrapper">';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button cta-button-large" style="' . esc_attr($button_style) . '"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .cta-overlay
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render simple template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_simple_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-simple ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background')) . '>';
        
        $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-link"';
        if ($atts['target'] === '_blank') {
            $output .= ' target="_blank" rel="noopener noreferrer"';
        }
        $output .= '>';
        
        // Icon or Emoji
        if (!empty($atts['icon'])) {
            // Check if it's an emoji (starts with emoji unicode or is single character that's not alphanumeric)
            if (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $atts['icon']) || 
                (mb_strlen($atts['icon']) === 1 && !ctype_alnum($atts['icon']))) {
                // It's an emoji
                $output .= '<span class="cta-icon cta-emoji" style="color: ' . esc_attr($atts['color_primary']) . '">' . $atts['icon'] . '</span>';
            } else {
                // It's a dashicon
                $output .= '<span class="cta-icon dashicons ' . esc_attr($atts['icon']) . '" style="color: ' . 
                           esc_attr($atts['color_primary']) . '"></span>';
            }
        }
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<span class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</span>';
        }
        
        $output .= '<span class="cta-arrow">→</span>';
        $output .= '</a>';
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render pulse template (with animation)
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_pulse_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-pulse ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background')) . '>';
        
        // Pulse indicator
        if (!empty($atts['pulse_text'])) {
            $output .= '<div class="cta-pulse-indicator">';
            $output .= '<span class="pulse-dot" style="background-color: ' . esc_attr($atts['pulse_color']) . '"></span>';
            $output .= '<span class="pulse-text" style="color: ' . esc_attr($atts['pulse_color']) . '">' . 
                       esc_html($atts['pulse_text']) . '</span>';
            $output .= '</div>';
        }
        
        // Content
        $output .= '<div class="cta-content">';
        
        // Icon or Emoji
        if (!empty($atts['icon'])) {
            $output .= '<div class="cta-icon-wrapper">';
            // Check if it's an emoji (starts with emoji unicode or is single character that's not alphanumeric)
            if (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $atts['icon']) || 
                (mb_strlen($atts['icon']) === 1 && !ctype_alnum($atts['icon']))) {
                // It's an emoji
                $output .= '<span class="cta-icon cta-emoji" style="color: ' . esc_attr($atts['color_primary']) . '">' . $atts['icon'] . '</span>';
            } else {
                // It's a dashicon
                $output .= '<span class="cta-icon dashicons ' . esc_attr($atts['icon']) . '" style="color: ' . 
                           esc_attr($atts['color_primary']) . '"></span>';
            }
            $output .= '</div>';
        }
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h3 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h3>';
        }
        
        // Subtitle
        if (!empty($atts['subtitle'])) {
            $output .= '<p class="cta-subtitle">' . esc_html($atts['subtitle']) . '</p>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $button_style = 'background-color: ' . $atts['color_button'] . '; color: #ffffff;';
            $output .= '<div class="cta-button-wrapper">';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button cta-button-pulse" style="' . esc_attr($button_style) . '"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render multi-button template
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_multi_button_template($atts) {
        $output = '<div class="alvobot-cta-card alvobot-cta-multi-button ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background', 'text-align')) . '>';
        
        // Image (optional)
        if (!empty($atts['image'])) {
            $output .= '<div class="cta-image">';
            $output .= '<img src="' . esc_url($atts['image']) . '" alt="' . esc_attr($atts['title']) . '">';
            $output .= '</div>';
        }
        
        // Content
        $output .= '<div class="cta-content">';
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h3 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h3>';
        }
        
        // Subtitle
        if (!empty($atts['subtitle'])) {
            $output .= '<p class="cta-subtitle">' . esc_html($atts['subtitle']) . '</p>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // Buttons container
        $output .= '<div class="cta-buttons-wrapper">';
        
        // Primary button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $button_style = 'background-color: ' . $atts['color_button'] . '; color: #ffffff;';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button cta-button-primary" style="' . esc_attr($button_style) . '"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button']) . '</a>';
        }
        
        // Secondary button
        if (!empty($atts['button2']) && !empty($atts['url2'])) {
            $button_style = 'background-color: ' . $atts['color_button2'] . '; color: #ffffff;';
            $output .= '<a href="' . esc_url($atts['url2']) . '" class="cta-button cta-button-secondary" style="' . esc_attr($button_style) . '"';
            if ($atts['target2'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button2']) . '</a>';
        }
        
        // Tertiary button
        if (!empty($atts['button3']) && !empty($atts['url3'])) {
            $button_style = 'background-color: transparent; border: 2px solid ' . $atts['color_button3'] . '; color: ' . $atts['color_button3'] . ';';
            $output .= '<a href="' . esc_url($atts['url3']) . '" class="cta-button cta-button-tertiary" style="' . esc_attr($button_style) . '"';
            if ($atts['target3'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>' . esc_html($atts['button3']) . '</a>';
        }
        
        $output .= '</div>'; // .cta-buttons-wrapper
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
    
    /**
     * Render LED border template (with rotating LED effect)
     *
     * @param array $atts Attributes
     * @return string HTML
     */
    private function render_led_border_template($atts) {
        // Generate CSS variables for LED colors and speed
        $led_style = '';
        if (!empty($atts['led_colors'])) {
            $colors = explode(',', $atts['led_colors']);
            $led_style .= '--led-colors: ' . implode(', ', array_map('trim', $colors)) . '; ';
        }
        if (!empty($atts['led_speed'])) {
            $led_style .= '--led-speed: ' . esc_attr($atts['led_speed']) . '; ';
        }
        
        $output = '<div class="alvobot-cta-card alvobot-cta-led-border ' . esc_attr($atts['class']) . '"' . 
                  $this->get_inline_styles($atts, array('background', 'text-align')) . 
                  (!empty($led_style) ? ' style="' . $led_style . '"' : '') . '>';
        
        // LED Border wrapper
        $output .= '<div class="cta-led-wrapper">';
        
        // Image (optional)
        if (!empty($atts['image'])) {
            $output .= '<div class="cta-image">';
            $output .= '<img src="' . esc_url($atts['image']) . '" alt="' . esc_attr($atts['title']) . '">';
            $output .= '</div>';
        }
        
        // Content
        $output .= '<div class="cta-content">';
        
        // Icon or Emoji (optional)
        if (!empty($atts['icon'])) {
            $output .= '<div class="cta-icon-wrapper">';
            // Check if it's an emoji (starts with emoji unicode or is single character that's not alphanumeric)
            if (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $atts['icon']) || 
                (mb_strlen($atts['icon']) === 1 && !ctype_alnum($atts['icon']))) {
                // It's an emoji
                $output .= '<span class="cta-icon cta-emoji" style="color: ' . esc_attr($atts['color_primary']) . '">' . $atts['icon'] . '</span>';
            } else {
                // It's a dashicon
                $output .= '<span class="cta-icon dashicons ' . esc_attr($atts['icon']) . '" style="color: ' . 
                           esc_attr($atts['color_primary']) . '"></span>';
            }
            $output .= '</div>';
        }
        
        // Title
        if (!empty($atts['title'])) {
            $output .= '<h3 class="cta-title"' . $this->get_inline_styles($atts, array('color')) . '>' . 
                       esc_html($atts['title']) . '</h3>';
        }
        
        // Subtitle
        if (!empty($atts['subtitle'])) {
            $output .= '<p class="cta-subtitle">' . esc_html($atts['subtitle']) . '</p>';
        }
        
        // Description
        if (!empty($atts['description'])) {
            $output .= '<div class="cta-description">' . wp_kses_post($atts['description']) . '</div>';
        }
        
        // LED Button
        if (!empty($atts['button']) && !empty($atts['url'])) {
            $output .= '<div class="cta-button-wrapper">';
            $output .= '<div class="cta-led-button-container">';
            $output .= '<a href="' . esc_url($atts['url']) . '" class="cta-button cta-led-button"';
            if ($atts['target'] === '_blank') {
                $output .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '>';
            $output .= '<span class="led-button-text">' . esc_html($atts['button']) . '</span>';
            $output .= '</a>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // .cta-content
        $output .= '</div>'; // .cta-led-wrapper
        $output .= '</div>'; // .alvobot-cta-card
        
        return $output;
    }
}