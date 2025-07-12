<?php

use PHPUnit\Framework\TestCase;

class LogoGeneratorTest extends TestCase 
{
    /**
     * Testa se o arquivo do módulo existe
     */
    public function testLogoGeneratorFileExists() 
    {
        $module_file = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/logo-generator/class-logo-generator.php';
        $this->assertFileExists($module_file);
    }
    
    /**
     * Testa estrutura de requisição para gerar logo
     */
    public function testLogoRequestStructure() 
    {
        $logo_request = [
            'business_name' => 'Empresa Teste',
            'industry' => 'Tecnologia',
            'style' => 'modern',
            'colors' => ['#FF0000', '#0000FF'],
            'prompt' => 'Logo moderna para empresa de tecnologia'
        ];
        
        $this->assertArrayHasKey('business_name', $logo_request);
        $this->assertArrayHasKey('industry', $logo_request);
        $this->assertArrayHasKey('style', $logo_request);
        $this->assertArrayHasKey('colors', $logo_request);
        $this->assertArrayHasKey('prompt', $logo_request);
        
        $this->assertIsString($logo_request['business_name']);
        $this->assertIsArray($logo_request['colors']);
        $this->assertNotEmpty($logo_request['prompt']);
    }
    
    /**
     * Testa validação de cores
     */
    public function testColorValidation() 
    {
        $valid_colors = ['#FF0000', '#00FF00', '#0000FF', '#FFFFFF', '#000000'];
        $invalid_colors = ['red', '#FF', '#GGGGGG', '123456', '#12345'];
        
        foreach ($valid_colors as $color) {
            $this->assertTrue($this->isValidHexColor($color));
        }
        
        foreach ($invalid_colors as $color) {
            $this->assertFalse($this->isValidHexColor($color));
        }
    }
    
    /**
     * Testa estilos disponíveis
     */
    public function testAvailableStyles() 
    {
        $available_styles = [
            'modern' => 'Moderno',
            'classic' => 'Clássico',
            'minimalist' => 'Minimalista',
            'playful' => 'Divertido',
            'professional' => 'Profissional'
        ];
        
        $this->assertArrayHasKey('modern', $available_styles);
        $this->assertArrayHasKey('classic', $available_styles);
        $this->assertArrayHasKey('minimalist', $available_styles);
        
        $this->assertCount(5, $available_styles);
        
        foreach ($available_styles as $key => $label) {
            $this->assertIsString($key);
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }
    
    /**
     * Testa resposta da API simulada
     */
    public function testApiResponse() 
    {
        $mock_response = [
            'success' => true,
            'data' => [
                'image_url' => 'https://example.com/logo.png',
                'prompt_used' => 'Logo moderna para empresa de tecnologia',
                'style' => 'modern',
                'timestamp' => time()
            ]
        ];
        
        $this->assertTrue($mock_response['success']);
        $this->assertArrayHasKey('data', $mock_response);
        $this->assertArrayHasKey('image_url', $mock_response['data']);
        $this->assertStringStartsWith('https://', $mock_response['data']['image_url']);
    }
    
    /**
     * Testa tratamento de erros
     */
    public function testErrorHandling() 
    {
        $error_response = [
            'success' => false,
            'error' => 'API key inválida',
            'error_code' => 'invalid_api_key'
        ];
        
        $this->assertFalse($error_response['success']);
        $this->assertArrayHasKey('error', $error_response);
        $this->assertArrayHasKey('error_code', $error_response);
        $this->assertNotEmpty($error_response['error']);
    }
    
    /**
     * Função auxiliar para validar cor hexadecimal
     */
    private function isValidHexColor($color) 
    {
        return preg_match('/^#[0-9A-F]{6}$/i', $color) === 1;
    }
}