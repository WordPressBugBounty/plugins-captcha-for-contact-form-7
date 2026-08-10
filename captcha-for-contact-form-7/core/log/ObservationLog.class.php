<?php

namespace f12_cf7_captcha\core\log;

use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anonymous detection measurements — what the protection modules *measured*, not what the
 * plugin *did*.
 *
 * ## Why this is not the block log
 *
 * It used to be. Observations were written into `f12_block_log` alongside real blocks and kept
 * apart by a `verdict` column, and every read path on the Analytics screen filtered them back
 * out. That held up in the UI and fell apart everywhere else, because the two kinds of row are
 * governed by opposite defaults:
 *
 * | | switch | default |
 * |---|---|---|
 * | blocks        | `protection_detailed_tracking` | **off** |
 * | observations  | `protection_anonymous_metrics` | **on**  |
 *
 * On a default installation the shared table therefore filled up with observations and held not
 * one block. A site owner who went looking for the reason a submission was rejected — the
 * Analytics screen being empty, which is what "detailed tracking off" is supposed to look like —
 * opened the table and found gibberish rows and nothing else. At least one concluded the
 * gibberish module had rejected their enquiry when it had passed it, and lost hours to it.
 *
 * Two purposes, two consents, two lifetimes: two tables. The block log answers "what did the
 * plugin do to this visitor", is IP-linked and opt-in. This one answers "how well is detection
 * working", is anonymous and on by default.
 *
 * ## What is deliberately not stored
 *
 * No submitted text, ever — modules pass counts and feature measurements, never values. And no
 * `page_url`, which the shared table did store: a URL carries a query string, a query string
 * carries whatever the form put there, and these are the rows meant to be exported and analysed
 * away from the site that produced them. The block log keeps page_url, because a site owner
 * debugging their own blocks needs to know which page it happened on.
 */
class ObservationLog {

	/**
	 * Days an observation is kept.
	 *
	 * Longer than the block log's default of 30: these rows exist to be replayed against changed
	 * thresholds, and a calibration series that only reaches back a month cannot show a seasonal
	 * effect. Not a setting — a site owner has no reason to tune this, and the one switch that
	 * matters (`protection_anonymous_metrics`) already turns the whole thing off.
	 */
	public const RETENTION_DAYS = 90;

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'f12_observation_log';
	}

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
			ip_hash varchar(64) NOT NULL DEFAULT '',
			protection varchar(50) NOT NULL,
			verdict varchar(20) NOT NULL DEFAULT 'scored',
			reason_code varchar(100) NOT NULL DEFAULT '',
			reason_detail text,
			score float DEFAULT NULL,
			reason_codes text DEFAULT NULL,
			meta text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_ts (ts),
			KEY idx_protection (protection),
			KEY idx_verdict (verdict)
		) {$charset};";

		dbDelta( $sql );

		$this->logger->info( 'ObservationLog table created/updated', [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table_name,
		] );
	}

	public function delete_table(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$this->get_table_name()}" );
	}

	/**
	 * Whether anonymous measurements may be written at all.
	 *
	 * Defaults to on when the option has never been saved, which is the state of every
	 * installation that has not visited the settings screen since this shipped.
	 */
	public static function is_enabled(): bool {
		$enabled = \f12_cf7_captcha\CF7Captcha::get_instance()
			->get_settings( 'protection_anonymous_metrics', 'global' );

		if ( $enabled === '' || $enabled === null ) {
			return true;
		}

		return (int) $enabled === 1;
	}

	/**
	 * Record one measurement.
	 *
	 * @param string $protection    The protection module name.
	 * @param string $reason_code   Machine-readable reason code.
	 * @param string $reason_detail Human-readable explanation.
	 * @param array  $extra         verdict, score, reason_codes, meta, form_plugin, form_id.
	 */
	public function log( string $protection, string $reason_code, string $reason_detail, array $extra = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( ! $this->table_exists() ) {
			return;
		}

		global $wpdb;

		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// Always pseudonymised. The `protection_log_plaintext` debugging switch deliberately does
		// not reach these rows: it exists so a site owner can read their own traffic, and these
		// rows are not about their traffic.
		$ip_hash = $ip_raw !== '' ? Pseudonymizer::hash_ip( $ip_raw ) : '';

		$data = [
			'ts'            => current_time( 'mysql', true ),
			'form_plugin'   => sanitize_text_field( $extra['form_plugin'] ?? '' ),
			'form_id'       => sanitize_text_field( $extra['form_id'] ?? '' ),
			'ip_hash'       => $ip_hash,
			'protection'    => sanitize_text_field( $protection ),
			'verdict'       => sanitize_text_field( $extra['verdict'] ?? 'scored' ),
			'reason_code'   => sanitize_text_field( $reason_code ),
			'reason_detail' => $reason_detail,
			'score'         => isset( $extra['score'] ) ? (float) $extra['score'] : null,
			'reason_codes'  => isset( $extra['reason_codes'] ) ? wp_json_encode( $extra['reason_codes'] ) : null,
			'meta'          => isset( $extra['meta'] ) ? wp_json_encode( $extra['meta'] ) : null,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $this->get_table_name(), $data );

		if ( false === $inserted ) {
			$this->logger->error( 'ObservationLog insert failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );
		}
	}

	/**
	 * Read measurements back, newest first.
	 *
	 * @param int    $limit      Max entries to return.
	 * @param int    $offset     Offset for pagination.
	 * @param int    $days       Only entries from the last N days.
	 * @param string $protection Optional module filter.
	 *
	 * @return array
	 */
	public function get_entries( int $limit = 100, int $offset = 0, int $days = 30, string $protection = '' ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		if ( $protection !== '' ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE ts >= %s AND protection = %s ORDER BY ts DESC LIMIT %d OFFSET %d",
					$since,
					$protection,
					$limit,
					$offset
				),
				ARRAY_A
			) ?: [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ts >= %s ORDER BY ts DESC LIMIT %d OFFSET %d",
				$since,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Counts per verdict — the shape any calibration pass starts from.
	 *
	 * @return array [ ['verdict' => 'scored', 'count' => 412], ... ]
	 */
	public function get_summary_by_verdict( int $days = 30 ): array {
		if ( ! $this->table_exists() ) {
			return [];
		}

		global $wpdb;

		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT verdict, COUNT(*) as count FROM {$table} WHERE ts >= %s GROUP BY verdict ORDER BY count DESC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Move the observation rows out of the block log.
	 *
	 * Run once on upgrade. Copies every row the block log kept apart by its verdict column into
	 * this table and deletes it there, so the block log is left holding blocks only and the
	 * calibration series survives the split.
	 *
	 * `page_url` is dropped rather than carried over — see the class docblock.
	 *
	 * @return int Rows moved.
	 */
	public function migrate_from_block_log(): int {
		global $wpdb;

		$block_log = new BlockLog( $this->logger );

		if ( ! $block_log->table_exists() || ! $this->table_exists() ) {
			return 0;
		}

		$source = $block_log->get_table_name();
		$target = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$moved = $wpdb->query(
			"INSERT INTO {$target}
				(ts, form_plugin, form_id, ip_hash, protection, verdict, reason_code, reason_detail, score, reason_codes, meta)
			 SELECT ts, form_plugin, form_id, ip_hash, protection, verdict, reason_code, reason_detail, score, reason_codes, meta
			 FROM {$source}
			 WHERE verdict <> 'blocked'"
		);

		if ( false === $moved ) {
			$this->logger->error( 'ObservationLog migration failed; block log left untouched', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			return 0;
		}

		// Only delete once the copy is known to have succeeded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$source} WHERE verdict <> 'blocked'" );

		$this->logger->info( 'Observations moved out of the block log', [
			'plugin' => 'f12-cf7-captcha',
			'rows'   => (int) $moved,
		] );

		return (int) $moved;
	}

	/**
	 * Delete entries older than the given number of days.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup( int $days = self::RETENTION_DAYS ): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table  = $this->get_table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE ts < %s", $cutoff )
		);

		if ( false === $deleted ) {
			$this->logger->error( 'ObservationLog cleanup failed', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $wpdb->last_error,
			] );

			return 0;
		}

		return (int) $deleted;
	}
}
