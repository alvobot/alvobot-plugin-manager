<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_MultiLanguages_Ajax_Controller')) {
    return;
}

/**
 * Controller para handlers AJAX do módulo Multi Languages
 * 
 * Centraliza e organiza todas as chamadas AJAX
 */
class AlvoBotPro_MultiLanguages_Ajax_Controller {
    
    /** @var AlvoBotPro_Translation_Engine Motor de tradução */
    private $translation_engine;
    
    /** @var AlvoBotPro_Translation_Queue Sistema de fila */
    private $translation_queue;
    
    public function __construct($translation_engine = null, $translation_queue = null) {
        $this->translation_engine = $translation_engine;
        $this->translation_queue = $translation_queue;
        
        $this->register_ajax_handlers();
    }
    
    /**
     * Registra todos os handlers AJAX
     */
    private function register_ajax_handlers() {
        // Tradução e criação de posts
        add_action('wp_ajax_alvobot_translate_and_create_post', array($this, 'translate_and_create_post'));
        add_action('wp_ajax_alvobot_get_post_language', array($this, 'get_post_language'));
        add_action('wp_ajax_alvobot_add_to_translation_queue', array($this, 'add_to_translation_queue'));
        add_action('wp_ajax_alvobot_force_process_queue', array($this, 'force_process_queue'));
        
        // Gerenciamento da fila
        add_action('wp_ajax_alvobot_process_translation_queue', array($this, 'process_translation_queue'));
        add_action('wp_ajax_alvobot_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_alvobot_get_queue_logs', array($this, 'get_queue_logs'));
        add_action('wp_ajax_alvobot_clear_queue', array($this, 'clear_queue'));
        add_action('wp_ajax_alvobot_remove_queue_item', array($this, 'remove_queue_item'));
        add_action('wp_ajax_alvobot_download_queue_item_logs', array($this, 'download_queue_item_logs'));
        add_action('wp_ajax_alvobot_reset_orphaned_items', array($this, 'reset_orphaned_items'));
        
        // Teste de conexão OpenAI
        add_action('wp_ajax_alvobot_test_openai_connection', array($this, 'test_openai_connection'));
        
        // Reset de estatísticas
        add_action('wp_ajax_alvobot_reset_usage_stats', array($this, 'reset_usage_stats'));
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX Controller: Handlers registrados');
    }
    
    /**
     * Define instâncias dos serviços
     */
    public function set_services($translation_engine, $translation_queue) {
        $this->translation_engine = $translation_engine;
        $this->translation_queue = $translation_queue;
    }
    
    /**
     * Traduz e cria post
     */
    public function translate_and_create_post() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => 'Permissão negada'));
            }
            
            $post_id = intval($_POST['post_id'] ?? 0);
            $target_lang = sanitize_text_field($_POST['target_lang'] ?? '');
            $options = $_POST['options'] ?? array();
            
            if (!$post_id || !$target_lang) {
                wp_send_json_error(array('message' => 'Parâmetros inválidos'));
            }
            
            if (!$this->translation_engine) {
                wp_send_json_error(array('message' => 'Motor de tradução não disponível'));
            }
            
            AlvoBotPro::debug_log('multi-languages', "AJAX: Traduzindo post {$post_id} para {$target_lang}");
            
            $result = $this->translation_engine->translate_post_content($post_id, $target_lang, $options);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Obtém idioma do post
     */
    public function get_post_language() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            $post_id = intval($_POST['post_id'] ?? 0);
            
            if (!$post_id) {
                wp_send_json_error(array('message' => 'Post ID inválido'));
            }
            
            $language = 'pt'; // Default
            
            if (function_exists('pll_get_post_language')) {
                $detected_lang = pll_get_post_language($post_id);
                if ($detected_lang) {
                    $language = $detected_lang;
                }
            }
            
            wp_send_json_success(array('language' => $language));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Adiciona item à fila de tradução
     */
    public function add_to_translation_queue() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => 'Permissão negada'));
            }
            
            $post_id = intval($_POST['post_id'] ?? 0);
            $target_langs = $_POST['target_langs'] ?? array();
            $options = $_POST['options'] ?? array();
            
            if (!$post_id || empty($target_langs)) {
                wp_send_json_error(array('message' => 'Parâmetros inválidos'));
            }
            
            if (!$this->translation_queue) {
                wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
            }
            
            AlvoBotPro::debug_log('multi-languages', "Adicionando à fila de tradução via AJAX");
            AlvoBotPro::debug_log('multi-languages', "Dados recebidos - Post ID: {$post_id}, Idiomas: " . implode(',', $target_langs) . ", Opções: " . json_encode($options));
            
            $queue_id = $this->translation_queue->add_to_queue($post_id, $target_langs, $options);
            
            if ($queue_id) {
                AlvoBotPro::debug_log('multi-languages', "Post {$post_id} processado na fila com ID {$queue_id}");
                wp_send_json_success(array(
                    'message' => 'Post adicionado à fila de tradução',
                    'queue_id' => $queue_id
                ));
            } else {
                wp_send_json_error(array('message' => 'Erro ao adicionar à fila'));
            }
            
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Força processamento da fila
     */
    public function force_process_queue() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => 'Permissão negada'));
            }
            
            if (!$this->translation_queue) {
                wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
            }
            
            AlvoBotPro::debug_log('multi-languages', 'AJAX: Forçando processamento da fila');
            
            $processed = $this->translation_queue->process_next_item();
            
            if ($processed) {
                wp_send_json_success(array('message' => 'Item processado com sucesso'));
            } else {
                wp_send_json_success(array('message' => 'Nenhum item pendente para processar'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handlers da fila - delega para o objeto da fila
     */
    public function process_translation_queue() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_process_queue();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function get_queue_status() {
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Chamada ajax_get_queue_status recebida');
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Translation queue instance: ' . (is_object($this->translation_queue) ? get_class($this->translation_queue) : 'null'));
        
        if ($this->translation_queue) {
            $this->translation_queue->ajax_get_queue_data();
        } else {
            AlvoBotPro::debug_log('multi-languages', 'AJAX ERROR: Sistema de fila não disponível');
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function get_queue_logs() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_get_logs();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function clear_queue() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_clear_queue();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function remove_queue_item() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_remove_item();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function download_queue_item_logs() {
        if ($this->translation_queue) {
            $this->translation_queue->download_logs_handler();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    public function reset_orphaned_items() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_reset_orphaned_items();
        } else {
            wp_send_json_error(array('message' => 'Sistema de fila não disponível'));
        }
    }
    
    /**
     * Valida nonce para AJAX
     */
    private function validate_nonce($action = 'alvobot_nonce') {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }
    }
    
    /**
     * Testa conexão com OpenAI
     */
    public function test_openai_connection() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permissão negada'));
            }
            
            // Carrega provider OpenAI
            if (!class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
                require_once plugin_dir_path(__FILE__) . '../includes/class-translation-providers.php';
            }
            
            $openai_provider = new AlvoBotPro_OpenAI_Translation_Provider();
            $result = $openai_provider->test_connection();
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Reseta estatísticas de uso
     */
    public function reset_usage_stats() {
        try {
            check_ajax_referer('alvobot_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permissão negada'));
            }
            
            // Carrega provider OpenAI
            if (!class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
                require_once plugin_dir_path(__FILE__) . '../includes/class-translation-providers.php';
            }
            
            $openai_provider = new AlvoBotPro_OpenAI_Translation_Provider();
            
            // Captura estatísticas antes do reset
            $old_stats = $openai_provider->get_usage_stats();
            
            // Executa o reset
            $stats = array(
                'total_translations' => 0,
                'total_tokens' => 0,
                'total_cost_estimate' => 0,
                'last_reset' => current_time('mysql')
            );
            
            $result = update_option('alvobot_openai_usage_stats', $stats);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Estatísticas resetadas com sucesso',
                    'old_stats' => $old_stats,
                    'new_stats' => $stats
                ));
            } else {
                wp_send_json_error(array('message' => 'Falha ao atualizar estatísticas'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Valida permissões do usuário
     */
    private function validate_permissions($capability = 'edit_posts') {
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => 'Permissão negada'));
        }
    }
}