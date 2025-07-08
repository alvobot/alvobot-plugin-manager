<?php
/**
 * AJAX handler class
 *
 * @package Alvobot_Quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all AJAX requests for the plugin
 * 
 * Processes quiz preview requests with comprehensive data validation
 * and sanitization. Receives structured data instead of raw shortcode
 * for enhanced security.
 *
 * @since 1.0.0
 */
class Alvobot_Quiz_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_alvobot_quiz_preview', array($this, 'ajax_quiz_preview'));
    }
    
    /**
     * Handle quiz preview AJAX request
     * Receives structured data instead of raw shortcode for better security
     */
    public function ajax_quiz_preview() {
        // Check nonce
        check_ajax_referer('alvobot_quiz_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get and validate data
        if (!isset($_POST['questions']) || !isset($_POST['settings'])) {
            wp_send_json_error('Missing required data');
        }
        
        // Sanitize questions data with comprehensive validation
        $questions = array();
        $raw_questions = $_POST['questions'];
        
        if (is_array($raw_questions) && !empty($raw_questions)) {
            foreach ($raw_questions as $index => $question) {
                // Skip invalid questions
                if (!is_array($question) || empty($question['question']) || empty($question['answers'])) {
                    continue;
                }
                
                $sanitized_question = array(
                    'question' => sanitize_text_field(trim($question['question'])),
                    'answers' => array()
                );
                
                // Sanitize answers with validation
                if (isset($question['answers']) && is_array($question['answers'])) {
                    foreach ($question['answers'] as $answer) {
                        $clean_answer = sanitize_text_field(trim($answer));
                        if (!empty($clean_answer)) {
                            $sanitized_question['answers'][] = $clean_answer;
                        }
                    }
                }
                
                // Skip questions without valid answers
                if (empty($sanitized_question['answers'])) {
                    continue;
                }
                
                // Sanitize optional fields with validation
                if (isset($question['correct']) && is_numeric($question['correct'])) {
                    $correct_index = intval($question['correct']);
                    // Validate correct index is within bounds
                    if ($correct_index >= 0 && $correct_index < count($sanitized_question['answers'])) {
                        $sanitized_question['correct'] = $correct_index;
                    }
                }
                
                if (isset($question['explanation']) && !empty(trim($question['explanation']))) {
                    $sanitized_question['explanation'] = sanitize_textarea_field(trim($question['explanation']));
                }
                
                $questions[] = $sanitized_question;
            }
        }
        
        // Ensure we have at least one valid question
        if (empty($questions)) {
            wp_send_json_error('No valid questions provided');
        }
        
        // Sanitize settings with validation
        $settings = array();
        $raw_settings = $_POST['settings'];
        
        if (is_array($raw_settings)) {
            $allowed_settings = array(
                'redirect_url' => 'url',
                'style' => array('default', 'modern', 'minimal'),
                'show_progress' => array('true', 'false'),
                'allow_back' => array('true', 'false'),
                'randomize' => array('true', 'false'),
                'auto_advance' => array('true', 'false'),
                'show_score' => array('true', 'false'),
                'show_nav_buttons' => array('true', 'false')
            );
            
            foreach ($allowed_settings as $setting => $validation) {
                if (isset($raw_settings[$setting]) && !empty($raw_settings[$setting])) {
                    $value = trim($raw_settings[$setting]);
                    
                    if ($setting === 'redirect_url') {
                        // Special handling for URL
                        $clean_url = esc_url_raw($value);
                        if (!empty($clean_url) && filter_var($clean_url, FILTER_VALIDATE_URL)) {
                            $settings[$setting] = $clean_url;
                        }
                    } elseif (is_array($validation)) {
                        // Validate against allowed values
                        if (in_array($value, $validation, true)) {
                            $settings[$setting] = $value;
                        }
                    } else {
                        $settings[$setting] = sanitize_text_field($value);
                    }
                }
            }
        }
        
        // Build shortcode safely with proper escaping
        $shortcode_atts = array();
        foreach ($settings as $key => $value) {
            if (!empty($value)) {
                // Double escape for safety in HTML context
                $escaped_value = esc_attr($value);
                $shortcode_atts[] = $key . '="' . $escaped_value . '"';
            }
        }
        
        $shortcode = '[quiz';
        if (!empty($shortcode_atts)) {
            $shortcode .= ' ' . implode(' ', $shortcode_atts);
        }
        $shortcode .= ']';
        
        // Generate clean JSON with consistent formatting
        $json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json_string = json_encode($questions, $json_flags);
        
        // Add extra protection against WordPress mangling
        // Escape problematic characters that WordPress might interpret
        $json_string = str_replace(array('<', '>'), array('&lt;', '&gt;'), $json_string);
        
        $shortcode .= "\n" . $json_string;
        $shortcode .= "\n" . '[/quiz]';
        
        AlvoBotPro::debug_log('quiz-builder', 'AJAX Preview - Generated shortcode: ' . $shortcode);
        
        // Generate preview HTML
        echo '<html><head>';
        echo '<link rel="stylesheet" href="' . ALVOBOT_QUIZ_URL . 'assets/css/quiz-frontend.css">';
        echo '</head><body style="padding: 20px;">';
        
        // Process shortcode
        $result = do_shortcode($shortcode);
        echo $result;
        
        echo '</body></html>';
        
        wp_die();
    }
}