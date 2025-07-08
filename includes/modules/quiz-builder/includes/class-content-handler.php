<?php
/**
 * Content Handler for Quiz Builder
 * 
 * Handles proper content formatting before and after quiz shortcodes
 *
 * @package Alvobot_Quiz
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle content processing around quiz shortcodes
 */
class Alvobot_Quiz_Content_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into content processing with high priority
        add_filter('the_content', array($this, 'process_quiz_content'), 8);
        
        // Temporarily disable wpautop while processing quiz content
        add_filter('the_content', array($this, 'fix_content_after_quiz'), 11);
    }
    
    /**
     * Process content containing quiz shortcodes
     * 
     * @param string $content The post content
     * @return string Processed content
     */
    public function process_quiz_content($content) {
        // Check if content has quiz shortcode
        if (!has_shortcode($content, 'quiz')) {
            return $content;
        }
        
        // Store original content for debugging
        $original_content = $content;
        
        AlvoBotPro::debug_log('quiz-builder', 'Processing content with quiz shortcode');
        
        // Mark sections of content for preservation
        $content = $this->mark_content_sections($content);
        
        return $content;
    }
    
    /**
     * Fix content formatting after quiz shortcode processing
     * 
     * @param string $content The post content
     * @return string Fixed content
     */
    public function fix_content_after_quiz($content) {
        // Only process if we have quiz markers
        if (strpos($content, '<!-- quiz-processed -->') === false) {
            return $content;
        }
        
        AlvoBotPro::debug_log('quiz-builder', 'Fixing content formatting after quiz');
        
        // Split content by quiz sections
        $parts = preg_split('/<!-- quiz-processed -->/', $content);
        
        if (count($parts) > 1) {
            // Process each part separately
            foreach ($parts as $index => &$part) {
                // Skip the quiz section itself
                if (strpos($part, 'wp-quiz-container') !== false) {
                    continue;
                }
                
                // Apply proper paragraph formatting to content after quiz
                $part = $this->apply_proper_formatting($part);
            }
            
            // Rejoin parts
            $content = implode('', $parts);
        }
        
        // Remove our markers
        $content = str_replace('<!-- quiz-processed -->', '', $content);
        $content = str_replace('<!-- quiz-section-start -->', '', $content);
        $content = str_replace('<!-- quiz-section-end -->', '', $content);
        
        return $content;
    }
    
    /**
     * Mark content sections for preservation
     * 
     * @param string $content The content to mark
     * @return string Marked content
     */
    private function mark_content_sections($content) {
        // Find quiz shortcode positions
        $pattern = '/(\[quiz[^\]]*\].*?\[\/quiz\])/s';
        
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = 0;
            $marked_content = '';
            
            foreach ($matches[0] as $match) {
                $quiz_content = $match[0];
                $position = $match[1];
                
                // Add content before quiz
                if ($position > $offset) {
                    $before_quiz = substr($content, $offset, $position - $offset);
                    $marked_content .= $before_quiz;
                }
                
                // Add quiz with markers
                $marked_content .= '<!-- quiz-section-start -->';
                $marked_content .= $quiz_content;
                $marked_content .= '<!-- quiz-section-end --><!-- quiz-processed -->';
                
                $offset = $position + strlen($quiz_content);
            }
            
            // Add remaining content after last quiz
            if ($offset < strlen($content)) {
                $after_quiz = substr($content, $offset);
                $marked_content .= $after_quiz;
            }
            
            return $marked_content;
        }
        
        return $content;
    }
    
    /**
     * Apply proper formatting to content sections
     * 
     * @param string $content Content to format
     * @return string Formatted content
     */
    private function apply_proper_formatting($content) {
        // Skip if already has paragraph tags
        if (strpos($content, '<p>') !== false || strpos($content, '<p ') !== false) {
            return $content;
        }
        
        // Remove excessive whitespace but preserve intentional line breaks
        $content = preg_replace('/^\s+|\s+$/m', '', $content);
        
        // Skip empty content
        if (empty(trim($content))) {
            return $content;
        }
        
        // Apply WordPress paragraph formatting
        $content = wpautop($content);
        
        // Fix common issues with wpautop
        $content = $this->fix_wpautop_issues($content);
        
        return $content;
    }
    
    /**
     * Fix common wpautop issues
     * 
     * @param string $content Content to fix
     * @return string Fixed content
     */
    private function fix_wpautop_issues($content) {
        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        
        // Fix double line breaks
        $content = preg_replace('/<\/p>\s*<br\s*\/?>\s*<p>/', '</p><p>', $content);
        
        // Remove br tags right before closing p tags
        $content = preg_replace('/<br\s*\/?>\s*<\/p>/', '</p>', $content);
        
        // Fix nested p tags
        $content = preg_replace('/<p>(<p>|<p\s[^>]*>)/', '$1', $content);
        $content = preg_replace('/<\/p><\/p>/', '</p>', $content);
        
        return $content;
    }
}

// Initialize the content handler
new Alvobot_Quiz_Content_Handler();