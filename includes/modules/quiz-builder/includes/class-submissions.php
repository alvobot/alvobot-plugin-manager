<?php
/**
 * Submissions handler class
 *
 * @package Alvobot_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles quiz submissions and webhooks
 *
 * @since 1.0.0
 */
class Alvobot_Quiz_Submissions {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'alvobot_quiz_submissions';
	}

	/**
	 * Create submissions table
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quiz_id varchar(50) NOT NULL,
            name varchar(100),
            email varchar(100),
            phone varchar(50),
            answers longtext,
            page_url varchar(500),
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get submissions with filters and pagination
	 *
	 * @param array $filters Filter options (quiz_id, search, date_from, date_to)
	 * @param int   $limit Number of results (-1 for all)
	 * @param int   $offset Offset for pagination
	 * @return array List of submissions
	 */
	public function get_submissions( $filters = array(), $limit = 20, $offset = 0 ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['quiz_id'] ) ) {
			$where[]  = 'quiz_id = %s';
			$values[] = $filters['quiz_id'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get total count of submissions with filters
	 *
	 * @param array $filters Filter options
	 * @return int Total count
	 */
	public function get_total_count( $filters = array() ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['quiz_id'] ) ) {
			$where[]  = 'quiz_id = %s';
			$values[] = $filters['quiz_id'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get unique quiz IDs for filter dropdown
	 *
	 * @return array List of quiz IDs
	 */
	public function get_quiz_ids() {
		global $wpdb;
		return $wpdb->get_col( "SELECT DISTINCT quiz_id FROM {$this->table_name} ORDER BY quiz_id ASC" );
	}

	/**
	 * Delete submissions by IDs
	 *
	 * @param array $ids Array of submission IDs
	 * @return int|false Number of rows deleted or false on error
	 */
	public function delete_submissions( $ids ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
				$ids
			)
		);
	}

	/**
	 * Get single submission by ID
	 *
	 * @param int $id Submission ID
	 * @return object|null Submission object or null
	 */
	public function get_submission( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Save submission
	 *
	 * @param array $data Submission data
	 * @return int|false Insert ID or false on failure
	 */
	public function save_submission( $data ) {
		global $wpdb;

		$defaults = array(
			'quiz_id'    => '',
			'name'       => '',
			'email'      => '',
			'phone'      => '',
			'answers'    => '',
			'page_url'   => '',
			'created_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Sanitize
		$data['quiz_id']  = sanitize_text_field( $data['quiz_id'] );
		$data['name']     = sanitize_text_field( $data['name'] );
		$data['email']    = sanitize_email( $data['email'] );
		$data['phone']    = sanitize_text_field( $data['phone'] );
		$data['page_url'] = esc_url_raw( $data['page_url'] );
		// answers is expected to be a JSON string or array, sanitize accordingly
		if ( is_array( $data['answers'] ) ) {
			$data['answers'] = json_encode( $data['answers'] );
		}

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array(
				'%s', // quiz_id
				'%s', // name
				'%s', // email
				'%s', // phone
				'%s', // answers
				'%s', // page_url
				'%s',  // created_at
			)
		);

		// Debug logging for database errors
		if ( $result === false && method_exists( 'AlvoBotPro', 'debug_log' ) ) {
			AlvoBotPro::debug_log( 'quiz-builder', 'DB Insert Error: ' . $wpdb->last_error );
			AlvoBotPro::debug_log( 'quiz-builder', 'Last Query: ' . $wpdb->last_query );
		}

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Send webhook based on platform
	 *
	 * @param string $url Webhook URL
	 * @param array  $data Data to send
	 * @param string $platform Platform type (generic, gohighlevel, sendpulse)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function send_webhook( $url, $data, $platform = 'generic' ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Format data based on platform
		$formatted_data = $this->format_webhook_data( $data, $platform );

		$args = array(
			'body'        => json_encode( $formatted_data ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 45,
			'blocking'    => true,
			'httpversion' => '1.1',
			'sslverify'   => true,
		);

		// Log webhook attempt
		if ( method_exists( 'AlvoBotPro', 'debug_log' ) ) {
			AlvoBotPro::debug_log( 'quiz-builder', 'Sending webhook to ' . $platform . ': ' . $url );
			AlvoBotPro::debug_log( 'quiz-builder', 'Webhook payload: ' . json_encode( $formatted_data ) );
		}

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( method_exists( 'AlvoBotPro', 'debug_log' ) ) {
				AlvoBotPro::debug_log( 'quiz-builder', 'Webhook error: ' . $response->get_error_message() );
			}
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( method_exists( 'AlvoBotPro', 'debug_log' ) ) {
			AlvoBotPro::debug_log( 'quiz-builder', 'Webhook response code: ' . $response_code );
			AlvoBotPro::debug_log( 'quiz-builder', 'Webhook response body: ' . $response_body );
		}

		return $response_code >= 200 && $response_code < 300;
	}

	/**
	 * Format webhook data based on platform requirements
	 *
	 * @param array  $data Raw data
	 * @param string $platform Platform type
	 * @return array Formatted data
	 */
	private function format_webhook_data( $data, $platform ) {
		switch ( $platform ) {
			case 'gohighlevel':
				return $this->format_for_gohighlevel( $data );

			case 'sendpulse':
				return $this->format_for_sendpulse( $data );

			case 'generic':
			default:
				return $this->format_generic( $data );
		}
	}

	/**
	 * Format data for GoHighLevel Inbound Webhook
	 * GoHighLevel expects specific field names for contact creation/update
	 *
	 * @param array $data Raw data
	 * @return array Formatted data for GHL
	 */
	private function format_for_gohighlevel( $data ) {
		$ghl_data = array();

		// Contact fields - GHL standard field names
		if ( ! empty( $data['email'] ) ) {
			$ghl_data['email'] = $data['email'];
		}

		if ( ! empty( $data['name'] ) ) {
			// GHL prefers first_name and last_name separated
			$name_parts             = explode( ' ', $data['name'], 2 );
			$ghl_data['first_name'] = $name_parts[0];
			$ghl_data['last_name']  = isset( $name_parts[1] ) ? $name_parts[1] : '';
			// Also send full name for flexibility
			$ghl_data['full_name'] = $data['name'];
			$ghl_data['name']      = $data['name'];
		}

		if ( ! empty( $data['phone'] ) ) {
			$ghl_data['phone'] = $data['phone'];
		}

		// Quiz-specific data as custom fields
		if ( ! empty( $data['quiz_id'] ) ) {
			$ghl_data['quiz_id'] = $data['quiz_id'];
		}

		if ( ! empty( $data['answers'] ) ) {
			$ghl_data['quiz_answers'] = $data['answers'];
		}

		// Source tracking
		$ghl_data['source']     = 'AlvoBot Quiz';
		$ghl_data['website']    = get_site_url();
		$ghl_data['created_at'] = current_time( 'c' ); // ISO 8601 format

		// Add page URL if available
		if ( ! empty( $data['page_url'] ) ) {
			$ghl_data['page_url'] = $data['page_url'];
		}

		return $ghl_data;
	}

	/**
	 * Format data for SendPulse
	 *
	 * @param array $data Raw data
	 * @return array Formatted data for SendPulse
	 */
	private function format_for_sendpulse( $data ) {
		$sp_data = array(
			'emails' => array(),
		);

		$contact = array();

		if ( ! empty( $data['email'] ) ) {
			$contact['email'] = $data['email'];
		}

		if ( ! empty( $data['name'] ) ) {
			$name_parts      = explode( ' ', $data['name'], 2 );
			$contact['name'] = $name_parts[0];
			if ( isset( $name_parts[1] ) ) {
				$contact['surname'] = $name_parts[1];
			}
		}

		if ( ! empty( $data['phone'] ) ) {
			$contact['phone'] = $data['phone'];
		}

		// Variables for SendPulse
		$contact['variables'] = array(
			'quiz_id'      => $data['quiz_id'] ?? '',
			'quiz_answers' => $data['answers'] ?? '',
			'source'       => 'AlvoBot Quiz',
			'website'      => get_site_url(),
		);

		if ( ! empty( $contact['email'] ) ) {
			$sp_data['emails'][] = $contact;
		}

		return $sp_data;
	}

	/**
	 * Format generic webhook data
	 *
	 * @param array $data Raw data
	 * @return array Formatted data
	 */
	private function format_generic( $data ) {
		return array(
			'name'      => $data['name'] ?? '',
			'email'     => $data['email'] ?? '',
			'phone'     => $data['phone'] ?? '',
			'quiz_id'   => $data['quiz_id'] ?? '',
			'answers'   => $data['answers'] ?? '',
			'source'    => 'AlvoBot Quiz',
			'website'   => get_site_url(),
			'timestamp' => current_time( 'c' ),
			'page_url'  => $data['page_url'] ?? '',
		);
	}
}
