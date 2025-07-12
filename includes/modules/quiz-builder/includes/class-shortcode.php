<?php
/**
 * Shortcode handler class
 *
 * @package Alvobot_Quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the [quiz] shortcode functionality
 * 
 * This class processes the [quiz] shortcode, parses JSON content,
 * validates questions, and generates the quiz HTML output.
 * Features anti-fragile JSON parsing to handle WordPress editor issues.
 *
 * @since 1.0.0
 */
class Alvobot_Quiz_Shortcode {
    
    /**
     * Assets handler instance
     *
     * @var Alvobot_Quiz_Assets
     */
    private $assets;
    
    /**
     * @var string Conteúdo do quiz atual sendo renderizado
     */
    private $quiz_content = '';

    /**
     * @var bool Flag para controle de debug
     */
    private $debug = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->assets = new Alvobot_Quiz_Assets();
        add_shortcode('quiz', array($this, 'render_quiz_content_shortcode'));
        
        // Add filter to preserve content structure around shortcodes
        add_filter('no_texturize_shortcodes', array($this, 'preserve_quiz_shortcode'));
        
        // Add high priority filter to ensure proper content formatting
        add_filter('the_content', array($this, 'ensure_content_formatting'), 1);
    }
    
    /**
     * Log debug messages if WP_DEBUG is enabled
     *
     * @param string $message Message to log
     * @param mixed $data Optional data to log
     */
    private function log_debug($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AlvoBotPro::debug_log('quiz-builder', '[AlvoBot Quiz] ' . $message . ($data !== null ? ' ' . print_r($data, true) : ''));
        }
    }
    
    /**
     * Convert hex color to RGB array
     *
     * @param string $hex Hex color code
     * @return array RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Calculate relative luminance of a color
     *
     * @param array $rgb RGB color array
     * @return float Relative luminance
     */
    private function get_relative_luminance($rgb) {
        $rsRGB = $rgb['r'] / 255;
        $gsRGB = $rgb['g'] / 255;
        $bsRGB = $rgb['b'] / 255;
        
        $r = ($rsRGB <= 0.03928) ? $rsRGB / 12.92 : pow(($rsRGB + 0.055) / 1.055, 2.4);
        $g = ($gsRGB <= 0.03928) ? $gsRGB / 12.92 : pow(($gsRGB + 0.055) / 1.055, 2.4);
        $b = ($bsRGB <= 0.03928) ? $bsRGB / 12.92 : pow(($bsRGB + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
    
    /**
     * Calculate contrast ratio between two colors
     *
     * @param string $color1 First color (hex)
     * @param string $color2 Second color (hex)
     * @return float Contrast ratio
     */
    private function get_contrast_ratio($color1, $color2) {
        $rgb1 = $this->hex_to_rgb($color1);
        $rgb2 = $this->hex_to_rgb($color2);
        
        $l1 = $this->get_relative_luminance($rgb1);
        $l2 = $this->get_relative_luminance($rgb2);
        
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        
        return ($lighter + 0.05) / ($darker + 0.05);
    }
    
    /**
     * Get a contrasting color for text based on background
     *
     * @param string $bg_color Background color (hex)
     * @param string $text_color Original text color (hex)
     * @return string Adjusted text color for proper contrast
     */
    private function ensure_text_contrast($bg_color, $text_color) {
        // Handle transparent background
        if (stripos($bg_color, 'transparent') !== false || empty($bg_color)) {
            // For transparent background, check contrast against white (assuming most backgrounds are light)
            $bg_color = '#ffffff';
        }
        
        // Calculate current contrast ratio
        $contrast = $this->get_contrast_ratio($bg_color, $text_color);
        
        // WCAG AA standard requires 4.5:1 for normal text
        if ($contrast >= 4.5) {
            return $text_color;
        }
        
        // Try to adjust the text color
        $bg_luminance = $this->get_relative_luminance($this->hex_to_rgb($bg_color));
        
        // If background is light, use dark text
        if ($bg_luminance > 0.5) {
            // Try darkening the text color
            $rgb = $this->hex_to_rgb($text_color);
            $factor = 0.8;
            while ($contrast < 4.5 && $factor > 0.1) {
                $test_color = sprintf('#%02x%02x%02x', 
                    $rgb['r'] * $factor,
                    $rgb['g'] * $factor,
                    $rgb['b'] * $factor
                );
                $contrast = $this->get_contrast_ratio($bg_color, $test_color);
                if ($contrast >= 4.5) {
                    return $test_color;
                }
                $factor -= 0.1;
            }
            // If still not enough contrast, use black
            return '#000000';
        } else {
            // Try lightening the text color
            $rgb = $this->hex_to_rgb($text_color);
            $factor = 1.2;
            while ($contrast < 4.5 && $factor < 3.0) {
                $test_color = sprintf('#%02x%02x%02x', 
                    min(255, $rgb['r'] * $factor),
                    min(255, $rgb['g'] * $factor),
                    min(255, $rgb['b'] * $factor)
                );
                $contrast = $this->get_contrast_ratio($bg_color, $test_color);
                if ($contrast >= 4.5) {
                    return $test_color;
                }
                $factor += 0.2;
            }
            // If still not enough contrast, use white
            return '#ffffff';
        }
    }
    
    /**
     * Render the [quiz] shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Content between shortcode tags
     * @param string $tag Shortcode tag
     * @return string HTML output
     */
    public function render_quiz_content_shortcode($atts, $content = '', $tag = '') {
        // Default attributes
        $default_atts = array(
            'redirect_url' => '', 
            'show_score' => 'true',
            'style' => 'default',
            'randomize' => 'false',
            'show_progress' => 'true',
            'url_mode' => 'suffix', // Recarregar página (compatível com AdSense) - padrão
            'thank_you_message' => __('Thank you for completing this quiz!', 'alvobot-quiz')
        );
        
        $atts = shortcode_atts($default_atts, $atts, $tag);

        // Check if content is empty
        if (empty($content)) {
            $this->log_debug('No quiz content found between shortcode tags');
            return '<div class="quiz-error">' . __('Error: No quiz content found in the shortcode.', 'alvobot-quiz') . '</div>';
        }

        // Store the quiz content including shortcode tags
        $atts_string = '';
        foreach ($atts as $key => $value) {
            if ($value !== '' && $key !== 'quiz_data') {
                $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }
        $this->quiz_content = '[quiz' . $atts_string . ']' . $content . '[/quiz]';
        $this->log_debug('Stored complete quiz content:', $this->quiz_content);

        AlvoBotPro::debug_log('quiz-builder', 'Shortcode render - URL Mode: ' . (isset($atts['url_mode']) ? $atts['url_mode'] : 'default'));
        
        // Enqueue assets
        $this->assets->enqueue_frontend_assets($atts);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                AlvoBotPro::debug_log('quiz-builder', 'Raw shortcode content: ' . var_export($content, true));
                AlvoBotPro::debug_log('quiz-builder', 'Raw shortcode content length: ' . strlen($content));
            }
        }
        
        // Clean up WordPress editor formatting and fix quotes
        $content = preg_replace('/<\/?(?:div|p|span|br)[^>]*>/i', '', $content);
        $content = preg_replace('/\n\s*\n/m', '\n', $content);
        
        // Fix special quotes that might have been introduced during translation
        $content = str_replace(['«', '»', '“', '”', '‟', '„'], '"', $content);
        
        // Debug after cleanup
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                AlvoBotPro::debug_log('quiz-builder', 'Content after initial cleanup: ' . var_export($content, true));
                AlvoBotPro::debug_log('quiz-builder', 'Content after cleanup length: ' . strlen($content));
            }
        }
        
        // Decode JSON content
        $questions = $this->decode_json_safely($content);
        
        // Validate JSON
        if (empty($questions) || !is_array($questions)) {
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    AlvoBotPro::debug_log('quiz-builder', 'AJAX Preview - Content received: ' . var_export($content, true));
                    AlvoBotPro::debug_log('quiz-builder', 'AJAX Preview - Decoded questions: ' . var_export($questions, true));
                }
            }
            return '<div class="quiz-error">' . __('Error: Invalid JSON in shortcode content. Please check your JSON syntax.', 'alvobot-quiz') . '</div>';
        }
        
        return $this->generate_quiz_html($questions, $atts);
    }
    
    /**
     * Safely decode JSON with multiple fallback strategies
     * This method is designed to be "anti-fragile" against WordPress editor mangling
     *
     * @param string $json_string JSON string to decode
     * @return array|null Decoded array or null on failure
     */
    private function decode_json_safely($json_string) {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            AlvoBotPro::debug_log('quiz-builder', 'decode_json_safely - Original content: ' . var_export($json_string, true));
        }
        
        // STEP 0: Remove UTF-8 Byte Order Mark (BOM) if present
        $json_string = str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $json_string);
        
        // Decode HTML entities
        $json_string = html_entity_decode($json_string, ENT_QUOTES, 'UTF-8');
        
        // Strip all HTML tags
        $json_string = wp_strip_all_tags($json_string);
        
        // STEP 1: Normalize line endings - convert all to \n
        $json_string = str_replace(["\r\n", "\r"], "\n", $json_string);
        
        // STEP 2: Remove control characters except \n
        $json_string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json_string);
        
        // STEP 3: Clean up excessive line breaks
        $json_string = preg_replace('/\n\s*\n+/', '\n', $json_string);
        
        // STEP 4: Replace smart quotes and special characters (comprehensive list)
        $replacements = array(
            // Unicode smart quotes (using hex encoding for safety)
            "\xE2\x80\x9C" => '"', // left double quote
            "\xE2\x80\x9D" => '"', // right double quote  
            "\xE2\x80\x98" => "'", // left single quote
            "\xE2\x80\x99" => "'", // right single quote
            "\xE2\x80\x9A" => "'", // single low-9 quotation mark
            "\xE2\x80\x9B" => "'", // single high-reversed-9 quotation mark
            "\xE2\x80\x9E" => '"', // double low-9 quotation mark
            "\xE2\x80\x9F" => '"', // double high-reversed-9 quotation mark
            // Unicode dashes
            "\xE2\x80\x93" => '-', // en dash
            "\xE2\x80\x94" => '-', // em dash
            "\xE2\x80\x95" => '-', // horizontal bar
            // Unicode spaces
            "\xC2\xA0" => ' ',     // non-breaking space
            "\xE2\x80\x80" => ' ', // en quad
            "\xE2\x80\x81" => ' ', // em quad
            "\xE2\x80\x82" => ' ', // en space
            "\xE2\x80\x83" => ' ', // em space
            "\xE2\x80\x84" => ' ', // three-per-em space
            "\xE2\x80\x85" => ' ', // four-per-em space
            "\xE2\x80\x86" => ' ', // six-per-em space
            "\xE2\x80\x87" => ' ', // figure space
            "\xE2\x80\x88" => ' ', // punctuation space
            "\xE2\x80\x89" => ' ', // thin space
            "\xE2\x80\x8A" => ' ', // hair space
            "\xE2\x80\x8B" => '',  // zero width space
            "\xE2\x80\x8C" => '',  // zero width non-joiner
            "\xE2\x80\x8D" => '',  // zero width joiner
            // HTML entities
            "&nbsp;" => ' ',       // HTML non-breaking space
            "&quot;" => '"',       // HTML quote entity
            "&apos;" => "'",       // HTML apostrophe
            "&amp;" => "&",        // HTML ampersand
            "&lt;" => "<",         // HTML less than
            "&gt;" => ">",         // HTML greater than
            // Other problematic characters (using hex for compatibility)
            "`" => '"',            // grave accent to quote
            "\xC2\xB4" => "'",     // acute accent to quote (hex encoded)
            "\xE2\x80\x9E" => '"', // double low-9 quotation mark (repeated for safety)
        );
        $json_string = str_replace(array_keys($replacements), array_values($replacements), $json_string);
        
        // STEP 5: Trim whitespace
        $json_string = trim($json_string);
        
        // Debug cleaned string
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            AlvoBotPro::debug_log('quiz-builder', 'decode_json_safely - After cleanup: ' . var_export($json_string, true));
            AlvoBotPro::debug_log('quiz-builder', 'decode_json_safely - String length: ' . strlen($json_string));
        }
        
        // First decode attempt
        $result = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        
        // Log error details for debugging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            AlvoBotPro::debug_log('quiz-builder', 'decode_json_safely - First attempt failed: ' . json_last_error_msg());
            
            // Show problematic characters
            $visible_chars = '';
            for ($i = 0; $i < strlen($json_string); $i++) {
                $char = $json_string[$i];
                $ord = ord($char);
                if ($ord < 32 || $ord > 126) {
                    $visible_chars .= '\\x' . sprintf('%02X', $ord);
                } else {
                    $visible_chars .= $char;
                }
            }
            AlvoBotPro::debug_log('quiz-builder', 'decode_json_safely - Visible chars: ' . substr($visible_chars, 0, 500));
        }
        
        // Second attempt: remove all line breaks
        $json_string = str_replace(["\n", "\r"], '', $json_string);
        $result = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        
        // Final attempt: remove all non-printable characters
        $json_string = preg_replace('/[[:cntrl:]]/', '', $json_string);
        $json_string = preg_replace('/[^[:print:]]/', '', $json_string);
        
        $result = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        
        // If all fails, return null
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            AlvoBotPro::debug_log('quiz-builder', 'JSON decode final error: ' . json_last_error_msg());
        }
        
        return null;
    }
    
    /**
     * Validate questions array structure
     *
     * @param array $questions Questions array to validate
     * @return string|null Error message or null if valid
     */
    private function validate_questions($questions) {
        if (!is_array($questions)) {
            return 'Questions data must be an array.';
        }
        
        foreach ($questions as $index => $question) {
            // Check required fields
            if (!isset($question['question']) || !isset($question['answers']) || !is_array($question['answers'])) {
                return "Question #" . ($index + 1) . " is missing required fields (question or answers).";
            }
            
            // Validate answer count
            if (count($question['answers']) < 1) {
                return "Question #" . ($index + 1) . " must have at least one answer option.";
            }
            
            // Validate correct answer index if present
            if (isset($question['correct']) && 
                (
                    !is_numeric($question['correct']) || 
                    $question['correct'] < 0 || 
                    $question['correct'] >= count($question['answers'])
                )) {
                return "Question #" . ($index + 1) . " has an invalid 'correct' index.";
            }
        }
        
        return null; // No errors
    }
    
    /**
     * Generate quiz HTML
     *
     * @param array $questions Questions array
     * @param array $atts Shortcode attributes
     * @return string Generated HTML
     */
    private function generate_quiz_html($questions, $atts) {
        // Validate questions structure
        $error = $this->validate_questions($questions);
        if ($error !== null) {
            return '<div class="quiz-error">Error: ' . esc_html($error) . '</div>';
        }
        
        // Generate unique quiz ID before any randomization
        $quiz_id = md5(serialize($questions));
        
        // Determine current quiz step from URL parameters
        $quiz_step = $this->get_current_quiz_step();

        $current_quiz_id = sanitize_text_field(isset($_GET['quiz_id']) ? $_GET['quiz_id'] : '');
        $action = sanitize_text_field(isset($_GET['action']) ? $_GET['action'] : '');
        $answer_string = sanitize_text_field(isset($_GET['answers']) ? $_GET['answers'] : '');
        
        // Parse answers
        $answers = !empty($answer_string) ? explode(',', $answer_string) : array();
        
        // Reset if different quiz
        if ($current_quiz_id !== $quiz_id) {
            $quiz_step = 0;
            $answers = array();
        }
        
        // Randomize questions if needed with consistent seed
        if ($atts['randomize'] === 'true') {
            // Use quiz ID as seed for consistent randomization
            // This ensures the same random order throughout the quiz session
            $seed = crc32($quiz_id);
            mt_srand($seed);
            
            // Create array of indices
            $indices = range(0, count($questions) - 1);
            
            // Shuffle indices using seeded random (Fisher-Yates algorithm)
            $count = count($indices);
            for ($i = $count - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $temp = $indices[$i];
                $indices[$i] = $indices[$j];
                $indices[$j] = $temp;
            }
            
            // Reorder questions based on shuffled indices
            $shuffled_questions = array();
            foreach ($indices as $index) {
                $shuffled_questions[] = $questions[$index];
            }
            $questions = $shuffled_questions;
            
            // Reset random seed to avoid affecting other random operations
            mt_srand();
        }
        
        // Process current step
        // Se quiz_display_step está definido (vem do JavaScript), usa ele diretamente
        if (isset($_GET['quiz_display_step'])) {
            $quiz_step = max(0, intval($_GET['quiz_display_step']));
            
            // Se há uma resposta atual sendo processada, salva ela
            if (isset($_GET['current_answer']) && $quiz_step > 0) {
                $current_answer = sanitize_text_field($_GET['current_answer']);
                $previous_step = $quiz_step - 1; // A resposta é para a pergunta anterior
                $answers[$previous_step] = $current_answer;
            }
        } else {
            // Lógica tradicional para compatibilidade (apenas avanço)
            if ($action === 'next' && isset($_GET['current_answer'])) {
                $current_answer = sanitize_text_field($_GET['current_answer']);
                $answers[$quiz_step] = $current_answer;
                $quiz_step++;
            }
        }
        
        // Convert answers to string
        $answer_string = implode(',', $answers);
        
        // Detect quiz mode
        $quiz_mode = 'form';
        foreach ($questions as $question) {
            if (isset($question['correct'])) {
                $quiz_mode = 'quiz';
                break;
            }
        }
        
        // Handle quiz completion
        if ($quiz_step >= count($questions)) {
            return $this->generate_results_html($questions, $answers, $atts, $quiz_mode, $quiz_id);
        }
        
        // Generate current question HTML
        return $this->generate_question_html($questions, $quiz_step, $answers, $atts, $quiz_id, $answer_string);
    }
    
    /**
     * Generate results HTML
     *
     * @param array $questions Questions array
     * @param array $answers User answers
     * @param array $atts Shortcode attributes
     * @param string $quiz_mode Quiz or form mode
     * @param string $quiz_id Unique quiz ID
     * @return string Results HTML
     */
    private function generate_results_html($questions, $answers, $atts, $quiz_mode, $quiz_id) {
        $output = '<div class="wp-quiz-container wp-quiz-style-' . esc_attr($atts['style']) . '">';
        
        if ($quiz_mode === 'quiz' && $atts['show_score'] === 'true') {
            // Calculate score
            $score = 0;
            $total_scored = 0;
            
            foreach ($questions as $index => $question) {
                if (isset($question['correct']) && isset($answers[$index])) {
                    $total_scored++;
                    if ((int)$answers[$index] === (int)$question['correct']) {
                        $score++;
                    }
                }
            }
            
            $percentage = $total_scored > 0 ? round(($score / $total_scored) * 100) : 0;
            
            $output .= '<div class="quiz-results">';
            $output .= '<h3>' . __('Quiz Results', 'alvobot-quiz') . '</h3>';
            $output .= '<p>' . sprintf(
                __('Your score: %1$s/%2$s (%3$s%%)', 'alvobot-quiz'), 
                '<strong>' . $score . '</strong>', 
                $total_scored, 
                $percentage
            ) . '</p>';
            $output .= '</div>';
            
            // Redirect with score
            if (!empty($atts['redirect_url'])) {
                $adjusted_redirect_url = $this->adjust_redirect_url_for_pre_article($atts['redirect_url']);
                $redirect_url = add_query_arg(array(
                    'score' => $score,
                    'total' => $total_scored,
                    'percentage' => $percentage,
                    'quiz_id' => $quiz_id
                ), $adjusted_redirect_url);
                
                $output .= $this->generate_redirect_html($redirect_url);
            }
        } else {
            // Form mode
            $output .= '<div class="quiz-completed">';
            $output .= '<h3>' . __('Please wait...', 'alvobot-quiz') . '</h3>';
            $output .= '</div>';
            
            // Redirect with responses
            if (!empty($atts['redirect_url'])) {
                $redirect_params = array('quiz_id' => $quiz_id);
                
                if (!empty($answers)) {
                    $redirect_params['responses'] = implode(',', $answers);
                }
                
                $adjusted_redirect_url = $this->adjust_redirect_url_for_pre_article($atts['redirect_url']);
                $redirect_url = add_query_arg($redirect_params, $adjusted_redirect_url);
                $output .= $this->generate_redirect_html($redirect_url);
            }
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Generate redirect HTML
     *
     * @param string $redirect_url URL to redirect to
     * @return string Redirect HTML
     */
    private function generate_redirect_html($redirect_url) {
        $output = '<div class="quiz-redirect">';
        $output .= '<p>' . __('Redirecting...', 'alvobot-quiz') . '</p>';
        $output .= '<p><a href="' . esc_url($redirect_url) . '">' . 
                   __('Click here if you are not redirected automatically.', 'alvobot-quiz') . 
                   '</a></p>';
        $output .= '</div>';
        $output .= '<meta http-equiv="refresh" content="2;url=' . esc_url($redirect_url) . '">';
        return $output;
    }
    
    /**
     * Generate question HTML
     *
     * @param array $questions All questions
     * @param int $quiz_step Current step
     * @param array $answers User answers
     * @param array $atts Shortcode attributes
     * @param string $quiz_id Quiz ID
     * @param string $answer_string Serialized answers
     * @return string Question HTML
     */
    private function generate_question_html($questions, $quiz_step, $answers, $atts, $quiz_id, $answer_string) {
        $current_question = $questions[$quiz_step];
        $total_questions = count($questions);
        
        // Auto-advance é sempre ativo (única opção disponível)
        $auto_advance = 'true';
        
        // Obter URL canônica e preservar parâmetros originais
        $canonical_url = get_permalink(get_the_ID());
        
        // Verificar se estamos em uma página de pré-artigo
        if (get_query_var('alvobot_pre_article')) {
            // Se estamos em pré-artigo, usar a URL com prefixo /pre/
            $post_slug = get_post_field('post_name', get_the_ID());
            $canonical_url = home_url('/pre/' . $post_slug);
        }
        
        // Garantir que a URL não tenha sufixos aquiz-e (mas preserva números originais do slug)
        $canonical_url = preg_replace('/-aquiz-e\d+\/?$/', '', $canonical_url);
        $canonical_url = rtrim($canonical_url, '/');
        
        // Capturar parâmetros originais da primeira visita (exceto parâmetros do quiz)
        $original_params = $this->get_original_url_params();
        
        // Start container com URL base canônica e parâmetros originais para o JS usar
        $output = '<div id="wp-quiz-' . esc_attr($quiz_id) . '" class="wp-quiz-container wp-quiz-style-' . 
                  esc_attr($atts['style']) . '" data-auto-advance="' . $auto_advance . '" data-base-url="' . esc_url($canonical_url) . '"';
        
        if (!empty($original_params)) {
            $output .= ' data-original-params="' . esc_attr(http_build_query($original_params)) . '"';
        }
        
        $output .= '>';
        
        // Progress bar
        if ($atts['show_progress'] === 'true') {
            $progress = ($quiz_step / $total_questions) * 100;
            $output .= '<div class="quiz-progress-bar">';
            $output .= '<div class="progress-fill" style="width: ' . $progress . '%"></div>';
            $output .= '<span class="progress-text">' . 
                       ($quiz_step + 1) . '/' . $total_questions . 
                       '</span>';
            $output .= '</div>';
        }
        
        // DEBUG: Log URL mode decision
        AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] URL Mode: ' . $atts['url_mode'] . ' | Quiz Step: ' . $quiz_step . ' | Total Questions: ' . count($questions));
        
        // Form (only for params mode, not for suffix mode)
        if ($atts['url_mode'] !== 'suffix') {
            AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] Creating FORM because url_mode is: ' . $atts['url_mode']);
            $output .= '<form method="get" class="quiz-form">';
            $output .= '<input type="hidden" name="quiz_id" value="' . esc_attr($quiz_id) . '">';
            $output .= '<input type="hidden" name="quiz_step" value="' . esc_attr($quiz_step) . '">';
            $output .= '<input type="hidden" name="answers" value="' . esc_attr($answer_string) . '">';
        } else {
            AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] SKIPPING form creation - using suffix mode with hyperlinks');
        }
        
        // Question
        // Extract question styles if available
        $question_container_styles = '';
        $question_text_styles = '';
        if (isset($current_question['styles'])) {
            $styles = $current_question['styles'];
            $container_style_parts = array();
            $text_style_parts = array();
            
            // Background color goes on container
            if (isset($styles['backgroundColor'])) {
                $container_style_parts[] = 'background-color: ' . esc_attr($styles['backgroundColor']) . ' !important';
            }
            
            // Text styles go on the h3 element
            if (isset($styles['color'])) {
                $text_style_parts[] = 'color: ' . esc_attr($styles['color']) . ' !important';
            }
            if (isset($styles['fontSize'])) {
                $text_style_parts[] = 'font-size: ' . esc_attr($styles['fontSize']) . ' !important';
            }
            if (isset($styles['fontWeight'])) {
                $text_style_parts[] = 'font-weight: ' . esc_attr($styles['fontWeight']) . ' !important';
            }
            
            if (!empty($container_style_parts)) {
                $question_container_styles = ' style="' . implode('; ', $container_style_parts) . '"';
            }
            if (!empty($text_style_parts)) {
                $question_text_styles = ' style="' . implode('; ', $text_style_parts) . '"';
            }
        }
        
        $output .= '<div class="quiz-question-container"' . $question_container_styles . '>';
        $output .= '<h3 class="quiz-question"' . $question_text_styles . '>' . esc_html($current_question['question']) . '</h3>';
        
        // Answer options
        $output .= '<div class="quiz-answers">';
        
        foreach ($current_question['answers'] as $index => $answer) {
            $checked = isset($answers[$quiz_step]) && $answers[$quiz_step] == $index ? 'checked' : '';
            
            // Extract answer text (handle both string and object formats)
            $answer_text = is_array($answer) ? $answer['text'] : $answer;
            
            // Extract answer styles if available
            $answer_styles = '';
            if (is_array($answer) && isset($answer['styles'])) {
                $styles = $answer['styles'];
                $style_parts = array();
                
                if (isset($styles['backgroundColor'])) {
                    $style_parts[] = 'background-color: ' . esc_attr($styles['backgroundColor']) . ' !important';
                }
                if (isset($styles['color'])) {
                    $style_parts[] = 'color: ' . esc_attr($styles['color']) . ' !important';
                }
                if (isset($styles['fontSize'])) {
                    $style_parts[] = 'font-size: ' . esc_attr($styles['fontSize']) . ' !important';
                }
                if (isset($styles['fontWeight'])) {
                    $style_parts[] = 'font-weight: ' . esc_attr($styles['fontWeight']) . ' !important';
                }
                
                if (!empty($style_parts)) {
                    $answer_styles = ' style="' . implode('; ', $style_parts) . '"';
                }
            }
            
            // Extract text styles separately for the span
            $answer_text_styles = '';
            $bg_color = '#ffffff'; // Default background
            $text_color = '#000000'; // Default text color
            
            if (is_array($answer) && isset($answer['styles'])) {
                $styles = $answer['styles'];
                $text_style_parts = array();
                
                // Get background and text colors
                if (isset($styles['backgroundColor'])) {
                    $bg_color = $styles['backgroundColor'];
                }
                if (isset($styles['color'])) {
                    $text_color = $styles['color'];
                }
                
                // NEVER allow transparent text
                if (stripos($text_color, 'transparent') !== false || empty($text_color)) {
                    $text_color = '#000000'; // Force to black if transparent
                    $this->log_debug('Text color was transparent, forcing to black');
                }
                
                // If background is transparent, ensure text has good contrast against white/common backgrounds
                if (stripos($bg_color, 'transparent') !== false) {
                    // For transparent background, ensure text is dark enough to be visible on light backgrounds
                    $rgb = $this->hex_to_rgb($text_color);
                    $luminance = $this->get_relative_luminance($rgb);
                    
                    // If text is too light (luminance > 0.5), make it darker
                    if ($luminance > 0.5) {
                        $text_color = '#000000'; // Force to black for transparent backgrounds
                        $this->log_debug('Text too light for transparent background, forcing to black');
                    }
                }
                
                // Check contrast ratio
                $bg_for_contrast = (stripos($bg_color, 'transparent') !== false) ? '#ffffff' : $bg_color;
                $contrast_ratio = $this->get_contrast_ratio($bg_for_contrast, $text_color);
                
                // Log if contrast is too low
                if ($contrast_ratio < 4.5) {
                    $this->log_debug('Low contrast detected: ' . $text_color . ' on ' . $bg_color . ' (ratio: ' . round($contrast_ratio, 2) . ')');
                }
                
                // Apply the color
                $text_style_parts[] = 'color: ' . esc_attr($text_color) . ' !important';
                
                if (isset($styles['fontSize'])) {
                    $text_style_parts[] = 'font-size: ' . esc_attr($styles['fontSize']) . ' !important';
                }
                if (isset($styles['fontWeight'])) {
                    $text_style_parts[] = 'font-weight: ' . esc_attr($styles['fontWeight']) . ' !important';
                }
                
                if (!empty($text_style_parts)) {
                    $answer_text_styles = ' style="' . implode('; ', $text_style_parts) . '"';
                }
            }
            
            // Generate URL for this answer option
            $next_step = $quiz_step + 1;
            $is_last_question = ($quiz_step >= count($questions) - 1);
            
            if ($is_last_question && !empty($atts['redirect_url'])) {
                // Para a última pergunta, gerar URL diretamente para o redirect
                $answer_url = $this->generate_final_answer_url($index, $quiz_id, $answer_string, $atts, $questions, $answers);
            } else {
                // Para outras perguntas, gerar URL normal
                $answer_url = $this->generate_answer_url($canonical_url, $next_step, $index, $quiz_id, $answer_string, $atts);
            }
            
            // Generate clickable answer with hyperlink
            if ($atts['url_mode'] === 'suffix' && !empty($answer_url)) {
                AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] Creating HYPERLINK for answer: ' . $answer_text . ' | URL: ' . $answer_url);
                $output .= '<a href="' . esc_url($answer_url) . '" class="quiz-answer-link"' . $answer_styles . '>';
                $output .= '<span class="answer-text"' . $answer_text_styles . '>' . esc_html($answer_text) . '</span>';
                $output .= '</a>';
            } else {
                AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] Creating RADIO BUTTON for answer: ' . $answer_text . ' | url_mode: ' . $atts['url_mode'] . ' | answer_url: ' . $answer_url);
                // Fallback: traditional radio button format
                $output .= '<label class="quiz-answer"' . $answer_styles . '>';
                $output .= '<input type="radio" name="current_answer" value="' . esc_attr($index) . '" ' . $checked . ' required>';
                $output .= '<span class="answer-text"' . $answer_text_styles . '>' . esc_html($answer_text) . '</span>';
                $output .= '</label>';
            }
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        // Hidden field para navegação automática (only for params mode)
        if ($atts['url_mode'] !== 'suffix') {
            AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] Closing FORM tag');
            $output .= '<input type="hidden" name="action" value="next">';
            $output .= '</form>';
        } else {
            AlvoBotPro::debug_log('quiz-builder', '[ALVOBOT DEBUG] NO form to close - suffix mode');
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Get current quiz step from URL parameters
     * Consolidated logic for determining quiz step from various URL sources
     *
     * @return int Current quiz step (zero-based)
     */
    private function get_current_quiz_step() {
        // Priority order:
        // 1. quiz_display_step (set by JavaScript - 0-based)
        // 2. quiz_step_suffix (from URL rewrite)  
        // 3. quiz_step (legacy parameter)
        // 4. q (legacy parameter)
        
        if (isset($_GET['quiz_display_step'])) {
            // quiz_display_step já vem 0-based do JavaScript
            return max(0, intval($_GET['quiz_display_step']));
        }
        
        $quiz_step_suffix = get_query_var('quiz_step_suffix');
        if (!empty($quiz_step_suffix)) {
            return max(0, intval($quiz_step_suffix) - 1);
        }
        
        if (isset($_GET['quiz_step'])) {
            return max(0, intval($_GET['quiz_step']) - 1);
        }
        
        if (isset($_GET['q'])) {
            return max(0, intval($_GET['q']) - 1);
        }
        
        return 0;
    }
    
    /**
     * Generate URL for an answer option
     *
     * @param string $canonical_url Base URL
     * @param int $next_step Next step number
     * @param int $answer_index Selected answer index
     * @param string $quiz_id Quiz ID
     * @param string $answer_string Current answers string
     * @param array $atts Shortcode attributes
     * @return string Generated URL
     */
    private function generate_answer_url($canonical_url, $next_step, $answer_index, $quiz_id, $answer_string, $atts) {
        // Clean base URL (remove only aquiz-e suffixes, preserve original slug numbers)
        $base_url = preg_replace('/-aquiz-e\d+\/?$/', '', $canonical_url);
        $base_url = rtrim($base_url, '/');
        
        // Build new URL
        $new_url = $base_url;
        
        // Add suffix if not first question
        // $next_step é 0-based, então:
        // Pergunta 1 ($next_step=0): sem sufixo
        // Pergunta 2 ($next_step=1): -aquiz-e1
        // Pergunta 3 ($next_step=2): -aquiz-e2
        if ($next_step > 0) {
            $new_url .= '-aquiz-e' . $next_step;
        }
        
        // Add trailing slash
        $new_url .= '/';
        
        // Get original parameters to preserve them
        $original_params = $this->get_original_url_params();
        
        // Add quiz parameters
        $params = array_merge($original_params, array(
            'quiz_display_step' => $next_step,
            'quiz_id' => $quiz_id,
            'action' => 'next',
            'current_answer' => $answer_index
        ));
        
        // Add existing answers
        if (!empty($answer_string)) {
            $params['answers'] = $answer_string;
        }
        
        return add_query_arg($params, $new_url);
    }
    
    /**
     * Generate final redirect URL for the last question
     *
     * @param int $answer_index Selected answer index
     * @param string $quiz_id Quiz ID
     * @param string $answer_string Current answers string
     * @param array $atts Shortcode attributes
     * @param array $questions All questions
     * @param array $answers Current answers
     * @return string Generated redirect URL
     */
    private function generate_final_answer_url($answer_index, $quiz_id, $answer_string, $atts, $questions, $answers) {
        // Adicionar a resposta atual ao array de respostas
        $final_answers = !empty($answer_string) ? explode(',', $answer_string) : array();
        $final_answers[] = $answer_index;
        
        // Detectar modo do quiz
        $quiz_mode = 'form';
        foreach ($questions as $question) {
            if (isset($question['correct'])) {
                $quiz_mode = 'quiz';
                break;
            }
        }
        
        // Preparar parâmetros para a URL de redirect, incluindo parâmetros originais
        $original_params = $this->get_original_url_params();
        $redirect_params = array_merge($original_params, array('quiz_id' => $quiz_id));
        
        if ($quiz_mode === 'quiz' && $atts['show_score'] === 'true') {
            // Calcular pontuação
            $score = 0;
            $total_scored = 0;
            
            foreach ($questions as $index => $question) {
                if (isset($question['correct']) && isset($final_answers[$index])) {
                    $total_scored++;
                    if ((int)$final_answers[$index] === (int)$question['correct']) {
                        $score++;
                    }
                }
            }
            
            $percentage = $total_scored > 0 ? round(($score / $total_scored) * 100) : 0;
            
            $redirect_params['score'] = $score;
            $redirect_params['total'] = $total_scored;
            $redirect_params['percentage'] = $percentage;
        } else {
            // Modo formulário
            if (!empty($final_answers)) {
                $redirect_params['responses'] = implode(',', $final_answers);
            }
        }
        
        $adjusted_redirect_url = $this->adjust_redirect_url_for_pre_article($atts['redirect_url']);
        return add_query_arg($redirect_params, $adjusted_redirect_url);
    }
    
    /**
     * Get original URL parameters (excluding quiz-specific parameters)
     * 
     * @return array Original URL parameters
     */
    private function get_original_url_params() {
        $original_params = $_GET;
        
        // Remove parâmetros específicos do quiz
        $quiz_params = array(
            'quiz_id',
            'quiz_step', 
            'quiz_display_step',
            'quiz_step_suffix',
            'action',
            'current_answer',
            'answers',
            'q'
        );
        
        foreach ($quiz_params as $param) {
            unset($original_params[$param]);
        }
        
        return $original_params;
    }
    
    /**
     * Preserve quiz shortcode from texturization
     * 
     * @param array $shortcodes List of shortcodes to preserve
     * @return array Modified list
     */
    public function preserve_quiz_shortcode($shortcodes) {
        $shortcodes[] = 'quiz';
        return $shortcodes;
    }
    
    /**
     * Ensure proper content formatting around quiz shortcodes
     * 
     * @param string $content The post content
     * @return string Formatted content
     */
    public function ensure_content_formatting($content) {
        // Only process if content has quiz shortcode
        if (!has_shortcode($content, 'quiz')) {
            return $content;
        }
        
        // Temporarily store quiz shortcodes to prevent wpautop interference
        $quiz_pattern = '/(\[quiz[^\]]*\].*?\[\/quiz\])/s';
        $placeholder_prefix = '<!-- QUIZ_PLACEHOLDER_';
        $placeholder_suffix = ' -->';
        $quiz_blocks = array();
        $index = 0;
        
        // Replace quiz shortcodes with placeholders
        $content = preg_replace_callback($quiz_pattern, function($matches) use (&$quiz_blocks, &$index, $placeholder_prefix, $placeholder_suffix) {
            $quiz_blocks[$index] = $matches[0];
            $placeholder = $placeholder_prefix . $index . $placeholder_suffix;
            $index++;
            return "\n\n" . $placeholder . "\n\n"; // Add line breaks to ensure proper paragraph separation
        }, $content);
        
        // Apply wpautop to content without quiz shortcodes
        $content = wpautop($content);
        
        // Restore quiz shortcodes
        foreach ($quiz_blocks as $i => $quiz_block) {
            $placeholder = $placeholder_prefix . $i . $placeholder_suffix;
            // Ensure quiz is in its own block, not wrapped in <p> tags
            $content = preg_replace('/<p>\s*' . preg_quote($placeholder, '/') . '\s*<\/p>/', $quiz_block, $content);
            $content = str_replace($placeholder, $quiz_block, $content);
        }
        
        // Fix any remaining formatting issues
        $content = $this->fix_post_quiz_formatting($content);
        
        return $content;
    }
    
    /**
     * Fix formatting issues after quiz shortcode
     * 
     * @param string $content The content to fix
     * @return string Fixed content
     */
    private function fix_post_quiz_formatting($content) {
        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        
        // Fix double br tags
        $content = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/', '<br />', $content);
        
        // Ensure content after quiz container has proper paragraph tags
        $content = preg_replace('/(<\/div>\s*<!--\s*\.wp-quiz-container\s*-->)\s*([^<\s])/', '$1<p>$2', $content);
        
        // Fix misplaced br tags at the beginning of paragraphs
        $content = preg_replace('/<p>\s*<br\s*\/?>\s*/', '<p>', $content);
        
        // Fix br tags at the end of paragraphs
        $content = preg_replace('/\s*<br\s*\/?>\s*<\/p>/', '</p>', $content);
        
        return $content;
    }

    /**
     * Adjust redirect URL for pre-article context
     *
     * @param string $redirect_url Original redirect URL
     * @return string Adjusted redirect URL
     */
    private function adjust_redirect_url_for_pre_article($redirect_url) {
        if (empty($redirect_url)) {
            return $redirect_url;
        }

        // Detectar se estamos em pré-artigo via múltiplas formas
        $is_pre_article = get_query_var('alvobot_pre_article') || 
                          (get_query_var('quiz_step_suffix') && 
                           get_query_var('pagename') && 
                           strpos(get_query_var('pagename'), 'pre/') === 0) ||
                          strpos($_SERVER['REQUEST_URI'], '/pre/') !== false;

        // Se não estamos em pré-artigo, retornar URL original
        if (!$is_pre_article) {
            return $redirect_url;
        }

        // Se já tem /pre/ no início, não modificar
        if (strpos($redirect_url, '/pre/') === 0) {
            return $redirect_url;
        }

        // Se é URL externa (http/https), não modificar
        if (strpos($redirect_url, 'http') === 0) {
            return $redirect_url;
        }

        // Se começa com /, adicionar /pre antes
        if (strpos($redirect_url, '/') === 0) {
            return '/pre' . $redirect_url;
        }

        // Se é slug relativo, adicionar /pre/
        return '/pre/' . $redirect_url;
    }
}