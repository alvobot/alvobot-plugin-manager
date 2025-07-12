<?php

use PHPUnit\Framework\TestCase;

class PluginBasicsTest extends TestCase 
{
    /**
     * Testa se as constantes do plugin estão definidas
     */
    public function testPluginConstantsDefined() 
    {
        $this->assertTrue(defined('ALVOBOT_PRO_VERSION'));
        $this->assertTrue(defined('ALVOBOT_PRO_PLUGIN_DIR'));
        $this->assertTrue(defined('ALVOBOT_PRO_PLUGIN_URL'));
        $this->assertTrue(defined('ALVOBOT_PRO_PLUGIN_FILE'));
    }
    
    /**
     * Testa os valores das constantes
     */
    public function testPluginConstantValues() 
    {
        $this->assertEquals('2.3.0', ALVOBOT_PRO_VERSION);
        $this->assertStringContainsString('alvobot-plugin-manager', ALVOBOT_PRO_PLUGIN_DIR);
        $this->assertStringContainsString('alvobot-plugin-manager', ALVOBOT_PRO_PLUGIN_URL);
    }
    
    /**
     * Testa se os diretórios do plugin existem
     */
    public function testPluginDirectoriesExist() 
    {
        $this->assertDirectoryExists(ALVOBOT_PRO_PLUGIN_DIR . 'includes');
        $this->assertDirectoryExists(ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules');
        $this->assertDirectoryExists(ALVOBOT_PRO_PLUGIN_DIR . 'assets');
    }
    
    /**
     * Testa se os arquivos principais existem
     */
    public function testMainFilesExist() 
    {
        $this->assertFileExists(ALVOBOT_PRO_PLUGIN_DIR . 'alvobot-pro.php');
        $this->assertFileExists(ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro.php');
        $this->assertFileExists(ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-pro-ajax.php');
    }
    
    /**
     * Testa módulos ativos
     */
    public function testActiveModules() 
    {
        $active_modules = get_option('alvobot_pro_active_modules');
        
        $this->assertIsArray($active_modules);
        $this->assertArrayHasKey('quiz-builder', $active_modules);
        $this->assertArrayHasKey('logo-generator', $active_modules);
        $this->assertArrayHasKey('author-box', $active_modules);
        
        $this->assertTrue($active_modules['quiz-builder']);
        $this->assertTrue($active_modules['logo-generator']);
    }
    
    /**
     * Testa configurações da API
     */
    public function testApiSettings() 
    {
        $api_settings = get_option('alvobot_openai_settings');
        
        $this->assertIsArray($api_settings);
        $this->assertArrayHasKey('api_key', $api_settings);
        $this->assertNotEmpty($api_settings['api_key']);
    }
}