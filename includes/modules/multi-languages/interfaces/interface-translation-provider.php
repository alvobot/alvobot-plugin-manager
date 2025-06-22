<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface para provedores de tradução
 * 
 * Define o contrato que todos os provedores de tradução devem implementar
 */
interface AlvoBotPro_Translation_Provider_Interface {
    
    /**
     * Traduz um texto do idioma de origem para o idioma de destino
     * 
     * @param string $text Texto a ser traduzido
     * @param string $source_lang Idioma de origem (ISO 639-1)
     * @param string $target_lang Idioma de destino (ISO 639-1)
     * @param array $options Opções adicionais de tradução
     * @return array Array com 'success', 'translated_text', 'error', 'usage'
     */
    public function translate($text, $source_lang, $target_lang, $options = array());
    
    /**
     * Verifica se o provider está configurado corretamente
     * 
     * @return bool True se configurado, false caso contrário
     */
    public function is_configured();
    
    /**
     * Verifica se o provider está disponível para uso
     * 
     * @return bool True se disponível, false caso contrário
     */
    public function is_available();
    
    /**
     * Retorna o nome do provider
     * 
     * @return string Nome do provider
     */
    public function get_name();
    
    /**
     * Retorna a descrição do provider
     * 
     * @return string Descrição do provider
     */
    public function get_description();
    
    /**
     * Retorna os idiomas suportados pelo provider
     * 
     * @return array Array de idiomas suportados (códigos ISO 639-1)
     */
    public function get_supported_languages();
    
    /**
     * Retorna informações de uso/custo
     * 
     * @return array Array com estatísticas de uso
     */
    public function get_usage_stats();
    
    /**
     * Atualiza estatísticas de uso
     * 
     * @param array $usage_data Dados de uso da última tradução
     * @return bool True se atualizado com sucesso
     */
    public function update_usage_stats($usage_data);
    
    /**
     * Incrementa contador de posts traduzidos
     * 
     * @return bool True se incrementado com sucesso
     */
    public function increment_post_translation_count();
    
    /**
     * Valida configurações do provider
     * 
     * @param array $settings Configurações a serem validadas
     * @return array Array com 'valid' e 'errors'
     */
    public function validate_settings($settings);
    
    /**
     * Testa conectividade com o serviço
     * 
     * @return array Array com 'success', 'message', 'response_time'
     */
    public function test_connection();
}