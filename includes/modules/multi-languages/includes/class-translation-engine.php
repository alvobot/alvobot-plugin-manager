<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_Translation_Engine')) {
    return;
}

/**
 * Motor principal de tradução do AlvoBot Multi Languages
 * 
 * Gerencia provedores de tradução, cache, logs e processamento de conteúdo
 */
class AlvoBotPro_Translation_Engine {
    
    /** @var array Provedores de tradução disponíveis */
    private $providers = [];
    
    /** @var string Provider ativo */
    private $active_provider = '';
    
    /** @var array Cache de traduções */
    private $translation_cache = [];
    
    /** @var array Configurações do motor */
    private $settings = [];
    
    /** @var int Limite máximo de cache */
    private const MAX_CACHE_SIZE = 1000;
    
    /** @var int Timeout para requisições */
    private const REQUEST_TIMEOUT = 30;

    public function __construct() {
        $this->load_settings();
        $this->init_providers();
        $this->load_cache();
        
        add_action('wp_ajax_alvobot_translate_content', array($this, 'ajax_translate_content'));
        add_action('wp_ajax_alvobot_get_translation_status', array($this, 'ajax_get_translation_status'));
        add_action('wp_ajax_alvobot_save_translation', array($this, 'ajax_save_translation'));
        add_action('wp_ajax_alvobot_batch_translate', array($this, 'ajax_batch_translate'));
    }

    /**
     * Carrega as configurações do motor de tradução
     */
    private function load_settings() {
        // Carrega configurações do OpenAI
        $openai_settings = get_option('alvobot_openai_settings', array());
        
        $default_settings = array(
            'active_provider' => 'openai',
            'cache_enabled' => !(isset($openai_settings['disable_cache']) && $openai_settings['disable_cache']),
            'cache_duration' => DAY_IN_SECONDS * 7, // 7 dias
            'batch_size' => 5, // Menor para OpenAI por causa dos custos
            'max_string_length' => 8000,
            'preserve_formatting' => true,
            'translate_links' => true,
            'auto_detect_language' => true,
            'rate_limiting' => true,
            'rate_limit_requests' => 60, // Limitação mais restritiva para OpenAI
            'rate_limit_period' => HOUR_IN_SECONDS
        );
        
        $saved_settings = get_option('alvobot_multi_languages_settings', array());
        $this->settings = array_merge($default_settings, $saved_settings);
        $this->active_provider = $this->settings['active_provider'];
        
        AlvoBotPro::debug_log('multi-languages', 'Settings carregados. Provider ativo: ' . $this->active_provider);
    }

    /**
     * Inicializa o provedor de tradução OpenAI
     */
    private function init_providers() {
        try {
            // Inicializa apenas o provider OpenAI diretamente
            if (class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
                $this->providers['openai'] = new AlvoBotPro_OpenAI_Translation_Provider();
                AlvoBotPro::debug_log('multi-languages', 'Provider OpenAI inicializado');
            } else {
                AlvoBotPro::debug_log('multi-languages', 'Classe OpenAI Provider não encontrada');
            }
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'Erro ao inicializar provider: ' . $e->getMessage());
        }
    }
    

    /**
     * Carrega o cache de traduções
     */
    private function load_cache() {
        if ($this->settings['cache_enabled']) {
            $this->translation_cache = get_transient('alvobot_translation_cache') ?: array();
        }
    }

    /**
     * Salva o cache de traduções
     */
    private function save_cache() {
        if ($this->settings['cache_enabled']) {
            // Limita o tamanho do cache
            if (count($this->translation_cache) > self::MAX_CACHE_SIZE) {
                $this->translation_cache = array_slice($this->translation_cache, -self::MAX_CACHE_SIZE, null, true);
            }
            
            set_transient('alvobot_translation_cache', $this->translation_cache, $this->settings['cache_duration']);
        }
    }

    /**
     * Traduz um texto usando o provider ativo
     * 
     * @param string $text Texto a ser traduzido
     * @param string $source_lang Idioma de origem
     * @param string $target_lang Idioma de destino
     * @param array $options Opções adicionais
     * @return array Resultado da tradução
     */
    public function translate_text($text, $source_lang, $target_lang, $options = array()) {
        try {
            // Validação de entrada
            $text_string = is_array($text) ? $text['text'] : $text;
            
            if (empty($text_string) || empty($target_lang)) {
                return $this->error_response('Texto ou idioma de destino não informado');
            }

            // Gera cache key
            $cache_key = $this->get_cache_key($text, $source_lang, $target_lang);
            
            // Verifica se já existe no cache
            if ($this->settings['cache_enabled'] && isset($this->translation_cache[$cache_key])) {
                $text_string = is_array($text) ? $text['text'] : $text;
                $this->log_action('cache_hit', 'info', 'Tradução encontrada no cache', array(
                    'cache_key' => $cache_key,
                    'text_length' => strlen($text_string)
                ));
                return $this->translation_cache[$cache_key];
            }

            // Verifica rate limiting
            if ($this->settings['rate_limiting'] && !$this->check_rate_limit()) {
                return $this->error_response('Rate limit excedido. Tente novamente em alguns minutos.');
            }

            // Auto-detecção de idioma
            if ($this->settings['auto_detect_language'] && empty($source_lang)) {
                $text_string = is_array($text) ? $text['text'] : $text;
                $source_lang = $this->detect_language($text_string);
            }

            // Verifica se o provider está disponível
            if (!isset($this->providers[$this->active_provider])) {
                return $this->error_response('Provider de tradução não encontrado: ' . $this->active_provider);
            }

            $provider = $this->providers[$this->active_provider];

            // Verifica se o provider está configurado
            if (!$provider->is_configured()) {
                return $this->error_response('Provider não está configurado corretamente');
            }

            // Preprocessa o texto
            $processed_text = $this->preprocess_text($text, $options);
            
            // Realiza a tradução
            $result = $provider->translate($processed_text, $source_lang, $target_lang, $options);

            if ($result['success']) {
                // Pós-processa o texto traduzido
                $original_text_string = is_array($text) ? $text['text'] : $text;
                $result['translated_text'] = $this->postprocess_text($result['translated_text'], $original_text_string, $options);

                // Adiciona ao cache
                if ($this->settings['cache_enabled']) {
                    $this->translation_cache[$cache_key] = $result;
                    $this->save_cache();
                }

                // Atualiza estatísticas de uso se for OpenAI
                if ($this->active_provider === 'openai' && isset($result['usage'])) {
                    $provider->update_usage_stats($result['usage']);
                }

                // Log da tradução bem-sucedida
                $this->log_action('translation_success', 'info', 'Tradução realizada com sucesso', array(
                    'provider' => $this->active_provider,
                    'source_lang' => $source_lang,
                    'target_lang' => $target_lang,
                    'text_length' => strlen($text),
                    'translated_length' => strlen($result['translated_text']),
                    'tokens_used' => $result['usage']['total_tokens'] ?? 0
                ));
            } else {
                // Log do erro
                $this->log_action('translation_error', 'error', 'Erro na tradução', array(
                    'provider' => $this->active_provider,
                    'error' => $result['error']
                ));
            }

            // Incrementa contador de rate limiting
            $this->increment_rate_limit();

            return $result;

        } catch (Exception $e) {
            $this->log_action('translation_exception', 'error', 'Exceção durante tradução', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            
            return $this->error_response('Erro interno: ' . $e->getMessage());
        }
    }

    /**
     * Traduz múltiplos textos em lote
     * 
     * @param array $texts Array de textos
     * @param string $source_lang Idioma de origem
     * @param string $target_lang Idioma de destino
     * @param array $options Opções adicionais
     * @return array Resultados das traduções
     */
    public function batch_translate($texts, $source_lang, $target_lang, $options = array()) {
        // ===== DEBUG: Log início do batch_translate =====
        error_log('====== BATCH TRANSLATE DEBUG START ======');
        error_log('Total texts to translate: ' . count($texts));
        error_log('Source language: ' . $source_lang);
        error_log('Target language: ' . $target_lang);
        error_log('Options: ' . json_encode($options));
        
        $results = array();
        $batch_size = $this->settings['batch_size'];
        
        // Debug: Analisa cada texto antes de processar
        $valid_texts = 0;
        $empty_texts = 0;
        $sample_texts = array();
        
        foreach ($texts as $index => $text_data) {
            $text_content = is_array($text_data) ? $text_data['text'] : $text_data;
            
            if (empty(trim($text_content))) {
                $empty_texts++;
            } else {
                $valid_texts++;
                if (count($sample_texts) < 5) {
                    $sample_texts[] = array(
                        'index' => $index,
                        'content' => substr($text_content, 0, 100) . (strlen($text_content) > 100 ? '...' : ''),
                        'length' => strlen($text_content)
                    );
                }
            }
        }
        
        error_log('Valid texts: ' . $valid_texts);
        error_log('Empty texts: ' . $empty_texts);
        error_log('Sample texts: ' . json_encode($sample_texts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Processa em lotes
        $text_chunks = array_chunk($texts, $batch_size);
        
        foreach ($text_chunks as $chunk_index => $chunk) {
            error_log("Processing chunk {$chunk_index} with " . count($chunk) . " texts");
            $chunk_results = array();
            
            foreach ($chunk as $text_index => $text_data) {
                // Extrai o texto do array de dados
                $text_content = is_array($text_data) ? $text_data['text'] : $text_data;
                $text_options = array_merge($options, array(
                    'preserve_html' => isset($text_data['preserve_html']) ? $text_data['preserve_html'] : false,
                    'context' => isset($text_data['context']) ? $text_data['context'] : ''
                ));
                
                // Debug: Log cada texto antes de traduzir
                error_log("====== TRANSLATING TEXT {$text_index} ======");
                error_log('Text content: ' . substr($text_content, 0, 200) . (strlen($text_content) > 200 ? '...' : ''));
                error_log('Text length: ' . strlen($text_content));
                error_log('Context: ' . ($text_options['context'] ?? 'None'));
                error_log('Preserve HTML: ' . ($text_options['preserve_html'] ? 'YES' : 'NO'));
                
                $result = $this->translate_text($text_content, $source_lang, $target_lang, $text_options);
                
                // Debug: Log resultado de cada tradução
                error_log('Translation result success: ' . ($result['success'] ? 'YES' : 'NO'));
                if ($result['success']) {
                    error_log('Translated text: ' . substr($result['translated_text'] ?? '', 0, 200));
                } else {
                    error_log('Translation error: ' . ($result['error'] ?? 'Unknown error'));
                }
                
                $chunk_results[$text_index] = $result;
                
                // Pequena pausa entre traduções para evitar rate limiting
                if ($text_index < count($chunk) - 1) {
                    usleep(100000); // 100ms
                }
            }
            
            $results = array_merge($results, $chunk_results);
            
            // Pausa maior entre lotes
            if ($chunk_index < count($text_chunks) - 1) {
                sleep(1);
            }
        }
        
        error_log('====== BATCH TRANSLATE DEBUG END ======');
        error_log('Total results: ' . count($results));
        
        return $results;
    }

    /**
     * Traduz conteúdo de post/página
     * 
     * @param int $post_id ID do post
     * @param string $target_lang Idioma de destino
     * @param array $options Opções de tradução
     * @return array Resultado da tradução
     */
    public function translate_post_content($post_id, $target_lang, $options = array()) {
        AlvoBotPro::debug_log('multi-languages', 'Iniciando tradução - Post ID: ' . $post_id . ', Target: ' . $target_lang);
        
        $post = get_post($post_id);
        if (!$post) {
            AlvoBotPro::debug_log('multi-languages', 'Post não encontrado: ' . $post_id);
            return $this->error_response('Post não encontrado');
        }

        $source_lang = $this->detect_post_language($post_id);
        AlvoBotPro::debug_log('multi-languages', 'Idioma detectado: ' . $source_lang);

        // Lógica diferente para Posts vs Páginas
        if ($post->post_type === 'post') {
            return $this->translate_post_content_blocks($post, $source_lang, $target_lang, $options);
        } else {
            return $this->translate_post_content_strings($post, $source_lang, $target_lang, $options);
        }
    }

    /**
     * Traduz posts usando chunks divididos por subtítulos (máximo 500 palavras)
     */
    private function translate_post_content_blocks($post, $source_lang, $target_lang, $options = array()) {
        AlvoBotPro::debug_log('multi-languages', 'Traduzindo POST em chunks divididos por subtítulos');
        
        // Limpa o conteúdo removendo scripts e mantendo apenas HTML padrão
        $clean_content = $this->clean_post_content($post->post_content);
        $clean_content = $this->deep_clean_content($clean_content);
        
        // Divide o conteúdo em chunks baseados nos subtítulos
        $content_chunks = $this->split_content_by_headings($clean_content, 500);
        
        $blocks_to_translate = array();
        
        // 1. Título
        if (!empty($post->post_title)) {
            $blocks_to_translate['title'] = array(
                'text' => $post->post_title,
                'type' => 'title',
                'preserve_html' => false
            );
        }
        
        // 2. Descrição (excerpt)
        $excerpt = get_the_excerpt($post->ID);
        if (!empty($excerpt)) {
            $blocks_to_translate['excerpt'] = array(
                'text' => $excerpt,
                'type' => 'excerpt', 
                'preserve_html' => false
            );
        }
        
        // 3. Chunks de conteúdo
        foreach ($content_chunks as $index => $chunk) {
            if (!empty(trim(strip_tags($chunk['content'])))) {
                $blocks_to_translate["chunk_{$index}"] = array(
                    'text' => $chunk['content'],
                    'type' => 'content_chunk',
                    'heading' => $chunk['heading'] ?? null,
                    'word_count' => $chunk['word_count'],
                    'preserve_html' => true
                );
            }
        }
        
        $total_blocks = count($blocks_to_translate);
        $translated_blocks = array();
        $current_block = 0;
        
        AlvoBotPro::debug_log('multi-languages', "Total de chunks para traduzir: {$total_blocks}");
        
        // Traduz cada chunk separadamente
        foreach ($blocks_to_translate as $block_id => $block_data) {
            $current_block++;
            $block_type = $block_data['type'];
            $block_content = $block_data['text'];
            
            AlvoBotPro::debug_log('multi-languages', "Traduzindo chunk {$current_block}/{$total_blocks}: {$block_id} ({$block_type})");
            
            // Configura opções específicas para cada tipo
            $translation_options = array_merge($options, array(
                'preserve_html' => $block_data['preserve_html'],
                'context' => $this->get_translation_context($block_type, $block_data)
            ));
            
            $translation_result = $this->translate_text_block($block_content, $source_lang, $target_lang, $translation_options);
            
            if ($translation_result['success']) {
                $translated_blocks[$block_id] = $translation_result['translated_text'];
                AlvoBotPro::debug_log('multi-languages', "Chunk {$block_id} traduzido com sucesso");
            } else {
                AlvoBotPro::debug_log('multi-languages', "Falha na tradução do chunk {$block_id}: " . ($translation_result['error'] ?? 'Erro desconhecido'));
                $translated_blocks[$block_id] = $block_content; // Mantém original em caso de erro
            }
        }
        
        // Reconstrói o conteúdo final juntando todos os chunks traduzidos
        $final_content = $this->rebuild_content_from_chunks($content_chunks, $translated_blocks);
        
        return array(
            'success' => true,
            'original_content' => $post->post_content,
            'translated_content' => $final_content,
            'translated_title' => $translated_blocks['title'] ?? $post->post_title,
            'translated_excerpt' => $translated_blocks['excerpt'] ?? '',
            'source_language' => $source_lang,
            'target_language' => $target_lang,
            'chunks_translated' => count($content_chunks),
            'total_strings_translated' => $total_blocks,
            'translation_method' => 'heading_chunks'
        );
    }

    /**
     * Traduz páginas usando o sistema de strings (compatível com Gutenberg/Elementor)
     */
    private function translate_post_content_strings($post, $source_lang, $target_lang, $options = array()) {
        AlvoBotPro::debug_log('multi-languages', 'Traduzindo conteúdo do post: ' . $post->ID);
        
        // Simplesmente traduz o conteúdo completo
        $content = $post->post_content;
        $title = $post->post_title;
        $excerpt = $post->post_excerpt;
        
        // Traduz título
        $translated_title = '';
        if (!empty($title)) {
            $title_result = $this->translate_text($title, $source_lang, $target_lang, $options);
            $translated_title = $title_result['success'] ? $title_result['translated_text'] : $title;
        }
        
        // Traduz conteúdo
        $translated_content = '';
        if (!empty($content)) {
            $content_result = $this->translate_text($content, $source_lang, $target_lang, $options);
            $translated_content = $content_result['success'] ? $content_result['translated_text'] : $content;
        }
        
        // Traduz excerpt se existir
        $translated_excerpt = '';
        if (!empty($excerpt)) {
            $excerpt_result = $this->translate_text($excerpt, $source_lang, $target_lang, $options);
            $translated_excerpt = $excerpt_result['success'] ? $excerpt_result['translated_text'] : $excerpt;
        }

        return array(
            'success' => true,
            'post_title' => $translated_title,
            'post_content' => $translated_content,
            'post_excerpt' => $translated_excerpt,
            'source_language' => $source_lang,
            'target_language' => $target_lang,
            'translation_method' => 'direct'
        );
    }

    /**
     * Limpa o conteúdo do post removendo scripts e mantendo apenas HTML padrão
     */
    private function clean_post_content($content) {
        // Remove scripts e tags perigosas
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        $content = preg_replace('/<link[^>]*>/mi', '', $content);
        $content = preg_replace('/<meta[^>]*>/mi', '', $content);
        
        // Remove iframes (geralmente anúncios)
        $content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $content);
        
        // Remove divs com classes de anúncios (com suporte para aninhamento)
        $content = preg_replace('/<div[^>]*class=["\'][^"\']*google-auto-placed[^"\']*["\'][^>]*>(?:[^<]|<(?!\/div>))*<\/div>/si', '', $content);
        $content = preg_replace('/<ins[^>]*class=["\'][^"\']*adsbygoogle[^"\']*["\'][^>]*>(?:[^<]|<(?!\/ins>))*<\/ins>/si', '', $content);
        
        // Remove containers de anúncios aninhados
        $content = preg_replace('/<div[^>]*id=["\']aswift_\d+_host["\'][^>]*>.*?<\/div>/si', '', $content);
        
        // Remove elementos vazios resultantes
        $content = preg_replace('/<div[^>]*>\s*<\/div>/i', '', $content);
        $content = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $content);
        
        // Remove atributos perigosos mas mantém estrutura HTML
        $content = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/mi', '', $content);
        $content = preg_replace('/\s*javascript\s*:/mi', '', $content);
        
        // Remove atributos data-* desnecessários
        $content = preg_replace('/\s*data-ad-[^=]*\s*=\s*["\'][^"\']*["\']/mi', '', $content);
        $content = preg_replace('/\s*data-adsbygoogle-[^=]*\s*=\s*["\'][^"\']*["\']/mi', '', $content);
        
        // Limpa espaços excessivos mas mantém quebras de linha importantes
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }

    /**
     * Limpeza profunda do conteúdo para remover blocos complexos
     */
    private function deep_clean_content($content) {
        error_log('Deep cleaning content - before: ' . strlen($content) . ' chars');
        
        // Remove blocos inteiros que contêm anúncios
        $patterns = array(
            // Remove iframes de anúncios com qualquer domínio suspeito
            '/<iframe[^>]*(?:googleads|doubleclick|googlesyndication|adsystem)[^>]*>.*?<\/iframe>/si',
            
            // Remove divs que contêm elementos de anúncios (melhor padrão para aninhamento)
            '/<div[^>]*>(?:[^<]|<(?!div\b))*<iframe[^>]*(?:googleads|doubleclick)[^>]*>.*?<\/iframe>(?:[^<]|<(?!\/div>))*<\/div>/si',
            '/<div[^>]*>(?:[^<]|<(?!div\b))*<ins[^>]*adsbygoogle[^>]*>.*?<\/ins>(?:[^<]|<(?!\/div>))*<\/div>/si',
            
            // Remove blocos com classes específicas de anúncios
            '/<div[^>]*class=["\'][^"\']*(?:google-auto-placed|ap_container|ad-container|advertisement|banner|adsbygoogle)[^"\']*["\'][^>]*>.*?<\/div>/si',
            
            // Remove elementos com IDs suspeitos
            '/<[^>]*id=["\'][^"\']*(?:aswift_|google_ads_|ad_|banner_)[^"\']*["\'][^>]*>.*?<\/[^>]*>/si',
            
            // Remove scripts de anúncios
            '/<script[^>]*(?:googleads|googlesyndication|doubleclick)[^>]*>.*?<\/script>/si',
            
            // Remove URLs longas de tracking
            '/https?:\/\/[^\s<>"\']{200,}/i',
            
            // Remove parâmetros de URL desnecessários em src de iframes
            '/src=["\'][^"\']*\?[^"\']{500,}["\']/i',
            
            // Remove comentários HTML que podem conter código de anúncios
            '/<!--.*?(?:google|ads|advertisement).*?-->/si',
            
            // Remove noscript tags que geralmente contêm fallbacks de anúncios
            '/<noscript[^>]*>.*?<\/noscript>/si',
        );
        
        foreach ($patterns as $pattern) {
            $old_content = $content;
            $content = preg_replace($pattern, '', $content);
            if ($old_content !== $content) {
                error_log('Pattern matched and removed content: ' . (strlen($old_content) - strlen($content)) . ' chars');
            }
        }
        
        // Segunda passada para limpar estruturas aninhadas recursivamente
        $max_iterations = 10;
        $iteration = 0;
        
        while ($iteration < $max_iterations) {
            $old_content = $content;
            
            // Remove divs vazias que podem ter sobrado
            $content = preg_replace('/<div[^>]*>\s*<\/div>/si', '', $content);
            
            // Remove parágrafos vazios
            $content = preg_replace('/<p[^>]*>\s*<\/p>/si', '', $content);
            
            // Remove spans vazios
            $content = preg_replace('/<span[^>]*>\s*<\/span>/si', '', $content);
            
            // Remove quebras de linha excessivas
            $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);
            
            // Se não houve mudança, podemos parar
            if ($old_content === $content) {
                break;
            }
            
            $iteration++;
        }
        
        error_log('Deep cleaning content - after: ' . strlen($content) . ' chars, iterations: ' . $iteration);
        
        return $content;
    }

    /**
     * Divide conteúdo em chunks baseados nos subtítulos (H1-H6)
     */
    private function split_content_by_headings($content, $max_words = 500) {
        AlvoBotPro::debug_log('multi-languages', "Dividindo conteúdo em chunks (máximo {$max_words} palavras)");
        
        // Remove elementos desnecessários primeiro
        $content = $this->clean_content_for_chunks($content);
        
        // Identifica todos os cabeçalhos
        $headings_pattern = '/<(h[1-6])[^>]*>(.*?)<\/h[1-6]>/si';
        preg_match_all($headings_pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        
        $chunks = array();
        $current_position = 0;
        
        foreach ($matches as $i => $match) {
            $heading_tag = $match[1][0];
            $heading_text = strip_tags($match[2][0]);
            $heading_position = $match[0][1];
            
            // Conteúdo antes do cabeçalho (se houver)
            if ($heading_position > $current_position) {
                $content_before = substr($content, $current_position, $heading_position - $current_position);
                $content_before = trim($content_before);
                
                if (!empty($content_before)) {
                    $chunks = array_merge($chunks, $this->split_large_content($content_before, $max_words));
                }
            }
            
            // Determina onde termina esta seção
            $next_heading_position = isset($matches[$i + 1]) ? $matches[$i + 1][0][1] : strlen($content);
            
            // Conteúdo desta seção (incluindo o cabeçalho)
            $section_content = substr($content, $heading_position, $next_heading_position - $heading_position);
            $section_content = trim($section_content);
            
            if (!empty($section_content)) {
                $word_count = str_word_count(strip_tags($section_content));
                
                if ($word_count <= $max_words) {
                    // Seção cabe em um chunk
                    $chunks[] = array(
                        'content' => $section_content,
                        'heading' => $heading_text,
                        'heading_level' => (int)substr($heading_tag, 1),
                        'word_count' => $word_count
                    );
                } else {
                    // Seção precisa ser dividida
                    $split_chunks = $this->split_large_content($section_content, $max_words, $heading_text);
                    $chunks = array_merge($chunks, $split_chunks);
                }
            }
            
            $current_position = $next_heading_position;
        }
        
        // Conteúdo restante após o último cabeçalho
        if ($current_position < strlen($content)) {
            $remaining_content = substr($content, $current_position);
            $remaining_content = trim($remaining_content);
            
            if (!empty($remaining_content)) {
                $chunks = array_merge($chunks, $this->split_large_content($remaining_content, $max_words));
            }
        }
        
        // Se não há cabeçalhos, divide o conteúdo inteiro
        if (empty($chunks)) {
            $chunks = $this->split_large_content($content, $max_words);
        }
        
        AlvoBotPro::debug_log('multi-languages', "Conteúdo dividido em " . count($chunks) . " chunks");
        
        return $chunks;
    }

    /**
     * Divide conteúdo grande em chunks menores
     */
    private function split_large_content($content, $max_words, $heading = null) {
        $chunks = array();
        $content = trim($content);
        
        if (empty($content)) {
            return $chunks;
        }
        
        $word_count = str_word_count(strip_tags($content));
        
        if ($word_count <= $max_words) {
            $chunks[] = array(
                'content' => $content,
                'heading' => $heading,
                'word_count' => $word_count
            );
            return $chunks;
        }
        
        // Divide por parágrafos primeiro
        $paragraphs = preg_split('/(<\/p>|<br\s*\/?>)/i', $content);
        
        $current_chunk = '';
        $current_words = 0;
        $chunk_index = 0;
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph) || in_array($paragraph, array('</p>', '<br>', '<br/>', '<br />'))) {
                continue;
            }
            
            $paragraph_words = str_word_count(strip_tags($paragraph));
            
            if ($current_words + $paragraph_words <= $max_words) {
                $current_chunk .= $paragraph;
                $current_words += $paragraph_words;
            } else {
                // Salva chunk atual se não estiver vazio
                if (!empty(trim($current_chunk))) {
                    $chunks[] = array(
                        'content' => trim($current_chunk),
                        'heading' => $chunk_index === 0 ? $heading : null,
                        'word_count' => $current_words
                    );
                    $chunk_index++;
                }
                
                // Inicia novo chunk
                $current_chunk = $paragraph;
                $current_words = $paragraph_words;
            }
        }
        
        // Adiciona último chunk se não estiver vazio
        if (!empty(trim($current_chunk))) {
            $chunks[] = array(
                'content' => trim($current_chunk),
                'heading' => $chunk_index === 0 ? $heading : null,
                'word_count' => $current_words
            );
        }
        
        return $chunks;
    }

    /**
     * Limpa conteúdo para divisão em chunks, mantendo apenas HTML essencial
     */
    private function clean_content_for_chunks($content) {
        // Remove scripts, styles e comentários
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        
        // Remove atributos desnecessários mas mantém estrutura HTML básica
        $allowed_tags = array(
            'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 'span', 'div',
            'ul', 'ol', 'li', 'blockquote',
            'img', 'video', 'audio', 'iframe',
            'a', 'table', 'tr', 'td', 'th'
        );
        
        // Mantém apenas tags essenciais
        $content = strip_tags($content, '<' . implode('><', $allowed_tags) . '>');
        
        return $content;
    }

    /**
     * Reconstrói conteúdo a partir dos chunks traduzidos
     */
    private function rebuild_content_from_chunks($original_chunks, $translated_blocks) {
        $rebuilt_content = '';
        
        foreach ($original_chunks as $index => $chunk) {
            $chunk_id = "chunk_{$index}";
            
            if (isset($translated_blocks[$chunk_id])) {
                $rebuilt_content .= $translated_blocks[$chunk_id];
            } else {
                // Fallback para conteúdo original se tradução falhou
                $rebuilt_content .= $chunk['content'];
            }
            
            // Adiciona espaçamento entre chunks
            if ($index < count($original_chunks) - 1) {
                $rebuilt_content .= "\n\n";
            }
        }
        
        return $rebuilt_content;
    }

    /**
     * Obtém contexto para tradução baseado no tipo de bloco
     */
    private function get_translation_context($block_type, $block_data) {
        switch ($block_type) {
            case 'title':
                return 'Post title - should be concise and engaging';
                
            case 'excerpt':
                return 'Post excerpt/description - should summarize the main content';
                
            case 'content_chunk':
                $context = 'Article content';
                if (!empty($block_data['heading'])) {
                    $context .= " under heading: " . $block_data['heading'];
                }
                if (!empty($block_data['word_count'])) {
                    $context .= " (approximately {$block_data['word_count']} words)";
                }
                return $context;
                
            default:
                return 'Website content';
        }
    }

    /**
     * Traduz um bloco de texto individual
     */
    private function translate_text_block($text, $source_lang, $target_lang, $options = array()) {
        // Obtém o provider ativo
        $active_provider_name = $this->get_active_provider_name();
        if (!$active_provider_name || !isset($this->providers[$active_provider_name])) {
            AlvoBotPro::debug_log('multi-languages', 'Nenhum provider de tradução ativo ou provider não encontrado: ' . $active_provider_name);
            return array(
                'success' => false,
                'error' => 'Nenhum provider de tradução ativo'
            );
        }

        $provider = $this->providers[$active_provider_name];
        
        AlvoBotPro::debug_log('multi-languages', 'Provider obtido: ' . gettype($provider) . ' - ' . (is_object($provider) ? get_class($provider) : $provider));
        
        try {
            AlvoBotPro::debug_log('multi-languages', "Traduzindo texto com provider {$active_provider_name}: " . substr($text, 0, 100));
            $translation_result = $provider->translate($text, $source_lang, $target_lang, $options);
            
            if ($translation_result['success']) {
                $translated_text = $translation_result['translated_text'];
                AlvoBotPro::debug_log('multi-languages', "Tradução concluída. Resultado: " . substr($translated_text, 0, 100));
                
                return array(
                    'success' => true,
                    'translated_text' => $translated_text,
                    'original_text' => $text
                );
            } else {
                AlvoBotPro::debug_log('multi-languages', "Erro na tradução: " . ($translation_result['error'] ?? 'Erro desconhecido'));
                return array(
                    'success' => false,
                    'error' => $translation_result['error'] ?? 'Erro na tradução'
                );
            }
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'Erro na tradução do bloco: ' . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Preprocessa o texto antes da tradução
     */
    private function preprocess_text($text, $options) {
        // Garante que $text seja string
        $text_string = is_array($text) ? $text['text'] : $text;
        
        // Remove espaços extras
        $text_string = trim($text_string);
        
        // Aplica limpeza de conteúdo se o texto contém HTML
        if (strpos($text_string, '<') !== false) {
            error_log('Preprocessing: Detected HTML content, applying content cleaning');
            $original_length = strlen($text_string);
            
            // Aplica limpeza básica
            $text_string = $this->clean_post_content($text_string);
            
            // Aplica limpeza profunda se ainda há muitos elementos HTML suspeitos
            if (substr_count($text_string, '<iframe') > 0 || substr_count($text_string, 'adsbygoogle') > 0) {
                $text_string = $this->deep_clean_content($text_string);
            }
            
            $cleaned_length = strlen($text_string);
            error_log("Content cleaning: {$original_length} -> {$cleaned_length} chars (" . round((($original_length - $cleaned_length) / $original_length) * 100, 1) . "% reduction)");
        }
        
        // Preserva formatação HTML se necessário
        if ($this->settings['preserve_formatting'] && 
            (!empty($options['preserve_html']) || !empty($options['is_html']))) {
            // Protege tags HTML
            $text_string = $this->protect_html_tags($text_string);
        }
        
        return $text_string;
    }

    /**
     * Pós-processa o texto após a tradução
     */
    private function postprocess_text($translated_text, $original_text, $options) {
        // Restaura formatação HTML se necessário
        if ($this->settings['preserve_formatting'] && !empty($options['is_html'])) {
            $translated_text = $this->restore_html_tags($translated_text);
        }
        
        // Traduz links se habilitado
        if ($this->settings['translate_links'] && !empty($options['translate_links'])) {
            $translated_text = $this->translate_internal_links($translated_text, $options['target_lang']);
        }
        
        return $translated_text;
    }

    /**
     * Protege tags HTML durante a tradução
     */
    private function protect_html_tags($text) {
        // Implementação simples - pode ser expandida
        return $text;
    }

    /**
     * Restaura tags HTML após a tradução
     */
    private function restore_html_tags($text) {
        // Implementação simples - pode ser expandida
        return $text;
    }

    /**
     * Traduz links internos
     */
    private function translate_internal_links($text, $target_lang) {
        // Implementação básica - pode ser expandida
        return $text;
    }

    /**
     * Detecta o idioma de um texto
     */
    private function detect_language($text) {
        // Implementação básica usando configuração padrão
        return $this->settings['default_source_language'] ?? 'pt';
    }

    /**
     * Detecta o idioma de um post usando Polylang
     */
    private function detect_post_language($post_id) {
        if (function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($post_id, 'slug');
            if ($lang) {
                return $lang;
            }
        }
        
        return get_locale();
    }

    /**
     * Gera chave de cache para uma tradução
     */
    private function get_cache_key($text, $source_lang, $target_lang) {
        // Garante que $text seja string
        $text_string = is_array($text) ? $text['text'] : $text;
        return md5($this->active_provider . '_' . $source_lang . '_' . $target_lang . '_' . $text_string);
    }

    /**
     * Verifica rate limiting
     */
    private function check_rate_limit() {
        $key = 'alvobot_rate_limit_' . $this->active_provider;
        $current_count = get_transient($key) ?: 0;
        
        return $current_count < $this->settings['rate_limit_requests'];
    }

    /**
     * Incrementa contador de rate limiting
     */
    private function increment_rate_limit() {
        $key = 'alvobot_rate_limit_' . $this->active_provider;
        $current_count = get_transient($key) ?: 0;
        $new_count = $current_count + 1;
        
        set_transient($key, $new_count, $this->settings['rate_limit_period']);
    }

    /**
     * Registra ação no log
     */
    private function log_action($action, $level, $message, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'context' => $data, // Mudando 'data' para 'context' para compatibilidade
            'provider' => $this->active_provider,
            'user_id' => get_current_user_id()
        );
        
        // Salva no log do módulo principal (com fallback seguro)
        try {
            do_action('alvobot_multi_languages_log', $log_entry);
        } catch (Exception $e) {
            // Fallback para error_log direto se o action falhar
            error_log('[Translation Engine] ' . strtoupper($level) . ': ' . $message);
        }
    }

    /**
     * Retorna resposta de erro padronizada
     */
    private function error_response($message) {
        return array(
            'success' => false,
            'error' => $message,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Handlers AJAX
     */
    
    public function ajax_translate_content() {
        // SEGURANÇA: Verificar nonce padronizado e permissões
        if (!check_ajax_referer('alvobot_multi_languages_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }

        // SEGURANÇA: Validar e sanitizar inputs
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $target_langs = isset($_POST['target_langs']) && is_array($_POST['target_langs']) 
            ? array_map('sanitize_text_field', $_POST['target_langs']) 
            : array();
        $options = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : array();

        if (!$post_id || empty($target_langs)) {
            wp_send_json_error(['message' => 'Post ID e idiomas de destino são obrigatórios']);
        }

        // UNIFICAÇÃO: Usar sempre a fila para garantir validação e consistência
        AlvoBotPro::debug_log('multi-languages', "AJAX: Redirecionando para fila - Post {$post_id}, idiomas: " . implode(', ', $target_langs));
        
        try {
            // Carrega a Translation Queue
            require_once dirname(__FILE__) . '/class-translation-queue.php';
            $translation_queue = new AlvoBotPro_Translation_Queue();
            
            // Adiciona à fila com prioridade alta para interface
            $queue_id = $translation_queue->add_to_queue($post_id, $target_langs, $options, 20);
            
            if (!$queue_id) {
                wp_send_json_error(['message' => 'Falha ao adicionar à fila de tradução']);
            }
            
            // Processa imediatamente
            $processed = $translation_queue->process_next_item();
            
            if ($processed) {
                wp_send_json_success([
                    'message' => 'Tradução processada com sucesso via fila',
                    'queue_id' => $queue_id,
                    'processed' => true
                ]);
            } else {
                wp_send_json_success([
                    'message' => 'Tradução adicionada à fila para processamento',
                    'queue_id' => $queue_id,
                    'processed' => false
                ]);
            }
            
        } catch (Exception $e) {
            AlvoBotPro::debug_log('multi-languages', 'Erro no AJAX unificado: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    public function ajax_get_translation_status() {
        // SEGURANÇA: Verificar nonce padronizado e permissões
        if (!check_ajax_referer('alvobot_multi_languages_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
        }
        
        $provider_status = array();
        foreach ($this->providers as $key => $provider) {
            $provider_status[$key] = array(
                'name' => $provider->get_name(),
                'configured' => $provider->is_configured(),
                'available' => $provider->is_available(),
                'rate_limit' => $this->get_provider_rate_limit($key)
            );
        }
        
        wp_send_json_success(array(
            'active_provider' => $this->active_provider,
            'providers' => $provider_status,
            'settings' => $this->settings
        ));
    }

    private function get_provider_rate_limit($provider) {
        $key = 'alvobot_rate_limit_' . $provider;
        $current_count = get_transient($key) ?: 0;
        
        return array(
            'current' => $current_count,
            'limit' => $this->settings['rate_limit_requests'],
            'remaining' => max(0, $this->settings['rate_limit_requests'] - $current_count)
        );
    }

    /**
     * Getters e Setters
     */
    
    public function get_active_provider_name() {
        return $this->active_provider;
    }

    /**
     * Retorna instância do provider ativo
     */
    public function get_active_provider() {
        return isset($this->providers[$this->active_provider]) ? $this->providers[$this->active_provider] : null;
    }

    public function set_active_provider($provider) {
        if (isset($this->providers[$provider])) {
            $this->active_provider = $provider;
            $this->settings['active_provider'] = $provider;
            update_option('alvobot_multi_languages_settings', $this->settings);
            return true;
        }
        return false;
    }

    public function get_available_providers() {
        $available = array();
        foreach ($this->providers as $key => $provider) {
            $available[$key] = array(
                'name' => $provider->get_name(),
                'description' => $provider->get_description(),
                'configured' => $provider->is_configured(),
                'available' => $provider->is_available()
            );
        }
        return $available;
    }

    public function get_settings() {
        return $this->settings;
    }

    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        update_option('alvobot_multi_languages_settings', $this->settings);
        
        // Recarrega se o provider mudou
        if (isset($new_settings['active_provider'])) {
            $this->active_provider = $new_settings['active_provider'];
        }
        
        return true;
    }
}