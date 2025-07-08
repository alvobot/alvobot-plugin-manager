<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_Translation_Queue')) {
    return;
}

/**
 * Sistema de Fila de Traduções para o AlvoBot Multi Languages
 */
class AlvoBotPro_Translation_Queue {
    
    private $table_name;
    
    /** @var array Configurações centralizadas de validação (carregadas dinamicamente) */
    private $validation_config;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'alvobot_translation_queue';
        $this->load_validation_config();
        $this->init();
    }
    
    /**
     * CORREÇÃO CONFIGURAÇÃO: Carrega configurações de validação de forma dinâmica
     */
    private function load_validation_config() {
        // Configurações padrão
        $defaults = array(
            'confidence_threshold' => 0.4,           // Threshold para validação programática
            'min_indicators_percent' => 0.05,        // 5% das palavras devem ser indicadores
            'min_indicators_absolute' => 3,          // Mínimo absoluto de indicadores
            'min_text_length' => 50,                 // Tamanho mínimo de texto para validar
            'orphan_timeout_minutes' => 15,          // Timeout para itens órfãos
            'chunk_sleep_ms' => 100,                 // Sleep entre chunks (ms)
            'string_sleep_ms' => 50                  // Sleep entre strings (ms)
        );
        
        // Permite sobrescrever via opções do WordPress
        $saved_config = get_option('alvobot_translation_queue_config', array());
        $this->validation_config = array_merge($defaults, $saved_config);
        
        // Log da configuração carregada para debug
        AlvoBotPro::debug_log('multi-languages', 'Configuração de validação carregada: ' . json_encode($this->validation_config));
    }
    
    private function init() {
        // Evento Cron para processar a fila em background
        add_action('alvobot_process_translation_queue_event', array($this, 'process_queue_background'));
        if (!wp_next_scheduled('alvobot_process_translation_queue_event')) {
            wp_schedule_event(time(), 'every_minute', 'alvobot_process_translation_queue_event');
        }

        // Nota: Handlers AJAX são registrados no Ajax Controller para evitar conflitos
    }
    
    /**
     * Cria a tabela da fila no banco de dados, se não existir.
     */
    public function create_table() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name));
        
        AlvoBotPro::debug_log('multi-languages', 'Verificando tabela: ' . $this->table_name . ' - Existe: ' . ($table_exists ? 'Sim' : 'Não'));
        
        if (!$table_exists) {
            AlvoBotPro::debug_log('multi-languages', 'Criando tabela da fila de traduções: ' . $this->table_name);
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$this->table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id BIGINT(20) UNSIGNED NOT NULL,
                post_type VARCHAR(20) NOT NULL,
                post_title TEXT NOT NULL,
                source_lang VARCHAR(10) NOT NULL,
                target_langs LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                progress TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                options LONGTEXT,
                logs LONGTEXT,
                error_message TEXT,
                priority TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME,
                completed_at DATETIME,
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY status (status)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Adiciona um item à fila de tradução.
     */
    public function add_to_queue($post_id, $target_langs, $options = array(), $priority = 10) {
        global $wpdb;
        $this->create_table();

        $post = get_post($post_id);
        if (!$post) return false;

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$this->table_name} WHERE post_id = %d AND status IN ('pending', 'processing')", $post_id));
        if ($existing) {
            AlvoBotPro::debug_log('multi-languages', "Post {$post_id} já está na fila com status {$existing->status}.");
            return $existing->id;
        }

        $source_lang = function_exists('pll_get_post_language') ? (pll_get_post_language($post_id) ?: pll_default_language()) : 'pt';
        $target_langs = array_filter($target_langs, fn($lang) => $lang !== $source_lang);
        
        if (empty($target_langs)) {
            AlvoBotPro::debug_log('multi-languages', "Nenhum idioma de destino válido para post {$post_id}. Idioma atual: {$source_lang}");
            return false;
        }
        
        AlvoBotPro::debug_log('multi-languages', "Post {$post_id} será traduzido de '{$source_lang}' para: " . implode(', ', $target_langs));

        $wpdb->insert(
            $this->table_name,
            [
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'post_title' => $post->post_title,
                'source_lang' => $source_lang,
                'target_langs' => json_encode(array_values($target_langs)),
                'options' => json_encode($options),
                'priority' => $priority,
                'logs' => json_encode([['timestamp' => current_time('mysql'), 'level' => 'info', 'message' => 'Item adicionado à fila.']])
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'
            ]
        );
        return $wpdb->insert_id;
    }

    /**
     * Obtém as estatísticas gerais da fila.
     */
    public function get_queue_status() {
        global $wpdb;
        $this->create_table();
        
        AlvoBotPro::debug_log('multi-languages', 'Obtendo status da fila - Tabela: ' . $this->table_name);
        
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(IF(status = 'pending', 1, 0)) as pending,
                SUM(IF(status = 'processing', 1, 0)) as processing,
                SUM(IF(status = 'completed', 1, 0)) as completed,
                SUM(IF(status = 'failed', 1, 0)) as failed,
                SUM(IF(status = 'partial', 1, 0)) as partial
            FROM {$this->table_name}
        ";
        
        AlvoBotPro::debug_log('multi-languages', 'Query: ' . $query);
        
        $stats = $wpdb->get_row($query);
        
        if ($wpdb->last_error) {
            AlvoBotPro::debug_log('multi-languages', 'Erro na query: ' . $wpdb->last_error);
            return (object) array(
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'partial' => 0
            );
        }
        
        AlvoBotPro::debug_log('multi-languages', 'Resultado: ' . json_encode($stats));
        
        // Converte valores nulos para 0
        foreach ($stats as $key => $value) {
            $stats->$key = (int) $value;
        }
        
        return $stats;
    }

    /**
     * Obtém os itens da fila para exibição no grid.
     */
    public function get_queue_items($args = []) {
        global $wpdb;
        $this->create_table();

        AlvoBotPro::debug_log('multi-languages', 'Consultando itens da fila na tabela: ' . $this->table_name);
        
        // SEGURANÇA: Query preparada com nome da tabela validado
        $query = "SELECT * FROM {$this->table_name} ORDER BY created_at DESC";
        
        AlvoBotPro::debug_log('multi-languages', 'Query: ' . $query);
        
        $items = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            AlvoBotPro::debug_log('multi-languages', 'Erro na query: ' . $wpdb->last_error);
            return array('items' => array(), 'total' => 0);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'Itens encontrados na consulta: ' . count($items));
        
        foreach ($items as $item) {
            $post_type_obj = get_post_type_object($item->post_type);
            $item->post_type = $post_type_obj ? $post_type_obj->labels->singular_name : $item->post_type;
        }

        return array('items' => $items, 'total' => count($items));
    }

    /**
     * Processa o próximo item pendente na fila.
     */
    public function process_next_item() {
        global $wpdb;
        $this->create_table();
        
        $start_time = microtime(true);
        // Define constante para tracking de tempo de execução se não existir
        if (!defined('ALVOBOT_PROCESS_START_TIME')) {
            define('ALVOBOT_PROCESS_START_TIME', $start_time);
        }
        $this->log_progress('system', 'info', '🔄 Iniciando verificação da fila de tradução', [
            'memory_usage' => $this->format_bytes(memory_get_usage()),
            'timestamp' => current_time('mysql')
        ]);
        
        // Primeiro, verifica e reseta itens orfãos (processing há mais de 10 minutos)
        $orphaned_count = $this->reset_orphaned_items();
        if ($orphaned_count > 0) {
            $this->log_progress('system', 'warning', "🔧 {$orphaned_count} itens órfãos resetados", [
                'orphaned_items' => $orphaned_count
            ]);
        }
        
        // CORREÇÃO RACE CONDITION: Atomic operation - busca E marca como processando em uma única query
        $this->log_progress('database', 'info', '🔍 Buscando e bloqueando próximo item (atomic)', [
            'table' => $this->table_name,
            'query_time' => microtime(true)
        ]);
        
        // Atomic update: busca item pendente E marca como processing em uma operação
        $affected_rows = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'processing', started_at = %s 
             WHERE status = 'pending' 
             ORDER BY priority DESC, id ASC 
             LIMIT 1",
            current_time('mysql')
        ));
        
        $query_time = microtime(true) - $start_time;
        
        if ($affected_rows === 0) {
            $this->log_progress('system', 'info', '✅ Nenhum item pendente na fila', [
                'total_query_time' => round($query_time * 1000, 2) . 'ms',
                'memory_usage' => $this->format_bytes(memory_get_usage())
            ]);
            return false;
        }
        
        // Agora busca o item que acabamos de marcar como processing
        $item = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'processing' AND started_at IS NOT NULL 
             ORDER BY started_at DESC 
             LIMIT 1"
        );
        
        if (!$item) {
            $this->log_progress('system', 'error', '❌ Falha ao recuperar item após lock atomic', []);
            return false;
        }
        
        $this->log_progress('queue', 'success', "🔒 Item bloqueado atomicamente", [
            'queue_id' => $item->id,
            'post_id' => $item->post_id,
            'post_title' => $item->post_title,
            'priority' => $item->priority,
            'source_lang' => $item->source_lang,
            'target_langs' => $item->target_langs,
            'created_at' => $item->created_at,
            'query_time' => round($query_time * 1000, 2) . 'ms'
        ]);
        
        try {
            // Obter o post original
            $this->log_progress('content', 'info', '📖 Carregando post original', [
                'queue_id' => $item->id,
                'post_id' => $item->post_id
            ]);
            
            $post = get_post($item->post_id);
            if (!$post) {
                throw new Exception("Post {$item->post_id} não encontrado");
            }
            
            $this->log_progress('content', 'success', '✅ Post carregado com sucesso', [
                'queue_id' => $item->id,
                'post_id' => $post->ID,
                'post_type' => $post->post_type,
                'post_title' => $post->post_title,
                'post_status' => $post->post_status,
                'content_length' => strlen($post->post_content),
                'excerpt_length' => strlen($post->post_excerpt ?? ''),
                'author_id' => $post->post_author
            ]);
            
            // Decodificar idiomas de destino
            $this->log_progress('languages', 'info', '🌐 Processando idiomas de destino', [
                'queue_id' => $item->id
            ]);
            
            $target_langs = json_decode($item->target_langs, true);
            if (!$target_langs) {
                throw new Exception("Idiomas de destino inválidos: {$item->target_langs}");
            }
            
            $this->log_progress('languages', 'success', '✅ Idiomas de destino decodificados', [
                'queue_id' => $item->id,
                'source_lang' => $item->source_lang,
                'target_langs' => $target_langs,
                'total_languages' => count($target_langs)
            ]);
            
            // Processa usando a lógica comum
            return $this->process_item($item);
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            AlvoBotPro::debug_log('multi-languages', "Erro ao processar item {$item->id}: {$error_message}");
            
            global $wpdb;
            $wpdb->update($this->table_name, [
                'status' => 'failed',
                'progress' => 0,
                'logs' => json_encode([[
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Erro: {$error_message}"
                ]]),
                'error_log' => $error_message,
                'completed_at' => current_time('mysql')
            ], ['id' => $item->id]);
            
            return false;
        }
    }
    
    /**
     * Processa um item específico da fila
     * 
     * @param int $queue_id ID do item na fila
     * @return bool True se processado com sucesso
     */
    public function process_specific_item($queue_id) {
        global $wpdb;
        $this->create_table();
        
        // Busca o item específico
        $item = $this->get_item($queue_id);
        
        if (!$item) {
            AlvoBotPro::debug_log('multi-languages', "Item {$queue_id} não encontrado");
            return false;
        }
        
        if ($item->status !== 'pending') {
            AlvoBotPro::debug_log('multi-languages', "Item {$queue_id} não está pendente. Status: {$item->status}");
            return false;
        }
        
        // Marca como processando atomicamente
        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            [
                'id' => $queue_id,
                'status' => 'pending' // Só atualiza se ainda estiver pendente
            ]
        );
        
        if (!$updated) {
            AlvoBotPro::debug_log('multi-languages', "Não foi possível marcar item {$queue_id} como processando");
            return false;
        }
        
        // Recarrega o item com status atualizado
        $item = $this->get_item($queue_id);
        
        // Processa o item usando a mesma lógica do process_next_item
        return $this->process_item($item);
    }
    
    /**
     * Lógica comum de processamento de item (extraída para reutilização)
     */
    private function process_item($item) {
        global $wpdb;
        
        if (!$item || $item->status !== 'processing') {
            return false;
        }
        
        try {
            $start_time = microtime(true);
            $post = get_post($item->post_id);
            
            if (!$post) {
                throw new Exception("Post {$item->post_id} não encontrado");
            }
            
            // Decodifica dados JSON
            $target_langs = json_decode($item->target_langs, true);
            $options = json_decode($item->options, true) ?: [];
            
            // Logs de progresso
            $logs = [
                [
                    'timestamp' => current_time('mysql'),
                    'level' => 'info',
                    'message' => "🚀 Iniciado processamento do post '{$post->post_title}'"
                ]
            ];
            
            $source_lang = $item->source_lang;
            $progress = 15;
            $translated_posts_created = 0;
            
            // Processa cada idioma
            foreach ($target_langs as $lang_index => $target_lang) {
                try {
                    $current_lang_progress = $lang_index + 1;
                    
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'info',
                        'message' => "🌍 Traduzindo de '{$source_lang}' para '{$target_lang}' ({$current_lang_progress}/" . count($target_langs) . ")"
                    ];
                    
                    // Atualizar progresso
                    $this->update_progress_realtime($item->id, $progress, "Traduzindo para {$target_lang}");
                    
                    // Verifica se já existe tradução
                    if (function_exists('pll_get_post_translations')) {
                        $translations = pll_get_post_translations($post->ID);
                        if (isset($translations[$target_lang])) {
                            $logs[] = [
                                'timestamp' => current_time('mysql'),
                                'level' => 'warning',
                                'message' => "⚠️ Tradução para '{$target_lang}' já existe"
                            ];
                            continue;
                        }
                    }
                    
                    // Realiza a tradução
                    if (!class_exists('AlvoBotPro_Translation_Service')) {
                        throw new Exception('Classe AlvoBotPro_Translation_Service não encontrada');
                    }
                    $translation_service = AlvoBotPro_Translation_Service::get_instance();
                    $result = $translation_service->translate_and_create_post($post->ID, $target_lang, $options);
                    
                    if ($result['success']) {
                        $translated_posts_created++;
                        $progress = min(95, 15 + (80 * ($lang_index + 1) / count($target_langs)));
                        
                        $logs[] = [
                            'timestamp' => current_time('mysql'),
                            'level' => 'success',
                            'message' => "✅ Tradução para '{$target_lang}' concluída"
                        ];
                    } else {
                        $logs[] = [
                            'timestamp' => current_time('mysql'),
                            'level' => 'error',
                            'message' => "❌ Falha ao traduzir para '{$target_lang}': " . ($result['error'] ?? 'Erro desconhecido')
                        ];
                    }
                    
                } catch (Exception $lang_e) {
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'error',
                        'message' => "❌ Erro ao traduzir para '{$target_lang}': " . $lang_e->getMessage()
                    ];
                }
            }
            
            // Determina status final
            if ($translated_posts_created === count($target_langs)) {
                $final_status = 'completed';
                $final_progress = 100;
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'success',
                    'message' => '🎉 Tradução concluída com sucesso para todos os idiomas'
                ];
            } elseif ($translated_posts_created > 0) {
                $final_status = 'partial';
                $final_progress = min(95, $progress);
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'warning',
                    'message' => "Tradução parcial: {$translated_posts_created} de " . count($target_langs) . " idiomas"
                ];
            } else {
                $final_status = 'failed';
                $final_progress = 0;
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Falha na tradução para todos os idiomas"
                ];
            }
            
            // Salvar logs e status final
            $wpdb->update($this->table_name, [
                'status' => $final_status,
                'progress' => $final_progress,
                'logs' => json_encode($logs),
                'completed_at' => current_time('mysql')
            ], ['id' => $item->id]);
            
            AlvoBotPro::debug_log('multi-languages', "Item {$item->id} processado com status '{$final_status}'");
            
            return true;
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            AlvoBotPro::debug_log('multi-languages', "Erro ao processar item {$item->id}: {$error_message}");
            
            $wpdb->update($this->table_name, [
                'status' => 'failed',
                'progress' => 0,
                'logs' => json_encode([[
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Erro: {$error_message}"
                ]]),
                'error_log' => $error_message,
                'completed_at' => current_time('mysql')
            ], ['id' => $item->id]);
            
            return false;
        }
    }

    // --- AJAX Handlers ---
    public function ajax_get_queue_data() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Obtendo dados da fila (via classe fila)');
        
        $status = $this->get_queue_status();
        $queue_data = $this->get_queue_items();
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Status - ' . json_encode($status));
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Queue data - Total itens: ' . count($queue_data['items']));
        
        wp_send_json_success(['status' => $status, 'queue' => $queue_data]);
    }

    public function ajax_process_queue() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Processando fila (via classe fila)');
        
        // Força o processamento independente do cron
        $processed = $this->process_next_item();
        
        if ($processed) {
            wp_send_json_success(['message' => 'Item processado com sucesso.']);
        } else {
            wp_send_json_success(['message' => 'Nenhum item pendente para processar.']);
        }
    }

    public function ajax_clear_queue() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Limpando fila (via classe fila)');
        global $wpdb;
        $this->create_table();
        
        // SEGURANÇA: Usar prepared statement
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE status IN (%s, %s, %s)", 
            'completed', 'failed', 'partial'
        ));
        
        wp_send_json_success(['message' => 'Itens finalizados foram limpos.']);
    }

    public function ajax_remove_item() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Removendo item da fila (via classe fila)');
        
        // SEGURANÇA: Validar e sanitizar input
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id || $id <= 0) {
            wp_send_json_error(['message' => 'ID inválido.']);
        }
        
        global $wpdb;
        $this->create_table();
        $deleted = $wpdb->delete($this->table_name, ['id' => $id], ['%d']);

        if ($deleted) {
            wp_send_json_success(['message' => "Item {$id} removido."]);
        } else {
            wp_send_json_error(['message' => 'Não foi possível remover o item.']);
        }
    }

    public function ajax_get_logs() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Obtendo logs (via classe fila)');
        
        // SEGURANÇA: Validar input
        $id = isset($_POST['queue_id']) ? intval($_POST['queue_id']) : 0;
        if (!$id || $id <= 0) {
            wp_send_json_error(['message' => 'ID inválido.']);
        }
        
        global $wpdb;
        $this->create_table();
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
        if ($item) {
            $logs = $item->logs ? json_decode($item->logs, true) : [];
            wp_send_json_success(['item' => $item, 'logs' => $logs ?: []]);
        } else {
            wp_send_json_error(['message' => 'Item não encontrado.']);
        }
    }
    
    public function ajax_get_item_details() {
        // SEGURANÇA: Verificar nonce e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        // SEGURANÇA: Validar input
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id || $id <= 0) {
            wp_send_json_error(['message' => 'ID inválido.']);
        }
        
        global $wpdb;
        $this->create_table();
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
        
        if (!$item) {
            wp_send_json_error(['message' => 'Item não encontrado.']);
        }
        
        // Coleta informações detalhadas
        $details = $this->get_item_detailed_info($item);
        
        wp_send_json_success([
            'item' => $item,
            'details' => $details
        ]);
    }
    
    private function get_item_detailed_info($item) {
        $details = [];
        
        // Carrega dados do post original
        if ($item->post_id) {
            $post = get_post($item->post_id);
            if ($post) {
                $details['post_data'] = [
                    'post_type' => $post->post_type,
                    'post_status' => $post->post_status,
                    'post_author' => $post->post_author,
                    'post_title' => $post->post_title,
                    'post_excerpt' => $post->post_excerpt,
                    'post_content' => $post->post_content,
                    'content_length' => strlen($post->post_content),
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified
                ];
            }
        }
        
        // Configurações de tradução
        if ($item->options) {
            $details['settings'] = json_decode($item->options, true);
        }
        
        // Sistema de tradução
        $details['translation_system'] = [
            'provider' => 'OpenAI',
            'model' => 'gpt-4o-mini',
            'system_status' => 'active'
        ];
        
        // Métricas
        $queue_stats = $this->get_queue_status();
        $details['metrics'] = [
            'queue_position' => $this->get_item_position_in_queue($item->id),
            'estimated_time' => 'Calculando...',
            'retry_count' => $item->retry_count ?? 0,
            'total_items_in_queue' => $queue_stats->total ?? 0
        ];
        
        // Logs detalhados com payloads
        if ($item->logs) {
            $logs = json_decode($item->logs, true);
            $details['logs'] = $this->parse_detailed_logs($logs);
        }
        
        return $details;
    }
    
    private function parse_detailed_logs($logs) {
        if (!is_array($logs)) {
            return [];
        }
        
        $parsed_logs = [];
        
        foreach ($logs as $log) {
            $parsed_log = $log;
            
            // Se o log contém JSON estruturado (payloads OpenAI)
            if (isset($log['message']) && is_string($log['message'])) {
                // Procura por logs estruturados de OpenAI
                if (strpos($log['message'], 'OPENAI_REQUEST_START:') !== false ||
                    strpos($log['message'], 'OPENAI_RESPONSE:') !== false ||
                    strpos($log['message'], 'OPENAI_API_ERROR:') !== false) {
                    
                    // Extrai o JSON do log
                    $json_start = strpos($log['message'], '{');
                    if ($json_start !== false) {
                        $json_part = substr($log['message'], $json_start);
                        $decoded = json_decode($json_part, true);
                        if ($decoded) {
                            $parsed_log['structured_data'] = $decoded;
                            $parsed_log['has_payload'] = true;
                        }
                    }
                }
            }
            
            $parsed_logs[] = $parsed_log;
        }
        
        return $parsed_logs;
    }
    
    private function get_item_position_in_queue($item_id) {
        global $wpdb;
        
        $position = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) + 1 
            FROM {$this->table_name} 
            WHERE status = 'pending' 
            AND priority >= (SELECT priority FROM {$this->table_name} WHERE id = %d)
            AND created_at < (SELECT created_at FROM {$this->table_name} WHERE id = %d)
        ", $item_id, $item_id));
        
        return $position ?: 1;
    }
    
    public function download_logs_handler() {
        // SEGURANÇA: Verificar nonce e permissões
        $nonce = $_GET['nonce'] ?? $_GET['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'alvobot_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permissões insuficientes');
        }
        
        // SEGURANÇA: Validar input
        $id = isset($_GET['queue_id']) ? intval($_GET['queue_id']) : 0;
        if (!$id || $id <= 0) {
            wp_die('ID inválido');
        }
        
        global $wpdb;
        $this->create_table();
        $logs_json = $wpdb->get_var($wpdb->prepare("SELECT logs FROM {$this->table_name} WHERE id = %d", $id));
        
        // SEGURANÇA: Sanitizar nome do arquivo
        $filename = sprintf('translation_logs_item_%d.txt', $id);
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        
        // Busca todos os dados do item para log detalhado
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d", 
            $id
        ), ARRAY_A);
        
        if (!$item) {
            echo "Item não encontrado na fila.\n";
            exit;
        }
        
        // Cabeçalho do relatório
        echo "================================================================================\n";
        echo "                    RELATÓRIO DETALHADO DE TRADUÇÃO\n";
        echo "================================================================================\n\n";
        
        // Informações gerais do item
        echo "INFORMAÇÕES GERAIS:\n";
        echo "─────────────────────────────────────────────────────────────────────────────\n";
        echo "Queue ID: {$item['id']}\n";
        echo "Post ID: {$item['post_id']}\n";
        echo "Status: {$item['status']}\n";
        echo "Criado em: {$item['created_at']}\n";
        echo "Atualizado em: {$item['updated_at']}\n";
        
        if (!empty($item['started_at'])) {
            echo "Iniciado em: {$item['started_at']}\n";
        }
        if (!empty($item['completed_at'])) {
            echo "Concluído em: {$item['completed_at']}\n";
        }
        
        echo "\n";
        
        // Decodifica dados JSON
        $target_languages = json_decode($item['target_languages'], true);
        $options = json_decode($item['options'], true);
        $result_data = json_decode($item['result_data'], true);
        $logs = json_decode($logs_json, true);
        
        // Idiomas de destino
        echo "IDIOMAS DE DESTINO:\n";
        echo "─────────────────────────────────────────────────────────────────────────────\n";
        if (is_array($target_languages)) {
            foreach ($target_languages as $index => $lang) {
                echo sprintf("  %d. %s\n", $index + 1, $lang);
            }
        }
        echo "\n";
        
        // Opções de tradução
        echo "OPÇÕES DE TRADUÇÃO:\n";
        echo "─────────────────────────────────────────────────────────────────────────────\n";
        if (is_array($options)) {
            foreach ($options as $key => $value) {
                $display_value = is_bool($value) ? ($value ? 'SIM' : 'NÃO') : $value;
                echo sprintf("  %-25s: %s\n", $key, $display_value);
            }
        }
        echo "\n";
        
        // Dados de resultado (se disponíveis)
        if (is_array($result_data) && !empty($result_data)) {
            echo "DADOS DE RESULTADO:\n";
            echo "─────────────────────────────────────────────────────────────────────────────\n";
            $this->print_array_detailed($result_data, "  ");
            echo "\n";
        }
        
        // Logs cronológicos detalhados
        echo "LOGS CRONOLÓGICOS DETALHADOS:\n";
        echo "─────────────────────────────────────────────────────────────────────────────\n";
        
        if (is_array($logs) && !empty($logs)) {
            foreach ($logs as $index => $log) {
                $timestamp = isset($log['timestamp']) ? $log['timestamp'] : 'N/A';
                $category = isset($log['category']) ? strtoupper($log['category']) : 'GENERAL';
                $level = isset($log['level']) ? strtoupper($log['level']) : 'INFO';
                $message = isset($log['message']) ? $log['message'] : '';
                
                echo sprintf("[%s] [%s:%s] %s\n", $timestamp, $category, $level, $message);
                
                // Mostra TODOS os dados armazenados no campo 'data'
                if (isset($log['data']) && is_array($log['data']) && !empty($log['data'])) {
                    echo "    ┌─ DADOS DETALHADOS:\n";
                    foreach ($log['data'] as $key => $value) {
                        if ($key === 'queue_id') continue; // Skip queue_id pois já temos
                        
                        if (is_array($value)) {
                            echo "    │  {$key}:\n";
                            $this->print_array_detailed($value, "    │    ");
                        } elseif (is_bool($value)) {
                            echo "    │  {$key}: " . ($value ? 'SIM' : 'NÃO') . "\n";
                        } elseif (is_numeric($value)) {
                            // Formata números grandes (como timestamps) de forma legível
                            if ($key === 'query_time' && $value > 1000000000) {
                                echo "    │  {$key}: " . date('Y-m-d H:i:s', (int)$value) . " ({$value})\n";
                            } else {
                                echo "    │  {$key}: {$value}\n";
                            }
                        } elseif (is_string($value)) {
                            // Mostra strings completas para logs detalhados
                            if (strlen($value) > 500) {
                                echo "    │  {$key}: " . substr($value, 0, 500) . "...\n";
                                echo "    │    [CONTEÚDO TRUNCADO - Total: " . strlen($value) . " caracteres]\n";
                            } else {
                                echo "    │  {$key}: {$value}\n";
                            }
                        } else {
                            echo "    │  {$key}: " . print_r($value, true) . "\n";
                        }
                    }
                    echo "    └─\n";
                }
                
                // Adiciona dados extras se existirem (compatibilidade)
                if (isset($log['extra_data']) && is_array($log['extra_data'])) {
                    echo "    ┌─ DADOS EXTRAS LEGADOS:\n";
                    $this->print_array_detailed($log['extra_data'], "    │  ");
                    echo "    └─\n";
                }
                
                echo "\n";
            }
        } else {
            echo "Nenhum log encontrado para este item.\n";
        }
        
        echo "\n================================================================================\n";
        echo "                           FIM DO RELATÓRIO\n";
        echo "================================================================================\n";
        exit;
    }
    
    /**
     * Imprime array de forma detalhada e legível
     */
    private function print_array_detailed($array, $indent = "") {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                echo "{$indent}{$key}:\n";
                $this->print_array_detailed($value, $indent . "  ");
            } elseif (is_bool($value)) {
                echo "{$indent}{$key}: " . ($value ? 'SIM' : 'NÃO') . "\n";
            } elseif (is_numeric($value)) {
                echo "{$indent}{$key}: {$value}\n";
            } elseif (is_string($value)) {
                // Trunca strings muito longas para legibilidade
                $display_value = strlen($value) > 200 ? substr($value, 0, 200) . '...[TRUNCADO]' : $value;
                echo "{$indent}{$key}: {$display_value}\n";
            } else {
                echo "{$indent}{$key}: " . print_r($value, true) . "\n";
            }
        }
    }
    
    public function process_queue_background() {
        $this->process_next_item();
    }
    
    /**
     * Reseta itens orfãos que estão em processamento há muito tempo
     */
    private function reset_orphaned_items() {
        global $wpdb;
        
        // Reseta itens que estão 'processing' há mais do que o configurado
        $timeout_minutes = $this->validation_config['orphan_timeout_minutes'];
        $timeout = date('Y-m-d H:i:s', strtotime("-{$timeout_minutes} minutes"));
        
        $orphaned_items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, started_at FROM {$this->table_name} WHERE status = 'processing' AND started_at < %s",
            $timeout
        ));
        
        if (!empty($orphaned_items)) {
            AlvoBotPro::debug_log('multi-languages', 'Encontrados ' . count($orphaned_items) . ' itens orfãos para resetar');
            
            foreach ($orphaned_items as $item) {
                AlvoBotPro::debug_log('multi-languages', "Resetando item orfão {$item->id} (post {$item->post_id}) - iniciado em {$item->started_at}");
                
                // Adiciona log do reset
                $current_logs = $wpdb->get_var($wpdb->prepare(
                    "SELECT logs FROM {$this->table_name} WHERE id = %d", 
                    $item->id
                ));
                
                $logs = $current_logs ? json_decode($current_logs, true) : [];
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'warning',
                    'message' => 'Item resetado por timeout - recolocado na fila'
                ];
                
                // Reseta para pending
                $wpdb->update(
                    $this->table_name,
                    [
                        'status' => 'pending',
                        'started_at' => null,
                        'progress' => 0,
                        'logs' => json_encode($logs)
                    ],
                    ['id' => $item->id]
                );
            }
        }
    }
    
    /**
     * Handler AJAX para resetar itens órfãos manualmente
     */
    public function ajax_reset_orphaned_items() {
        // SEGURANÇA: Verificar nonce padronizado e permissões
        if (!check_ajax_referer('alvobot_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        AlvoBotPro::debug_log('multi-languages', 'AJAX: Forçando reset de itens órfãos');
        
        // Força reset de todos os itens travados (processing e failed)
        global $wpdb;
        $this->create_table();
        
        $stuck_items = $wpdb->get_results(
            "SELECT id, post_id, status, started_at FROM {$this->table_name} WHERE status IN ('processing', 'failed')"
        );
        
        if (!empty($stuck_items)) {
            foreach ($stuck_items as $item) {
                AlvoBotPro::debug_log('multi-languages', "Forçando reset do item {$item->id} (post {$item->post_id}, status: {$item->status})");
                
                $current_logs = $wpdb->get_var($wpdb->prepare(
                    "SELECT logs FROM {$this->table_name} WHERE id = %d", 
                    $item->id
                ));
                
                $logs = $current_logs ? json_decode($current_logs, true) : [];
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'info',
                    'message' => 'Item resetado manualmente via AJAX'
                ];
                
                $wpdb->update(
                    $this->table_name,
                    [
                        'status' => 'pending',
                        'started_at' => null,
                        'progress' => 0,
                        'logs' => json_encode($logs)
                    ],
                    ['id' => $item->id]
                );
            }
            
            wp_send_json_success([
                'message' => 'Resetados ' . count($stuck_items) . ' itens órfãos',
                'items_reset' => count($stuck_items)
            ]);
        } else {
            wp_send_json_success([
                'message' => 'Nenhum item órfão encontrado',
                'items_reset' => 0
            ]);
        }
    }
    
    /**
     * Obtém instância do motor de tradução com verificação de dependências
     */
    private function get_translation_engine() {
        // CORREÇÃO DEPENDÊNCIAS: Verificar se arquivo existe antes de incluir
        $engine_file = dirname(__FILE__) . '/class-translation-engine.php';
        if (!file_exists($engine_file)) {
            AlvoBotPro::debug_log('multi-languages', 'ERRO CRÍTICO: Arquivo class-translation-engine.php não encontrado em: ' . $engine_file);
            return null;
        }
        
        // Incluir apenas se não foi incluído antes
        if (!class_exists('AlvoBotPro_Translation_Engine')) {
            require_once $engine_file;
        }
        
        // Verificar se a classe existe após inclusão
        if (!class_exists('AlvoBotPro_Translation_Engine')) {
            AlvoBotPro::debug_log('multi-languages', 'ERRO CRÍTICO: Classe AlvoBotPro_Translation_Engine não encontrada após inclusão');
            return null;
        }
        
        try {
            $engine = new AlvoBotPro_Translation_Engine();
            
            // Verificar se o engine está funcionalmente disponível
            if (!method_exists($engine, 'translate_text')) {
                AlvoBotPro::debug_log('multi-languages', 'ERRO CRÍTICO: Motor de tradução não tem método translate_text');
                return null;
            }
            
            AlvoBotPro::debug_log('multi-languages', 'Motor de tradução carregado e verificado com sucesso');
            return $engine;
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'ERRO CRÍTICO ao instanciar motor de tradução: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cria um post traduzido e vincula ao Polylang
     * 
     * @param object $original_post Post original
     * @param string $translated_title Título traduzido
     * @param string $translated_content Conteúdo traduzido
     * @param string $target_lang Idioma de destino
     * @param string $status Status do post ('publish', 'draft', etc.)
     * @return int|false ID do post criado ou false em caso de erro
     */
    private function create_translated_post($original_post, $translated_title, $translated_content, $target_lang, $status = 'publish') {
        global $wpdb;
        
        try {
            // ROBUSTEZ: Iniciar transação para garantir atomicidade
            $wpdb->query('START TRANSACTION');
            
            // Dados do novo post
            $post_data = array(
                'post_title' => $translated_title,
                'post_content' => $translated_content,
                'post_status' => $status, // Status definido pelo parâmetro
                'post_type' => $original_post->post_type,
                'post_author' => $original_post->post_author,
                'post_category' => array(), // Será definido posteriormente
                'meta_input' => array(
                    '_alvobot_translated_from' => $original_post->ID,
                    '_alvobot_translation_lang' => $target_lang,
                    '_alvobot_translation_date' => current_time('mysql')
                )
            );
            
            // Cria o post traduzido
            $translated_post_id = wp_insert_post($post_data);
            
            if (is_wp_error($translated_post_id) || !$translated_post_id) {
                throw new Exception('Erro ao criar post: ' . (is_wp_error($translated_post_id) ? $translated_post_id->get_error_message() : 'ID inválido'));
            }
            
            AlvoBotPro::debug_log('multi-languages', "Post traduzido criado (ID: {$translated_post_id})");
            
            // Vincula ao Polylang se disponível
            if (function_exists('pll_set_post_language') && function_exists('pll_save_post_translations')) {
                // Define o idioma do post traduzido
                pll_set_post_language($translated_post_id, $target_lang);
                
                // Obtém traduções existentes do post original
                $translations = function_exists('pll_get_post_translations') ? 
                    pll_get_post_translations($original_post->ID) : array();
                
                // Adiciona a nova tradução
                $translations[$target_lang] = $translated_post_id;
                
                // Salva a vinculação entre os posts
                pll_save_post_translations($translations);
                
                AlvoBotPro::debug_log('multi-languages', "Post vinculado ao Polylang no idioma {$target_lang}");
            } else {
                AlvoBotPro::debug_log('multi-languages', 'Polylang não disponível - post criado sem vinculação');
            }
            
            // Copia categorias e tags se possível
            $this->copy_post_taxonomies($original_post->ID, $translated_post_id, $target_lang);
            
            // Adicionar meta específico se o post foi criado como draft por falha de validação
            if ($status === 'draft') {
                $meta_result1 = update_post_meta($translated_post_id, '_alvobot_validation_failed', true);
                $meta_result2 = update_post_meta($translated_post_id, '_alvobot_validation_reason', 'Falha na validação de idioma - requer revisão manual');
                
                if (!$meta_result1 || !$meta_result2) {
                    AlvoBotPro::debug_log('multi-languages', 'Aviso: Falha ao salvar metas de validação para post draft');
                }
                
                AlvoBotPro::debug_log('multi-languages', "Post {$translated_post_id} criado como rascunho devido à falha de validação");
            } else {
                AlvoBotPro::debug_log('multi-languages', "Post {$translated_post_id} criado com status: {$status}");
            }
            
            // ROBUSTEZ: Confirmar transação se tudo deu certo
            $wpdb->query('COMMIT');
            AlvoBotPro::debug_log('multi-languages', "Transação confirmada para post {$translated_post_id}");
            
            return $translated_post_id;
            
        } catch (Exception $e) {
            // ROBUSTEZ: Reverter transação em caso de erro
            $wpdb->query('ROLLBACK');
            AlvoBotPro::debug_log('multi-languages', 'Transação revertida devido ao erro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Copia taxonomias (categorias/tags) do post original para o traduzido
     */
    private function copy_post_taxonomies($original_post_id, $translated_post_id, $target_lang) {
        $taxonomies = get_object_taxonomies(get_post_type($original_post_id));
        
        foreach ($taxonomies as $taxonomy) {
            // Ignora taxonomias do Polylang
            if (in_array($taxonomy, array('language', 'post_translations'))) {
                continue;
            }
            
            $terms = wp_get_post_terms($original_post_id, $taxonomy, array('fields' => 'ids'));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                // Se o Polylang estiver ativo, tenta obter traduções dos termos
                if (function_exists('pll_get_term_translations')) {
                    $translated_terms = array();
                    
                    foreach ($terms as $term_id) {
                        $term_translations = pll_get_term_translations($term_id);
                        if (isset($term_translations[$target_lang])) {
                            $translated_terms[] = $term_translations[$target_lang];
                        } else {
                            // Se não há tradução, usa o termo original
                            $translated_terms[] = $term_id;
                        }
                    }
                    
                    $terms = $translated_terms;
                }
                
                wp_set_post_terms($translated_post_id, $terms, $taxonomy);
                AlvoBotPro::debug_log('multi-languages', "Taxonomia {$taxonomy} copiada com " . count($terms) . " termos");
            }
        }
    }
    
    /**
     * Traduz um artigo (post) dividindo em chunks
     */
    private function translate_article($post, $source_lang, $target_lang, $translation_engine, &$logs, $queue_id) {
        try {
            $article_start_time = microtime(true);
            
            $this->log_progress('article', 'info', '📄 Iniciando tradução de ARTIGO em chunks', [
                'queue_id' => $queue_id,
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'content_length' => strlen($post->post_content),
                'strategy' => 'chunk_by_headings'
            ]);
            
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => '📄 Iniciando tradução de artigo em chunks',
                'progress_data' => [
                    'content_length' => strlen($post->post_content),
                    'estimated_time' => 'calculating...'
                ]
            ];
            
            // 1. Traduzir título
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => 'Traduzindo título do artigo'
            ];
            
            $title_result = $translation_engine->translate_text($post->post_title, $source_lang, $target_lang, [
                'context' => 'article_title',
                'prompt_prefix' => 'Traduza este título de artigo mantendo o tom e impacto original:'
            ]);
            
            if (!$title_result['success']) {
                throw new Exception('Falha ao traduzir título: ' . ($title_result['error'] ?? 'Erro desconhecido'));
            }
            
            $translated_title = $title_result['translated_text'];
            
            // 2. Traduzir descrição/excerpt
            $excerpt = get_the_excerpt($post->ID) ?: '';
            $translated_excerpt = '';
            
            if (!empty($excerpt)) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'info',
                    'message' => 'Traduzindo descrição do artigo'
                ];
                
                $excerpt_result = $translation_engine->translate_text($excerpt, $source_lang, $target_lang, [
                    'context' => 'article_excerpt',
                    'prompt_prefix' => 'Traduza esta descrição de artigo mantendo o sentido e atratividade:'
                ]);
                
                if ($excerpt_result['success']) {
                    $translated_excerpt = $excerpt_result['translated_text'];
                }
            }
            
            // 3. Limpar e preparar conteúdo
            $content_clean_start = microtime(true);
            $original_length = strlen($post->post_content);
            
            $this->log_progress('article', 'info', '📜 Limpando conteúdo do artigo', [
                'queue_id' => $queue_id,
                'original_length' => $original_length,
                'cleaning_strategy' => 'remove_ads_scripts_unsafe_html'
            ]);
            
            $clean_content = $this->clean_article_content($post->post_content);
            $cleaned_length = strlen($clean_content);
            $clean_time = microtime(true) - $content_clean_start;
            
            $this->log_progress('article', 'success', '✅ Conteúdo limpo', [
                'queue_id' => $queue_id,
                'clean_time' => round($clean_time * 1000, 2) . 'ms',
                'original_length' => $original_length,
                'cleaned_length' => $cleaned_length,
                'reduction' => round((($original_length - $cleaned_length) / $original_length) * 100, 1) . '%'
            ]);
            
            // 4. Dividir conteúdo em chunks
            $chunk_start = microtime(true);
            
            $this->log_progress('article', 'info', '✂️ Dividindo conteúdo em chunks', [
                'queue_id' => $queue_id,
                'content_length' => $cleaned_length,
                'max_words_per_chunk' => 500,
                'strategy' => 'split_by_headings'
            ]);
            
            $content_chunks = $this->split_content_into_chunks($clean_content, 500);
            $chunk_time = microtime(true) - $chunk_start;
            
            $this->log_progress('article', 'success', '✅ Conteúdo dividido em chunks', [
                'queue_id' => $queue_id,
                'chunk_time' => round($chunk_time * 1000, 2) . 'ms',
                'total_chunks' => count($content_chunks),
                'avg_chunk_size' => $cleaned_length > 0 ? round($cleaned_length / count($content_chunks)) . ' chars' : '0 chars'
            ]);
            
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => '✂️ Conteúdo dividido em ' . count($content_chunks) . ' chunks',
                'progress_data' => [
                    'total_chunks' => count($content_chunks),
                    'avg_size' => round($cleaned_length / count($content_chunks)) . ' chars'
                ]
            ];
            
            // 5. Traduzir cada chunk
            $chunks_start = microtime(true);
            $translated_chunks = [];
            $successful_chunks = 0;
            $failed_chunks = 0;
            
            foreach ($content_chunks as $index => $chunk) {
                $chunk_start_time = microtime(true);
                $chunk_number = $index + 1;
                $chunk_length = strlen($chunk);
                
                $this->log_progress('chunk', 'info', "🔄 Traduzindo chunk {$chunk_number}/" . count($content_chunks), [
                    'queue_id' => $queue_id,
                    'chunk_index' => $chunk_number,
                    'total_chunks' => count($content_chunks),
                    'chunk_length' => $chunk_length,
                    'progress_percent' => round(($chunk_number / count($content_chunks)) * 100, 1) . '%'
                ]);
                
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'info',
                    'message' => "🔄 Traduzindo chunk {$chunk_number}/" . count($content_chunks),
                    'progress_data' => [
                        'chunk_index' => $chunk_number,
                        'chunk_length' => $chunk_length,
                        'progress' => round(($chunk_number / count($content_chunks)) * 100, 1) . '%'
                    ]
                ];
                
                $chunk_result = $translation_engine->translate_text($chunk, $source_lang, $target_lang, [
                    'context' => 'article_content_chunk',
                    'preserve_html' => true,
                    'prompt_prefix' => 'Traduza este conteúdo de artigo mantendo todas as tags HTML, imagens e vídeos. Remova scripts e artefatos que não sejam conteúdo do artigo:'
                ]);
                
                $chunk_time = microtime(true) - $chunk_start_time;
                
                if ($chunk_result['success']) {
                    $translated_chunks[] = $chunk_result['translated_text'];
                    $successful_chunks++;
                    
                    $this->log_progress('chunk', 'success', "✅ Chunk {$chunk_number} traduzido com sucesso", [
                        'queue_id' => $queue_id,
                        'chunk_index' => $chunk_number,
                        'translation_time' => round($chunk_time, 2) . 's',
                        'original_length' => $chunk_length,
                        'translated_length' => strlen($chunk_result['translated_text']),
                        'tokens_used' => $chunk_result['usage']['total_tokens'] ?? 'N/A'
                    ]);
                } else {
                    $translated_chunks[] = $chunk;
                    $failed_chunks++;
                    
                    $this->log_progress('chunk', 'error', "❌ Falha no chunk {$chunk_number}", [
                        'queue_id' => $queue_id,
                        'chunk_index' => $chunk_number,
                        'error' => $chunk_result['error'] ?? 'Erro desconhecido',
                        'fallback' => 'using_original_content'
                    ]);
                    
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'warning',
                        'message' => "❌ Falha ao traduzir chunk {$chunk_number}, mantendo original",
                        'progress_data' => [
                            'error' => $chunk_result['error'] ?? 'Erro desconhecido'
                        ]
                    ];
                }
                
                // OTIMIZAÇÃO: Liberar memória do chunk processado
                unset($chunk, $chunk_result);
                
                // Pausa configurável para rate limiting da API
                if ($index < count($content_chunks) - 1) {
                    usleep($this->validation_config['chunk_sleep_ms'] * 1000);
                }
            }
            
            $total_chunks_time = microtime(true) - $chunks_start;
            
            $this->log_progress('article', 'info', '📊 Resumo da tradução de chunks', [
                'queue_id' => $queue_id,
                'total_time' => round($total_chunks_time, 2) . 's',
                'successful_chunks' => $successful_chunks,
                'failed_chunks' => $failed_chunks,
                'success_rate' => round(($successful_chunks / count($content_chunks)) * 100, 1) . '%',
                'avg_time_per_chunk' => round($total_chunks_time / count($content_chunks), 2) . 's'
            ]);
            
            // 6. Juntar chunks traduzidos
            $assembly_start = microtime(true);
            $translated_content = implode("\n\n", $translated_chunks);
            $assembly_time = microtime(true) - $assembly_start;
            $total_article_time = microtime(true) - $article_start_time;
            
            $this->log_progress('article', 'success', '🎉 Tradução de ARTIGO concluída', [
                'queue_id' => $queue_id,
                'total_time' => round($total_article_time, 2) . 's',
                'assembly_time' => round($assembly_time * 1000, 2) . 'ms',
                'original_title' => $post->post_title,
                'translated_title' => $translated_title,
                'chunks_processed' => count($content_chunks),
                'successful_chunks' => $successful_chunks,
                'failed_chunks' => $failed_chunks,
                'final_content_length' => strlen($translated_content),
                'success_rate' => round(($successful_chunks / count($content_chunks)) * 100, 1) . '%'
            ]);
            
            return [
                'success' => true,
                'title' => $translated_title,
                'content' => $translated_content,
                'excerpt' => $translated_excerpt,
                'chunks_translated' => count($content_chunks),
                'successful_chunks' => $successful_chunks,
                'failed_chunks' => $failed_chunks,
                'translation_method' => 'article_chunks',
                'total_time' => round($total_article_time, 2),
                'logs' => []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => []
            ];
        }
    }
    
    /**
     * Traduz uma página usando parser de strings para Gutenberg/Elementor
     */
    private function translate_page($post, $source_lang, $target_lang, $translation_engine, &$logs, $queue_id) {
        try {
            $this->log_progress('page', 'info', '📄 Iniciando tradução de PÁGINA', [
                'queue_id' => $queue_id,
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'content_length' => strlen($post->post_content),
                'strategy' => 'page_parser'
            ]);

            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => 'Iniciando tradução de página com parser de strings'
            ];
            
            // 1. Traduzir título
            $title_result = $translation_engine->translate_text($post->post_title, $source_lang, $target_lang, [
                'context' => 'page_title',
                'prompt_prefix' => 'Traduza este título de página:'
            ]);
            
            if (!$title_result['success']) {
                throw new Exception('Falha ao traduzir título: ' . ($title_result['error'] ?? 'Erro desconhecido'));
            }
            
            $translated_title = $title_result['translated_text'];
            
            // 2. Preparar conteúdo para tradução
            $content_to_translate = array(
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt
            );
            
            $translatable_strings = array_filter($content_to_translate);
            
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => 'Encontradas ' . count($translatable_strings) . ' strings para traduzir'
            ];
            
            // 3. Traduzir strings em lote
            $translated_strings = [];
            foreach ($translatable_strings as $index => $string_data) {
                $string_text = is_array($string_data) ? $string_data['text'] : $string_data;
                
                if (empty(trim($string_text))) {
                    $translated_strings[$index] = ['success' => true, 'translated_text' => $string_text];
                    continue;
                }
                
                $string_result = $translation_engine->translate_text($string_text, $source_lang, $target_lang, [
                    'context' => 'page_content_string',
                    'preserve_html' => isset($string_data['preserve_html']) ? $string_data['preserve_html'] : false,
                    'prompt_prefix' => 'Traduza este texto de página mantendo formatação e estrutura:'
                ]);
                
                $translated_strings[$index] = $string_result;
                
                if (!$string_result['success']) {
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'warning',
                        'message' => "Falha ao traduzir string " . ($index + 1) . ", mantendo original"
                    ];
                }
                
                // OTIMIZAÇÃO: Liberar memória da string processada
                unset($string_text, $string_result);
                
                // Pausa configurável para rate limiting
                if ($index < count($translatable_strings) - 1) {
                    usleep($this->validation_config['string_sleep_ms'] * 1000);
                }
            }
            
            // 4. Organizar conteúdo traduzido
            $translated_content = array(
                'title' => $translated_title,
                'content' => isset($translated_strings['content']) ? $translated_strings['content'] : $post->post_content,
                'excerpt' => isset($translated_strings['excerpt']) ? $translated_strings['excerpt'] : $post->post_excerpt
            );
            
            return [
                'success' => true,
                'title' => $translated_content['title'],
                'content' => $translated_content['content'],
                'excerpt' => $translated_content['excerpt'],
                'logs' => []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => []
            ];
        }
    }
    
    /**
     * Limpa conteúdo de artigo removendo scripts e mantendo apenas HTML padrão
     */
    private function clean_article_content($content) {
        // Remove scripts, estilos e elementos perigosos
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        $content = preg_replace('/<link[^>]*>/mi', '', $content);
        $content = preg_replace('/<meta[^>]*>/mi', '', $content);
        $content = preg_replace('/<noscript\b[^<]*(?:(?!<\/noscript>)<[^<]*)*<\/noscript>/mi', '', $content);
        
        // Remove atributos JavaScript
        $content = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/mi', '', $content);
        $content = preg_replace('/\s*javascript\s*:/mi', '', $content);
        
        // Remove comentários HTML
        $content = preg_replace('/<!--[^>]*-->/mi', '', $content);
        
        // Mantém apenas tags HTML seguras para artigos
        $allowed_tags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><figure><figcaption><blockquote><div><span><table><tr><td><th><thead><tbody>';
        $content = strip_tags($content, $allowed_tags);
        
        // Limpa espaços excessivos
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Divide conteúdo em chunks baseado em subtítulos e limite de palavras
     */
    private function split_content_into_chunks($content, $max_words = 500) {
        // Primeiro extrai e protege shortcodes e JSON
        $protected_blocks = [];
        $block_id = 0;
        
        // Função para substituir blocos protegidos por placeholders
        $protect_block = function($matches) use (&$protected_blocks, &$block_id) {
            $placeholder = "<!--PROTECTED_BLOCK_{$block_id}-->";
            $protected_blocks[$placeholder] = $matches[0];
            $block_id++;
            return $placeholder;
        };
        
        // Protege shortcodes com JSON (como [quiz])
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            AlvoBotPro::debug_log('multi-languages', 'Processando shortcode quiz - Conteúdo original: ' . var_export($content, true));
        }
        
        $content = preg_replace_callback('/\[quiz[^\]]*\](.*?)\[\/quiz\]/s', function($matches) use (&$protected_blocks, &$block_id) {
            $placeholder = "<!--PROTECTED_BLOCK_{$block_id}-->";
            $protected_blocks[$placeholder] = $matches[0];
            
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                AlvoBotPro::debug_log('multi-languages', 'Quiz protegido - ID: ' . $block_id . ' - Conteúdo: ' . var_export($matches[0], true));
            }
            
            $block_id++;
            return $placeholder;
        }, $content);
        
        // Protege outros shortcodes
        $content = preg_replace_callback('/\[[^\]]+\]/', $protect_block, $content);
        
        // Divide por subtítulos
        $headers = [];
        $pattern = '/<(h[1-6])[^>]*>(.*?)<\/\1>/i';
        preg_match_all($pattern, $content, $headers, PREG_OFFSET_CAPTURE);
        
        $chunks = [];
        if (count($headers[0]) > 1) {
            // Divide por subtítulos
            $last_pos = 0;
            
            foreach ($headers[0] as $header) {
                $header_pos = $header[1];
                
                if ($header_pos > $last_pos) {
                    $chunk = substr($content, $last_pos, $header_pos - $last_pos);
                    $word_count = str_word_count(strip_tags($chunk));
                    
                    if ($word_count > $max_words) {
                        // Se ainda for muito grande, divide por parágrafos
                        $sub_chunks = $this->split_by_paragraphs($chunk, $max_words);
                        $chunks = array_merge($chunks, $sub_chunks);
                    } else {
                        $chunks[] = trim($chunk);
                    }
                }
                
                $last_pos = $header_pos;
            }
            
            // Adiciona o último chunk
            if ($last_pos < strlen($content)) {
                $final_chunk = substr($content, $last_pos);
                $word_count = str_word_count(strip_tags($final_chunk));
                
                if ($word_count > $max_words) {
                    $sub_chunks = $this->split_by_paragraphs($final_chunk, $max_words);
                    $chunks = array_merge($chunks, $sub_chunks);
                } else {
                    $chunks[] = trim($final_chunk);
                }
            }
            
            // Restaura os blocos protegidos em cada chunk
            foreach ($chunks as &$chunk) {
                foreach ($protected_blocks as $placeholder => $original) {
                    $chunk = str_replace($placeholder, $original, $chunk);
                }
                
                // Remove divs e spans vazios
                $chunk = preg_replace('/<(div|span)[^>]*>\s*<\/\1>/i', '', $chunk);
                
                // Substitui &nbsp; por espaço normal
                $chunk = str_replace('&nbsp;', ' ', $chunk);
                
                // Remove divs e spans que não contêm outros elementos HTML
                $chunk = preg_replace('/<(div|span)[^>]*>([^<]+)<\/\1>/i', '\2', $chunk);
            }
            
            return array_filter($chunks, function($chunk) {
                return !empty(trim(strip_tags($chunk)));
            });
        }
        
        // Se não tem subtítulos, divide por parágrafos
        return $this->split_by_paragraphs($content, $max_words);
    }
    
    /**
     * Divide conteúdo por parágrafos respeitando limite de palavras
     */
    private function split_by_paragraphs($content, $max_words) {
        $paragraphs = preg_split('/<\/p>\s*<p[^>]*>|\n\s*\n/', $content);
        $chunks = [];
        $current_chunk = '';
        $current_word_count = 0;
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            $paragraph_words = str_word_count(strip_tags($paragraph));
            
            if ($current_word_count + $paragraph_words > $max_words && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $paragraph;
                $current_word_count = $paragraph_words;
            } else {
                $current_chunk .= ($current_chunk ? "\n\n" : '') . $paragraph;
                $current_word_count += $paragraph_words;
            }
        }
        
        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }
        
        return array_filter($chunks);
    }
    
    /**
     * Sistema de validação em duas camadas para garantir qualidade da tradução
     * 
     * @param string $text_to_validate Texto traduzido para validar
     * @param string $expected_lang Código ISO do idioma esperado
     * @param array &$logs Array de logs (passado por referência)
     * @return bool True se validação passou, false caso contrário
     */
    private function validate_translation($text_to_validate, $expected_lang, &$logs) {
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'message' => "Iniciando validação em duas camadas para idioma: {$expected_lang}"
        ];
        
        // TIER 1: VALIDAÇÃO PROGRAMÁTICA
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'message' => "Tier 1: Iniciando validação programática"
        ];
        
        try {
            if (!class_exists('AlvoBotPro_Language_Validator')) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'warning',
                    'message' => "Language Validator não disponível - pulando para validação AI"
                ];
                return $this->ai_language_validation($text_to_validate, $expected_lang, $logs);
            }
            
            $validator = new AlvoBotPro_Language_Validator();
            
            // Verificar se o idioma é suportado
            if (!$validator->supports_language($expected_lang)) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'warning',
                    'message' => "Idioma '{$expected_lang}' não suportado pela validação programática - aceitando por padrão"
                ];
                return true;
            }
            
            // Calcular confiança
            $confidence = $validator->get_confidence_score($text_to_validate, $expected_lang);
            $confidence_percent = round($confidence * 100, 1);
            
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => "Validação programática para '{$expected_lang}': Confiança de {$confidence_percent}%"
            ];
            
            // Se confiança alta, aprovar imediatamente
            if ($confidence >= $this->validation_config['confidence_threshold']) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'success',
                    'message' => "Alta confiança programática ({$confidence_percent}%) - validação aprovada"
                ];
                return true;
            }
            
            // TIER 2: VALIDAÇÃO AI (FALLBACK)
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'warning',
                'message' => "Baixa confiança programática ({$confidence_percent}%). Acionando validação AI como segunda opinião"
            ];
            
            return $this->ai_language_validation($text_to_validate, $expected_lang, $logs);
            
        } catch (Exception $e) {
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'error',
                'message' => "Erro na validação programática: " . $e->getMessage() . " - usando fallback AI"
            ];
            
            return $this->ai_language_validation($text_to_validate, $expected_lang, $logs);
        }
    }
    
    /**
     * Tier 2: Validação AI-powered usando OpenAI
     * 
     * @param string $text_to_validate Texto para validar
     * @param string $expected_lang Código ISO esperado
     * @param array &$logs Array de logs
     * @return bool True se validação passou
     */
    private function ai_language_validation($text_to_validate, $expected_lang, &$logs) {
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'message' => "Tier 2: Iniciando validação AI"
        ];
        
        try {
            // Instanciar OpenAI Provider
            if (!class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "OpenAI Provider não disponível para validação AI"
                ];
                return false;
            }
            
            $openai_provider = new AlvoBotPro_OpenAI_Translation_Provider();
            
            if (!$openai_provider->is_configured()) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "OpenAI Provider não configurado - validação AI falhará"
                ];
                return false;
            }
            
            // Usar método especializado de validação de idioma
            $validation_result = $openai_provider->validate_language_with_ai($text_to_validate);
            
            if (!$validation_result['success']) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Falha na validação AI: " . ($validation_result['error'] ?? 'Erro desconhecido')
                ];
                return false;
            }
            
            // Processar resultado da AI
            $ai_detected_lang = $validation_result['detected_language'];
            
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'info',
                'message' => "AI detectou idioma: '{$ai_detected_lang}'. Esperado: '{$expected_lang}'"
            ];
            
            // Verificar match
            $validation_passed = ($ai_detected_lang === strtolower($expected_lang));
            
            if ($validation_passed) {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'success',
                    'message' => "Validação AI aprovada - idiomas coincidem"
                ];
            } else {
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Validação AI falhou - idiomas não coincidem ('{$ai_detected_lang}' != '{$expected_lang}')"
                ];
            }
            
            return $validation_passed;
            
        } catch (Exception $e) {
            $logs[] = [
                'timestamp' => current_time('mysql'),
                'level' => 'error',
                'message' => "Exceção na validação AI: " . $e->getMessage()
            ];
            return false;
        }
    }
    
    
    
    /**
     * Sistema de logs de progresso em tempo real
     * 
     * @param string $category Categoria do log (system, queue, translation, validation, etc.)
     * @param string $level Nível do log (info, success, warning, error)
     * @param string $message Mensagem do log
     * @param array $data Dados adicionais para contexto
     */
    private function log_progress($category, $level, $message, $data = []) {
        $timestamp = current_time('mysql');
        
        // Log direto com formatação melhorada
        $formatted_message = sprintf(
            '[%s] [%s:%s] %s',
            $timestamp,
            strtoupper($category),
            strtoupper($level),
            $message
        );
        
        // Adiciona dados extras se fornecidos
        if (!empty($data)) {
            $data_string = [];
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data_string[] = $key . ': [' . implode(', ', $value) . ']';
                } else {
                    $data_string[] = $key . ': ' . $value;
                }
            }
            $formatted_message .= ' | ' . implode(' | ', $data_string);
        }
        
        // Envia para o debug_log do WordPress
        AlvoBotPro::debug_log('multi-languages', $formatted_message);
        
        // Salva o log na tabela da fila se houver um item sendo processado
        if (isset($data['queue_id']) && $data['queue_id'] > 0) {
            global $wpdb;
            $queue_id = intval($data['queue_id']);
            
            // Busca logs existentes
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT logs FROM {$this->table_name} WHERE id = %d",
                $queue_id
            ));
            
            // Decodifica logs existentes ou inicia array vazio
            $logs = $item && $item->logs ? json_decode($item->logs, true) : [];
            if (!is_array($logs)) $logs = [];
            
            // Adiciona novo log
            $logs[] = [
                'timestamp' => $timestamp,
                'category' => $category,
                'level' => $level,
                'message' => $message,
                'data' => $data
            ];
            
            // Atualiza na tabela
            $wpdb->update(
                $this->table_name,
                ['logs' => json_encode($logs)],
                ['id' => $queue_id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    /**
     * Atualiza progresso no banco de dados em tempo real
     * 
     * @param int $queue_id ID do item na fila
     * @param int $progress Progresso atual (0-100)
     * @param string $status_message Mensagem de status atual (apenas para log)
     */
    private function update_progress_realtime($queue_id, $progress, $status_message = '') {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            ['progress' => min(100, max(0, $progress))],
            ['id' => $queue_id],
            ['%d'],
            ['%d']
        );
        
        $this->log_progress('progress', 'info', "📊 Progresso atualizado: {$progress}%", [
            'queue_id' => $queue_id,
            'progress' => $progress . '%',
            'status' => $status_message
        ]);
    }

    /**
     * Obtém tempo de execução atual
     * 
     * @return string Tempo de execução formatado
     */
    private function get_execution_time() {
        if (defined('ALVOBOT_PROCESS_START_TIME')) {
            $elapsed = microtime(true) - ALVOBOT_PROCESS_START_TIME;
            return round($elapsed, 3) . 's';
        }
        return 'N/A';
    }

    /**
     * Formata bytes para formato legível
     * 
     * @param int $bytes Bytes
     * @return string Formato legível (MB, KB, etc.)
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    /**
     * Obter um item específico da fila
     * 
     * @param int $queue_id ID do item
     * @return object|null Item da fila ou null se não encontrado
     */
    public function get_queue_item($queue_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'alvobot_translation_queue';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $queue_id
        ));
        
        return $result;
    }
    
    /**
     * Alias para get_queue_item para compatibilidade
     */
    public function get_item($queue_id) {
        return $this->get_queue_item($queue_id);
    }
    
    /**
     * Obter posição na fila
     * 
     * @param int $queue_id ID do item
     * @return int Posição na fila (1-indexed)
     */
    public function get_queue_position($queue_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'alvobot_translation_queue';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) + 1 
             FROM {$table_name} 
             WHERE status IN ('pending', 'processing') 
             AND (priority > (SELECT priority FROM {$table_name} WHERE id = %d)
                 OR (priority = (SELECT priority FROM {$table_name} WHERE id = %d) 
                     AND created_at < (SELECT created_at FROM {$table_name} WHERE id = %d)))",
            $queue_id, $queue_id, $queue_id
        ));
        
        return (int) $result;
    }
    
    /**
     * Estimar tempo de conclusão
     * 
     * @param int $queue_id ID do item
     * @return string Tempo estimado
     */
    public function get_estimated_completion_time($queue_id) {
        $position = $this->get_queue_position($queue_id);
        
        // Estimativa simples: 5 minutos por item
        $minutes = $position * 5;
        
        if ($minutes < 60) {
            return $minutes . ' minutos';
        } else {
            $hours = floor($minutes / 60);
            $remaining_minutes = $minutes % 60;
            return $hours . 'h ' . $remaining_minutes . 'm';
        }
    }
    
    /**
     * Obter total de itens na fila
     * 
     * @return int Total de itens
     */
    public function get_total_items() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'alvobot_translation_queue';
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
    
    /**
     * Obter itens à frente na fila
     * 
     * @param int $queue_id ID do item
     * @return int Número de itens à frente
     */
    public function get_items_ahead($queue_id) {
        return $this->get_queue_position($queue_id) - 1;
    }
    
    /**
     * Obter logs detalhados de um item específico da fila
     * 
     * @param int $queue_id ID do item
     * @return array Array de logs estruturados com payloads completos
     */
    public function get_item_logs($queue_id) {
        $item = $this->get_queue_item($queue_id);
        if (!$item) {
            return array();
        }
        
        $logs = array();
        
        // 1. Buscar logs do PHP error log
        $php_logs = $this->extract_php_error_logs($queue_id, $item->post_id);
        
        // 2. Buscar logs detalhados de requisições OpenAI
        $openai_logs = $this->extract_openai_request_logs($queue_id, $item->post_id);
        
        // 3. Buscar logs do sistema da fila
        $queue_logs = $this->extract_queue_system_logs($queue_id);
        
        // 4. Combinar e ordenar todos os logs por timestamp
        $all_logs = array_merge($php_logs, $openai_logs, $queue_logs);
        
        // Ordenar por timestamp
        usort($all_logs, function($a, $b) {
            $time_a = strtotime($a['timestamp'] ?? '1970-01-01');
            $time_b = strtotime($b['timestamp'] ?? '1970-01-01');
            return $time_a - $time_b;
        });
        
        return $all_logs;
    }
    
    /**
     * Extrair logs do PHP error log relacionados ao item
     */
    private function extract_php_error_logs($queue_id, $post_id) {
        $logs = array();
        
        // Tentar ler o arquivo de log do PHP
        $log_files = array(
            ini_get('error_log'),
            WP_CONTENT_DIR . '/debug.log',
            '/var/log/php_errors.log',
            '/var/log/apache2/error.log'
        );
        
        foreach ($log_files as $log_file) {
            if (!$log_file || !file_exists($log_file) || !is_readable($log_file)) {
                continue;
            }
            
            $file_logs = $this->parse_php_log_file($log_file, $queue_id, $post_id);
            $logs = array_merge($logs, $file_logs);
        }
        
        return $logs;
    }
    
    /**
     * Parse arquivo de log PHP procurando entradas relacionadas
     */
    private function parse_php_log_file($log_file, $queue_id, $post_id) {
        $logs = array();
        
        // Ler últimas 10MB do arquivo (para performance)
        $file_size = filesize($log_file);
        $chunk_size = min($file_size, 10 * 1024 * 1024); // 10MB
        
        $handle = fopen($log_file, 'r');
        if (!$handle) {
            return $logs;
        }
        
        fseek($handle, max(0, $file_size - $chunk_size));
        $content = fread($handle, $chunk_size);
        fclose($handle);
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            // Buscar por padrões relacionados ao nosso sistema
            if (strpos($line, 'multi-languages') !== false && 
                (strpos($line, "queue_id: {$queue_id}") !== false || 
                 strpos($line, "post_id: {$post_id}") !== false ||
                 strpos($line, "Post ID: {$post_id}") !== false ||
                 strpos($line, "Queue ID: {$queue_id}") !== false ||
                 strpos($line, "OPENAI_REQUEST") !== false ||
                 strpos($line, "OPENAI_RESPONSE") !== false)) {
                
                $parsed_log = $this->parse_log_line($line);
                if ($parsed_log) {
                    $logs[] = $parsed_log;
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Extrair logs detalhados de requisições OpenAI
     */
    private function extract_openai_request_logs($queue_id, $post_id) {
        $logs = array();
        
        // Buscar logs específicos de requisições OpenAI nos logs do WordPress
        $wp_logs = $this->get_wordpress_debug_logs($queue_id, $post_id);
        
        foreach ($wp_logs as $log) {
            if (strpos($log['message'], 'OPENAI_REQUEST') !== false || 
                strpos($log['message'], 'OPENAI_RESPONSE') !== false ||
                strpos($log['message'], 'OPENAI_SUCCESS') !== false ||
                strpos($log['message'], 'OPENAI_API_ERROR') !== false) {
                
                $parsed = $this->parse_openai_log($log);
                if ($parsed) {
                    $logs[] = $parsed;
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Extrair logs do sistema da fila
     */
    private function extract_queue_system_logs($queue_id) {
        $logs = array();
        
        // Buscar logs específicos do sistema de fila
        $queue_logs = $this->get_wordpress_debug_logs($queue_id, null);
        
        foreach ($queue_logs as $log) {
            if (strpos($log['message'], '[QUEUE:') !== false ||
                strpos($log['message'], '[SYSTEM:') !== false ||
                strpos($log['message'], '[TRANSLATION:') !== false ||
                strpos($log['message'], '[ENGINE:') !== false) {
                
                $logs[] = array(
                    'timestamp' => $log['timestamp'],
                    'level' => $this->extract_log_level($log['message']),
                    'type' => 'queue_system',
                    'message' => $log['message'],
                    'raw_data' => $log
                );
            }
        }
        
        return $logs;
    }
    
    /**
     * Obter logs de debug do WordPress
     */
    private function get_wordpress_debug_logs($queue_id, $post_id) {
        $logs = array();
        
        // Se WP_DEBUG_LOG estiver ativado, tentar ler o debug.log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_log = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($debug_log) && is_readable($debug_log)) {
                $file_logs = $this->parse_php_log_file($debug_log, $queue_id, $post_id);
                $logs = array_merge($logs, $file_logs);
            }
        }
        
        return $logs;
    }
    
    /**
     * Parse linha de log individual
     */
    private function parse_log_line($line) {
        // Padrão típico: [timestamp] [AlvoBot Pro - module] message
        if (preg_match('/\[([^\]]+)\]\s*\[AlvoBot Pro - ([^\]]+)\]\s*(.+)/', $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'module' => $matches[2],
                'message' => trim($matches[3]),
                'level' => $this->extract_log_level($matches[3]),
                'type' => 'system',
                'raw_line' => $line
            );
        }
        
        // Padrão alternativo para logs PHP
        if (preg_match('/\[([^\]]+)\]\s*(.+)/', $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'message' => trim($matches[2]),
                'level' => $this->extract_log_level($matches[2]),
                'type' => 'php',
                'raw_line' => $line
            );
        }
        
        return null;
    }
    
    /**
     * Parse log específico do OpenAI com payload completo
     */
    private function parse_openai_log($log) {
        $message = $log['message'];
        
        // Extrair JSON do log
        if (strpos($message, '🚀 OPENAI REQUEST START:') !== false) {
            $json_start = strpos($message, '{');
            if ($json_start !== false) {
                $json_data = substr($message, $json_start);
                $parsed_data = json_decode($json_data, true);
                
                if ($parsed_data) {
                    return array(
                        'timestamp' => $log['timestamp'],
                        'level' => 'info',
                        'type' => 'openai_request',
                        'event' => 'REQUEST_START',
                        'message' => 'OpenAI Request Started',
                        'request_details' => $parsed_data['request_details'] ?? null,
                        'text_analytics' => $parsed_data['text_analytics'] ?? null,
                        'cost_estimation' => $parsed_data['cost_estimation'] ?? null,
                        'headers' => $parsed_data['headers'] ?? null,
                        'payload' => $parsed_data['payload'] ?? null,
                        'curl_equivalent' => $parsed_data['curl_equivalent'] ?? null,
                        'raw_data' => $parsed_data
                    );
                }
            }
        }
        
        if (strpos($message, '📥 OPENAI RESPONSE:') !== false) {
            $json_start = strpos($message, '{');
            if ($json_start !== false) {
                $json_data = substr($message, $json_start);
                $parsed_data = json_decode($json_data, true);
                
                if ($parsed_data) {
                    return array(
                        'timestamp' => $log['timestamp'],
                        'level' => 'info',
                        'type' => 'openai_response',
                        'event' => 'RESPONSE_RECEIVED',
                        'message' => 'OpenAI Response Received',
                        'response_details' => $parsed_data['response_details'] ?? null,
                        'rate_limit_info' => $parsed_data['rate_limit_info'] ?? null,
                        'response_headers' => $parsed_data['response_headers'] ?? null,
                        'response_body' => $parsed_data['full_response_body'] ?? null,
                        'raw_data' => $parsed_data
                    );
                }
            }
        }
        
        if (strpos($message, '✅ OPENAI SUCCESS:') !== false) {
            $json_start = strpos($message, '{');
            if ($json_start !== false) {
                $json_data = substr($message, $json_start);
                $parsed_data = json_decode($json_data, true);
                
                if ($parsed_data) {
                    return array(
                        'timestamp' => $log['timestamp'],
                        'level' => 'success',
                        'type' => 'openai_success',
                        'event' => 'SUCCESS',
                        'message' => 'OpenAI Request Successful',
                        'success_details' => $parsed_data['success_details'] ?? null,
                        'token_usage' => $parsed_data['token_usage'] ?? null,
                        'cost_calculation' => $parsed_data['cost_calculation'] ?? null,
                        'translation_result' => $parsed_data['translation_result'] ?? null,
                        'raw_data' => $parsed_data
                    );
                }
            }
        }
        
        if (strpos($message, '💥 OPENAI API ERROR:') !== false) {
            $json_start = strpos($message, '{');
            if ($json_start !== false) {
                $json_data = substr($message, $json_start);
                $parsed_data = json_decode($json_data, true);
                
                if ($parsed_data) {
                    return array(
                        'timestamp' => $log['timestamp'],
                        'level' => 'error',
                        'type' => 'openai_error',
                        'event' => 'API_ERROR',
                        'message' => 'OpenAI API Error',
                        'error_details' => $parsed_data['error_details'] ?? null,
                        'rate_limit_analysis' => $parsed_data['rate_limit_analysis'] ?? null,
                        'full_error_response' => $parsed_data['full_error_response'] ?? null,
                        'raw_data' => $parsed_data
                    );
                }
            }
        }
        
        return array(
            'timestamp' => $log['timestamp'],
            'level' => 'info',
            'type' => 'openai_generic',
            'message' => $message,
            'raw_data' => $log
        );
    }
    
    /**
     * Extrair nível do log da mensagem
     */
    private function extract_log_level($message) {
        if (strpos($message, 'ERROR') !== false || strpos($message, '💥') !== false) {
            return 'error';
        }
        if (strpos($message, 'WARNING') !== false || strpos($message, '⚠️') !== false) {
            return 'warning';
        }
        if (strpos($message, 'SUCCESS') !== false || strpos($message, '✅') !== false) {
            return 'success';
        }
        return 'info';
    }
}