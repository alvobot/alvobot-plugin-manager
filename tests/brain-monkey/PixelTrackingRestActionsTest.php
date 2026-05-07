<?php
/**
 * Brain Monkey tests for Pixel Tracking REST admin actions.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

class PixelTrackingRestCleanupModuleStub
{
	public function run_cleanup_now(): array
	{
		return array(
			'message'       => 'Limpeza concluida: 2 evento(s) e 1 lead(s) removido(s).',
			'deleted_total' => 3,
		);
	}
}

class PixelTrackingRestRefreshModuleStub
{
	public function fetch_alvobot_pixels( $force = false ): array
	{
		return array(
			array(
				'pixel_id'     => '123456789012345',
				'pixel_name'   => 'Main Pixel',
				'access_token' => 'EAAB-secret-token',
				'api_token'    => 'legacy-secret-token',
			),
		);
	}
}

class PixelTrackingRestActionsTest extends BrainMonkeyTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value, $expiration = 0 ) {
				$GLOBALS['alvobot_pt_test_transients'][] = array(
					'key'        => $key,
					'value'      => $value,
					'expiration' => $expiration,
				);
				return true;
			}
		);
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'legacy-token-ref' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $value ) {
				return $value instanceof WP_Error;
			}
		);
		Functions\when( 'rest_ensure_response' )->alias(
			static function ( $response ) {
				return $response;
			}
		);

		$GLOBALS['alvobot_pt_test_transients'] = array();

		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/class-alvobot-module-base.php';
		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking.php';
		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-rest.php';
	}

	public function testCleanupActionReturnsCleanupSummaryForAdminFeedback(): void
	{
		$rest = new AlvoBotPro_PixelTracking_REST( new PixelTrackingRestCleanupModuleStub() );

		$response = $rest->action_cleanup();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 3, $response['data']['deleted_total'] );
		$this->assertSame( 'Limpeza concluida: 2 evento(s) e 1 lead(s) removido(s).', $response['data']['message'] );
	}

	public function testRefreshPixelsDoesNotReturnRawTokens(): void
	{
		$rest = new AlvoBotPro_PixelTracking_REST( new PixelTrackingRestRefreshModuleStub() );

		$response = $rest->action_refresh_pixels();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Pixels atualizados: 1 encontrado(s).', $response['data']['message'] );
		$this->assertTrue( $response['data']['pixels'][0]['has_api_token'] );
		$this->assertArrayNotHasKey( 'access_token', $response['data']['pixels'][0] );
		$this->assertArrayNotHasKey( 'api_token', $response['data']['pixels'][0] );
	}

	public function testPrepareAlvobotPixelsForAdminAcceptsLegacyApiTokenField(): void
	{
		$reflection = new ReflectionClass( 'AlvoBotPro_PixelTracking' );
		$module     = $reflection->newInstanceWithoutConstructor();

		$pixels = $module->prepare_alvobot_pixels_for_admin(
			array(
				array(
					'pixel_id'   => '123456789012345',
					'pixel_name' => 'Legacy Pixel',
					'api_token'  => 'legacy-secret-token',
				),
			)
		);

		$this->assertCount( 1, $pixels );
		$this->assertTrue( $pixels[0]['has_api_token'] );
		$this->assertSame( 'legacy-token-ref', $pixels[0]['token_ref'] );
		$this->assertArrayNotHasKey( 'api_token', $pixels[0] );
		$this->assertSame( 'legacy-secret-token', $GLOBALS['alvobot_pt_test_transients'][0]['value']['api_token'] );
	}
}
