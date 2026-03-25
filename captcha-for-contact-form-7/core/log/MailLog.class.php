<?php

namespace f12_cf7_captcha\core\log;

use f12_cf7_captcha\CF7Captcha;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mail log for tracking sent and blocked form submissions.
 * Stores email metadata and body for review and optional resend.
 */
class MailLog {

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if mail logging is enabled.
	 */
	public static function is_enabled(): bool {
		$instance = CF7Captcha::get_instance();
		$enabled  = $instance->get_settings( 'protection_mail_log_enable', 'global' );

		return (int) $enabled === 1;
	}

	/**
	 * Get the table name with WordPress prefix.
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'f12_mail_log';
	}

	/**
	 * Check if the mail_log table exists.
	 */
	public function table_exists(): bool {
		global $wpdb;

		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->get_table_name() )
		);
		$wpdb->suppress_errors( $suppress );

		return $result !== null;
	}

	/**
	 * Create the mail_log table using dbDelta.
	 */
	public function create_table(): void {
		global $wpdb;

		$table_name = $this->get_table_name();
		$charset    = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			form_plugin varchar(50) NOT NULL DEFAULT '',
			form_id varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'sent',
			block_reason varchar(100) NOT NULL DEFAULT '',
			sender varchar(255) NOT NULL DEFAULT '',
			recipient varchar(255) NOT NULL DEFAULT '',
			subject varchar(500) NOT NULL DEFAULT '',
			body longtext,
			headers text DEFAULT NULL,
			attachments text DEFAULT NULL,
			form_data longtext DEFAULT NULL,
			ip_hash varchar(64) NOT NULL DEFAULT '',
			page_url varchar(500) NOT NULL DEFAULT '',
			meta text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_ts (ts),
			KEY idx_status (status),
			KEY idx_form_plugin (form_plugin)
		) {$charset};";

		dbDelta( $sql );

		$this->logger->info( 'MailLog table created/updated', [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name,
		] );
	}

	/**
	 * Drop the table.
	 */
	public function delete_table(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$this->get_table_name()}" );
	}

	/**
	 * Log a mail event.
	 *
	 * @param array $data Mail data with keys: form_plugin, form_id, status, block_reason,
	 *                     sender, recipient, subject, body, headers, attachments, form_data, meta.
	 *
	 * @return int|false The inserted row ID, or false on failure.
	 */
	public function log( array $data ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( ! $this->table_exists() ) {
			return false;
		}

		global $wpdb;

		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$plaintext = (int) CF7Captcha::get_instance()
			->get_settings( 'protection_log_plaintext', 'global' ) === 1;
		$ip_hash = ! empty( $ip_raw )
			? ( $plaintext ? $ip_raw : hash( 'sha256', $ip_raw ) )
			: '';

		$page_url = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$row = [
			'ts'           => current_time( 'mysql', true ),
			'form_plugin'  => sanitize_text_field( $data['form_plugin'] ?? '' ),
			'form_id'      => sanitize_text_field( $data['form_id'] ?? '' ),
			'status'       => sanitize_text_field( $data['status'] ?? 'sent' ),
			'block_reason' => sanitize_text_field( $data['block_reason'] ?? '' ),
			'sender'       => sanitize_text_field( $data['sender'] ?? '' ),
			'recipient'    => sanitize_text_field( $data['recipient'] ?? '' ),
			'subject'      => sanitize_text_field( $data['subject'] ?? '' ),
			'body'         => $data['body'] ?? '',
			'headers'      => isset( $data['headers'] ) ? wp_json_encode( $data['headers'] ) : null,
			'attachments'  => isset( $data['attachments'] ) ? wp_json_encode( $data['attachments'] ) : null,
			'form_data'    => isset( $data['form_data'] ) ? wp_json_encode( $data['form_data'] ) : null,
			'ip_hash'      => $ip_hash,
			'page_url'     => substr( $page_url, 0, 500 ),
			'meta'         => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
		];

		$format = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->get_table_name(), $row, $format );
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			$this->logger->error( 'MailLog insert failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Log a blocked form submission.
	 *
	 * @param string      $form_plugin   Integration ID (cf7, wpforms, etc.)
	 * @param string|null $form_id       Form ID.
	 * @param string      $block_reason  Reason code (e.g. CAPTCHA_FAILED).
	 * @param array       $post_data     The $_POST data.
	 * @param array|null  $api_response  Optional SilentShield API response data.
	 *
	 * @return int|false
	 */
	public function log_blocked( string $form_plugin, ?string $form_id, string $block_reason, array $post_data, ?array $api_response = null ) {
		// Check if blocked logging is enabled
		$log_blocked = (int) CF7Captcha::get_instance()
			->get_settings( 'protection_mail_log_blocked', 'global' );
		if ( $log_blocked !== 1 ) {
			return false;
		}

		// Try to extract email-relevant fields from POST data
		$sender    = $post_data['your-email'] ?? $post_data['email'] ?? $post_data['_replyto'] ?? '';
		$subject   = $post_data['your-subject'] ?? $post_data['subject'] ?? '';
		$body      = $post_data['your-message'] ?? $post_data['message'] ?? '';

		// Strip captcha/timing fields from stored form data
		$clean_data = $post_data;
		unset(
			$clean_data['php_start_time'],
			$clean_data['js_start_time'],
			$clean_data['js_end_time'],
			$clean_data['f12_captcha'],
			$clean_data['_wpcf7_unit_tag'],
			$clean_data['_wpnonce']
		);

		$data = [
			'form_plugin'  => $form_plugin,
			'form_id'      => $form_id ?? '',
			'status'       => 'blocked',
			'block_reason' => $block_reason,
			'sender'       => $sender,
			'subject'      => $subject,
			'body'         => $body,
			'form_data'    => $clean_data,
		];

		if ( $api_response !== null ) {
			$data['meta'] = self::build_api_meta( $api_response );
		}

		return $this->log( $data );
	}

	/**
	 * Log a sent mail.
	 *
	 * @param string $form_plugin Integration ID.
	 * @param string $form_id     Form ID.
	 * @param string $recipient   To address.
	 * @param string $sender      From address.
	 * @param string $subject     Subject line.
	 * @param string $body        Mail body.
	 * @param array  $headers     Mail headers.
	 * @param array  $attachments Attachment paths.
	 * @param array  $form_data   Posted form data (cleaned).
	 *
	 * @return int|false
	 */
	public function log_sent(
		string $form_plugin,
		string $form_id,
		string $recipient,
		string $sender,
		string $subject,
		string $body,
		array $headers = [],
		array $attachments = [],
		array $form_data = [],
		?array $api_response = null
	) {
		// Check if sent logging is enabled
		$log_sent = (int) CF7Captcha::get_instance()
			->get_settings( 'protection_mail_log_sent', 'global' );
		if ( $log_sent !== 1 ) {
			return false;
		}

		$data = [
			'form_plugin' => $form_plugin,
			'form_id'     => $form_id,
			'status'      => 'sent',
			'recipient'   => $recipient,
			'sender'      => $sender,
			'subject'     => $subject,
			'body'        => $body,
			'headers'     => $headers,
			'attachments' => $attachments,
		];

		if ( ! empty( $form_data ) ) {
			$data['form_data'] = $form_data;
		}

		if ( $api_response !== null ) {
			$data['meta'] = self::build_api_meta( $api_response );
		}

		return $this->log( $data );
	}

	/**
	 * Build a structured meta array from the SilentShield API response.
	 *
	 * @param array $api_response The raw API response.
	 *
	 * @return array Cleaned meta data with verdict, confidence, score_breakdown, and reason_codes.
	 */
	private static function build_api_meta( array $api_response ): array {
		$meta = [
			'verdict'    => $api_response['verdict'] ?? null,
			'confidence' => $api_response['confidence'] ?? null,
		];

		if ( ! empty( $api_response['score_breakdown'] ) ) {
			$meta['score_breakdown'] = $api_response['score_breakdown'];
		}

		if ( ! empty( $api_response['reason_codes'] ) ) {
			$meta['reason_codes'] = $api_response['reason_codes'];
		}

		if ( isset( $api_response['requested_nonce'] ) ) {
			$meta['nonce'] = $api_response['requested_nonce'];
		}

		return $meta;
	}

	/**
	 * Get mail log entries (newest first).
	 *
	 * @param int         $limit       Max entries to return.
	 * @param int         $offset      Offset for pagination.
	 * @param int         $days        Only entries from last N days.
	 * @param string|null $status      Filter by status (sent, blocked, resent).
	 * @param string|null $form_plugin Filter by form plugin.
	 * @param string|null $search      Search in subject/recipient.
	 *
	 * @return array
	 */
	public function get_entries(
		int $limit = 50,
		int $offset = 0,
		int $days = 30,
		?string $status = null,
		?string $form_plugin = null,
		?string $search = null
	): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table  = $this->get_table_name();
		$since  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$where  = 'WHERE ts >= %s';
		$params = [ $since ];

		if ( $status !== null ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $form_plugin !== null ) {
			$where   .= ' AND form_plugin = %s';
			$params[] = $form_plugin;
		}

		if ( $search !== null && $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (subject LIKE %s OR recipient LIKE %s OR sender LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// Body excluded from list view for performance
				"SELECT id, ts, form_plugin, form_id, status, block_reason, sender, recipient, subject, ip_hash, page_url FROM {$table} {$where} ORDER BY ts DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get total count of mail log entries with optional filters.
	 */
	public function get_count(
		int $days = 30,
		?string $status = null,
		?string $form_plugin = null,
		?string $search = null
	): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table  = $this->get_table_name();
		$since  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$where  = 'WHERE ts >= %s';
		$params = [ $since ];

		if ( $status !== null ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $form_plugin !== null ) {
			$where   .= ' AND form_plugin = %s';
			$params[] = $form_plugin;
		}

		if ( $search !== null && $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (subject LIKE %s OR recipient LIKE %s OR sender LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} {$where}",
				...$params
			)
		);
	}

	/**
	 * Get a single mail log entry by ID (including body).
	 */
	public function get_entry( int $id ): ?array {
		if ( ! $this->table_exists() ) {
			return null;
		}

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get summary counts by status.
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return array ['total' => int, 'sent' => int, 'blocked' => int, 'resent' => int]
	 */
	public function get_summary( int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [ 'total' => 0, 'sent' => 0, 'blocked' => 0, 'resent' => 0 ];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$table} WHERE ts >= %s GROUP BY status",
				$since
			),
			ARRAY_A
		) ?: [];

		$summary = [ 'total' => 0, 'sent' => 0, 'blocked' => 0, 'resent' => 0 ];
		foreach ( $rows as $row ) {
			$summary[ $row['status'] ] = (int) $row['count'];
			$summary['total']         += (int) $row['count'];
		}

		return $summary;
	}

	/**
	 * Update the status of a mail log entry.
	 */
	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->get_table_name(),
			[ 'status' => sanitize_text_field( $status ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		$wpdb->suppress_errors( $suppress );

		return $result !== false;
	}

	/**
	 * Delete a single entry.
	 */
	public function delete_entry( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->get_table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete entries older than the given number of days.
	 *
	 * @param int $days Delete entries older than N days.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup( int $days = 30 ): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE ts < %s", $since )
		);

		if ( false === $deleted ) {
			$this->logger->error( 'MailLog cleanup failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			return 0;
		}

		return (int) $deleted;
	}

	/**
	 * Get total row count (for cleanup page).
	 */
	public function get_total_row_count(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_name()}" );
	}

	/**
	 * Get count of blocked entries (for cleanup page).
	 */
	public function get_blocked_count(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE status = %s",
				'blocked'
			)
		);
	}

	/**
	 * Delete all entries.
	 */
	public function reset_table(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_name()}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$this->get_table_name()}" );

		return $count;
	}

	/**
	 * Delete all blocked entries.
	 */
	public function delete_blocked(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->get_table_name()} WHERE status = %s",
				'blocked'
			)
		);

		return $deleted !== false ? (int) $deleted : 0;
	}
}
