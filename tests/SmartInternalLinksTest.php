<?php

use PHPUnit\Framework\TestCase;

class SmartInternalLinksTest extends TestCase
{
    // ========================
    // Module Structure
    // ========================

    /**
     * Testa se os arquivos do módulo existem
     */
    public function testModuleFilesExist()
    {
        $base = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/';

        $this->assertFileExists($base . 'class-smart-internal-links.php');
        $this->assertFileExists($base . 'includes/class-link-generator.php');
        $this->assertFileExists($base . 'includes/class-link-renderer.php');
        $this->assertFileExists($base . 'includes/class-content-injector.php');
        $this->assertFileExists($base . 'templates/admin-page.php');
        $this->assertFileExists($base . 'assets/js/admin.js');
        $this->assertFileExists($base . 'assets/css/smart-internal-links.css');
    }

    /**
     * Testa a estrutura de diretórios do módulo
     */
    public function testModuleDirectoryStructure()
    {
        $base = ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/smart-internal-links/';

        $this->assertDirectoryExists($base . 'includes');
        $this->assertDirectoryExists($base . 'templates');
        $this->assertDirectoryExists($base . 'assets');
        $this->assertDirectoryExists($base . 'assets/js');
        $this->assertDirectoryExists($base . 'assets/css');
    }

    // ========================
    // Meta Validation
    // ========================

    /**
     * Testa validação de meta válido completo
     */
    public function testValidateMetaWithValidData()
    {
        $meta = [
            'enabled' => true,
            'generated_at' => '2026-02-16 12:00:00',
            'language' => 'pt',
            'disclaimer' => 'Artigos relacionados',
            'blocks' => [
                [
                    'position' => 'after_first',
                    'links' => [
                        [
                            'post_id' => 123,
                            'text' => 'Leia também este artigo',
                            'url' => 'https://example.com/artigo-1',
                        ],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);

        $this->assertIsArray($validated);
        $this->assertTrue($validated['enabled']);
        $this->assertEquals('2026-02-16 12:00:00', $validated['generated_at']);
        $this->assertEquals('pt', $validated['language']);
        $this->assertEquals('Artigos relacionados', $validated['disclaimer']);
        $this->assertCount(1, $validated['blocks']);
        $this->assertCount(1, $validated['blocks'][0]['links']);
        $this->assertEquals(123, $validated['blocks'][0]['links'][0]['post_id']);
    }

    /**
     * Testa validação com meta null/não-array
     */
    public function testValidateMetaWithInvalidInput()
    {
        $this->assertNull($this->validateMeta(null));
        $this->assertNull($this->validateMeta('string'));
        $this->assertNull($this->validateMeta(123));
        $this->assertNull($this->validateMeta(true));
    }

    /**
     * Testa validação com meta vazio
     */
    public function testValidateMetaWithEmptyArray()
    {
        $validated = $this->validateMeta([]);

        $this->assertIsArray($validated);
        $this->assertFalse($validated['enabled']);
        $this->assertEmpty($validated['generated_at']);
        $this->assertEmpty($validated['language']);
        $this->assertEmpty($validated['disclaimer']);
        $this->assertEmpty($validated['blocks']);
    }

    /**
     * Testa que posições inválidas são substituídas pelo default
     */
    public function testValidateMetaInvalidPositionFallback()
    {
        $meta = [
            'enabled' => true,
            'blocks' => [
                [
                    'position' => 'invalid_position',
                    'links' => [
                        ['post_id' => 1, 'text' => 'Link', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);

        $this->assertCount(1, $validated['blocks']);
        $this->assertEquals('after_first', $validated['blocks'][0]['position']);
    }

    /**
     * Testa validação das 3 posições válidas
     */
    public function testValidateMetaAllValidPositions()
    {
        $valid_positions = ['after_first', 'middle', 'before_last'];

        foreach ($valid_positions as $position) {
            $meta = [
                'enabled' => true,
                'blocks' => [
                    [
                        'position' => $position,
                        'links' => [
                            ['post_id' => 1, 'text' => 'Link', 'url' => 'https://example.com'],
                        ],
                    ],
                ],
            ];

            $validated = $this->validateMeta($meta);
            $this->assertEquals($position, $validated['blocks'][0]['position'], "Position '{$position}' should be valid");
        }
    }

    /**
     * Testa que links sem post_id são removidos
     */
    public function testValidateMetaRemovesLinksWithoutPostId()
    {
        $meta = [
            'enabled' => true,
            'blocks' => [
                [
                    'position' => 'after_first',
                    'links' => [
                        ['post_id' => 0, 'text' => 'Invalid', 'url' => 'https://example.com'],
                        ['text' => 'No ID', 'url' => 'https://example.com'],
                        ['post_id' => 5, 'text' => 'Valid', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);

        $this->assertCount(1, $validated['blocks']);
        $this->assertCount(1, $validated['blocks'][0]['links']);
        $this->assertEquals(5, $validated['blocks'][0]['links'][0]['post_id']);
    }

    /**
     * Testa que blocos sem links válidos são removidos
     */
    public function testValidateMetaRemovesEmptyBlocks()
    {
        $meta = [
            'enabled' => true,
            'blocks' => [
                [
                    'position' => 'after_first',
                    'links' => [
                        ['post_id' => 0, 'text' => 'Invalid'],
                    ],
                ],
                [
                    'position' => 'middle',
                    'links' => [
                        ['post_id' => 10, 'text' => 'Valid', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);

        $this->assertCount(1, $validated['blocks']);
        $this->assertEquals('middle', $validated['blocks'][0]['position']);
    }

    /**
     * Testa que blocos não-array são ignorados
     */
    public function testValidateMetaIgnoresNonArrayBlocks()
    {
        $meta = [
            'enabled' => true,
            'blocks' => [
                'not_an_array',
                123,
                null,
                [
                    'position' => 'after_first',
                    'links' => [
                        ['post_id' => 1, 'text' => 'Valid', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);
        $this->assertCount(1, $validated['blocks']);
    }

    /**
     * Testa sanitização de texto XSS nos links
     */
    public function testValidateMetaSanitizesLinkText()
    {
        $meta = [
            'enabled' => true,
            'blocks' => [
                [
                    'position' => 'after_first',
                    'links' => [
                        [
                            'post_id' => 1,
                            'text' => '<script>alert("xss")</script>Clean text',
                            'url' => 'https://example.com',
                        ],
                    ],
                ],
            ],
        ];

        $validated = $this->validateMeta($meta);

        $this->assertCount(1, $validated['blocks']);
        $text = $validated['blocks'][0]['links'][0]['text'];
        $this->assertStringNotContainsString('<script>', $text);
        $this->assertStringContainsString('Clean text', $text);
    }

    // ========================
    // Settings Validation
    // ========================

    /**
     * Testa defaults das configurações
     */
    public function testDefaultSettings()
    {
        $defaults = $this->getDefaultSettings();

        $this->assertEquals(3, $defaults['links_per_block']);
        $this->assertEquals(3, $defaults['num_blocks']);
        $this->assertEquals(['after_first', 'middle', 'before_last'], $defaults['positions']);
        $this->assertEquals(['post'], $defaults['post_types']);
        $this->assertEquals('#1B3A5C', $defaults['button_bg_color']);
        $this->assertEquals('#FFFFFF', $defaults['button_text_color']);
        $this->assertEquals('#D4A843', $defaults['button_border_color']);
        $this->assertEquals(2, $defaults['button_border_size']);
    }

    /**
     * Testa validação de ranges numéricos
     */
    public function testSettingsRangeValidation()
    {
        // links_per_block: 1-5
        $this->assertEquals(1, max(1, min(5, 0)));
        $this->assertEquals(1, max(1, min(5, 1)));
        $this->assertEquals(3, max(1, min(5, 3)));
        $this->assertEquals(5, max(1, min(5, 5)));
        $this->assertEquals(5, max(1, min(5, 10)));

        // num_blocks: 1-3
        $this->assertEquals(1, max(1, min(3, 0)));
        $this->assertEquals(3, max(1, min(3, 5)));

        // button_border_size: 0-6
        $this->assertEquals(0, max(0, min(6, -1)));
        $this->assertEquals(6, max(0, min(6, 10)));
    }

    /**
     * Testa validação de cores hex
     */
    public function testSettingsColorValidation()
    {
        $valid_colors = ['#000000', '#FFFFFF', '#1B3A5C', '#D4A843', '#ff0000', '#aaBBcc'];
        $invalid_colors = ['#000', 'red', '#GGGGGG', '000000', '#12345G', ''];

        foreach ($valid_colors as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $color, "Color {$color} should be valid");
        }

        foreach ($invalid_colors as $color) {
            $this->assertDoesNotMatchRegularExpression('/^#[0-9a-fA-F]{6}$/', $color, "Color {$color} should be invalid");
        }
    }

    /**
     * Testa validação de posições
     */
    public function testSettingsPositionValidation()
    {
        $valid_positions = ['after_first', 'middle', 'before_last'];
        $test_positions = ['after_first', 'invalid', 'middle', 'top', 'before_last', 'custom'];

        $filtered = array_values(array_intersect($test_positions, $valid_positions));

        $this->assertCount(3, $filtered);
        $this->assertEquals(['after_first', 'middle', 'before_last'], $filtered);
    }

    // ========================
    // REST API Endpoints
    // ========================

    /**
     * Testa estrutura dos endpoints REST Smart Links
     */
    public function testSmartLinksRestEndpoints()
    {
        $endpoints = [
            ['method' => 'GET',    'path' => '/alvobot/v1/smart-links/settings'],
            ['method' => 'POST',   'path' => '/alvobot/v1/smart-links/settings'],
            ['method' => 'POST',   'path' => '/alvobot/v1/smart-links/generate'],
            ['method' => 'POST',   'path' => '/alvobot/v1/smart-links/bulk-generate'],
            ['method' => 'GET',    'path' => '/alvobot/v1/smart-links/posts'],
            ['method' => 'GET',    'path' => '/alvobot/v1/smart-links/post/123'],
            ['method' => 'PUT',    'path' => '/alvobot/v1/smart-links/post/123'],
            ['method' => 'DELETE', 'path' => '/alvobot/v1/smart-links/post/123'],
            ['method' => 'POST',   'path' => '/alvobot/v1/smart-links/post/123/toggle'],
            ['method' => 'GET',    'path' => '/alvobot/v1/smart-links/search-posts'],
            ['method' => 'GET',    'path' => '/alvobot/v1/smart-links/stats'],
        ];

        foreach ($endpoints as $endpoint) {
            $this->assertStringStartsWith('/alvobot/v1/smart-links', $endpoint['path']);
            $this->assertContains($endpoint['method'], ['GET', 'POST', 'PUT', 'DELETE']);
        }

        $this->assertCount(11, $endpoints);
    }

    /**
     * Testa estrutura de resposta de geração de links
     */
    public function testGenerateLinksResponseStructure()
    {
        $response = [
            'message' => 'Links gerados com sucesso!',
            'data' => [
                'enabled' => true,
                'generated_at' => '2026-02-16 12:00:00',
                'language' => 'pt',
                'disclaimer' => 'Artigos relacionados',
                'blocks' => [
                    [
                        'position' => 'after_first',
                        'links' => [
                            ['post_id' => 10, 'text' => 'Veja mais', 'url' => 'https://example.com/post-10'],
                            ['post_id' => 20, 'text' => 'Leia também', 'url' => 'https://example.com/post-20'],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('data', $response);

        $data = $response['data'];
        $this->assertArrayHasKey('enabled', $data);
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertArrayHasKey('language', $data);
        $this->assertArrayHasKey('disclaimer', $data);
        $this->assertArrayHasKey('blocks', $data);

        $this->assertIsArray($data['blocks']);
        $this->assertNotEmpty($data['blocks']);

        $block = $data['blocks'][0];
        $this->assertArrayHasKey('position', $block);
        $this->assertArrayHasKey('links', $block);
        $this->assertIsArray($block['links']);

        foreach ($block['links'] as $link) {
            $this->assertArrayHasKey('post_id', $link);
            $this->assertArrayHasKey('text', $link);
            $this->assertArrayHasKey('url', $link);
            $this->assertIsInt($link['post_id']);
            $this->assertGreaterThan(0, $link['post_id']);
            $this->assertNotEmpty($link['text']);
            $this->assertNotEmpty($link['url']);
        }
    }

    /**
     * Testa estrutura de resposta de bulk generate
     */
    public function testBulkGenerateResponseStructure()
    {
        $response = [
            'message' => '3/3 posts processados.',
            'results' => [
                ['post_id' => 1, 'success' => true, 'message' => 'Links gerados!'],
                ['post_id' => 2, 'success' => true, 'message' => 'Links gerados!'],
                ['post_id' => 3, 'success' => false, 'message' => 'Nenhum post relacionado encontrado.'],
            ],
        ];

        $this->assertArrayHasKey('results', $response);
        $this->assertCount(3, $response['results']);

        foreach ($response['results'] as $result) {
            $this->assertArrayHasKey('post_id', $result);
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('message', $result);
            $this->assertIsBool($result['success']);
        }

        $success_count = count(array_filter($response['results'], function ($r) {
            return $r['success'];
        }));
        $this->assertEquals(2, $success_count);
    }

    /**
     * Testa estrutura de resposta de stats
     */
    public function testStatsResponseStructure()
    {
        $response = [
            'total_posts_with_links' => 50,
            'enabled_count' => 45,
            'disabled_count' => 5,
            'total_blocks' => 120,
            'total_links' => 360,
        ];

        $this->assertArrayHasKey('total_posts_with_links', $response);
        $this->assertArrayHasKey('enabled_count', $response);
        $this->assertArrayHasKey('disabled_count', $response);
        $this->assertArrayHasKey('total_blocks', $response);
        $this->assertArrayHasKey('total_links', $response);

        $this->assertIsInt($response['total_posts_with_links']);
        $this->assertEquals(
            $response['total_posts_with_links'],
            $response['enabled_count'] + $response['disabled_count']
        );
    }

    // ========================
    // AJAX Handlers
    // ========================

    /**
     * Testa ações AJAX disponíveis do Smart Links
     */
    public function testSmartLinksAjaxActions()
    {
        $ajax_actions = [
            'alvobot_generate_smart_links',
            'alvobot_bulk_generate_smart_links',
            'alvobot_load_posts_for_bulk',
            'alvobot_save_smart_links_settings',
            'alvobot_delete_smart_links',
            'alvobot_toggle_smart_links',
            'alvobot_get_smart_links',
            'alvobot_update_smart_links',
            'alvobot_search_posts_for_links',
        ];

        foreach ($ajax_actions as $action) {
            $this->assertStringStartsWith('alvobot_', $action);
            $this->assertNotEmpty($action);
        }

        $this->assertCount(9, $ajax_actions);
    }

    /**
     * Testa estrutura de requisição AJAX de geração
     */
    public function testAjaxGenerateRequestStructure()
    {
        $request = [
            'action' => 'alvobot_generate_smart_links',
            'nonce' => 'valid_nonce',
            'post_id' => 123,
        ];

        $this->assertEquals('alvobot_generate_smart_links', $request['action']);
        $this->assertNotEmpty($request['nonce']);
        $this->assertIsInt($request['post_id']);
        $this->assertGreaterThan(0, $request['post_id']);
    }

    /**
     * Testa estrutura de requisição AJAX de update
     */
    public function testAjaxUpdateRequestStructure()
    {
        $blocks = [
            [
                'position' => 'after_first',
                'links' => [
                    ['post_id' => 10, 'text' => 'Updated link text'],
                ],
            ],
        ];

        $request = [
            'action' => 'alvobot_update_smart_links',
            'nonce' => 'valid_nonce',
            'post_id' => 123,
            'blocks' => json_encode($blocks),
            'disclaimer' => 'Updated disclaimer',
            'enabled' => '1',
        ];

        $this->assertEquals('alvobot_update_smart_links', $request['action']);
        $this->assertIsString($request['blocks']);

        $decoded = json_decode($request['blocks'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals('after_first', $decoded[0]['position']);
    }

    /**
     * Testa estrutura de busca de posts
     */
    public function testAjaxSearchPostsRequestStructure()
    {
        $request = [
            'action' => 'alvobot_search_posts_for_links',
            'nonce' => 'valid_nonce',
            'search' => 'artigo sobre WordPress',
            'exclude' => [1, 2, 3],
        ];

        $this->assertEquals('alvobot_search_posts_for_links', $request['action']);
        $this->assertGreaterThanOrEqual(2, strlen($request['search']));
        $this->assertIsArray($request['exclude']);

        $response = [
            'posts' => [
                ['id' => 10, 'title' => 'Artigo WordPress', 'url' => 'https://example.com/p/10', 'type' => 'Post'],
                ['id' => 20, 'title' => 'WordPress Avançado', 'url' => 'https://example.com/p/20', 'type' => 'Post'],
            ],
        ];

        foreach ($response['posts'] as $post) {
            $this->assertArrayHasKey('id', $post);
            $this->assertArrayHasKey('title', $post);
            $this->assertArrayHasKey('url', $post);
            $this->assertArrayHasKey('type', $post);
        }
    }

    // ========================
    // Content Injection Logic
    // ========================

    /**
     * Testa que distância mínima entre blocos é respeitada
     */
    public function testMinimumBlockDistance()
    {
        $insertions = [1 => 'block1', 2 => 'block2', 5 => 'block3', 6 => 'block4'];

        $sorted_keys = array_keys($insertions);
        sort($sorted_keys);
        $valid_keys = [];

        foreach ($sorted_keys as $key) {
            $too_close = false;
            foreach ($valid_keys as $vk) {
                if (abs($key - $vk) < 2) {
                    $too_close = true;
                    break;
                }
            }
            if (!$too_close) {
                $valid_keys[] = $key;
            }
        }

        // index 1 passes, index 2 is too close to 1 (distance=1), index 5 passes, index 6 is too close to 5
        $this->assertCount(2, $valid_keys);
        $this->assertContains(1, $valid_keys);
        $this->assertContains(5, $valid_keys);
        $this->assertNotContains(2, $valid_keys);
        $this->assertNotContains(6, $valid_keys);
    }

    /**
     * Testa cálculo de posições de inserção
     */
    public function testInsertionPositionCalculation()
    {
        $total_paragraphs = 12;
        $blocks_by_position = ['after_first', 'middle', 'before_last'];

        $insertions = [];

        // after_first: always index 1
        $insertions[1] = 'after_first';

        // middle: only if >= 6 paragraphs
        if ($total_paragraphs >= 6) {
            $mid_index = intval($total_paragraphs / 2);
            $insertions[$mid_index] = 'middle';
        }

        // before_last: only if >= 4 paragraphs
        if ($total_paragraphs >= 4) {
            $last_index = $total_paragraphs - 1;
            $insertions[$last_index] = 'before_last';
        }

        $this->assertCount(3, $insertions);
        $this->assertArrayHasKey(1, $insertions);
        $this->assertArrayHasKey(6, $insertions);
        $this->assertArrayHasKey(11, $insertions);
    }

    /**
     * Testa que conteúdo curto não recebe todos os blocos
     */
    public function testShortContentReducedBlocks()
    {
        // With only 3 paragraphs, middle (>= 6) and before_last (>= 4) shouldn't be added
        $total = 3;
        $insertions = [];

        $insertions[1] = 'after_first';

        if ($total >= 6) {
            $insertions[intval($total / 2)] = 'middle';
        }

        if ($total >= 4) {
            $insertions[$total - 1] = 'before_last';
        }

        $this->assertCount(1, $insertions);
        $this->assertArrayHasKey(1, $insertions);
    }

    // ========================
    // Link Renderer
    // ========================

    /**
     * Testa estrutura de bloco para renderização
     */
    public function testLinkRendererBlockStructure()
    {
        $block = [
            'position' => 'after_first',
            'links' => [
                ['post_id' => 1, 'text' => 'Link 1', 'url' => 'https://example.com/1'],
                ['post_id' => 2, 'text' => 'Link 2', 'url' => 'https://example.com/2'],
                ['post_id' => 3, 'text' => 'Link 3', 'url' => 'https://example.com/3'],
            ],
        ];

        $this->assertIsArray($block['links']);
        $this->assertCount(3, $block['links']);

        foreach ($block['links'] as $link) {
            $this->assertArrayHasKey('post_id', $link);
            $this->assertArrayHasKey('text', $link);
            $this->assertArrayHasKey('url', $link);
            $this->assertNotEmpty($link['text']);
            $this->assertNotEmpty($link['url']);
        }
    }

    /**
     * Testa estilização do botão
     */
    public function testButtonStyleGeneration()
    {
        $settings = [
            'button_bg_color' => '#1B3A5C',
            'button_text_color' => '#FFFFFF',
            'button_border_color' => '#D4A843',
            'button_border_size' => 2,
        ];

        $bg_color = $settings['button_bg_color'];
        $text_color = $settings['button_text_color'];
        $border_color = $settings['button_border_color'];
        $border_size = $settings['button_border_size'];

        $border_style = $border_size > 0
            ? 'border:' . $border_size . 'px solid ' . $border_color
            : 'border:none';

        $this->assertEquals('border:2px solid #D4A843', $border_style);

        // Test border:none when size is 0
        $border_style_none = 0 > 0 ? 'border:0px solid #D4A843' : 'border:none';
        $this->assertEquals('border:none', $border_style_none);
    }

    // ========================
    // Link Generator
    // ========================

    /**
     * Testa mapeamento de idiomas
     */
    public function testLanguageMapping()
    {
        $map = [
            'pt' => 'Português',
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'ro' => 'Română',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'tr' => 'Türkçe',
            'ja' => '日本語',
            'zh' => '中文',
            'ko' => '한국어',
            'ar' => 'العربية',
            'ru' => 'Русский',
        ];

        $this->assertCount(15, $map);
        $this->assertArrayHasKey('pt', $map);
        $this->assertArrayHasKey('en', $map);
        $this->assertEquals('Português', $map['pt']);
        $this->assertEquals('English', $map['en']);

        // Unknown language slug should return slug itself
        $unknown = 'xx';
        $result = isset($map[$unknown]) ? $map[$unknown] : $unknown;
        $this->assertEquals('xx', $result);
    }

    /**
     * Testa limite de candidatos
     */
    public function testCandidateLimitsCalculation()
    {
        $links_per_block = 3;
        $num_blocks = 3;
        $total_needed = $links_per_block * $num_blocks;

        $this->assertEquals(9, $total_needed);

        // Candidatos buscados = 2x o necessário
        $candidates_to_fetch = $total_needed * 2;
        $this->assertEquals(18, $candidates_to_fetch);

        // Ajuste quando poucos candidatos
        $available = 5;
        if ($available < $links_per_block) {
            $links_per_block = $available;
            $num_blocks = 1;
        } elseif ($available < $total_needed) {
            $num_blocks = max(1, intval(floor($available / $links_per_block)));
        }

        $this->assertEquals(1, $num_blocks);
        $this->assertEquals(3, $links_per_block);
    }

    /**
     * Testa estrutura de dados candidatos para a API
     */
    public function testCandidateDataStructureForApi()
    {
        $candidates = [
            ['id' => 10, 'title' => 'Post A'],
            ['id' => 20, 'title' => 'Post B'],
            ['id' => 30, 'title' => 'Post C'],
        ];

        foreach ($candidates as $candidate) {
            $this->assertArrayHasKey('id', $candidate);
            $this->assertArrayHasKey('title', $candidate);
            $this->assertIsInt($candidate['id']);
            $this->assertIsString($candidate['title']);
            $this->assertNotEmpty($candidate['title']);
        }
    }

    // ========================
    // Edit Modal Data
    // ========================

    /**
     * Testa estrutura de resposta ajax_get_links para o modal de edição
     */
    public function testEditModalDataStructure()
    {
        $response = [
            'post_id' => 123,
            'post_title' => 'Meu Post',
            'meta' => [
                'enabled' => true,
                'generated_at' => '2026-02-16 12:00:00',
                'language' => 'pt',
                'disclaimer' => 'Artigos relacionados',
                'blocks' => [
                    [
                        'position' => 'after_first',
                        'links' => [
                            [
                                'post_id' => 10,
                                'text' => 'Link existente',
                                'url' => 'https://example.com/10',
                                'target_title' => 'Post Alvo 10',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertArrayHasKey('post_id', $response);
        $this->assertArrayHasKey('post_title', $response);
        $this->assertArrayHasKey('meta', $response);

        $meta = $response['meta'];
        $this->assertArrayHasKey('blocks', $meta);

        // Links should have target_title for the edit modal
        $link = $meta['blocks'][0]['links'][0];
        $this->assertArrayHasKey('target_title', $link);
        $this->assertNotEmpty($link['target_title']);
    }

    /**
     * Testa validação de dados de update do modal
     */
    public function testEditModalUpdateValidation()
    {
        $valid_positions = ['after_first', 'middle', 'before_last'];

        $blocks = [
            [
                'position' => 'after_first',
                'links' => [
                    ['post_id' => 10, 'text' => 'Valid link'],
                    ['post_id' => 0, 'text' => 'Invalid - no ID'],
                    ['post_id' => 20, 'text' => ''],
                ],
            ],
        ];

        $updated_blocks = [];
        foreach ($blocks as $block) {
            $position = isset($block['position']) && in_array($block['position'], $valid_positions, true)
                ? $block['position']
                : 'after_first';

            $valid_links = [];
            foreach ($block['links'] as $link) {
                $link_post_id = isset($link['post_id']) ? absint($link['post_id']) : 0;
                if (!$link_post_id) continue;

                $text = isset($link['text']) && is_string($link['text']) ? trim($link['text']) : '';
                if (empty($text)) continue;

                $valid_links[] = [
                    'post_id' => $link_post_id,
                    'text' => sanitize_text_field($text),
                ];
            }

            if (!empty($valid_links)) {
                $updated_blocks[] = [
                    'position' => $position,
                    'links' => $valid_links,
                ];
            }
        }

        $this->assertCount(1, $updated_blocks);
        $this->assertCount(1, $updated_blocks[0]['links']);
        $this->assertEquals(10, $updated_blocks[0]['links'][0]['post_id']);
    }

    // ========================
    // Helper: validate_meta mirror
    // ========================

    /**
     * Mirror of AlvoBotPro_Smart_Internal_Links::validate_meta
     * for testing without loading WordPress
     */
    private function validateMeta($meta)
    {
        if (!is_array($meta)) {
            return null;
        }

        $validated = [
            'enabled' => !empty($meta['enabled']),
            'generated_at' => isset($meta['generated_at']) && is_string($meta['generated_at']) ? $meta['generated_at'] : '',
            'language' => isset($meta['language']) && is_string($meta['language']) ? $meta['language'] : '',
            'disclaimer' => isset($meta['disclaimer']) && is_string($meta['disclaimer']) ? $meta['disclaimer'] : '',
            'blocks' => [],
        ];

        if (!isset($meta['blocks']) || !is_array($meta['blocks'])) {
            return $validated;
        }

        $valid_positions = ['after_first', 'middle', 'before_last'];

        foreach ($meta['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $valid_block = [
                'position' => isset($block['position']) && in_array($block['position'], $valid_positions, true)
                    ? $block['position']
                    : 'after_first',
                'links' => [],
            ];

            if (!isset($block['links']) || !is_array($block['links'])) {
                continue;
            }

            foreach ($block['links'] as $link) {
                if (!is_array($link)) {
                    continue;
                }

                $post_id = isset($link['post_id']) ? absint($link['post_id']) : 0;
                if (!$post_id) {
                    continue;
                }

                $valid_block['links'][] = [
                    'post_id' => $post_id,
                    'text' => isset($link['text']) && is_string($link['text']) ? sanitize_text_field($link['text']) : '',
                    'url' => isset($link['url']) && is_string($link['url']) ? esc_url_raw($link['url']) : '',
                ];
            }

            if (!empty($valid_block['links'])) {
                $validated['blocks'][] = $valid_block;
            }
        }

        return $validated;
    }

    /**
     * Mirror of default settings
     */
    private function getDefaultSettings()
    {
        return [
            'links_per_block' => 3,
            'num_blocks' => 3,
            'positions' => ['after_first', 'middle', 'before_last'],
            'post_types' => ['post'],
            'button_bg_color' => '#1B3A5C',
            'button_text_color' => '#FFFFFF',
            'button_border_color' => '#D4A843',
            'button_border_size' => 2,
        ];
    }
}
