<?php
/**
 * Brain Monkey tests for Pixel Tracking CPT persistence.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

class PixelTrackingCptTest extends BrainMonkeyTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-cpt.php';
	}

	public function testUpdatingConversionClearsGoogleAdsLabelsMapWhenSubmittedMapIsEmpty(): void
	{
		Functions\expect( 'sanitize_text_field' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				static function ( $value ) {
					return is_scalar( $value ) ? trim( (string) $value ) : '';
				}
			);

		Functions\expect( 'wp_update_post' )
			->once()
			->with(
				array(
					'ID'         => 123,
					'post_title' => 'Page View',
				)
			)
			->andReturn( 123 );

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( array() )
			->andReturn( '{}' );

		Functions\expect( 'update_post_meta' )
			->once()
			->with( 123, '_gads_labels_map', '{}' );

		$cpt = new AlvoBotPro_PixelTracking_CPT();

		$result = $cpt->update_conversion(
			123,
			array(
				'name'            => 'Page View',
				'gads_labels_map' => '',
			)
		);

		$this->assertSame( 123, $result );
	}
}
