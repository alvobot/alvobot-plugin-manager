<?php
/**
 * Brain Monkey tests for Pixel Tracking frontend runtime helpers.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

class PixelTrackingFrontendTest extends BrainMonkeyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $_SERVER = [];
        Functions\when('is_admin')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-attribution.php';
        require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-frontend.php';
    }

    public function testConnectedGoogleAdsTrackerUsesAwTrackerIdAsTagIdWhenExplicitTagIdIsMissing(): void
    {
        Functions\expect('sanitize_text_field')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($value) {
                return is_scalar($value) ? trim((string) $value) : '';
            });

        $frontend = $this->newFrontend();

        $tagId = $this->invokePrivate(
            $frontend,
            'get_google_tracker_tag_id',
            [
                [
                    'tracker_id'    => 'AW-6886514439',
                    'type'          => 'google_ads',
                    'connection_id' => '022ff4f9-c016-4516-bf52-0669fd4ba3fe',
                    'tag_id'        => '',
                ],
            ]
        );

        $this->assertSame('AW-6886514439', $tagId);
    }

    public function testGoogleAdsCustomerIdIsNotUsedAsBrowserTagId(): void
    {
        Functions\expect('sanitize_text_field')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($value) {
                return is_scalar($value) ? trim((string) $value) : '';
            });

        $frontend = $this->newFrontend();

        $tagId = $this->invokePrivate(
            $frontend,
            'get_google_tracker_tag_id',
            [
                [
                    'tracker_id'  => 'customers/1234567890',
                    'type'        => 'google_ads',
                    'customer_id' => '123-456-7890',
                    'tag_id'      => '',
                ],
            ]
        );

        $this->assertSame('', $tagId);
    }

    public function testTrackingRestUrlIsRelativeToCurrentOrigin(): void
    {
        Functions\expect('sanitize_text_field')
            ->once()
            ->with('events/track')
            ->andReturn('events/track');

        Functions\expect('rest_url')
            ->once()
            ->with('alvobot-pro/v1/pixel-tracking/events/track')
            ->andReturn('https://plugin-developers/wp-json/alvobot-pro/v1/pixel-tracking/events/track');

        Functions\expect('wp_make_link_relative')
            ->once()
            ->with('https://plugin-developers/wp-json/alvobot-pro/v1/pixel-tracking/events/track')
            ->andReturn('/wp-json/alvobot-pro/v1/pixel-tracking/events/track');

        $frontend = $this->newFrontend();

        $url = $this->invokePrivate($frontend, 'get_tracking_rest_url', ['events/track']);

        $this->assertSame('/wp-json/alvobot-pro/v1/pixel-tracking/events/track', $url);
    }

    public function testAlvocastClickTokenValidationOnlyAcceptsUuidTokens(): void
    {
        $frontend = $this->newFrontend();

        $this->assertTrue(
            $this->invokePrivate(
                $frontend,
                'is_valid_alvocast_click_token',
                ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d']
            )
        );
        $this->assertFalse(
            $this->invokePrivate(
                $frontend,
                'is_valid_alvocast_click_token',
                ['javascript:alert(1)']
            )
        );
    }

    public function testDispatchAlvocastClickPostsToRunsPluginEndpoint(): void
    {
        $token = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
        $_SERVER['HTTP_HOST'] = 'blog.example.com';
        $_SERVER['REQUEST_URI'] = '/landing?ab_click=' . $token . '&utm_source=alvobot';

        Functions\expect('apply_filters')
            ->once()
            ->with('alvobot_cast_api_base_url', 'https://api.alvobot.com')
            ->andReturn('https://api.alvobot.com/');

        Functions\expect('esc_url_raw')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($value) {
                return is_scalar($value) ? (string) $value : '';
            });

        Functions\expect('untrailingslashit')
            ->once()
            ->with('https://api.alvobot.com/')
            ->andReturn('https://api.alvobot.com');

        Functions\expect('home_url')
            ->once()
            ->with('/')
            ->andReturn('https://blog.example.com/');

        Functions\expect('wp_get_referer')
            ->once()
            ->andReturn('https://facebook.com/');

        Functions\expect('get_option')
            ->once()
            ->with('alvobot_site_token', '')
            ->andReturn('site-token-1');

        Functions\expect('is_ssl')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_unslash')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($value) {
                return $value;
            });

        Functions\expect('sanitize_text_field')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function ($value) {
                return is_scalar($value) ? trim((string) $value) : '';
            });

        Functions\expect('wp_json_encode')
            ->once()
            ->andReturnUsing(static function ($payload) {
                return json_encode($payload);
            });

        Functions\expect('wp_remote_post')
            ->once()
            ->andReturnUsing(function ($url, $args) use ($token) {
                $this->assertSame('https://api.alvobot.com/runs/plugin-click', $url);
                $this->assertSame(false, $args['blocking']);
                $this->assertSame('application/json', $args['headers']['Content-Type']);

                $payload = json_decode($args['body'], true);
                $this->assertSame($token, $payload['token']);
                $this->assertSame('https://blog.example.com/', $payload['siteUrl']);
                $this->assertSame(
                    'https://blog.example.com/landing?ab_click=' . $token . '&utm_source=alvobot',
                    $payload['pageUrl']
                );
                $this->assertSame('site-token-1', $payload['siteToken']);

                return ['response' => ['code' => 202]];
            });

        Functions\expect('is_wp_error')
            ->once()
            ->andReturn(false);

        $frontend = $this->newFrontend();
        $this->invokePrivate($frontend, 'dispatch_alvocast_click', [$token]);
    }

    public function testAlvocastClickRuntimeRemovesQueryParamAndPostsToSameOriginRest(): void
    {
        $runtimeScriptMatched = false;

        Functions\expect('rest_url')
            ->once()
            ->with('alvobot-pro/v1/alvocast-click')
            ->andReturn('https://blog.example.com/wp-json/alvobot-pro/v1/alvocast-click');

        Functions\expect('wp_make_link_relative')
            ->once()
            ->with('https://blog.example.com/wp-json/alvobot-pro/v1/alvocast-click')
            ->andReturn('/wp-json/alvobot-pro/v1/alvocast-click');

        Functions\expect('wp_json_encode')
            ->twice()
            ->andReturnUsing(static function ($value) {
                return json_encode($value);
            });

        Functions\expect('wp_print_inline_script_tag')
            ->once()
            ->with(\Mockery::on(static function ($script) use (&$runtimeScriptMatched) {
                $runtimeScriptMatched = strpos($script, 'searchParams.delete(param)') !== false
                    && strpos($script, 'navigator.sendBeacon') !== false
                    && strpos($script, 'alvocast-click') !== false;
                return $runtimeScriptMatched;
            }));

        $frontend = $this->newFrontend();
        $frontend->inject_alvocast_click_runtime();

        $this->assertTrue($runtimeScriptMatched);
    }

    public function testPageLoadConversionUsesRobustReadyQueue(): void
    {
        $frontend = $this->newFrontend();

        $js = $this->invokePrivate(
            $frontend,
            'generate_trigger_js',
            array( 'page_load', '', '', '{"event_name":"PageView"}', 456 )
        );

        $this->assertStringContainsString( 'window.alvobotPixelReady', $js );
        $this->assertStringContainsString( 'send_event({"event_name":"PageView"})', $js );
    }

    private function newFrontend(): AlvoBotPro_PixelTracking_Frontend
    {
        $module = new class {
            public function get_settings(): array
            {
                return [];
            }
        };

        return new AlvoBotPro_PixelTracking_Frontend($module);
    }

    /**
     * @param mixed[] $args
     * @return mixed
     */
    private function invokePrivate(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        if ( PHP_VERSION_ID < 80100 ) {
            $reflection->setAccessible( true );
        }

        return $reflection->invokeArgs($object, $args);
    }
}
