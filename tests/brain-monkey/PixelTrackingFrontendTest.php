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

        Functions\when('is_admin')->justReturn(true);
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
