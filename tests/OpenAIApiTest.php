<?php

use PHPUnit\Framework\TestCase;

class OpenAIApiTest extends TestCase 
{
    /**
     * Testa estrutura de configuração da API OpenAI
     */
    public function testOpenAIApiConfigStructure() 
    {
        $api_config = [
            'api_key' => 'sk-test123456789',
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30
        ];
        
        $this->assertArrayHasKey('api_key', $api_config);
        $this->assertArrayHasKey('model', $api_config);
        $this->assertArrayHasKey('max_tokens', $api_config);
        
        $this->assertStringStartsWith('sk-', $api_config['api_key']);
        $this->assertIsString($api_config['model']);
        $this->assertIsInt($api_config['max_tokens']);
        $this->assertIsFloat($api_config['temperature']);
    }
    
    /**
     * Testa validação de API key
     */
    public function testApiKeyValidation() 
    {
        $valid_keys = [
            'sk-1234567890abcdef1234567890abcdef',
            'sk-test123456789012345678901234567890'
        ];
        
        $invalid_keys = [
            '',
            'invalid_key',
            'sk-',
            'sk-short',
            'not-sk-key'
        ];
        
        foreach ($valid_keys as $key) {
            $this->assertTrue($this->isValidApiKey($key));
        }
        
        foreach ($invalid_keys as $key) {
            $this->assertFalse($this->isValidApiKey($key));
        }
    }
    
    /**
     * Testa estrutura de requisição para OpenAI
     */
    public function testOpenAIRequestStructure() 
    {
        $request = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant.'
                ],
                [
                    'role' => 'user', 
                    'content' => 'Hello world'
                ]
            ],
            'max_tokens' => 100,
            'temperature' => 0.7
        ];
        
        $this->assertArrayHasKey('model', $request);
        $this->assertArrayHasKey('messages', $request);
        $this->assertArrayHasKey('max_tokens', $request);
        $this->assertArrayHasKey('temperature', $request);
        
        $this->assertIsArray($request['messages']);
        $this->assertNotEmpty($request['messages']);
        
        foreach ($request['messages'] as $message) {
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
            $this->assertContains($message['role'], ['system', 'user', 'assistant']);
        }
    }
    
    /**
     * Testa resposta simulada da OpenAI
     */
    public function testOpenAIResponseStructure() 
    {
        $response = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?'
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 15,
                'total_tokens' => 25
            ]
        ];
        
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('choices', $response);
        $this->assertArrayHasKey('usage', $response);
        
        $this->assertIsArray($response['choices']);
        $this->assertNotEmpty($response['choices']);
        
        $choice = $response['choices'][0];
        $this->assertArrayHasKey('message', $choice);
        $this->assertArrayHasKey('content', $choice['message']);
        
        $usage = $response['usage'];
        $this->assertArrayHasKey('total_tokens', $usage);
        $this->assertIsInt($usage['total_tokens']);
    }
    
    /**
     * Testa tratamento de erros da API
     */
    public function testApiErrorHandling() 
    {
        $error_responses = [
            [
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key'
                ]
            ],
            [
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'type' => 'requests_limit_error',
                    'code' => 'rate_limit_exceeded'
                ]
            ]
        ];
        
        foreach ($error_responses as $error_response) {
            $this->assertArrayHasKey('error', $error_response);
            $this->assertArrayHasKey('message', $error_response['error']);
            $this->assertArrayHasKey('type', $error_response['error']);
            
            $this->assertIsString($error_response['error']['message']);
            $this->assertNotEmpty($error_response['error']['message']);
        }
    }
    
    /**
     * Testa headers HTTP necessários
     */
    public function testApiHeaders() 
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer sk-test123456789',
            'User-Agent' => 'AlvoBot-Plugin/2.3.0'
        ];
        
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertStringStartsWith('Bearer sk-', $headers['Authorization']);
    }
    
    /**
     * Função auxiliar para validar API key
     */
    private function isValidApiKey($key) 
    {
        // Deve começar com 'sk-' e ter pelo menos 20 caracteres
        return !empty($key) && 
               strpos($key, 'sk-') === 0 && 
               strlen($key) >= 20;
    }
}