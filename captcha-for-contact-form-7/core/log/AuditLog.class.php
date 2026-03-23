<?php

namespace f12_cf7_captcha\core\log;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit log for admin/system events (settings changes, cron, lifecycle, errors).
 * Always active — does not depend on any feature toggle.
 */
class AuditLog {

	// Event types
	const TYPE_SETTINGS   = 'settings_change';
	const TYPE_CRON       = 'cron_run';
	const TYPE_ACTIVATION = 'activation';
	const TYPE_RATE_LIMIT = 'rate_limit';
	const TYPE_API        = 'api_error';
	const TYPE_API_ERROR  = 'api_error';
	const TYPE_DB         = 'db_error';
	const TYPE_DB_ERROR   = 'db_error';
	const TYPE_TRIAL      = 'trial';
	const TYPE_I18N       = 'i18n';

	// Severity levels
	const SEVERITY_INFO     = 'info';
	const SEVERITY_WARNING  = 'warning';
	const SEVERITY_ERROR    = 'error';
	const SEVERITY_CRITICAL = 'critical';

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the table name with WordPress prefix.
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'f12_audit_log';
	}

	/**
	 * Check if the audit_log table exists.
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
	 * Create the audit_log table using dbDelta.
	 */
	public function create_table(): void {
		global $wpdb;

		$table_name = $this->get_table_name();
		$charset    = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			event_type varchar(50) NOT NULL,
			event_code varchar(100) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			user_id bigint(20) unsigned DEFAULT NULL,
			description text NOT NULL,
			context text DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_ts (ts),
			KEY idx_event_type (event_type),
			KEY idx_severity (severity)
		) {$charset};";

		dbDelta( $sql );

		$this->logger->info( 'AuditLog table created/updated', [
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
	 * Log an audit event.
	 *
	 * This method never throws. If the insert fails, it falls back to error_log()
	 * to avoid circular failures.
	 *
	 * @param string $event_type  One of the TYPE_* constants.
	 * @param string $event_code  Machine-readable code (e.g. 'SETTINGS_UPDATED').
	 * @param string $severity    One of the SEVERITY_* constants.
	 * @param string $description Human-readable description.
	 * @param array  $context     Optional additional data (will be stored as JSON).
	 */
	public static function log( string $event_type, string $event_code, string $severity, string $description, array $context = [] ): void {
		global $wpdb;

		// Throttle: limit repeated events to 1 per 5 minutes per event_code
		// Prevents log flooding from recurring errors (API unreachable, rate limits, DB errors)
		if ( in_array( $severity, [ self::SEVERITY_WARNING, self::SEVERITY_ERROR, self::SEVERITY_CRITICAL ], true ) ) {
			$throttle_key = 'f12_audit_throttle_' . md5( $event_code );
			if ( get_transient( $throttle_key ) ) {
				return;
			}
			set_transient( $throttle_key, true, 5 * MINUTE_IN_SECONDS );
		}

		// Mask sensitive values in context
		$context = self::mask_sensitive_data( $context );

		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		$ip_hash = ! empty( $ip_raw ) ? hash( 'sha256', $ip_raw ) : null;

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : null;
		if ( $user_id === 0 ) {
			$user_id = null;
		}

		$table_name = $wpdb->prefix . 'f12_audit_log';

		$data = [
			'ts'          => current_time( 'mysql', true ),
			'event_type'  => sanitize_text_field( $event_type ),
			'event_code'  => sanitize_text_field( $event_code ),
			'severity'    => sanitize_text_field( $severity ),
			'user_id'     => $user_id,
			'description' => sanitize_textarea_field( $description ),
			'context'     => ! empty( $context ) ? wp_json_encode( $context ) : null,
			'ip_hash'     => $ip_hash,
		];

		$format = [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ];

		// If user_id is null, we need to handle the format differently
		if ( $user_id === null ) {
			$data['user_id'] = null;
			$format[4]       = null;
		}

		// Suppress wpdb error output to prevent HTML leaking into REST responses
		// when the table does not exist yet (e.g. before upgrade migration runs).
		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table_name, $data, $format );
		$wpdb->suppress_errors( $suppress );

		// If insert fails, fall back to PHP error_log to avoid recursion
		if ( $result === false ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[SilentShield AuditLog] Insert failed (%s): [%s] %s — %s',
				$wpdb->last_error,
				$event_code,
				$severity,
				$description
			) );
		}
	}

	/**
	 * Get audit log entries (newest first).
	 *
	 * @param int         $limit           Max entries to return.
	 * @param int         $offset          Offset for pagination.
	 * @param string|null $type_filter     Filter by event_type (optional).
	 * @param string|null $severity_filter Filter by severity (optional).
	 * @param int         $days            Only entries from last N days.
	 *
	 * @return array
	 */
	public function get_entries( int $limit = 50, int $offset = 0, ?string $type_filter = null, ?string $severity_filter = null, int $days = 90 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$where = 'WHERE ts >= %s';
		$params = [ $since ];

		if ( $type_filter !== null ) {
			$where   .= ' AND event_type = %s';
			$params[] = $type_filter;
		}

		if ( $severity_filter !== null ) {
			$where   .= ' AND severity = %s';
			$params[] = $severity_filter;
		}

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY ts DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get total count of audit entries with optional filters.
	 *
	 * @param string|null $type_filter     Filter by event_type.
	 * @param string|null $severity_filter Filter by severity.
	 * @param int         $days            Only entries from last N days.
	 *
	 * @return int
	 */
	public function get_count( ?string $type_filter = null, ?string $severity_filter = null, int $days = 90 ): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$where  = 'WHERE ts >= %s';
		$params = [ $since ];

		if ( $type_filter !== null ) {
			$where   .= ' AND event_type = %s';
			$params[] = $type_filter;
		}

		if ( $severity_filter !== null ) {
			$where   .= ' AND severity = %s';
			$params[] = $severity_filter;
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
	 * Get summary counts grouped by event_type.
	 *
	 * @param int $days Only entries from last N days.
	 *
	 * @return array [ ['event_type' => 'settings_change', 'count' => 5], ... ]
	 */
	public function get_summary_by_type( int $days = 90 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) as count FROM {$table} WHERE ts >= %s GROUP BY event_type ORDER BY count DESC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get recent warnings and errors for the dashboard widget.
	 *
	 * @param int $limit Max entries.
	 *
	 * @return array
	 */
	public function get_recent_issues( int $limit = 5 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE severity IN ('warning', 'error', 'critical') ORDER BY ts DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Delete entries older than the given number of days.
	 *
	 * @param int $days Delete entries older than N days.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup( int $days = 90 ): int {
		global $wpdb;

		$table  = $this->get_table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE ts < %s",
				$cutoff
			)
		);

		$this->logger->info( 'AuditLog cleanup completed', [
			'plugin'  => 'f12-cf7-captcha',
			'deleted' => $deleted,
			'cutoff'  => $cutoff,
		] );

		return (int) $deleted;
	}

	/**
	 * Mask sensitive values in context arrays before storage.
	 *
	 * @param array $data The context data.
	 *
	 * @return array The masked data.
	 */
	private static function mask_sensitive_data( array $data ): array {
		$sensitive_keys = [ 'api_key', 'password', 'secret', 'token', 'beta_captcha_api_key' ];

		foreach ( $data as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = self::mask_sensitive_data( $value );
			} elseif ( is_string( $value ) ) {
				foreach ( $sensitive_keys as $sensitive ) {
					if ( stripos( $key, $sensitive ) !== false && strlen( $value ) > 4 ) {
						$value = substr( $value, 0, 4 ) . str_repeat( '*', max( strlen( $value ) - 4, 4 ) );
						break;
					}
				}
			}
		}
		unset( $value );

		return $data;
	}
}
