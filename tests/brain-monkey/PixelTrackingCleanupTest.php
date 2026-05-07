<?php
/**
 * Brain Monkey tests for Pixel Tracking cleanup routines.
 */

use Brain\Monkey\Functions;

require_once __DIR__ . '/../BrainMonkeyTestCase.php';

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

class PixelTrackingCleanupQueryStub {
	public static $queries = array();
	public static $deleted = array();

	public static function reset(): void {
		self::$queries = array();
		self::$deleted = array();
	}

	public static function resolve_posts( array $args ): array {
		self::$queries[] = $args;

		if ( 'alvobot_pixel_lead' !== $args['post_type'] ) {
			return array();
		}

		$excluded = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] )
			? array_map( 'intval', $args['post__not_in'] )
			: array();

		if ( empty( $excluded ) ) {
			return range( 1, 100 );
		}

		if ( 100 === count( $excluded ) ) {
			return array( 101 );
		}

		return array();
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();

		public function __construct( array $args = array() ) {
			$this->posts = PixelTrackingCleanupQueryStub::resolve_posts( $args );
		}
	}
}

class PixelTrackingCleanupTest extends BrainMonkeyTestCase {
	protected function setUp(): void {
		parent::setUp();

		PixelTrackingCleanupQueryStub::reset();

		Functions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( $post_id, $key ) {
				if ( '_last_seen_ts' === $key ) {
					return '';
				}

				return gmdate( 'c', time() - ( 10 * DAY_IN_SECONDS ) );
			}
		);
		Functions\when( 'wp_delete_post' )->alias(
			static function ( $post_id ) {
				PixelTrackingCleanupQueryStub::$deleted[] = (int) $post_id;
				return true;
			}
		);

		require_once ALVOBOT_PRO_PLUGIN_DIR . 'includes/modules/pixel-tracking/class-pixel-tracking-cleanup.php';
	}

	public function testLeadCleanupDoesNotUseOffsetWhileDeletingBatches(): void {
		$reflection = new ReflectionClass( 'AlvoBotPro_PixelTracking_Cleanup' );
		$cleanup    = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'cleanup_leads_by_last_seen' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$deleted = $method->invoke( $cleanup, 7, 0 );

		$this->assertSame( 101, $deleted );
		$this->assertCount( 101, PixelTrackingCleanupQueryStub::$deleted );
		$this->assertArrayNotHasKey( 'offset', PixelTrackingCleanupQueryStub::$queries[0] );
		$this->assertArrayNotHasKey( 'offset', PixelTrackingCleanupQueryStub::$queries[1] );
		$this->assertSame( '_last_seen_ts', PixelTrackingCleanupQueryStub::$queries[0]['meta_query'][0]['key'] );
		$this->assertSame( 'NUMERIC', PixelTrackingCleanupQueryStub::$queries[0]['meta_query'][0]['type'] );
		$this->assertCount( 100, PixelTrackingCleanupQueryStub::$queries[1]['post__not_in'] );
	}
}
