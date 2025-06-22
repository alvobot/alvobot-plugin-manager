<?php

if (!defined('ABSPATH')) {
    exit;
}

// Evita redeclaração da classe
if (class_exists('AlvoBotPro_OpenAI_Translation_Provider')) {
    return;
}

/**
 * Provider OpenAI para tradução usando ChatGPT
 * 
 * Integrado com as linguagens configuradas no Polylang
 */
class AlvoBotPro_OpenAI_Translation_Provider implements AlvoBotPro_Translation_Provider_Interface {
    
    /** @var array Configurações do provider */
    private $settings = array();
    
    /** @var array Cache de linguagens do Polylang */
    private $polylang_languages = array();
    
    /** @var string URL da API OpenAI */
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    /** @var string URL da API OpenAI para buscar modelos */
    private $openai_models_url = 'https://api.openai.com/v1/models';
    
    /** @var array Cache de modelos disponíveis */
    private $available_models = array();
    
    public function __construct() {
        $this->load_settings();
        $this->load_polylang_languages();
        $this->load_available_models();
    }
    
    /**
     * Carrega as configurações do provider
     */
    private function load_settings() {
        $default_settings = array(
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'max_tokens' => 3000,
            'temperature' => 0.3,
            'timeout' => 60
        );
        
        $saved_settings = get_option('alvobot_openai_settings', array());
        $this->settings = array_merge($default_settings, $saved_settings);
        
        // SEGURANÇA: Prioriza API key do wp-config.php sobre banco de dados
        if (defined('ALVOBOT_OPENAI_API_KEY') && !empty(ALVOBOT_OPENAI_API_KEY)) {
            $this->settings['api_key'] = ALVOBOT_OPENAI_API_KEY;
            AlvoBotPro::debug_log('multi-languages', 'OpenAI API key carregada do wp-config.php');
        } elseif (!empty($this->settings['api_key'])) {
            AlvoBotPro::debug_log('multi-languages', 'OpenAI API key carregada do banco de dados');
        } else {
            AlvoBotPro::debug_log('multi-languages', 'ALERTA: Nenhuma API key configurada');
        }
    }
    
    /**
     * Carrega as linguagens configuradas no Polylang
     */
    private function load_polylang_languages() {
        if (!function_exists('PLL') || !PLL()->model) {
            return;
        }
        
        try {
            $languages = PLL()->model->get_languages_list();
            
            if (empty($languages)) {
                return;
            }
            
            foreach ($languages as $language) {
                // Verifica se o objeto de linguagem tem as propriedades necessárias
                if (!isset($language->slug) || !isset($language->name)) {
                    continue;
                }
                
                $this->polylang_languages[$language->slug] = array(
                    'name' => $language->name,
                    'native_name' => isset($language->flag_title) ? $language->flag_title : $language->name,
                    'locale' => isset($language->locale) ? $language->locale : $language->slug,
                    'slug' => $language->slug,
                    'flag' => isset($language->flag_url) ? $language->flag_url : '',
                    'is_rtl' => isset($language->is_rtl) ? $language->is_rtl : false
                );
            }
        } catch (Exception $e) {
            error_log('AlvoBot Multi Languages: Erro ao carregar linguagens do Polylang: ' . $e->getMessage());
        }
    }
    
    /**
     * Traduz texto usando OpenAI
     * 
     * @param string $text Texto a ser traduzido
     * @param string $source_lang Idioma de origem (slug do Polylang)
     * @param string $target_lang Idioma de destino (slug do Polylang)
     * @param array $options Opções adicionais
     * @return array Resultado da tradução
     */
    public function translate($text, $source_lang, $target_lang, $options = array()) {
        try {
            // Validação
            if (!$this->is_configured()) {
                return $this->error_response('OpenAI não está configurado. Adicione uma API key.');
            }
            
            if (empty($text)) {
                return $this->error_response('Texto para tradução está vazio.');
            }
            
            // Verifica se as linguagens estão configuradas no Polylang
            if (!isset($this->polylang_languages[$target_lang])) {
                return $this->error_response("Idioma de destino '{$target_lang}' não encontrado no Polylang.");
            }
            
            $source_language_name = isset($this->polylang_languages[$source_lang]) 
                ? $this->polylang_languages[$source_lang]['native_name']
                : 'auto-detect';
                
            $target_language_name = $this->polylang_languages[$target_lang]['native_name'];
            
            // Prepara o prompt
            $prompt = $this->build_translation_prompt($text, $source_language_name, $target_language_name, $options);
            
            // Prepara os dados da requisição
            $request_data = array(
                'model' => $this->settings['model'],
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a professional translator. Translate the given text accurately while preserving formatting, tone, and context. Return only the translated text without explanations.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => $this->settings['max_tokens'],
                'temperature' => $this->settings['temperature']
            );
            
            // Headers da requisição
            $headers = array(
                'Authorization' => 'Bearer ' . $this->settings['api_key'],
                'Content-Type' => 'application/json'
            );
            
            
            // Faz a requisição
            $response = $this->make_request($request_data, $headers);
            
            if (!$response['success']) {
                return $response;
            }
            
            $result = json_decode($response['data'], true);
            
            if (!$result || !isset($result['choices'][0]['message']['content'])) {
                return $this->error_response('Resposta inválida da OpenAI.');
            }
            
            $translated_text = trim($result['choices'][0]['message']['content']);
            
            // Remove aspas adicionais se presentes
            if (substr($translated_text, 0, 1) === '"' && substr($translated_text, -1) === '"') {
                $translated_text = substr($translated_text, 1, -1);
            }
            
            $usage_info = array(
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0
            );
            
            return array(
                'success' => true,
                'translated_text' => $translated_text,
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'provider' => 'openai',
                'model' => $this->settings['model'],
                'usage' => $usage_info
            );
            
        } catch (Exception $e) {
            return $this->error_response('Erro na OpenAI: ' . $e->getMessage());
        }
    }
    
    /**
     * Constrói o prompt de tradução
     */
    private function build_translation_prompt($text, $source_lang_name, $target_lang_name, $options) {
        $preserve_html = isset($options['preserve_html']) && $options['preserve_html'];
        $context = isset($options['context']) ? $options['context'] : '';
        
        // Determinar código ISO para maior precisão
        $target_lang_code = $this->get_language_iso_code($target_lang_name);
        
        $prompt = "You are an expert multilingual translator. Your task is to translate the provided text accurately and fluently.

**Instructions:**
1. Translate the text from {$source_lang_name} to {$target_lang_name} ({$target_lang_code}).
2. Preserve the original tone, context, and intent.

**CRITICAL - Shortcodes and JSON:**
1. DO NOT modify or translate ANY content between shortcode tags [shortcode]...[/shortcode]
2. DO NOT modify or translate ANY content that looks like JSON (text between { and })
3. Treat the following as immutable blocks that must be preserved exactly as they are:
   - [quiz]...content...[/quiz]
   - Any JSON objects/arrays with properties like 'question', 'answers', 'styles'
   - Any shortcode parameters like [shortcode param=\"value\"]
4. If you see JSON or shortcodes, copy them verbatim without any changes
5. NEVER add spaces or line breaks inside JSON or shortcode content";
        
        // Instruções específicas para HTML
        if ($preserve_html) {
            $prompt .= "
3. If the text contains HTML tags like <p>, <strong>, <h1>, <h2>, <h3>, <div>, or <img>, keep them intact.
4. **CRITICAL**: NEVER add new HTML tags, IDs, spans, divs, or attributes that were not in the original text.
5. **CRITICAL**: Do not add any <span>, <div>, or other wrapper elements around translated text.
6. Maintain the exact same HTML structure - do not modify IDs, classes, or tag attributes.
7. Do not add closing tags like </div> if they were not in the original text.
8. Convert HTML entities like &nbsp; to regular spaces when appropriate.
9. Do not wrap translated text in additional HTML elements.
10. **NEVER** add structural elements like <span>, <div>, <section> that don't exist in the original.";
        } else {
            $prompt .= "
3. Return only clean text without any HTML tags or scripts.";
        }
        
        $prompt .= "
11. **Crucially, your response must contain ONLY the translated text and nothing else.** Do not add any explanations, apologies, or introductory phrases like \"Here is the translation:\".
12. **CRITICAL: Do not add, modify, or remove any HTML structure. Only translate the text content within existing tags.**
13. **ABSOLUTELY FORBIDDEN**: Do not add <span>, <div>, <section>, or any wrapper elements.
14. **ABSOLUTELY FORBIDDEN**: Do not add closing tags like </div>, </span>, </section> that weren't in the original.";
        
        if (!empty($context)) {
            $prompt .= "

**Context for this text:** {$context}";
        }
        
        $prompt .= "

Now, translate the following text:

---
{$text}
---";
        
        return $prompt;
    }
    
    /**
     * Converte nome de idioma para código ISO 639-1
     */
    private function get_language_iso_code($language_name) {
        $iso_codes = array(
            'português' => 'pt',
            'portuguese' => 'pt',
            'inglês' => 'en',
            'english' => 'en',
            'espanhol' => 'es',
            'spanish' => 'es',
            'español' => 'es',
            'francês' => 'fr',
            'french' => 'fr',
            'français' => 'fr',
            'italiano' => 'it',
            'italian' => 'it',
            'alemão' => 'de',
            'german' => 'de',
            'deutsch' => 'de'
        );
        
        $lang_key = strtolower($language_name);
        return isset($iso_codes[$lang_key]) ? $iso_codes[$lang_key] : strtolower(substr($language_name, 0, 2));
    }
    
    /**
     * Faz requisição para a API OpenAI
     */
    private function make_request($data, $headers) {
        $args = array(
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => $this->settings['timeout'],
            'user-agent' => 'AlvoBot Multi Languages'
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            // SEGURANÇA: Remove dados sensíveis dos logs de erro
            $safe_args = $args;
            if (isset($safe_args['headers']['Authorization'])) {
                $safe_args['headers']['Authorization'] = 'Bearer [HIDDEN]';
            }
            
            AlvoBotPro::debug_log('multi-languages', 'Erro wp_remote_post: ' . $response->get_error_message());
            
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : 'HTTP Error: ' . $code;
                
            // SEGURANÇA: Log sem expor dados sensíveis
            AlvoBotPro::debug_log('multi-languages', "Erro OpenAI API (HTTP {$code}): {$error_message}");
                
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        return array(
            'success' => true,
            'data' => $body,
            'response_code' => $code
        );
    }
    
    /**
     * Verifica se o provider está configurado
     */
    public function is_configured() {
        return !empty($this->settings['api_key']);
    }
    
    /**
     * Verifica se o provider está disponível
     */
    public function is_available() {
        return $this->is_configured() && !empty($this->polylang_languages);
    }
    
    /**
     * Retorna o nome do provider
     */
    public function get_name() {
        return 'OpenAI ChatGPT';
    }
    
    /**
     * Retorna a descrição do provider
     */
    public function get_description() {
        return 'Tradução de alta qualidade usando OpenAI ChatGPT. Integrado com idiomas do Polylang.';
    }
    
    /**
     * Retorna as linguagens suportadas (baseadas no Polylang)
     */
    public function get_supported_languages() {
        return $this->polylang_languages;
    }
    
    /**
     * Atualiza as configurações
     */
    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        update_option('alvobot_openai_settings', $this->settings);
        return true;
    }
    
    /**
     * Retorna as configurações atuais
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Testa a conexão com a OpenAI
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API key não configurada'
            );
        }
        
        // Teste simples de tradução
        $test_result = $this->translate('Hello', 'en', 'pt', array('test' => true));
        
        if ($test_result['success']) {
            return array(
                'success' => true,
                'message' => 'Conexão com OpenAI estabelecida com sucesso',
                'model' => $this->settings['model']
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Falha na conexão: ' . $test_result['error']
            );
        }
    }
    
    /**
     * Retorna estatísticas de uso
     */
    public function get_usage_stats() {
        $stats = get_option('alvobot_openai_usage_stats', array(
            'total_translations' => 0,
            'total_tokens' => 0,
            'total_cost_estimate' => 0,
            'last_reset' => current_time('mysql')
        ));
        
        return $stats;
    }
    
    /**
     * Atualiza estatísticas de uso
     */
    public function update_usage_stats($usage_data) {
        $stats = $this->get_usage_stats();
        
        // NÃO incrementa total_translations aqui - será feito apenas quando post completo for traduzido
        $stats['total_tokens'] += $usage_data['total_tokens'] ?? 0;
        
        // Calcula custo real baseado no modelo usado
        $model_info = $this->get_model_info($this->settings['model']);
        if ($model_info && isset($usage_data['prompt_tokens']) && isset($usage_data['completion_tokens'])) {
            $input_cost = ($usage_data['prompt_tokens'] / 1000) * $model_info['cost_input'];
            $output_cost = ($usage_data['completion_tokens'] / 1000) * $model_info['cost_output'];
            $total_cost = $input_cost + $output_cost;
        } else {
            // Fallback para estimativa básica
            $cost_per_token = 0.000002;
            $total_cost = ($usage_data['total_tokens'] ?? 0) * $cost_per_token;
        }
        
        $stats['total_cost_estimate'] += $total_cost;
        
        update_option('alvobot_openai_usage_stats', $stats);
    }
    
    /**
     * Incrementa contador de posts traduzidos (chamado apenas uma vez por post)
     */
    public function increment_post_translation_count() {
        $stats = $this->get_usage_stats();
        $stats['total_translations']++;
        update_option('alvobot_openai_usage_stats', $stats);
        
        AlvoBotPro::debug_log('multi-languages', 'Contador de posts traduzidos incrementado. Total: ' . $stats['total_translations']);
    }
    
    
    /**
     * Verifica se o cache está habilitado nas configurações
     */
    private function is_cache_enabled() {
        $openai_settings = get_option('alvobot_openai_settings', array());
        return !(isset($openai_settings['disable_cache']) && $openai_settings['disable_cache']);
    }

    /**
     * Verifica se o cache de modelos está habilitado
     * Independente do cache geral, mantemos o cache de modelos por padrão
     */
    private function is_models_cache_enabled() {
        $openai_settings = get_option('alvobot_openai_settings', array());
        return !(isset($openai_settings['disable_models_cache']) && $openai_settings['disable_models_cache']);
    }

    /**
     * Carrega modelos disponíveis da API OpenAI
     */
    private function load_available_models() {
        // Verifica cache de modelos
        if ($this->is_models_cache_enabled()) {
            $cached_models = get_transient('alvobot_openai_models');
            if ($cached_models !== false) {
                $this->available_models = $cached_models;
                return true;
            }
        }
        
        // Se não tiver API key, usa apenas modelos fallback
        if (empty($this->settings['api_key'])) {
            $this->load_fallback_models();
            return;
        }

        error_log('AlvoBot Multi Languages: Buscando modelos da API OpenAI...');
        
        // Busca modelos da API OpenAI
        $headers = array(
            'Authorization' => 'Bearer ' . $this->settings['api_key'],
            'Content-Type' => 'application/json',
            'User-Agent' => 'AlvoBot Multi Languages'
        );
        
        $response = wp_remote_get($this->openai_models_url, array(
            'timeout' => 30,
            'headers' => $headers
        ));
        
        if (is_wp_error($response)) {
            error_log('AlvoBot Multi Languages: Erro na requisição - ' . $response->get_error_message());
            $this->load_fallback_models();
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('AlvoBot Multi Languages: Response code: ' . $response_code);
            $this->load_fallback_models();
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['data'])) {
            error_log('AlvoBot Multi Languages: JSON inválido ou estrutura inesperada');
            $this->load_fallback_models();
            return;
        }
        
        $openai_models = array();
        foreach ($data['data'] as $model) {
            // Filtra apenas modelos GPT compatíveis com chat
            if (isset($model['id']) && $this->is_chat_model($model['id'])) {
                $openai_models[] = array(
                    'id' => $model['id'],
                    'name' => $this->get_model_display_name($model['id']),
                    'description' => $this->get_model_description($model['id']),
                    'max_tokens' => $this->get_model_max_tokens($model['id']),
                    'cost_input' => $this->get_model_cost_input($model['id']),
                    'cost_output' => $this->get_model_cost_output($model['id']),
                    'created' => $model['created'] ?? 0,
                    'owned_by' => $model['owned_by'] ?? 'openai'
                );
            }
        }
        
        if (!empty($openai_models)) {
            // Ordena por data de criação (mais recentes primeiro)
            usort($openai_models, function($a, $b) {
                return $b['created'] - $a['created'];
            });
            
            $this->available_models = $openai_models;
            // Cache por 24 horas se o cache de modelos estiver habilitado
            if ($this->is_models_cache_enabled()) {
                set_transient('alvobot_openai_models', $openai_models, 24 * HOUR_IN_SECONDS);
            }
            error_log('AlvoBot Multi Languages: ' . count($openai_models) . ' modelos carregados com sucesso da API OpenAI');
        } else {
            error_log('AlvoBot Multi Languages: Nenhum modelo de chat compatível encontrado');
            $this->load_fallback_models();
        }
    }
    
    /**
     * Carrega modelos padrão como fallback
     */
    public function load_fallback_models() {
        $this->available_models = array(
            array(
                'id' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'description' => 'Modelo otimizado para velocidade e custo',
                'max_tokens' => 16385,
                'cost_input' => 0.00015,
                'cost_output' => 0.0006
            ),
            array(
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'description' => 'Modelo mais avançado da OpenAI',
                'max_tokens' => 4096,
                'cost_input' => 0.005,
                'cost_output' => 0.015
            ),
            array(
                'id' => 'gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'description' => 'Balanceado entre performance e custo',
                'max_tokens' => 4096,
                'cost_input' => 0.01,
                'cost_output' => 0.03
            ),
            array(
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'description' => 'Modelo econômico e rápido',
                'max_tokens' => 4096,
                'cost_input' => 0.0005,
                'cost_output' => 0.0015
            )
        );
    }
    
    /**
     * Retorna modelos disponíveis
     */
    public function get_available_models() {
        return $this->available_models;
    }
    
    /**
     * Força atualização dos modelos
     */
    public function refresh_models() {
        delete_transient('alvobot_openai_models');
        $this->load_available_models();
        return $this->available_models;
    }
    
    /**
     * Retorna informações de um modelo específico
     */
    public function get_model_info($model_id) {
        foreach ($this->available_models as $model) {
            if ($model['id'] === $model_id) {
                return $model;
            }
        }
        return null;
    }
    
    /**
     * Estima custo de uma tradução
     */
    /**
     * Valida configurações do provider
     * 
     * @param array $settings Configurações a serem validadas
     * @return array Array com 'valid' e 'errors'
     */
    public function validate_settings($settings) {
        $errors = array();
        
        // Valida API key
        if (empty($settings['api_key'])) {
            $errors[] = 'API Key é obrigatória';
        } elseif (!preg_match('/^sk-[a-zA-Z0-9]+$/', $settings['api_key'])) {
            $errors[] = 'Formato de API Key inválido';
        }
        
        // Valida modelo
        if (empty($settings['model'])) {
            $errors[] = 'Modelo é obrigatório';
        }
        
        // Valida parâmetros numéricos
        if (isset($settings['max_tokens']) && (!is_numeric($settings['max_tokens']) || $settings['max_tokens'] < 1)) {
            $errors[] = 'Max tokens deve ser um número positivo';
        }
        
        if (isset($settings['temperature']) && (!is_numeric($settings['temperature']) || $settings['temperature'] < 0 || $settings['temperature'] > 2)) {
            $errors[] = 'Temperature deve estar entre 0 e 2';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    public function estimate_cost($text, $model_id = null) {
        if (!$model_id) {
            $model_id = $this->settings['model'];
        }
        
        $model_info = $this->get_model_info($model_id);
        if (!$model_info) {
            return 0;
        }
        
        // Estimativa básica: ~1 token por 4 caracteres
        $estimated_tokens = ceil(strlen($text) / 4);
        $input_cost = $estimated_tokens * ($model_info['cost_input'] / 1000);
        $output_cost = $estimated_tokens * ($model_info['cost_output'] / 1000);
        
        return $input_cost + $output_cost;
    }
    
    /**
     * Verifica se o modelo é compatível com chat/completions
     */
    private function is_chat_model($model_id) {
        $chat_models = array(
            'gpt-4o', 'gpt-4o-mini', 'gpt-4o-2024-11-20', 'gpt-4o-2024-08-06', 'gpt-4o-2024-05-13',
            'gpt-4-turbo', 'gpt-4-turbo-2024-04-09', 'gpt-4-turbo-preview',
            'gpt-4', 'gpt-4-0613', 'gpt-4-0314',
            'gpt-3.5-turbo', 'gpt-3.5-turbo-0125', 'gpt-3.5-turbo-1106', 'gpt-3.5-turbo-0613'
        );
        
        // Verifica se é exatamente um dos modelos conhecidos ou começa com gpt- e contém turbo/4o/4
        return in_array($model_id, $chat_models) || 
               (strpos($model_id, 'gpt-') === 0 && 
                (strpos($model_id, 'turbo') !== false || 
                 strpos($model_id, '4o') !== false || 
                 strpos($model_id, 'gpt-4') === 0));
    }
    
    /**
     * Retorna nome de exibição do modelo
     */
    private function get_model_display_name($model_id) {
        $display_names = array(
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
        );
        
        return $display_names[$model_id] ?? ucfirst(str_replace('-', ' ', $model_id));
    }
    
    /**
     * Retorna descrição do modelo baseado no nome
     */
    private function get_model_description($model_name) {
        $descriptions = array(
            'gpt-4o' => 'Modelo mais avançado da OpenAI com capacidades multimodais',
            'gpt-4o-mini' => 'Versão otimizada e econômica do GPT-4o',
            'gpt-4-turbo' => 'Modelo balanceado entre performance e custo',
            'gpt-4' => 'Modelo de alta qualidade para tarefas complexas',
            'gpt-3.5-turbo' => 'Modelo rápido e econômico para uso geral'
        );
        
        // Detecta versões com data
        if (strpos($model_name, 'gpt-4o') === 0) {
            return 'Modelo avançado GPT-4o com capacidades multimodais';
        } elseif (strpos($model_name, 'gpt-4-turbo') === 0) {
            return 'Modelo GPT-4 Turbo otimizado';
        } elseif (strpos($model_name, 'gpt-4') === 0) {
            return 'Modelo GPT-4 de alta qualidade';
        } elseif (strpos($model_name, 'gpt-3.5') === 0) {
            return 'Modelo GPT-3.5 rápido e econômico';
        }
        
        return $descriptions[$model_name] ?? 'Modelo OpenAI GPT';
    }
    
    /**
     * Retorna max_tokens baseado no modelo
     */
    private function get_model_max_tokens($model_name) {
        $max_tokens = array(
            'gpt-4o' => 128000,
            'gpt-4o-mini' => 16385,
            'gpt-4-turbo' => 128000,
            'gpt-4' => 8192,
            'gpt-3.5-turbo' => 4096
        );
        
        return $max_tokens[$model_name] ?? 4096;
    }
    
    /**
     * Retorna custo de input por 1K tokens
     */
    private function get_model_cost_input($model_id) {
        $costs = array(
            'gpt-4o' => 0.005,
            'gpt-4o-mini' => 0.00015,
            'gpt-4-turbo' => 0.01,
            'gpt-4' => 0.03,
            'gpt-3.5-turbo' => 0.0005
        );
        
        // Detecta por prefixo
        if (strpos($model_id, 'gpt-4o-mini') === 0) {
            return 0.00015;
        } elseif (strpos($model_id, 'gpt-4o') === 0) {
            return 0.005;
        } elseif (strpos($model_id, 'gpt-4-turbo') === 0) {
            return 0.01;
        } elseif (strpos($model_id, 'gpt-4') === 0) {
            return 0.03;
        } elseif (strpos($model_id, 'gpt-3.5') === 0) {
            return 0.0005;
        }
        
        return $costs[$model_id] ?? 0.002; // Default fallback
    }
    
    /**
     * Retorna custo de output por 1K tokens
     */
    private function get_model_cost_output($model_id) {
        $costs = array(
            'gpt-4o' => 0.015,
            'gpt-4o-mini' => 0.0006,
            'gpt-4-turbo' => 0.03,
            'gpt-4' => 0.06,
            'gpt-3.5-turbo' => 0.0015
        );
        
        // Detecta por prefixo
        if (strpos($model_id, 'gpt-4o-mini') === 0) {
            return 0.0006;
        } elseif (strpos($model_id, 'gpt-4o') === 0) {
            return 0.015;
        } elseif (strpos($model_id, 'gpt-4-turbo') === 0) {
            return 0.03;
        } elseif (strpos($model_id, 'gpt-4') === 0) {
            return 0.06;
        } elseif (strpos($model_id, 'gpt-3.5') === 0) {
            return 0.0015;
        }
        
        return $costs[$model_id] ?? 0.002; // Default fallback
    }
    
    /**
     * Limpa HTML traduzido removendo elementos indesejados e corrigindo problemas comuns
     * 
     * @param string $translated_text Texto traduzido pela AI
     * @param string $original_text Texto original para comparação
     * @return string Texto limpo
     */
    private function clean_translated_html($translated_text, $original_text) {
        // 1. Remove spans e divs desnecessários que a AI pode ter adicionado
        $cleaned_text = $this->remove_unwanted_html_elements($translated_text, $original_text);
        
        // 2. Corrige entidades HTML
        $cleaned_text = $this->fix_html_entities($cleaned_text);
        
        // 3. Remove atributos ID/classes não presentes no original
        $cleaned_text = $this->preserve_original_attributes($cleaned_text, $original_text);
        
        // 4. Normaliza espaçamento
        $cleaned_text = $this->normalize_spacing($cleaned_text);
        
        return $cleaned_text;
    }
    
    /**
     * Remove spans e divs que não existiam no texto original
     */
    private function remove_unwanted_html_elements($translated_text, $original_text) {
        $cleaned_text = $translated_text;
        
        // Lista de elementos para verificar (mais elementos problemáticos)
        $elements_to_check = array('span', 'div', 'section', 'article', 'aside');
        
        foreach ($elements_to_check as $element) {
            // Conta quantos elementos existem no original vs traduzido
            $original_count = preg_match_all("/<{$element}[^>]*>/i", $original_text);
            $translated_count = preg_match_all("/<{$element}[^>]*>/i", $cleaned_text);
            
            $this->log_html_comparison($element, $original_count, $translated_count);
            
            // MAIS AGRESSIVO: Remove qualquer elemento extra, independente da contagem
            if ($translated_count > $original_count || $element === 'span') {
                // Remove spans e divs que envolvem apenas texto
                $cleaned_text = preg_replace("/<{$element}(?:\s+[^>]*)?>([^<]+)<\/{$element}>/i", '$1', $cleaned_text);
                
                // Remove elementos vazios
                $cleaned_text = preg_replace("/<{$element}[^>]*><\/{$element}>/i", '', $cleaned_text);
                
                // Remove elementos que apenas envolvem outros elementos válidos
                $cleaned_text = preg_replace("/<{$element}[^>]*>(<[^>]+>[^<]*<\/[^>]+>)<\/{$element}>/i", '$1', $cleaned_text);
                
                // Remove tags de fechamento órfãs
                $cleaned_text = $this->remove_orphaned_closing_tags($cleaned_text, $element);
            }
        }
        
        // LIMPEZA EXTRA: Remove spans simples que a AI pode ter adicionado
        $cleaned_text = preg_replace('/<span[^>]*>([^<]+)<\/span>/i', '$1', $cleaned_text);
        
        return $cleaned_text;
    }
    
    /**
     * Log para debug de comparação HTML
     */
    private function log_html_comparison($element, $original_count, $translated_count) {
        if ($translated_count > $original_count) {
            AlvoBotPro::debug_log('multi-languages', "HTML Cleanup: {$element} - Original: {$original_count}, Translated: {$translated_count} (removing extras)");
        }
    }
    
    /**
     * Remove tags de fechamento órfãs que não têm abertura correspondente
     */
    private function remove_orphaned_closing_tags($text, $element) {
        // Conta tags de abertura e fechamento
        $opening_count = preg_match_all("/<{$element}[^>]*>/i", $text);
        $closing_count = preg_match_all("/<\/{$element}>/i", $text);
        
        // Se há mais fechamentos que aberturas, remove os extras
        if ($closing_count > $opening_count) {
            $excess_closings = $closing_count - $opening_count;
            
            // Remove as últimas tags de fechamento em excesso
            for ($i = 0; $i < $excess_closings; $i++) {
                $text = preg_replace("/<\/{$element}>/i", '', $text, 1);
            }
        }
        
        // Remove também tags </div> soltas que aparecem no meio do texto
        if ($element === 'div') {
            // Remove </div> que aparece isolado ou após espaços/quebras
            $text = preg_replace('/\s*<\/div>\s*(?!\s*<)/i', ' ', $text);
            // Remove </div> no final de linha
            $text = preg_replace('/\s*<\/div>\s*$/i', '', $text);
        }
        
        return $text;
    }
    
    /**
     * Corrige entidades HTML comuns
     */
    private function fix_html_entities($text) {
        $replacements = array(
            '&nbsp;' => ' ',
            '&#160;' => ' ',
            '&amp;nbsp;' => ' ',
            '&rsquo;' => "'",
            '&lsquo;' => "'",
            '&rdquo;' => '"',
            '&ldquo;' => '"',
            '&mdash;' => '—',
            '&ndash;' => '–',
            '&hellip;' => '…'
        );
        
        $cleaned_text = str_replace(array_keys($replacements), array_values($replacements), $text);
        
        // Remove múltiplos espaços criados pela conversão de &nbsp;
        $cleaned_text = preg_replace('/\s+/', ' ', $cleaned_text);
        
        return $cleaned_text;
    }
    
    /**
     * Preserva atributos originais e remove os adicionados pela AI
     */
    private function preserve_original_attributes($translated_text, $original_text) {
        // Extrai todas as tags com atributos do texto original
        preg_match_all('/<(\w+)([^>]*)>/i', $original_text, $original_tags, PREG_SET_ORDER);
        
        $original_attributes = array();
        foreach ($original_tags as $tag_match) {
            $tag_name = strtolower($tag_match[1]);
            $attributes = trim($tag_match[2]);
            
            if (!empty($attributes)) {
                $original_attributes[$tag_name][] = $attributes;
            }
        }
        
        // Remove IDs e classes que não existiam no original
        $cleaned_text = preg_replace_callback(
            '/<(\w+)([^>]*)>/i',
            function($matches) use ($original_attributes) {
                // Removed HTML cleaning to prevent corruption of shortcodes and JSON structures
                // The translation prompt has been updated to handle HTML preservation
                return $matches[0];
            },
            $translated_text
        );
        
        return $cleaned_text;
    }
    
    /**
     * Normaliza espaçamento e quebras de linha - VERSÃO MAIS AGRESSIVA
     */
    private function normalize_spacing($text) {
        // LIMPEZA FINAL: Remove todas as tags estruturais soltas que a AI pode ter adicionado
        $unwanted_closing_tags = array('</div>', '</span>', '</section>', '</article>', '</aside>');
        foreach ($unwanted_closing_tags as $tag) {
            $text = preg_replace('/\s*' . preg_quote($tag, '/') . '\s*/i', ' ', $text);
        }
        
        // Remove tags de abertura órfãs também (spans e divs sem fechamento)
        $text = preg_replace('/<(span|div|section|article|aside)[^>]*>(?![^<]*<\/\1>)/i', '', $text);
        
        // Remove espaços extras dentro de tags
        $text = preg_replace('/(<[^>]+)\s+([^>]*>)/', '$1 $2', $text);
        
        // Normaliza espaços entre palavras
        $text = preg_replace('/\s{2,}/', ' ', $text);
        
        // Remove espaços no início e fim de elementos block
        $block_elements = array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote');
        foreach ($block_elements as $element) {
            $text = preg_replace("/(<{$element}[^>]*>)\s+/", '$1', $text);
            $text = preg_replace("/\s+(<\/{$element}>)/", '$1', $text);
        }
        
        // Remove quebras de linha extras
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        
        // Limpeza final de espaços em volta de elementos HTML
        $text = preg_replace('/>\s+</', '><', $text);
        
        AlvoBotPro::debug_log('multi-languages', "HTML Normalize: Completed aggressive cleanup");
        
        return trim($text);
    }
    
    /**
     * Valida idioma de um texto usando OpenAI (método especializado)
     * 
     * @param string $text Texto para validar
     * @return array Resultado da validação
     */
    public function validate_language_with_ai($text) {
        try {
            if (!$this->is_configured()) {
                return array('success' => false, 'error' => 'OpenAI não configurado');
            }
            
            // Prompt especializado para detecção de idioma
            $prompt = "Your only task is to identify the primary language of the text provided below.

**Output format rules:**
- Return ONLY the two-letter ISO 639-1 code for the language.
- Do not include any other words, explanations, punctuation, or formatting.
- If the text is primarily in Spanish, return \"es\".
- If the text is primarily in English, return \"en\".
- If the text is primarily in Portuguese, return \"pt\".
- If the text is primarily in French, return \"fr\".
- If the text is primarily in Italian, return \"it\".
- If the text is primarily in German, return \"de\".
- If the text is a mix of languages or you cannot determine a single primary language, return \"mix\".

Analyze the following text and provide your response:

---
" . substr(trim($text), 0, 2000) . "
---";

            // Dados da requisição otimizada para validação
            $request_data = array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a language detection expert. Respond only with the ISO 639-1 language code.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 10, // Muito baixo já que esperamos apenas 2 caracteres
                'temperature' => 0 // Determinístico
            );
            
            // Headers da requisição
            $headers = array(
                'Authorization' => 'Bearer ' . $this->settings['api_key'],
                'Content-Type' => 'application/json'
            );
            
            // Faz a requisição
            $response = $this->make_request($request_data, $headers);
            
            if (!$response['success']) {
                return array('success' => false, 'error' => $response['error']);
            }
            
            $result = json_decode($response['data'], true);
            
            if (!$result || !isset($result['choices'][0]['message']['content'])) {
                return array('success' => false, 'error' => 'Resposta inválida da OpenAI');
            }
            
            $detected_lang = trim(strtolower($result['choices'][0]['message']['content']));
            
            return array(
                'success' => true,
                'detected_language' => $detected_lang,
                'usage' => array(
                    'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                    'total_tokens' => $result['usage']['total_tokens'] ?? 0
                )
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => 'Erro na validação AI: ' . $e->getMessage());
        }
    }
    
    /**
     * Retorna resposta de erro padronizada
     */
    private function error_response($message) {
        return array(
            'success' => false,
            'error' => $message,
            'provider' => 'openai',
            'timestamp' => current_time('mysql')
        );
    }
}