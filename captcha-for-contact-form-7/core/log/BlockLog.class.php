<?php

namespace f12_cf7_captcha\core\log;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured block log for detailed tracking of spam protection events.
 * Stores machine-readable reason codes and human-readable explanations.
 */
class BlockLog {

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the table name with WordPress prefix.
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'f12_block_log';
	}

	/**
	 * Check if the block_log table exists.
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
	 * Create the block_log table using dbDelta.
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
			page_url varchar(500) NOT NULL DEFAULT '',
			ip_hash varchar(64) NOT NULL DEFAULT '',
			protection varchar(50) NOT NULL,
			verdict varchar(20) NOT NULL DEFAULT 'blocked',
			reason_code varchar(100) NOT NULL DEFAULT '',
			reason_detail text,
			score float DEFAULT NULL,
			reason_codes text DEFAULT NULL,
			meta text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_ts (ts),
			KEY idx_protection (protection),
			KEY idx_ip_hash (ip_hash)
		) {$charset};";

		dbDelta( $sql );

		$this->logger->info( 'BlockLog table created/updated', [
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
	 * Check if detailed tracking is enabled.
	 */
	public static function is_enabled(): bool {
		$instance  = \f12_cf7_captcha\CF7Captcha::get_instance();
		$enabled   = $instance->get_settings( 'protection_detailed_tracking', 'global' );

		return (int) $enabled === 1;
	}

	/**
	 * Check whether anonymous observation rows may be written.
	 *
	 * Separate from {@see is_enabled()} on purpose. Detailed tracking turns on an IP-linked
	 * log the site owner reads on the Analytics page; observations are anonymous measurements
	 * that exist to calibrate detection. Two purposes, two switches — a site owner who wants
	 * neither, or only one, can say so.
	 *
	 * Defaults to on when the option has never been saved, which is the state of every
	 * installation that has not visited the settings screen since this shipped.
	 */
	public static function is_observing(): bool {
		$enabled = \f12_cf7_captcha\CF7Captcha::get_instance()
			->get_settings( 'protection_anonymous_metrics', 'global' );

		if ( $enabled === '' || $enabled === null ) {
			return true;
		}

		return (int) $enabled === 1;
	}

	/**
	 * Log a block event.
	 *
	 * @param string $protection    The protection module name (e.g. 'timer', 'honeypot', 'api')
	 * @param string $reason_code   Machine-readable reason code (e.g. 'SUBMIT_TOO_FAST')
	 * @param string $reason_detail Human-readable explanation
	 * @param array  $extra         Optional extra data: form_plugin, form_id, score, reason_codes, meta
	 */
	public function log( string $protection, string $reason_code, string $reason_detail, array $extra = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$this->insert( $protection, $reason_code, $reason_detail, $extra, false );
	}

	/**
	 * Log an anonymous observation — a verdict a module reached without acting on it.
	 *
	 * Written under {@see is_observing()} rather than under detailed tracking, and always with
	 * a pseudonymised IP: these rows are meant to be analysed away from the site that produced
	 * them, so the `protection_log_plaintext` debugging switch deliberately does not reach
	 * them. A site owner debugging their own blocks sees plaintext in the rows that are about
	 * their own traffic, and hashes in the rows that are about detection quality.
	 *
	 * @param string $protection    The protection module name.
	 * @param string $reason_code   Machine-readable reason code.
	 * @param string $reason_detail Human-readable explanation.
	 * @param array  $extra         verdict, score, reason_codes, meta, form_plugin, form_id.
	 */
	public function log_observation( string $protection, string $reason_code, string $reason_detail, array $extra = [] ): void {
		if ( ! self::is_observing() ) {
			return;
		}

		$this->insert( $protection, $reason_code, $reason_detail, $extra, true );
	}

	/**
	 * Write one row.
	 *
	 * @param bool $anonymous When true, the IP is always pseudonymised regardless of the
	 *                        plaintext debugging switch.
	 */
	private function insert( string $protection, string $reason_code, string $reason_detail, array $extra, bool $anonymous ): void {
		global $wpdb;

		$ip_raw  = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// Plaintext is an explicit debugging opt-in and never applies to anonymous rows.
		$plaintext = ! $anonymous && (int) \f12_cf7_captcha\CF7Captcha::get_instance()
				->get_settings( 'protection_log_plaintext', 'global' ) === 1;

		// Pseudonymizer keys the digest against a rotating site secret. A bare SHA-256 of an
		// IP is not a pseudonym — the whole IPv4 space can be enumerated and reversed in
		// seconds — which is what this column used to hold.
		$ip_hash = ! empty( $ip_raw )
			? ( $plaintext ? $ip_raw : Pseudonymizer::hash_ip( $ip_raw ) )
			: '';

		$page_url = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$data = [
			'ts'            => current_time( 'mysql', true ),
			'form_plugin'   => sanitize_text_field( $extra['form_plugin'] ?? '' ),
			'form_id'       => sanitize_text_field( $extra['form_id'] ?? '' ),
			'page_url'      => substr( $page_url, 0, 500 ),
			'ip_hash'       => $ip_hash,
			'protection'    => sanitize_text_field( $protection ),
			'verdict'       => sanitize_text_field( $extra['verdict'] ?? 'blocked' ),
			'reason_code'   => sanitize_text_field( $reason_code ),
			'reason_detail' => sanitize_textarea_field( $reason_detail ),
			'score'         => isset( $extra['score'] ) ? (float) $extra['score'] : null,
			'reason_codes'  => isset( $extra['reason_codes'] ) ? wp_json_encode( $extra['reason_codes'] ) : null,
			'meta'          => isset( $extra['meta'] ) ? wp_json_encode( $extra['meta'] ) : null,
		];

		$format = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ];

		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->get_table_name(), $data, $format );
		$wpdb->suppress_errors( $suppress );

		if ( false === $result ) {
			$this->logger->error( 'BlockLog insert failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			AuditLog::log(
				AuditLog::TYPE_DB,
				'BLOCKLOG_INSERT_FAILED',
				AuditLog::SEVERITY_ERROR,
				sprintf( 'BlockLog insert failed: %s', $wpdb->last_error ),
				[ 'table' => $this->get_table_name(), 'error' => $wpdb->last_error, 'data_keys' => array_keys( $data ) ]
			);
		} else {
			$this->logger->debug( 'Block event logged', [
				'plugin'      => 'f12-cf7-captcha',
				'protection'  => $protection,
				'reason_code' => $reason_code,
			] );
		}
	}

	/**
	 * SQL predicate limiting a query to rows that represent an actual block.
	 *
	 * The table also holds anonymous observations — `monitored` (would have blocked) and
	 * `scored` (passed, measured anyway). Those exist to calibrate detection and must never
	 * reach the Analytics screen, which reports what the plugin *did*. Rows written before the
	 * verdict column was used carry the 'blocked' default, so history is unaffected.
	 */
	private const BLOCKED_ONLY = "verdict = 'blocked'";

	/**
	 * Get block log entries (newest first).
	 *
	 * @param int $limit  Max entries to return.
	 * @param int $offset Offset for pagination.
	 * @param int $days   Only entries from last N days.
	 *
	 * @return array
	 */
	public function get_entries( int $limit = 50, int $offset = 0, int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY . " ORDER BY ts DESC LIMIT %d OFFSET %d",
				$since,
				$limit,
				$offset
			),
			ARRAY_A
		);

		if ( null === $results && ! empty( $wpdb->last_error ) ) {
			AuditLog::log(
				AuditLog::TYPE_DB,
				'BLOCKLOG_QUERY_FAILED',
				AuditLog::SEVERITY_ERROR,
				sprintf( 'BlockLog query failed in get_entries(): %s', $wpdb->last_error ),
				[ 'table' => $table, 'error' => $wpdb->last_error ]
			);
			return [];
		}

		return $results ?: [];
	}

	/**
	 * Get anonymous observation entries — everything the Analytics screen deliberately hides.
	 *
	 * This is the read side of {@see log_observation()} and the intended source for any later
	 * export: it returns only rows that carry no personal data by construction. The mail log
	 * holds full submission text and must never be used for that purpose.
	 *
	 * @param int $limit  Max entries to return.
	 * @param int $offset Offset for pagination.
	 * @param int $days   Only entries from last N days.
	 *
	 * @return array
	 */
	public function get_observations( int $limit = 100, int $offset = 0, int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ts >= %s AND verdict <> 'blocked' ORDER BY ts DESC LIMIT %d OFFSET %d",
				$since,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get summary counts grouped by protection module.
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return array [ ['protection' => 'timer', 'count' => 42], ... ]
	 */
	public function get_summary_by_protection( int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT protection, COUNT(*) as count FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY . " GROUP BY protection ORDER BY count DESC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get summary counts grouped by reason_code.
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return array [ ['reason_code' => 'SUBMIT_TOO_FAST', 'count' => 42], ... ]
	 */
	public function get_summary_by_reason( int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT reason_code, COUNT(*) as count FROM {$table} WHERE ts >= %s AND reason_code != '' AND " . self::BLOCKED_ONLY . " GROUP BY reason_code ORDER BY count DESC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get total block count for a time period.
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return int
	 */
	public function get_total_count( int $days = 30 ): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY,
				$since
			)
		);
	}

	/**
	 * Get daily block counts for a time period (for timeline chart).
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return array [ ['day' => '2026-03-01', 'count' => 12], ... ]
	 */
	public function get_daily_counts( int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(ts) AS day, COUNT(*) AS count FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY . " GROUP BY DATE(ts) ORDER BY day ASC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get overview counts (today, week, month) and block rate.
	 *
	 * @return array ['today' => int, 'week' => int, 'month' => int, 'rate' => float]
	 */
	public function get_overview(): array {
		if ( ! $this->table_exists() ) {
			$counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
			return [ 'today' => 0, 'week' => 0, 'month' => 0, 'rate' => 0.0 ];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$today = gmdate( 'Y-m-d 00:00:00' );
		$week  = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		$month = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY, $today )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$week_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY, $week )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$month_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ts >= %s AND " . self::BLOCKED_ONLY, $month )
		);

		if ( null === $today_count && ! empty( $wpdb->last_error ) ) {
			AuditLog::log(
				AuditLog::TYPE_DB,
				'BLOCKLOG_QUERY_FAILED',
				AuditLog::SEVERITY_ERROR,
				sprintf( 'BlockLog query failed in get_overview(): %s', $wpdb->last_error ),
				[ 'table' => $table, 'error' => $wpdb->last_error ]
			);
		}

		$today_count = (int) $today_count;
		$week_count  = (int) $week_count;
		$month_count = (int) $month_count;

		// Block rate: blocked / total checks from telemetry counters
		$counters    = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
		$total       = (int) ( $counters['checks_total'] ?? 0 );
		$rate        = $total > 0 ? round( ( $month_count / $total ) * 100, 1 ) : 0.0;

		return [
			'today' => $today_count,
			'week'  => $week_count,
			'month' => $month_count,
			'rate'  => $rate,
		];
	}

	/**
	 * Delete entries older than the given number of days.
	 *
	 * @param int $days Delete entries older than N days.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup( int $days = 30 ): int {
		global $wpdb;

		$table    = $this->get_table_name();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE ts < %s",
				$cutoff
			)
		);

		if ( false === $deleted ) {
			$this->logger->error( 'BlockLog cleanup failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			AuditLog::log(
				AuditLog::TYPE_DB,
				'BLOCKLOG_CLEANUP_FAILED',
				AuditLog::SEVERITY_ERROR,
				sprintf( 'BlockLog cleanup failed: %s', $wpdb->last_error ),
				[ 'table' => $table, 'error' => $wpdb->last_error, 'cutoff' => $cutoff ]
			);

			return 0;
		}

		$this->logger->info( 'BlockLog cleanup completed', [
			'plugin'  => 'f12-cf7-captcha',
			'deleted' => $deleted,
			'cutoff'  => $cutoff,
		] );

		return (int) $deleted;
	}
}
