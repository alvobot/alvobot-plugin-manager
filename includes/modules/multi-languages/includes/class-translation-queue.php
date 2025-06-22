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
            'orphan_timeout_minutes' => 5,           // Timeout para itens órfãos
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
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'processing' AND started_at IS NOT NULL 
             ORDER BY started_at DESC 
             LIMIT 1"
        ));
        
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
            
            // Implementar tradução real com OpenAI
            $logs = [
                [
                    'timestamp' => current_time('mysql'),
                    'level' => 'info',
                    'message' => "🚀 Iniciado processamento do post '{$post->post_title}'",
                    'progress_data' => [
                        'step' => 'initialization',
                        'total_languages' => count($target_langs),
                        'estimated_chunks' => 'calculating...'
                    ]
                ]
            ];
            
            $source_lang = $item->source_lang;
            $progress = 15; // Progresso inicial mais alto após validações
            $translated_posts_created = 0;
            
            foreach ($target_langs as $lang_index => $target_lang) {
                try {
                    $lang_start_time = microtime(true);
                    $current_lang_progress = $lang_index + 1;
                    
                    $this->log_progress('translation', 'info', "🌍 Iniciando tradução para {$target_lang}", [
                        'queue_id' => $item->id,
                        'source_lang' => $source_lang,
                        'target_lang' => $target_lang,
                        'language_progress' => "{$current_lang_progress}/{" . count($target_langs) . "}",
                        'overall_progress' => $progress . '%',
                        'memory_usage' => $this->format_bytes(memory_get_usage())
                    ]);
                    
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'info',
                        'message' => "🌍 Traduzindo de '{$source_lang}' para '{$target_lang}' ({$current_lang_progress}/" . count($target_langs) . ")",
                        'progress_data' => [
                            'current_language' => $target_lang,
                            'language_index' => $current_lang_progress,
                            'total_languages' => count($target_langs),
                            'overall_progress' => $progress
                        ]
                    ];
                    
                    // Atualizar progresso no banco
                    $this->update_progress_realtime($item->id, $progress, "Traduzindo para {$target_lang}");
                    
                    // PRIMEIRA VERIFICAÇÃO: Verificar se já existe tradução para este idioma (mais robusta)
                    $this->log_progress('polylang', 'info', '🔍 Verificando traduções existentes', [
                        'queue_id' => $item->id,
                        'target_lang' => $target_lang,
                        'post_id' => $post->ID
                    ]);
                    
                    if (function_exists('pll_get_post_translations')) {
                        $polylang_check_start = microtime(true);
                        $translations = pll_get_post_translations($post->ID);
                        $polylang_check_time = microtime(true) - $polylang_check_start;
                        
                        // Verificação adicional por query direta para garantir
                        global $wpdb;
                        $existing_post = $wpdb->get_var($wpdb->prepare(
                            "SELECT p.ID FROM {$wpdb->posts} p 
                             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                             WHERE p.post_status IN ('publish', 'draft') 
                             AND p.post_type = %s
                             AND tt.taxonomy = 'language'
                             AND t.slug = %s
                             AND p.ID != %d",
                            $post->post_type,
                            $target_lang,
                            $post->ID
                        ));
                        
                        $this->log_progress('polylang', 'info', '✅ Verificação Polylang concluída', [
                            'queue_id' => $item->id,
                            'check_time' => round($polylang_check_time * 1000, 2) . 'ms',
                            'existing_translations' => array_keys($translations),
                            'total_existing' => count($translations),
                            'direct_query_result' => $existing_post ? 'found_duplicate' : 'no_duplicate'
                        ]);
                        
                        // Verifica tanto pelo Polylang quanto pela query direta
                        if (isset($translations[$target_lang]) || $existing_post) {
                            $existing_id = isset($translations[$target_lang]) ? $translations[$target_lang] : $existing_post;
                            
                            $this->log_progress('translation', 'error', "❌ Tradução já existe para {$target_lang}", [
                                'queue_id' => $item->id,
                                'existing_post_id' => $existing_id,
                                'detection_method' => isset($translations[$target_lang]) ? 'polylang' : 'direct_query',
                                'skipping_language' => $target_lang
                            ]);
                            
                            $logs[] = [
                                'timestamp' => current_time('mysql'),
                                'level' => 'error',
                                'message' => "❌ Já existe uma tradução para '{$target_lang}' (Post ID: {$existing_id})"
                            ];
                            
                            // Marcar como erro em vez de sucesso parcial
                            throw new Exception("Já existe uma tradução para este idioma");
                        }
                    }
                    
                    // Obter instância do translation engine
                    $this->log_progress('engine', 'info', '🔧 Carregando motor de tradução', [
                        'queue_id' => $item->id
                    ]);
                    
                    $engine_start = microtime(true);
                    $translation_engine = $this->get_translation_engine();
                    $engine_load_time = microtime(true) - $engine_start;
                    
                    if (!$translation_engine) {
                        throw new Exception("Motor de tradução não disponível");
                    }
                    
                    $this->log_progress('engine', 'success', '✅ Motor de tradução carregado', [
                        'queue_id' => $item->id,
                        'load_time' => round($engine_load_time * 1000, 2) . 'ms',
                        'engine_class' => get_class($translation_engine)
                    ]);
                    
                    // Traduzir baseado no tipo de conteúdo
                    $translation_start = microtime(true);
                    $this->log_progress('translation', 'info', "🎯 Iniciando tradução de {$post->post_type}", [
                        'queue_id' => $item->id,
                        'post_type' => $post->post_type,
                        'content_length' => strlen($post->post_content),
                        'strategy' => $post->post_type === 'post' ? 'article_chunks' : 'page_strings'
                    ]);
                    
                    if ($post->post_type === 'post') {
                        $translation_result = $this->translate_article($post, $source_lang, $target_lang, $translation_engine, $logs, $item->id);
                    } else {
                        $translation_result = $this->translate_page($post, $source_lang, $target_lang, $translation_engine, $logs, $item->id);
                    }
                    
                    $translation_time = microtime(true) - $translation_start;
                    
                    if (!$translation_result['success']) {
                        throw new Exception($translation_result['error'] ?? 'Falha na tradução');
                    }
                    
                    $this->log_progress('translation', 'success', "✅ Tradução concluída para {$target_lang}", [
                        'queue_id' => $item->id,
                        'translation_time' => round($translation_time, 2) . 's',
                        'original_title' => $post->post_title,
                        'translated_title' => $translation_result['title'],
                        'original_length' => strlen($post->post_content),
                        'translated_length' => strlen($translation_result['content']),
                        'chunks_translated' => $translation_result['chunks_translated'] ?? 'N/A',
                        'method' => $translation_result['translation_method'] ?? 'unknown'
                    ]);
                    
                    $translated_title = $translation_result['title'];
                    $translated_content = $translation_result['content'];
                    $logs = array_merge($logs, $translation_result['logs'] ?? []);
                    
                    // VALIDAÇÃO EM DUAS CAMADAS: Programática + AI
                    $validation_start = microtime(true);
                    $full_translated_text = $translated_title . ' ' . wp_strip_all_tags($translated_content);
                    
                    $this->log_progress('validation', 'info', '🔍 Iniciando validação em duas camadas', [
                        'queue_id' => $item->id,
                        'text_length' => strlen($full_translated_text),
                        'expected_language' => $target_lang,
                        'validation_strategy' => 'programmatic_plus_ai'
                    ]);
                    
                    $validation_passed = $this->validate_translation($full_translated_text, $target_lang, $logs);
                    $validation_time = microtime(true) - $validation_start;
                    
                    if (!$validation_passed) {
                        $this->log_progress('validation', 'error', "❌ Validação falhou para {$target_lang}", [
                            'queue_id' => $item->id,
                            'validation_time' => round($validation_time, 2) . 's',
                            'result' => 'draft',
                            'reason' => 'failed_language_validation'
                        ]);
                        
                        $logs[] = [
                            'timestamp' => current_time('mysql'),
                            'level' => 'error',
                            'message' => "❌ Validação de idioma falhou - post será criado como rascunho para revisão manual",
                            'progress_data' => [
                                'validation_time' => round($validation_time, 2) . 's',
                                'status' => 'draft'
                            ]
                        ];
                        
                        // Criar post como rascunho quando validação falha
                        $post_creation_start = microtime(true);
                        $translated_post_id = $this->create_translated_post($post, $translated_title, $translated_content, $target_lang, 'draft');
                        $post_creation_time = microtime(true) - $post_creation_start;
                        
                        if ($translated_post_id) {
                            $this->log_progress('post', 'warning', "⚠️ Post criado como RASCUNHO", [
                                'queue_id' => $item->id,
                                'post_id' => $translated_post_id,
                                'status' => 'draft',
                                'creation_time' => round($post_creation_time, 2) . 's',
                                'requires_review' => true
                            ]);
                            
                            $logs[] = [
                                'timestamp' => current_time('mysql'),
                                'level' => 'warning',
                                'message' => "⚠️ Post traduzido criado como rascunho (ID: {$translated_post_id}) - requer revisão",
                                'progress_data' => [
                                    'post_id' => $translated_post_id,
                                    'status' => 'draft'
                                ]
                            ];
                        }
                        
                        $progress += (75 / count($target_langs)); // Progresso mesmo falhando validação
                        continue;
                    }
                    
                    $this->log_progress('validation', 'success', "✅ Validação aprovada para {$target_lang}", [
                        'queue_id' => $item->id,
                        'validation_time' => round($validation_time, 2) . 's',
                        'result' => 'publish',
                        'quality_approved' => true
                    ]);
                    
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'success',
                        'message' => "✅ Validação de idioma aprovada - criando post para publicação",
                        'progress_data' => [
                            'validation_result' => 'passed',
                            'status' => 'publish'
                        ]
                    ];
                    
                    // Criar post traduzido e publicar quando validação passa
                    $post_creation_start = microtime(true);
                    $translated_post_id = $this->create_translated_post($post, $translated_title, $translated_content, $target_lang, 'publish');
                    $post_creation_time = microtime(true) - $post_creation_start;
                    
                    if (!$translated_post_id) {
                        throw new Exception("Falha ao criar post traduzido");
                    }
                    
                    $this->log_progress('post', 'success', "🎉 Post PUBLICADO com sucesso", [
                        'queue_id' => $item->id,
                        'post_id' => $translated_post_id,
                        'status' => 'publish',
                        'creation_time' => round($post_creation_time, 2) . 's',
                        'language' => $target_lang,
                        'total_process_time' => round(microtime(true) - $lang_start_time, 2) . 's'
                    ]);
                    
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'success',
                        'message' => "🎉 Post traduzido criado com sucesso (ID: {$translated_post_id})",
                        'progress_data' => [
                            'post_id' => $translated_post_id,
                            'status' => 'publish',
                            'language' => $target_lang
                        ]
                    ];
                    
                    $translated_posts_created++;
                    $progress += (75 / count($target_langs));
                    
                } catch (Exception $e) {
                    $logs[] = [
                        'timestamp' => current_time('mysql'),
                        'level' => 'error',
                        'message' => "Erro ao traduzir para '{$target_lang}': " . $e->getMessage()
                    ];
                    AlvoBotPro::debug_log('multi-languages', "Erro tradução {$target_lang}: " . $e->getMessage());
                }
            }
            
            // Determinar status final
            if ($translated_posts_created === count($target_langs)) {
                $final_status = 'completed';
                $final_progress = 100;
                $logs[] = [
                    'timestamp' => current_time('mysql'),
                    'level' => 'success',
                    'message' => "Tradução concluída com sucesso para todos os idiomas"
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
            
            // Incrementa contador de posts traduzidos se a tradução foi bem-sucedida
            if ($final_status === 'completed' && $translated_posts_created > 0) {
                if (class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
                    $provider = new AlvoBotPro_OpenAI_Translation_Provider();
                    if (method_exists($provider, 'increment_post_translation_count')) {
                        // Incrementa uma vez por idioma traduzido (não apenas uma vez por post)
                        for ($i = 0; $i < $translated_posts_created; $i++) {
                            $provider->increment_post_translation_count();
                        }
                        AlvoBotPro::debug_log('multi-languages', "Contador incrementado {$translated_posts_created} vezes para item {$item->id}");
                    }
                }
            }
            
            AlvoBotPro::debug_log('multi-languages', "Item {$item->id} finalizado com status '{$final_status}' - {$translated_posts_created} traduções criadas");
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            AlvoBotPro::debug_log('multi-languages', "Erro ao processar item {$item->id}: {$error_message}");
            
            $logs = [
                [
                    'timestamp' => current_time('mysql'),
                    'level' => 'error',
                    'message' => "Erro: {$error_message}"
                ]
            ];
            
            $wpdb->update($this->table_name, [
                'status' => 'failed',
                'progress' => 0,
                'logs' => json_encode($logs),
                'error_message' => $error_message,
                'completed_at' => current_time('mysql')
            ], ['id' => $item->id]);
        }
        
        return true;
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
    
    public function download_logs_handler() {
        // SEGURANÇA: Verificar nonce com nome padronizado e permissões
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'alvobot_multi_languages_nonce')) {
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
        
        $logs = json_decode($logs_json, true);
        if (is_array($logs) && !empty($logs)) {
            foreach ($logs as $log) {
                $timestamp = isset($log['timestamp']) ? sanitize_text_field($log['timestamp']) : 'N/A';
                $level = isset($log['level']) ? strtoupper(sanitize_text_field($log['level'])) : 'INFO';
                $message = isset($log['message']) ? sanitize_text_field($log['message']) : '';
                echo "[{$timestamp}] [{$level}] {$message}\n";
            }
        } else {
            echo "Nenhum log encontrado para este item.";
        }
        exit;
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
        
        // Força reset de todos os itens em processing (independente do tempo)
        global $wpdb;
        $this->create_table();
        
        $processing_items = $wpdb->get_results(
            "SELECT id, post_id, started_at FROM {$this->table_name} WHERE status = 'processing'"
        );
        
        if (!empty($processing_items)) {
            foreach ($processing_items as $item) {
                AlvoBotPro::debug_log('multi-languages', "Forçando reset do item {$item->id} (post {$item->post_id})");
                
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
                'message' => 'Resetados ' . count($processing_items) . ' itens órfãos',
                'items_reset' => count($processing_items)
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
                
                $this->log_progress('chunk', 'info', "🔄 Traduzindo chunk {$chunk_number}/{count($content_chunks)}", [
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
}