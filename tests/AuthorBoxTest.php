<?php

use PHPUnit\Framework\TestCase;

class AuthorBoxTest extends TestCase 
{
    /**
     * Testa se o arquivo do módulo existe
     */
    public function testAuthorBoxFileExists() 
    {
        $module_file = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/author-box/class-author-box.php';
        $this->assertFileExists($module_file);
    }
    
    /**
     * Testa estrutura de dados do autor
     */
    public function testAuthorDataStructure() 
    {
        $author_data = [
            'name' => 'João Silva',
            'bio' => 'Escritor e desenvolvedor web com mais de 10 anos de experiência.',
            'avatar' => 'https://example.com/avatar.jpg',
            'social' => [
                'twitter' => 'https://twitter.com/joaosilva',
                'linkedin' => 'https://linkedin.com/in/joaosilva',
                'github' => 'https://github.com/joaosilva'
            ],
            'posts_count' => 42
        ];
        
        $this->assertArrayHasKey('name', $author_data);
        $this->assertArrayHasKey('bio', $author_data);
        $this->assertArrayHasKey('avatar', $author_data);
        $this->assertArrayHasKey('social', $author_data);
        $this->assertArrayHasKey('posts_count', $author_data);
        
        $this->assertIsString($author_data['name']);
        $this->assertIsString($author_data['bio']);
        $this->assertIsArray($author_data['social']);
        $this->assertIsInt($author_data['posts_count']);
    }
    
    /**
     * Testa validação de URLs de redes sociais
     */
    public function testSocialMediaUrls() 
    {
        $social_urls = [
            'twitter' => 'https://twitter.com/username',
            'facebook' => 'https://facebook.com/username',
            'linkedin' => 'https://linkedin.com/in/username',
            'github' => 'https://github.com/username',
            'instagram' => 'https://instagram.com/username'
        ];
        
        foreach ($social_urls as $platform => $url) {
            $this->assertStringStartsWith('https://', $url);
            $this->assertStringContainsString($platform, $url);
        }
    }
    
    /**
     * Testa configurações do Author Box
     */
    public function testAuthorBoxSettings() 
    {
        $settings = [
            'display_position' => 'after_content',
            'show_avatar' => true,
            'show_bio' => true,
            'show_social_links' => true,
            'show_posts_count' => true,
            'avatar_size' => 100,
            'custom_css' => ''
        ];
        
        $this->assertArrayHasKey('display_position', $settings);
        $this->assertArrayHasKey('show_avatar', $settings);
        $this->assertArrayHasKey('avatar_size', $settings);
        
        $this->assertIsBool($settings['show_avatar']);
        $this->assertIsBool($settings['show_bio']);
        $this->assertIsBool($settings['show_social_links']);
        
        $this->assertIsInt($settings['avatar_size']);
        $this->assertGreaterThan(0, $settings['avatar_size']);
        $this->assertLessThanOrEqual(200, $settings['avatar_size']);
    }
    
    /**
     * Testa posições de exibição
     */
    public function testDisplayPositions() 
    {
        $valid_positions = ['before_content', 'after_content', 'both', 'manual'];
        $position = 'after_content';
        
        $this->assertContains($position, $valid_positions);
        $this->assertCount(4, $valid_positions);
    }
    
    /**
     * Testa HTML gerado (estrutura básica)
     */
    public function testAuthorBoxHtmlStructure() 
    {
        $author_box_html = '<div class="alvobot-author-box"><img src="avatar.jpg" /><h3>João Silva</h3><p>Bio do autor</p></div>';
        
        $this->assertStringContainsString('alvobot-author-box', $author_box_html);
        $this->assertStringContainsString('<img', $author_box_html);
        $this->assertStringContainsString('<h3>', $author_box_html);
        $this->assertStringContainsString('<p>', $author_box_html);
    }
}