<?php
/**
 * Handles AJAX requests for the AlvoBot Pro plugin.
 *
 * This class is responsible for managing all AJAX actions, including security checks
 * and processing data for various plugin functionalities like module toggling,
 * site registration, settings management, and onboarding.
 *
 * @package AlvoBotPro
 * @since 2.0.0 
 */
class AlvoBotPro_Ajax {
    /**
     * Constructor.
     * Initializes AJAX action hooks.
     */
    public function __construct() {
        // Handlers para usuários logados
        add_action('wp_ajax_alvobot_pro_toggle_module', array($this, 'toggle_module'));
        add_action('wp_ajax_grp_register_site', array($this, 'register_site'));
        add_action('wp_ajax_grp_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_grp_reset_plugin', array($this, 'reset_plugin'));
        add_action('wp_ajax_grp_get_activity_log', array($this, 'get_activity_log'));
        add_action('wp_ajax_alvobot_pro_complete_onboarding', array($this, 'complete_onboarding'));
        add_action('wp_ajax_alvobot_pro_save_onboarding_settings', array($this, 'save_onboarding_settings'));
    }

    /**
     * Saves module activation states selected during the onboarding process.
     * 
     * Verifies user permissions and nonce before updating the 'alvobot_pro_active_modules' option.
     * Expects 'module_states' (JSON string) in POST data.
     *
     * @since 2.2.0
     * @return void Sends JSON response (success or error).
     */
    public function save_onboarding_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada', 'alvobot-pro')));
            return;
        }

        if (!check_ajax_referer('alvobot_pro_onboarding_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Nonce inválido', 'alvobot-pro')));
            return;
        }

        $module_states_raw = isset($_POST['module_states']) ? json_decode(stripslashes($_POST['module_states']), true) : null;

        if (empty($module_states_raw) || !is_array($module_states_raw)) {
            wp_send_json_error(array('message' => __('Nenhum estado de módulo recebido.', 'alvobot-pro')));
            return;
        }

        $current_active_modules = get_option('alvobot_pro_active_modules', array());
        $new_active_modules = array();

        // Get all defined modules to iterate over them
        global $alvobot_pro;
        $all_defined_modules_info = array();
        if ($alvobot_pro && method_exists($alvobot_pro, 'get_module_info')) {
            $all_defined_modules_info = $alvobot_pro->get_module_info();
        }


        foreach ($all_defined_modules_info as $slug => $info) {
            if ($slug === 'plugin-manager') { // Plugin Manager is always active
                $new_active_modules[$slug] = true;
                continue;
            }
            // Set state from onboarding if present, otherwise keep current state or default to false
            if (isset($module_states_raw[$slug])) {
                $new_active_modules[$slug] = filter_var($module_states_raw[$slug], FILTER_VALIDATE_BOOLEAN);
            } elseif (isset($current_active_modules[$slug])) {
                $new_active_modules[$slug] = filter_var($current_active_modules[$slug], FILTER_VALIDATE_BOOLEAN);
            } else {
                $new_active_modules[$slug] = false; // Default for any module not in current or new states
            }
        }
        
        // Ensure all modules defined in $default_modules (from class-alvobot-pro.php init_modules)
        // are present, using their default if not touched by onboarding.
        // This step is complex as defaults are applied in init_modules.
        // For simplicity, we trust that $current_active_modules reflects a valid state
        // and we only update based on onboarding input.
        // A more robust way would be to fetch default_modules array here too.

        $updated = update_option('alvobot_pro_active_modules', $new_active_modules);

        if ($updated) {
            wp_send_json_success(array('message' => __('Configurações dos módulos salvas.', 'alvobot-pro')));
        } else {
            // This might also mean the option was the same as before.
            // For the wizard flow, this is still a success in terms of user action.
            wp_send_json_success(array('message' => __('Configurações dos módulos processadas (sem alterações detectadas).', 'alvobot-pro')));
        }
    }

    /**
     * Marks the onboarding process as complete.
     * 
     * Verifies user permissions and nonce, then updates the 'alvobot_pro_onboarding_complete' option.
     * Redirects to the main AlvoBot Pro dashboard on success.
     *
     * @since 2.2.0
     * @return void Sends JSON response (success or error).
     */
    public function complete_onboarding() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada', 'alvobot-pro')));
            return;
        }

        if (!check_ajax_referer('alvobot_pro_onboarding_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Nonce inválido', 'alvobot-pro')));
            return;
        }

        // $skipped = isset($_POST['skipped']) ? filter_var($_POST['skipped'], FILTER_VALIDATE_BOOLEAN) : false;
        // Future: Could store if skipped to offer to resume later.

        update_option('alvobot_pro_onboarding_complete', true);

        wp_send_json_success(array(
            'message' => __('Onboarding concluído!', 'alvobot-pro'),
            'redirect_url' => admin_url('admin.php?page=alvobot-pro')
        ));
    }


    /**
     * Toggles the activation state of a specified module.
     * 
     * Verifies user permissions and nonce. Expects 'module' (slug) and 'enabled' (boolean string) in POST data.
     * Updates the 'alvobot_pro_active_modules' option.
     * The 'plugin-manager' module cannot be deactivated.
     *
     * @since 2.0.0
     * @return void Sends JSON response with success/error message and current module states.
     */
    public function toggle_module() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }

        if (!check_ajax_referer('alvobot_pro_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }

        $module_slug_raw = isset($_POST['module']) ? sanitize_text_field($_POST['module']) : '';
        $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($module_slug_raw)) {
            wp_send_json_error(array('message' => 'Módulo não especificado'));
            return;
        }

        // Get defined modules to validate against
        global $alvobot_pro;
        $defined_modules = array();
        if ($alvobot_pro && method_exists($alvobot_pro, 'get_module_info')) {
            $defined_modules = $alvobot_pro->get_module_info();
        }

        if (!isset($defined_modules[$module_slug_raw])) {
            wp_send_json_error(array('message' => 'Módulo inválido ou não reconhecido: ' . esc_html($module_slug_raw)));
            return;
        }
        
        // Use the validated slug
        $module_slug = $module_slug_raw;

        // Cannot disable plugin-manager
        if ($module_slug === 'plugin-manager' && !$enabled) {
             wp_send_json_error(array('message' => 'O módulo Gerenciador de Plugins não pode ser desativado.'));
            return;
        }

        $active_modules = get_option('alvobot_pro_active_modules', array());
        
        // Ensure all keys are boolean
        foreach ($active_modules as $key => $value) {
            $active_modules[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        // Merge with defaults from AlvoBotPro class to ensure all modules are represented
        // This part is tricky as defaults are set in AlvoBotPro::init_modules
        // For toggle, we only care about the specific module being toggled.
        // The init_modules method in AlvoBotPro should handle merging with defaults when the plugin loads.
        // So, we just update the specific module.
        
        $active_modules[$module_slug] = $enabled;

        $updated = update_option('alvobot_pro_active_modules', $active_modules);

        // Re-fetch to confirm
        $final_state = get_option('alvobot_pro_active_modules');
        $final_enabled = isset($final_state[$module_slug]) ? filter_var($final_state[$module_slug], FILTER_VALIDATE_BOOLEAN) : $enabled; // Fallback to $enabled if key disappears

        // If $updated is false, it might mean the value was already the same.
        // So, the primary check should be if the final state matches the intended state.
        if ($final_enabled !== $enabled) {
             wp_send_json_error(array(
                'message' => 'Erro ao atualizar o estado do módulo. O estado final não corresponde ao desejado.'
            ));
            return;
        }

        // Limpa os caches
        wp_cache_delete('alvobot_pro_active_modules', 'options'); // For single site
        // wp_cache_delete('alloptions', 'options'); // This is too broad, use individual option cache clear

        wp_send_json_success(array(
            'message' => $enabled ? __('Módulo ativado com sucesso', 'alvobot-pro') : __('Módulo desativado com sucesso', 'alvobot-pro'),
            'active_modules' => $final_state, // Send the complete state back
            'module_state' => array(
                'module' => $module_slug,
                'enabled' => $final_enabled // Use the actual final state
            )
        ));
    }

    /**
     * Handles site registration with the AlvoBot central server.
     * 
     * Verifies user permissions and nonce. Expects 'app_password' in POST data.
     * Calls the `AlvoBotPro_PluginManager::register_site()` method.
     * Note: This AJAX action seems specific to the 'Plugin Manager' module's functionality.
     *
     * @since 2.0.0
     * @return void Sends JSON response (success or error).
     */
    public function register_site() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }

        if (!check_ajax_referer('grp_register_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }

        $app_password = isset($_POST['app_password']) ? sanitize_text_field($_POST['app_password']) : '';
        if (empty($app_password)) {
            wp_send_json_error(array('message' => 'Senha do aplicativo não fornecida'));
        }

        $plugin_manager = new AlvoBotPro_PluginManager();
        $result = $plugin_manager->register_site($app_password);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Site registrado com sucesso'));
    }

    /**
     * Saves general plugin settings (related to 'grp_settings' option).
     * 
     * Verifies user permissions and nonce. Expects 'settings' (serialized form data) in POST data.
     * Note: This seems to handle settings for an older or different settings group ('grp_settings'),
     * potentially related to the Plugin Manager or a general settings area not fully detailed elsewhere.
     *
     * @since 2.0.0
     * @return void Sends JSON response (success or error).
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }

        if (!check_ajax_referer('grp_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        if (empty($settings)) {
            wp_send_json_error(array('message' => 'Nenhuma configuração fornecida'));
        }

        parse_str($settings, $parsed_settings);
        $sanitized_settings = array(
            'auto_update' => isset($parsed_settings['grp_settings']['auto_update']),
            'allow_install' => isset($parsed_settings['grp_settings']['allow_install']),
            'allow_delete' => isset($parsed_settings['grp_settings']['allow_delete'])
        );

        update_option('grp_settings', $sanitized_settings);
        wp_send_json_success(array('message' => 'Configurações salvas com sucesso'));
    }

    /**
     * Resets the plugin to its default state.
     * 
     * Verifies user permissions and nonce. Deletes several 'grp_' prefixed options
     * and the 'alvobot' WordPress user.
     * Note: This is a destructive action.
     *
     * @since 2.0.0
     * @return void Sends JSON response (success or error).
     */
    public function reset_plugin() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }

        if (!check_ajax_referer('grp_reset_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }

        // Remove todas as opções do plugin
        delete_option('grp_site_token');
        delete_option('grp_registration_status');
        delete_option('grp_settings');
        delete_option('grp_activity_log');

        // Remove o usuário alvobot se existir
        $user = get_user_by('login', 'alvobot');
        if ($user) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($user->ID);
        }

        wp_send_json_success(array('message' => 'Plugin resetado com sucesso'));
    }

    /**
     * Retrieves the activity log (stored in 'grp_activity_log' option).
     * 
     * Verifies user permissions and nonce.
     * Note: This log seems related to the 'grp_' set of functionalities.
     *
     * @since 2.0.0
     * @return void Sends JSON response with the log data or an error.
     */
    public function get_activity_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }

        if (!check_ajax_referer('grp_log_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }

        $log = get_option('grp_activity_log', array());
        wp_send_json_success(array('log' => $log));
    }
}
