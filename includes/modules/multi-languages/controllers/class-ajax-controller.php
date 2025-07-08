<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controlador AJAX para Multi Languages
 * 
 * Centraliza todas as ações AJAX do módulo
 */
class AlvoBotPro_MultiLanguages_Ajax_Controller {
    
    /** @var AlvoBotPro_Translation_Engine */
    private $translation_engine;
    
    /** @var AlvoBotPro_Translation_Queue */
    private $translation_queue;
    
    public function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * Define os serviços de tradução
     */
    public function set_services($translation_engine, $translation_queue) {
        $this->translation_engine = $translation_engine;
        $this->translation_queue = $translation_queue;
    }
    
    /**
     * Validação centralizada para chamadas AJAX
     * 
     * @param string $capability Capacidade necessária ('edit_posts', 'manage_options', etc.)
     * @param array $required_params Parâmetros obrigatórios do POST
     * @return array Dados validados ou dispara wp_send_json_error
     */
    private function validate_ajax_request($capability = 'edit_posts', $required_params = []) {
        // Verificar nonce
        check_ajax_referer('alvobot_nonce', 'nonce');
        
        // Verificar permissões
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $validated_data = [];
        
        // Validar parâmetros obrigatórios
        foreach ($required_params as $param => $validation) {
            $value = $_POST[$param] ?? null;
            
            // Verificar se parâmetro existe
            if ($value === null || $value === '') {
                wp_send_json_error(['message' => "Parâmetro obrigatório '{$param}' não fornecido"]);
            }
            
            // Aplicar validação específica
            switch ($validation['type']) {
                case 'int':
                    $validated_data[$param] = intval($value);
                    if ($validated_data[$param] <= 0) {
                        wp_send_json_error(['message' => "Parâmetro '{$param}' deve ser um número inteiro positivo"]);
                    }
                    break;
                    
                case 'array':
                    if (!is_array($value) || empty($value)) {
                        wp_send_json_error(['message' => "Parâmetro '{$param}' deve ser um array não vazio"]);
                    }
                    $validated_data[$param] = array_map('sanitize_text_field', $value);
                    break;
                    
                case 'string':
                    $validated_data[$param] = sanitize_text_field($value);
                    if (isset($validation['min_length']) && strlen($validated_data[$param]) < $validation['min_length']) {
                        wp_send_json_error(['message' => "Parâmetro '{$param}' muito curto"]);
                    }
                    break;
                    
                default:
                    $validated_data[$param] = sanitize_text_field($value);
            }
        }
        
        // Capturar parâmetros opcionais
        $optional_params = $_POST['options'] ?? [];
        if (is_array($optional_params)) {
            $validated_data['options'] = array_map('sanitize_text_field', $optional_params);
        } else {
            $validated_data['options'] = [];
        }
        
        return $validated_data;
    }
    
    /**
     * Registra todos os handlers AJAX
     */
    private function register_ajax_handlers() {
        // Handlers principais
        add_action('wp_ajax_alvobot_translate_post', array($this, 'translate_post'));
        add_action('wp_ajax_alvobot_add_to_translation_queue', array($this, 'add_to_translation_queue'));
        add_action('wp_ajax_alvobot_process_translation_queue', array($this, 'process_translation_queue'));
        add_action('wp_ajax_alvobot_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_alvobot_get_queue_logs', array($this, 'get_queue_logs'));
        add_action('wp_ajax_alvobot_clear_queue', array($this, 'clear_queue'));
        add_action('wp_ajax_alvobot_remove_queue_item', array($this, 'remove_queue_item'));
        add_action('wp_ajax_alvobot_download_queue_item_logs', array($this, 'download_queue_item_logs'));
        add_action('wp_ajax_alvobot_reset_orphaned_items', array($this, 'reset_orphaned_items'));
        add_action('wp_ajax_alvobot_test_openai_connection', array($this, 'test_openai_connection'));
        add_action('wp_ajax_alvobot_reset_usage_stats', array($this, 'reset_usage_stats'));
        add_action('wp_ajax_alvobot_get_queue_item_details', array($this, 'get_queue_item_details'));
        
        // Nota: não registrar download_logs como AJAX pois é um download direto
        add_action('admin_init', array($this, 'handle_download_logs'));
    }
    
    /**
     * Handler para download de logs (não é AJAX)
     */
    public function handle_download_logs() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'alvobot_download_queue_item_logs') {
            return;
        }
        
        $this->download_queue_item_logs();
    }
    
    /**
     * Traduz um post
     */
    public function translate_post() {
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Iniciando translate_post');
        
        try {
            // Validação centralizada
            $data = $this->validate_ajax_request('edit_posts', [
                'post_id' => ['type' => 'int'],
                'target_langs' => ['type' => 'array']
            ]);
            
            $post_id = $data['post_id'];
            $target_langs = $data['target_langs'];
            $options = $data['options'];
            
            AlvoBotPro::debug_log('multi-languages', "AJAX: Adicionando post {$post_id} à fila");
            
            // Adiciona à fila em vez de processar diretamente
            $queue_id = $this->translation_queue->add_to_queue($post_id, $target_langs, $options);
            
            if (!$queue_id) {
                wp_send_json_error(array('message' => 'Erro ao adicionar à fila'));
            }
            
            AlvoBotPro::debug_log('multi-languages', "AJAX: Post adicionado à fila com ID {$queue_id}");
            
            // Processa imediatamente se solicitado
            if (!empty($options['process_immediately'])) {
                AlvoBotPro::debug_log('multi-languages', "AJAX: Processando item imediatamente");
                $processed = $this->translation_queue->process_specific_item($queue_id);
                
                if ($processed) {
                    $item = $this->translation_queue->get_item($queue_id);
                    if ($item && $item->status === 'completed') {
                        wp_send_json_success(array(
                            'message' => 'Tradução concluída com sucesso',
                            'queue_id' => $queue_id,
                            'status' => 'completed'
                        ));
                    } elseif ($item && $item->status === 'failed') {
                        wp_send_json_error(array(
                            'message' => 'Erro na tradução: ' . ($item->error_log ?? 'Erro desconhecido'),
                            'queue_id' => $queue_id
                        ));
                    }
                }
            }
            
            wp_send_json_success(array(
                'message' => 'Post adicionado à fila de tradução',
                'queue_id' => $queue_id
            ));
            
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Adiciona itens à fila de tradução
     */
    public function add_to_translation_queue() {
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Iniciando add_to_translation_queue');
        
        try {
            // Validação centralizada
            $data = $this->validate_ajax_request('edit_posts', [
                'post_id' => ['type' => 'int'],
                'target_langs' => ['type' => 'array']
            ]);
            
            $post_id = $data['post_id'];
            $target_langs = $data['target_langs'];
            $options = $data['options'];
            
            AlvoBotPro::debug_log('multi-languages', "AJAX: Adicionando post {$post_id} à fila com idiomas: " . implode(', ', $target_langs));
            
            $queue_id = $this->translation_queue->add_to_queue($post_id, $target_langs, $options);
            
            if (!$queue_id) {
                wp_send_json_error(array('message' => 'Erro ao adicionar à fila'));
            }
            
            // Se solicitado, processa imediatamente
            if (!empty($options['process_immediately'])) {
                AlvoBotPro::debug_log('multi-languages', "AJAX: Processando item {$queue_id} imediatamente");
                $processed = $this->translation_queue->process_specific_item($queue_id);
                
                if ($processed) {
                    wp_send_json_success(array(
                        'message' => 'Item processado com sucesso',
                        'queue_id' => $queue_id,
                        'processed' => true
                    ));
                }
            }
            
            wp_send_json_success(array(
                'message' => 'Item adicionado à fila',
                'queue_id' => $queue_id
            ));
            
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Processa a fila de tradução
     */
    public function process_queue() {
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Processando fila de tradução');
        
        try {
            // Validação centralizada (sem parâmetros obrigatórios, apenas permissão)
            $this->validate_ajax_request('manage_options', []);
            
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
    
    public function get_queue_item_details() {
        if ($this->translation_queue) {
            $this->translation_queue->ajax_get_item_details();
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
            
            update_option('alvobot_openai_usage_stats', $stats);
            
            AlvoBotPro::debug_log('multi-languages', 'Estatísticas de uso resetadas. Valores anteriores: ' . json_encode($old_stats));
            
            wp_send_json_success(array(
                'message' => 'Estatísticas resetadas com sucesso',
                'new_stats' => $stats,
                'old_stats' => $old_stats
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
}