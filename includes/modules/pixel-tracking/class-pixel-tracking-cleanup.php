<?php
/**
 * Pixel Tracking Module - Data Cleanup
 *
 * Handles data retention via Action Scheduler daily cleanup.
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleanup executor for retention and volume controls.
 */
class AlvoBotPro_PixelTracking_Cleanup {

	/**
	 * Module instance.
	 *
	 * @var AlvoBotPro_PixelTracking
	 */
	private $module;

	public function __construct( $module ) {
		$this->module = $module;
		$this->schedule_cleanup();
		add_action( 'alvobot_pixel_cleanup', array( $this, 'run_cleanup' ) );
	}

	/**
	 * Schedule daily cleanup action.
	 */
	private function schedule_cleanup() {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( 'alvobot_pixel_cleanup' ) ) {
				as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, 'alvobot_pixel_cleanup' );
			}
		} elseif ( ! wp_next_scheduled( 'alvobot_pixel_cleanup' ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'alvobot_pixel_cleanup' );
		}
	}

	/**
	 * Maximum posts to delete per run to avoid timeout.
	 *
	 * @var int
	 */
	private $max_deletions_per_run = 5000;

	/**
	 * Run the cleanup process.
	 */
	public function run_cleanup() {
		$settings       = $this->module->get_settings();
		$retention_days = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 7;
		$max_events     = isset( $settings['max_events'] ) ? absint( $settings['max_events'] ) : 50000;
		$max_leads      = isset( $settings['max_leads'] ) ? absint( $settings['max_leads'] ) : 10000;

		// Floor: minimum 1 day retention to prevent accidental wipe.
		$retention_days = max( 1, $retention_days );

		$total_deleted = 0;

		// Stale pending events: Meta CAPI rejects events older than 7 days — irrecoverable.
		$deleted_stale  = $this->cleanup_stale_pending( $total_deleted );
		$total_deleted += $deleted_stale;

		// Only delete sent/error events — never pending (except stale above).
		$deleted_events = $this->cleanup_events_by_age( $retention_days, $total_deleted );
		$total_deleted += $deleted_events;

		$deleted_leads  = $this->cleanup_by_age( 'alvobot_pixel_lead', $retention_days, $total_deleted );
		$total_deleted += $deleted_leads;

		$deleted_events += $this->cleanup_events_by_volume( $max_events, $total_deleted );
		$total_deleted  += $deleted_events;

		$deleted_leads += $this->cleanup_by_volume( 'alvobot_pixel_lead', $max_leads, $total_deleted );

		if ( $deleted_events > 0 || $deleted_leads > 0 ) {
			AlvoBotPro::debug_log( 'pixel-tracking', "Cleanup: deleted {$deleted_events} events, {$deleted_leads} leads" );
		}
	}

	/**
	 * Delete sent/error events older than retention days (never pending).
	 */
	private function cleanup_events_by_age( $retention_days, $already_deleted = 0 ) {
		$deleted = 0;
		$batch   = 100;
		$cap     = $this->max_deletions_per_run - $already_deleted;

		if ( $cap <= 0 ) {
			return 0;
		}

		$posts_count = 0;
		do {
			$limit = min( $batch, $cap - $deleted );
			$query = new WP_Query(
				array(
					'post_type'      => 'alvobot_pixel_event',
					'post_status'    => array( 'pixel_sent', 'pixel_error' ),
					'posts_per_page' => $limit,
					'date_query'     => array(
						array(
							'before' => "{$retention_days} days ago",
						),
					),
					'fields'         => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				wp_delete_post( $post_id, true );
				++$deleted;
			}

			$posts_count = count( $query->posts );
		} while ( $posts_count === $limit && $deleted < $cap );

		return $deleted;
	}

	/**
	 * Delete pending events older than 7 days.
	 *
	 * Meta Conversion API rejects events where (now - event_time) > 7 days.
	 * These events are irrecoverable regardless of token status, so we clean
	 * them up to prevent unbounded growth during long token outages.
	 */
	private function cleanup_stale_pending( $already_deleted = 0 ) {
		$deleted = 0;
		$batch   = 100;
		$cap     = $this->max_deletions_per_run - $already_deleted;

		if ( $cap <= 0 ) {
			return 0;
		}

		$posts_count = 0;
		do {
			$limit = min( $batch, $cap - $deleted );
			$query = new WP_Query(
				array(
					'post_type'      => 'alvobot_pixel_event',
					'post_status'    => 'pixel_pending',
					'posts_per_page' => $limit,
					'date_query'     => array(
						array(
							'before' => '7 days ago',
						),
					),
					'fields'         => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				wp_delete_post( $post_id, true );
				++$deleted;
			}

			$posts_count = count( $query->posts );
		} while ( $posts_count === $limit && $deleted < $cap );

		if ( $deleted > 0 ) {
			AlvoBotPro::debug_log( 'pixel-tracking', "Cleanup: deleted {$deleted} stale pending events (older than 7 days — Meta CAPI window expired)" );
		}

		return $deleted;
	}

	/**
	 * Delete non-event posts older than retention days.
	 */
	private function cleanup_by_age( $post_type, $retention_days, $already_deleted = 0 ) {
		$deleted = 0;
		$batch   = 100;
		$cap     = $this->max_deletions_per_run - $already_deleted;

		if ( $cap <= 0 ) {
			return 0;
		}

		$posts_count = 0;
		do {
			$limit = min( $batch, $cap - $deleted );
			$query = new WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => $limit,
					'date_query'     => array(
						array(
							'before' => "{$retention_days} days ago",
						),
					),
					'fields'         => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				wp_delete_post( $post_id, true );
				++$deleted;
			}

			$posts_count = count( $query->posts );
		} while ( $posts_count === $limit && $deleted < $cap );

		return $deleted;
	}

	/**
	 * Delete oldest sent/error events if total exceeds volume cap (never pending).
	 */
	private function cleanup_events_by_volume( $max_count, $already_deleted = 0 ) {
		global $wpdb;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('pixel_sent','pixel_error')",
				'alvobot_pixel_event'
			)
		);

		if ( $total <= $max_count ) {
			return 0;
		}

		$to_delete = $total - $max_count;
		$cap       = $this->max_deletions_per_run - $already_deleted;
		$to_delete = min( $to_delete, $cap );

		if ( $to_delete <= 0 ) {
			return 0;
		}

		$deleted = 0;
		$batch   = 100;

		while ( $deleted < $to_delete ) {
			$limit = min( $batch, $to_delete - $deleted );
			$query = new WP_Query(
				array(
					'post_type'      => 'alvobot_pixel_event',
					'post_status'    => array( 'pixel_sent', 'pixel_error' ),
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				wp_delete_post( $post_id, true );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Delete oldest non-event posts if total exceeds volume cap.
	 */
	private function cleanup_by_volume( $post_type, $max_count, $already_deleted = 0 ) {
		global $wpdb;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'",
				$post_type
			)
		);

		if ( $total <= $max_count ) {
			return 0;
		}

		$to_delete = $total - $max_count;
		$cap       = $this->max_deletions_per_run - $already_deleted;
		$to_delete = min( $to_delete, $cap );

		if ( $to_delete <= 0 ) {
			return 0;
		}

		$deleted = 0;
		$batch   = 100;

		while ( $deleted < $to_delete ) {
			$limit = min( $batch, $to_delete - $deleted );
			$query = new WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				wp_delete_post( $post_id, true );
				++$deleted;
			}
		}

		return $deleted;
	}
}
