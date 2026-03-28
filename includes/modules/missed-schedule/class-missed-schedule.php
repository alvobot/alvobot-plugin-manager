<?php
/**
 * Missed Schedule Publisher — Internal AlvoBot Module
 *
 * Detects WordPress posts stuck in "future" status past their scheduled date
 * and publishes them automatically. Replaces the standalone plugin
 * "Missed Scheduled Posts Publisher" by WPBeginner.
 *
 * Mechanism: hooks into `shutdown` with a non-blocking loopback to admin-ajax.
 * Fallback: on cached sites, injects a frontend JS fetch to trigger the check.
 * Does NOT use wp-cron (that's the root cause of missed schedules).
 *
 * @package AlvoBotPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlvoBotPro_MissedSchedule {

	/** @var string AJAX action identifier. */
	const ACTION = 'alvobot_missed_schedule_publisher';

	/** @var int Maximum posts to process per run. */
	const BATCH_LIMIT = 20;

	/** @var string Option key that stores the last-run Unix timestamp. */
	const OPTION_NAME = 'alvobot_missed_schedule_last_run';

	/** @var float Multiplier for the frontend fallback interval. */
	const FALLBACK_MULTIPLIER = 1.1;

	/** @var string Salt used in session-independent nonce hashing. */
	const NONCE_SALT = 'alvobot-missed-schedule';

	/**
	 * Boot the module: register all hooks.
	 */
	public function __construct() {
		// AJAX handler (both logged-in and public — the loopback has no session).
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_ajax' ) );

		// Primary trigger: non-blocking loopback fired after every page load.
		add_action( 'shutdown', array( $this, 'maybe_trigger_via_loopback' ) );

		// Fallback trigger: frontend JS for fully-cached sites.
		add_action( 'send_headers', array( $this, 'maybe_trigger_via_frontend' ) );
	}

	// ------------------------------------------------------------------
	// Triggers
	// ------------------------------------------------------------------

	/**
	 * Frequency in seconds between runs (filterable).
	 *
	 * @return int
	 */
	private function get_frequency() {
		return (int) apply_filters( 'alvobot_missed_schedule_frequency', 900 );
	}

	/**
	 * Whether enough time has passed since the last run.
	 *
	 * @param float $multiplier Optional multiplier on the frequency.
	 * @return bool
	 */
	private function is_due( $multiplier = 1.0 ) {
		$last_run  = (int) get_option( self::OPTION_NAME, 0 );
		$frequency = $this->get_frequency() * $multiplier;

		return ( time() - $last_run ) >= $frequency;
	}

	/**
	 * Primary trigger — fires a non-blocking POST to admin-ajax.php.
	 *
	 * Hooked to `shutdown` so it never delays the current response.
	 */
	public function maybe_trigger_via_loopback() {
		if ( ! $this->is_due() ) {
			return;
		}

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => array(
					'action' => self::ACTION,
					'nonce'  => $this->create_nonce(),
				),
			)
		);
	}

	/**
	 * Fallback trigger for fully-cached sites.
	 *
	 * If the loopback hasn't fired within frequency × 1.1, inject an inline
	 * JS fetch and send no-cache headers so this response isn't cached.
	 */
	public function maybe_trigger_via_frontend() {
		// Skip non-frontend contexts where wp_footer never fires.
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_CRON' ) ) {
			return;
		}

		if ( ! $this->is_due( self::FALLBACK_MULTIPLIER ) ) {
			return;
		}

		// Prevent the shutdown loopback from also firing (avoid duplicates).
		remove_action( 'shutdown', array( $this, 'maybe_trigger_via_loopback' ) );

		nocache_headers();

		// Update immediately so concurrent visitors don't also bust the cache.
		update_option( self::OPTION_NAME, time(), true );

		$nonce    = $this->create_nonce();
		$ajax_url = esc_js( esc_url_raw( admin_url( 'admin-ajax.php' ) ) );

		add_action(
			'wp_footer',
			function () use ( $ajax_url, $nonce ) {
				printf(
					'<script>fetch("%s",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=%s&nonce=%s"})</script>',
					$ajax_url,
					self::ACTION,
					esc_js( $nonce )
				);
			}
		);
	}

	// ------------------------------------------------------------------
	// AJAX handler
	// ------------------------------------------------------------------

	/**
	 * Handle the AJAX request: verify nonce, then publish missed posts.
	 *
	 * This endpoint is intentionally public (nopriv). The nonce prevents
	 * arbitrary external triggering but is not session-bound. The operation
	 * (publishing already-overdue posts) is idempotent and non-destructive.
	 */
	public function handle_ajax() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $this->verify_nonce( $nonce ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		$this->publish_missed_posts();

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Core logic
	// ------------------------------------------------------------------

	/**
	 * Find posts stuck in "future" status and publish them.
	 */
	private function publish_missed_posts() {
		global $wpdb;

		// Transient-based lock to prevent concurrent executions.
		// TOCTOU window is acceptable — wp_publish_post is idempotent, so duplicate runs are harmless.
		$lock_key = 'alvobot_missed_schedule_lock';
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 60 );

		update_option( self::OPTION_NAME, time(), true );

		$missed_post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_date <= %s AND post_status = 'future' LIMIT %d",
				current_time( 'mysql', 0 ),
				self::BATCH_LIMIT
			)
		);

		if ( empty( $missed_post_ids ) ) {
			delete_transient( $lock_key );
			return;
		}

		// If we hit the batch limit, reset last-run so the next request processes more.
		if ( count( $missed_post_ids ) === self::BATCH_LIMIT ) {
			update_option( self::OPTION_NAME, 0, true );
		}

		foreach ( $missed_post_ids as $post_id ) {
			wp_publish_post( $post_id );
		}

		delete_transient( $lock_key );

		AlvoBotPro::debug_log( 'missed-schedule', sprintf( 'Published %d missed scheduled post(s).', count( $missed_post_ids ) ) );
	}

	// ------------------------------------------------------------------
	// Nonce helpers (session-independent)
	// ------------------------------------------------------------------

	/**
	 * Compute a nonce hash for a specific tick.
	 *
	 * @param int $tick The nonce tick (rotates every 12 hours).
	 * @return string 10-character nonce string.
	 */
	private function compute_nonce_for_tick( $tick ) {
		return substr( wp_hash( $tick . self::ACTION . self::NONCE_SALT, 'nonce' ), -12, 10 );
	}

	/**
	 * Create a nonce that works without a logged-in user session.
	 *
	 * Uses a static salt + the WP nonce tick so it rotates every 12-24 hours.
	 *
	 * @return string
	 */
	private function create_nonce() {
		return $this->compute_nonce_for_tick( ceil( time() / ( DAY_IN_SECONDS / 2 ) ) );
	}

	/**
	 * Verify a session-independent nonce (valid for the current or previous tick).
	 *
	 * @param string $nonce The nonce to verify.
	 * @return bool
	 */
	private function verify_nonce( $nonce ) {
		$tick = ceil( time() / ( DAY_IN_SECONDS / 2 ) );

		return hash_equals( $this->compute_nonce_for_tick( $tick ), $nonce )
			|| hash_equals( $this->compute_nonce_for_tick( $tick - 1 ), $nonce );
	}
}
