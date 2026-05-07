<?php
/**
 * Brain Monkey tests for Pixel Tracking attribution helpers.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

class PixelTrackingAttributionTest extends BrainMonkeyTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$_COOKIE = array();

		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static function ( $value ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', is_scalar( $value ) ? (string) $value : '' ) );
			}
		);
		Functions\when( 'esc_url_raw' )->alias(
			static function ( $value ) {
				return is_scalar( $value ) ? (string) $value : '';
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
			}
		);
		Functions\when( 'wp_unslash' )->alias(
			static function ( $value ) {
				return $value;
			}
		);
		Functions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return $value;
			}
		);

		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-attribution.php';
	}

	public function testSanitizeUrlRemovesSensitiveParameters(): void
	{
		$url = AlvoBotPro_PixelTracking_Attribution::sanitize_url_for_storage(
			'https://example.com/landing?utm_source=meta&email=user@example.com&token=secret&fbclid=abc#hero'
		);

		$this->assertSame( 'https://example.com/landing?utm_source=meta&fbclid=abc#hero', $url );
	}

	public function testBuildAttributionDefaultsSourceFromClickIdWhenUtmIsMissing(): void
	{
		$attribution = AlvoBotPro_PixelTracking_Attribution::build_attribution_snapshot(
			array(),
			'https://example.com/page?gclid=google-click',
			'',
			array(
				'gclid' => 'google-click',
			)
		);

		$this->assertSame( 'google', $attribution['utm_source'] );
		$this->assertSame( 'cpc', $attribution['utm_medium'] );
		$this->assertSame( 'google-click', $attribution['click_ids']['gclid'] );
	}

	public function testCloudflareContextIsOptionalAndFallsBackCleanly(): void
	{
		$context = AlvoBotPro_PixelTracking_Attribution::get_edge_context( array() );

		$this->assertFalse( $context['cloudflare_detected'] );
		$this->assertSame( '', $context['bot']['score'] );
		$this->assertFalse( $context['bot']['verified'] );
	}

	public function testMalformedEdgePayloadIsIgnoredWithoutWarnings(): void
	{
		$context = AlvoBotPro_PixelTracking_Attribution::sanitize_edge_context(
			array(
				'cloudflare_detected' => true,
				'geo'                 => 'not-an-array',
				'network'             => 'not-an-array',
				'bot'                 => 'not-an-array',
			)
		);

		$this->assertTrue( $context['cloudflare_detected'] );
		$this->assertArrayNotHasKey( 'geo', $context );
		$this->assertArrayNotHasKey( 'network', $context );
		$this->assertArrayNotHasKey( 'bot', $context );
	}

	public function testConsentCheckBlocksCookieCaptureWhenConsentCookieIsMissing(): void
	{
		$_COOKIE = array();

		Functions\when( 'get_option' )->justReturn(
			array(
				'consent_check'  => true,
				'consent_cookie' => 'alvobot_tracking_consent',
			)
		);

		$this->assertFalse( $this->invokeStaticPrivate( 'has_tracking_consent' ) );
	}

	public function testConsentCheckAllowsCookieCaptureWhenConfiguredCookieIsGranted(): void
	{
		$_COOKIE = array(
			'alvobot_tracking_consent' => 'true',
		);

		Functions\when( 'get_option' )->justReturn(
			array(
				'consent_check'  => true,
				'consent_cookie' => 'alvobot_tracking_consent',
			)
		);

		$this->assertTrue( $this->invokeStaticPrivate( 'has_tracking_consent' ) );
	}

	private function invokeStaticPrivate( $method )
	{
		$reflection = new ReflectionMethod( 'AlvoBotPro_PixelTracking_Attribution', $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( null );
	}
}
