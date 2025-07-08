<?php
/**
 * Quiz Builder Admin Page Template
 *
 * @package AlvoBotPro
 * @subpackage Modules/QuizBuilder
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'builder';
?>

<div class="alvobot-admin-wrap">
    <div class="alvobot-admin-container">
        <div class="alvobot-admin-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Create interactive quizzes with unique URL navigation for monetization.', 'alvobot-pro'); ?></p>
        </div>
        
        <nav class="nav-tab-wrapper">
            <a href="?page=alvobot-quiz-builder&tab=builder" 
               class="nav-tab <?php echo $active_tab == 'builder' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Quiz Builder', 'alvobot-pro'); ?>
            </a>
            <a href="?page=alvobot-quiz-builder&tab=docs" 
               class="nav-tab <?php echo $active_tab == 'docs' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Documentation', 'alvobot-pro'); ?>
            </a>
        </nav>
        
        <div class="alvobot-card">
            <div class="alvobot-card-content quiz-tab-content">
        <?php
        switch ($active_tab) {
            case 'builder':
                include plugin_dir_path(__FILE__) . '../admin/views/view-builder.php';
                break;
            case 'docs':
                include plugin_dir_path(__FILE__) . '../admin/views/view-docs.php';
                break;
            default:
                include plugin_dir_path(__FILE__) . '../admin/views/view-builder.php';
        }
        ?>
            </div>
        </div>
    </div>
</div>