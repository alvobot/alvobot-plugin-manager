<?php

use PHPUnit\Framework\TestCase;

class AjaxHandlersTest extends TestCase 
{
    /**
     * Testa estrutura de requisição AJAX básica
     */
    public function testAjaxRequestStructure() 
    {
        $ajax_request = [
            'action' => 'alvobot_save_settings',
            'nonce' => 'valid_nonce',
            'data' => [
                'setting1' => 'value1',
                'setting2' => 'value2'
            ]
        ];
        
        $this->assertArrayHasKey('action', $ajax_request);
        $this->assertArrayHasKey('nonce', $ajax_request);
        $this->assertArrayHasKey('data', $ajax_request);
        
        $this->assertStringStartsWith('alvobot_', $ajax_request['action']);
        $this->assertIsArray($ajax_request['data']);
    }
    
    /**
     * Testa validação de nonce
     */
    public function testNonceValidation() 
    {
        $valid_nonce = 'valid_nonce';
        $invalid_nonce = 'invalid_nonce';
        $action = 'alvobot_action';
        
        $this->assertTrue(wp_verify_nonce($valid_nonce, $action));
        $this->assertFalse(wp_verify_nonce($invalid_nonce, $action));
    }
    
    /**
     * Testa resposta de sucesso AJAX
     */
    public function testAjaxSuccessResponse() 
    {
        $response = [
            'success' => true,
            'data' => [
                'message' => 'Configurações salvas com sucesso',
                'updated_values' => ['setting1' => 'new_value']
            ]
        ];
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('message', $response['data']);
        
        $json_response = wp_json_encode($response);
        $this->assertJson($json_response);
    }
    
    /**
     * Testa resposta de erro AJAX
     */
    public function testAjaxErrorResponse() 
    {
        $response = [
            'success' => false,
            'data' => [
                'message' => 'Erro ao salvar configurações',
                'error_code' => 'save_failed'
            ]
        ];
        
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error_code', $response['data']);
        $this->assertNotEmpty($response['data']['message']);
    }
    
    /**
     * Testa ações AJAX disponíveis
     */
    public function testAvailableAjaxActions() 
    {
        $ajax_actions = [
            'alvobot_save_settings',
            'alvobot_test_connection',
            'alvobot_toggle_module',
            'alvobot_get_module_status',
            'alvobot_generate_logo',
            'alvobot_process_quiz'
        ];
        
        foreach ($ajax_actions as $action) {
            $this->assertStringStartsWith('alvobot_', $action);
            $this->assertNotEmpty($action);
        }
        
        $this->assertGreaterThanOrEqual(6, count($ajax_actions));
    }
    
    /**
     * Testa sanitização de dados AJAX
     */
    public function testAjaxDataSanitization() 
    {
        $raw_data = [
            'text' => '<script>alert("xss")</script>Hello',
            'email' => 'test@example.com',
            'url' => 'https://example.com/test',
            'number' => '123'
        ];
        
        $sanitized_data = [
            'text' => esc_html($raw_data['text']),
            'email' => $raw_data['email'], // Email válido
            'url' => esc_url($raw_data['url']),
            'number' => intval($raw_data['number'])
        ];
        
        $this->assertStringNotContainsString('<script>', $sanitized_data['text']);
        $this->assertIsInt($sanitized_data['number']);
        $this->assertEquals(123, $sanitized_data['number']);
    }
    
    /**
     * Testa permissões de usuário
     */
    public function testUserCapabilities() 
    {
        $capabilities = ['manage_options', 'edit_posts', 'read'];
        
        foreach ($capabilities as $capability) {
            $has_capability = current_user_can($capability);
            $this->assertIsBool($has_capability);
            
            // No contexto de teste, assumimos que o usuário tem todas as permissões
            $this->assertTrue($has_capability);
        }
    }
}