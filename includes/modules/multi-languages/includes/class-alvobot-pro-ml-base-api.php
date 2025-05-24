<?php
/**
 * AlvoBot Pro Multi-Languages Base API Class
 * 
 * Provides common functionalities for Multi-Languages API handlers.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AlvoBotPro_MultiLanguages_Base_API
 * Base class for Multi-Languages API handlers.
 */
class AlvoBotPro_MultiLanguages_Base_API {

    /**
     * Common REST API namespace.
     * @var string
     */
    protected $namespace = 'alvobot-pro/v1';

    /** 
     * Maximum number of logs to store.
     * @var int 
     */
    protected const MAX_LOGS = 100;

    /**
     * Constructor.
     */
    public function __construct() {
        // Base constructor, can be used for common setup if needed in the future.
    }

    /**
     * Checks if the current user has permissions to perform write operations.
     * Typically checks for 'edit_posts' capability.
     * 
     * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
     */
    public function permissions_check() {
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden_context',
                __('Desculpe, você não tem permissão para executar esta ação.', 'alvobot-pro'),
                array('status' => rest_authorization_required_code())
            );
        }
        return true;
    }
    
    /**
     * Permission callback to allow public read access (for GET requests).
     * 
     * @return bool Always true.
     */
    public function public_permissions_check() {
        return true; 
    }


    /**
     * Logs an action related to the Multi-Languages module.
     * Limits the number of logs stored in the database.
     *
     * @param string $action  The action performed (e.g., 'create_translation', 'sync_categories').
     * @param string $status  Status of the action ('success', 'error', 'info').
     * @param string $message A descriptive message for the log entry.
     * @param array  $details Optional additional details to store with the log entry.
     */
    protected function log_action(string $action, string $status, string $message, array $details = []) {
        $logs = get_option('alvobot_multi_languages_logs', []);
        if (!is_array($logs)) {
            $logs = [];
        }
        
        // Adiciona novo log
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'action'    => $action,
            'status'    => $status,
            'message'   => $message,
            'details'   => $details
        ];
        
        // Limita o número de logs
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }
        
        update_option('alvobot_multi_languages_logs', $logs);
    }

    /**
     * Checks if the Polylang plugin and its core functions are active.
     * 
     * @return bool True if Polylang is active and key functions exist, false otherwise.
     */
    protected function is_polylang_active() {
        return function_exists('pll_languages_list') && function_exists('pll_get_post_language');
    }

    /**
     * Returns a WP_Error object if Polylang is not active.
     * 
     * This method should be called at the beginning of any API callback
     * that depends on Polylang functionality.
     * 
     * @return WP_Error|null Returns a WP_Error if Polylang is not active, otherwise null.
     */
    protected function polylang_not_active_error() {
        if (!$this->is_polylang_active()) {
            return new WP_Error(
                'polylang_not_active',
                __('O plugin Polylang não está ativo ou funções essenciais não estão disponíveis.', 'alvobot-pro'),
                array('status' => 503) // Service Unavailable
            );
        }
        return null;
    }
}
