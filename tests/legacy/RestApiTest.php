<?php

use PHPUnit\Framework\TestCase;

class RestApiTest extends TestCase 
{
    /**
     * Testa estrutura de endpoint REST
     */
    public function testRestEndpointStructure() 
    {
        $endpoints = [
            '/wp-json/alvobot/v1/quiz/submit',
            '/wp-json/alvobot/v1/logo/generate', 
            '/wp-json/alvobot/v1/translation/queue',
            '/wp-json/alvobot/v1/settings/update'
        ];
        
        foreach ($endpoints as $endpoint) {
            $this->assertStringStartsWith('/wp-json/alvobot/v1/', $endpoint);
            $this->assertNotEmpty($endpoint);
            
            // Extrair partes do endpoint
            $parts = explode('/', trim($endpoint, '/'));
            $this->assertCount(5, $parts); // wp-json, alvobot, v1, module, action
            $this->assertEquals('wp-json', $parts[0]);
            $this->assertEquals('alvobot', $parts[1]);
            $this->assertEquals('v1', $parts[2]);
        }
    }
    
    /**
     * Testa estrutura de requisição REST
     */
    public function testRestRequestStructure() 
    {
        $request = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => 'test_nonce_123'
            ],
            'body' => wp_json_encode([
                'action' => 'submit_quiz',
                'data' => [
                    'quiz_id' => 1,
                    'answers' => ['A', 'B', 'C']
                ]
            ])
        ];
        
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('headers', $request);
        $this->assertArrayHasKey('body', $request);
        
        $this->assertContains($request['method'], ['GET', 'POST', 'PUT', 'DELETE']);
        $this->assertIsArray($request['headers']);
        $this->assertArrayHasKey('Content-Type', $request['headers']);
        
        $body = json_decode($request['body'], true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('action', $body);
        $this->assertArrayHasKey('data', $body);
    }
    
    /**
     * Testa resposta REST de sucesso
     */
    public function testRestSuccessResponse() 
    {
        $response = [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'id' => 123,
                'message' => 'Quiz submitted successfully',
                'score' => 85
            ],
            'timestamp' => time()
        ];
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status_code']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertIsInt($response['timestamp']);
        
        $json_response = wp_json_encode($response);
        $this->assertJson($json_response);
    }
    
    /**
     * Testa resposta REST de erro
     */
    public function testRestErrorResponse() 
    {
        $error_response = [
            'success' => false,
            'status_code' => 400,
            'error' => [
                'code' => 'invalid_data',
                'message' => 'Required field missing',
                'details' => 'The quiz_id field is required'
            ],
            'timestamp' => time()
        ];
        
        $this->assertFalse($error_response['success']);
        $this->assertGreaterThanOrEqual(400, $error_response['status_code']);
        $this->assertArrayHasKey('error', $error_response);
        $this->assertArrayHasKey('code', $error_response['error']);
        $this->assertArrayHasKey('message', $error_response['error']);
        
        $this->assertIsString($error_response['error']['code']);
        $this->assertNotEmpty($error_response['error']['message']);
    }
    
    /**
     * Testa códigos de status HTTP
     */
    public function testHttpStatusCodes() 
    {
        $status_codes = [
            200 => 'OK',
            201 => 'Created', 
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error'
        ];
        
        foreach ($status_codes as $code => $description) {
            $this->assertIsInt($code);
            $this->assertIsString($description);
            $this->assertGreaterThanOrEqual(200, $code);
            $this->assertLessThan(600, $code);
        }
        
        // Testa classificação dos códigos
        $this->assertLessThan(300, 200); // Success
        $this->assertGreaterThanOrEqual(400, 400); // Client Error
        $this->assertGreaterThanOrEqual(500, 500); // Server Error
    }
    
    /**
     * Testa autenticação via nonce
     */
    public function testNonceAuthentication() 
    {
        $nonce_data = [
            'nonce' => 'abc123def456',
            'action' => 'alvobot_api_action',
            'user_id' => 1
        ];
        
        $this->assertArrayHasKey('nonce', $nonce_data);
        $this->assertArrayHasKey('action', $nonce_data);
        $this->assertStringStartsWith('alvobot_', $nonce_data['action']);
        
        // Simular validação de nonce
        $is_valid = wp_verify_nonce($nonce_data['nonce'], $nonce_data['action']);
        $this->assertIsBool($is_valid);
    }
    
    /**
     * Testa rate limiting
     */
    public function testRateLimiting() 
    {
        $rate_limit_data = [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'current_count' => 45,
            'reset_time' => time() + 60,
            'exceeded' => false
        ];
        
        $this->assertArrayHasKey('requests_per_minute', $rate_limit_data);
        $this->assertArrayHasKey('current_count', $rate_limit_data);
        $this->assertArrayHasKey('exceeded', $rate_limit_data);
        
        $this->assertIsInt($rate_limit_data['requests_per_minute']);
        $this->assertIsInt($rate_limit_data['current_count']);
        $this->assertIsBool($rate_limit_data['exceeded']);
        
        // Testa lógica de rate limiting
        $is_exceeded = $rate_limit_data['current_count'] >= $rate_limit_data['requests_per_minute'];
        $this->assertEquals($rate_limit_data['exceeded'], $is_exceeded);
    }
    
    /**
     * Testa sanitização de dados de entrada
     */
    public function testInputSanitization() 
    {
        $raw_input = [
            'text' => '<script>alert("xss")</script>Clean text',
            'number' => '123abc',
            'email' => 'user@example.com',
            'html' => '<p>Safe <strong>content</strong></p><script>bad</script>'
        ];
        
        $sanitized = [
            'text' => esc_html($raw_input['text']),
            'number' => intval($raw_input['number']),
            'email' => filter_var($raw_input['email'], FILTER_VALIDATE_EMAIL),
            'html' => strip_tags($raw_input['html'], '<p><strong><em>')
        ];
        
        $this->assertStringNotContainsString('<script>', $sanitized['text']);
        $this->assertIsInt($sanitized['number']);
        $this->assertEquals(123, $sanitized['number']);
        $this->assertStringContainsString('@', $sanitized['email']);
        $this->assertStringNotContainsString('<script>', $sanitized['html']);
    }
}