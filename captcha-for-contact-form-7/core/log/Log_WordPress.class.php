<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\log\Array_Formatter;
use forge12\contactform7\CF7Captcha\core\log\Log_Item;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;
use RuntimeException;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


require_once( plugin_dir_path( dirname(dirname(__FILE__) )) . '/logger/logger.php' );

require_once( 'Array_Formatter.class.php' );

class Log_WordPress {
	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;
	/**
	 * The current instance
	 *
	 * @var Log_WordPress
	 */
	private static $_instance = null;

	/**
	 * @var int
	 */
	private int $last_insert_id = 0;

	/**
	 * Get the current instance of the object or create one
	 *
	 * @return Log_WordPress
	 */
	public static function get_instance(): Log_WordPress {
		if ( self::$_instance === null ) {
			self::$_instance = new Log_WordPress();
		}

		return self::$_instance;
	}

	/**
	 * Get the Logger for System Logs
	 *
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * The constructor, ensure that only one instance could be created
	 */
	private function __construct() {
		try {
			$this->logger = Logger::getInstance();

			/*
			 * Load Taxonomy
			 */
			add_action( 'init', [ $this, 'wp_register_taxonomy' ] );
			$this->logger->debug( "Hook registriert: init -> wp_register_taxonomy", [
				'plugin' => 'f12-cf7-captcha'
			] );

			/*
			 * Load Post Type
			 */
			add_action( 'init', [ $this, 'wp_register_post_type' ] );
			$this->logger->debug( "Hook registriert: init -> wp_register_post_type", [
				'plugin' => 'f12-cf7-captcha'
			] );

			/*
			 * Add Menu Entries for Logger
			 */
			add_action( 'admin_menu', [ $this, 'wp_set_admin_submenu_page' ] );
			$this->logger->debug( "Hook registriert: admin_menu -> wp_set_admin_submenu_page", [
				'plugin' => 'f12-cf7-captcha'
			] );

			add_filter( 'parent_file', [ $this, 'wp_set_admin_menu_active' ] );
			$this->logger->debug( "Hook registriert: parent_file -> wp_set_admin_menu_active", [
				'plugin' => 'f12-cf7-captcha'
			] );
		} catch ( \Throwable $e ) {
			// Fehler sofort im Log dokumentieren
			if ( isset( $this->logger ) ) {
				$this->logger->error( "Fehler beim Initialisieren des Logger-Moduls", [
					'plugin' => 'f12-cf7-captcha',
					'class'  => static::class,
					'error'  => $e->getMessage()
				] );
			}
			throw $e; // wichtig: Fehler nicht verschlucken
		}
	}

	/**
	 * Sets the admin submenu page for the "Log Entries" menu item.
	 *
	 * @return void
	 *
	 */
	public function wp_set_admin_submenu_page(): void {
		add_submenu_page(
			'f12-cf7-captcha',
			__( 'Log Entries', 'captcha-for-contact-form-7' ),
			__( 'Log Entries', 'captcha-for-contact-form-7' ),
			'edit_pages',
			'edit.php?post_type=f12_captcha_log'
		);

		$this->get_logger()->debug("Admin-Submenu für Logs registriert", [
			'plugin'     => 'f12-cf7-captcha',
			'menu_slug'  => 'f12-cf7-captcha',
			'page_title' => 'Log Entries',
			'capability' => 'edit_pages',
			'target'     => 'f12_captcha_log'
		]);
	}


	/**
	 * Sets the active menu item in the WordPress Admin menu based on the parent file.
	 *
	 * @param string          $parent_file    The parent file name.
	 *
	 * @return string The updated parent file name.
	 * @throws RuntimeException If the current screen is not defined.
	 *
	 * @global string|null    $submenu_file   The submenu file.
	 * @global WP_Screen|null $current_screen The current screen object.
	 */
	public function wp_set_admin_menu_active( string $parent_file ): string {
		global $submenu_file, $current_screen;

		if ( ! $current_screen ) {
			$this->get_logger()->error("Admin-Menü konnte nicht gesetzt werden: current_screen fehlt", [
				'plugin' => 'f12-cf7-captcha',
				'class'  => static::class
			]);
			throw new \RuntimeException('Current Screen is not defined');
		}

		if ( $current_screen->post_type === 'f12_captcha_log' ) {
			$submenu_file = 'edit.php?post_type=f12_captcha_log';
			$parent_file  = 'f12-cf7-captcha';

			$this->get_logger()->debug("Admin-Menü aktiv gesetzt", [
				'plugin'    => 'f12-cf7-captcha',
				'post_type' => $current_screen->post_type,
				'submenu'   => $submenu_file,
				'parent'    => $parent_file
			]);
		} else {
			$this->get_logger()->debug("Admin-Menü nicht geändert", [
				'plugin'    => 'f12-cf7-captcha',
				'post_type' => $current_screen->post_type,
				'parent'    => $parent_file
			]);
		}

		return $parent_file;
	}


	/**
	 * Register the custom post type for Captcha Log.
	 *
	 * This method registers a custom post type called "Captcha Log" with the necessary labels and
	 * arguments. It also associates it with the "log_status" taxonomy.
	 *
	 * @return void
	 *
	 * @global string $plugin_text_domain The text domain of the plugin.
	 */
	public function wp_register_post_type(): void {
		$labels = array(
			'name'           => _x( 'Captcha Log', 'Post type general name', 'captcha-for-contact-form-7' ),
			'singular_name'  => _x( 'Captcha Log', 'Post type singular name', 'captcha-for-contact-form-7' ),
			'menu_name'      => _x( 'Captcha Log', 'Admin Menu text', 'captcha-for-contact-form-7' ),
			'name_admin_bar' => _x( 'Captcha Log', 'Add New on Toolbar', 'captcha-for-contact-form-7' ),
			'edit_item'      => __( 'Edit', 'captcha-for-contact-form-7' ),
			'view_item'      => __( 'View', 'captcha-for-contact-form-7' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor' ),
			'taxonomies'         => array( 'log_status' )
		);

		register_post_type( 'f12_captcha_log', $args );
	}

	/**
	 * Register a new taxonomy for the "deals" post type.
	 *
	 * @return void
	 */
	public function wp_register_taxonomy(): void {
		$labels = array(
			'name'          => _x( 'Status', 'Post type general name', 'captcha-for-contact-form-7' ),
			'singular_name' => _x( 'Status', 'Post type singular name', 'captcha-for-contact-form-7' ),
			'menu_name'     => _x( 'Status', 'Admin Menu text', 'captcha-for-contact-form-7' ),
		);

		try {
			register_taxonomy( 'log_status', array( 'deals' ), array(
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'public'            => false,
			) );

			$this->get_logger()->info("Taxonomie registriert", [
				'plugin'     => 'f12-cf7-captcha',
				'taxonomy'   => 'log_status',
				'post_types' => ['deals']
			]);
		} catch (\Throwable $e) {
			$this->get_logger()->error("Fehler beim Registrieren der Taxonomie", [
				'plugin'   => 'f12-cf7-captcha',
				'taxonomy' => 'log_status',
				'error'    => $e->getMessage()
			]);
			throw $e;
		}

		/*
		 * Create the default taxonomies if not exists
		 */
		$terms = get_terms('log_status');
		$defaultTerms = [ 'spam' => 'Spam', 'verified' => 'Verified' ];

		foreach ($terms as $term) {
			foreach ($defaultTerms as $slug => $l10n) {
				if ($term->slug == $slug) {
					unset($defaultTerms[$slug]);
				}
			}
		}

		if (empty($defaultTerms)) {
			$this->get_logger()->debug("Alle Default-Terme bereits vorhanden", [
				'plugin'   => 'f12-cf7-captcha',
				'taxonomy' => 'log_status'
			]);
			return;
		}

		/*
		 * Add default data to the term
		 */
		foreach ($defaultTerms as $slug => $l10n) {
			$result = wp_insert_term($l10n, 'log_status', ['slug' => $slug]);

			if (is_wp_error($result)) {
				$this->get_logger()->error("Fehler beim Anlegen des Default-Terms", [
					'plugin'   => 'f12-cf7-captcha',
					'taxonomy' => 'log_status',
					'term'     => $slug,
					'error'    => $result->get_error_message()
				]);
			} else {
				$this->get_logger()->info("Default-Term angelegt", [
					'plugin'   => 'f12-cf7-captcha',
					'taxonomy' => 'log_status',
					'term'     => $slug,
					'name'     => $l10n
				]);
			}
		}
	}


	/**
	 * Check if the logging is enabled.
	 *
	 * @return int Return 1 for true, 0 for false.
	 *
	 * @since 1.12.3
	 */
	public function is_logging_enabled(): bool {
		$enabled = (int) CF7Captcha::get_instance()->get_settings('protection_log_enable', 'global') === 1;

		// Optional Debug-Log
		$this->get_logger()->debug("Logging Status geprüft", [
			'plugin'  => 'f12-cf7-captcha',
			'enabled' => $enabled
		]);

		return $enabled;
	}

	/**
	 * Returns the current timezone of the server
	 *
	 * @return string, default: Europe/Berlin
	 *
	 * @since 1.12.3
	 */
	public function get_timezone_id(): string {
		$timezone_id = get_option('timezone_string');

		if (empty($timezone_id)) {
			$timezone_id = 'Europe/Berlin';
			$this->get_logger()->debug("Keine Zeitzone in WP konfiguriert, Fallback gesetzt", [
				'plugin'   => 'f12-cf7-captcha',
				'timezone' => $timezone_id
			]);
		} else {
			$this->get_logger()->debug("Zeitzone geladen", [
				'plugin'   => 'f12-cf7-captcha',
				'timezone' => $timezone_id
			]);
		}

		return $timezone_id;
	}


	/**
	 * maybe_log
	 *
	 * Logs form submissions based on the provided type, form data, and spam status.
	 *
	 * @param string $type      The type of log entry to create.
	 * @param array  $form_data The form data to be logged.
	 * @param bool   $is_spam   (Optional) Indicates whether the submission is considered spam. Default is true.
	 *
	 * @return bool Returns true if the log entry was created successfully, otherwise false.
	 *
	 * @since 1.0.0
	 */
	public function maybe_log( string $type, array $form_data, bool $is_spam = true, string $message = '' ) {
		/*
		 * Skip if logging is disabled
		 */
		if ( ! $this->is_logging_enabled() ) {
			$this->get_logger()->debug("Logging übersprungen – deaktiviert", [
				'plugin' => 'f12-cf7-captcha',
				'type'   => $type
			]);
			return false;
		}

		/**
		 * Retrieve additional information for logging
		 *
		 * This allows developers to store additional information within a log entry.
		 *
		 * @param array $data Additional Fields
		 *
		 * @since 1.0.0
		 */
		$additional_information = apply_filters( 'f12-cf7-captcha-log-data', [] );

		/*
		 * Switch the type to convert to a log title
		 */
		switch ( $type ) {
			case 'comments-protection':
				$log_title                             = __( 'Comments Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The protection for the comment was verified. The form has been submitted.', 'captcha-for-contact-form-7' );
				break;
			case 'cf7-protection':
				$log_title                             = __( 'CF7 Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The protection for cf7 was verified. The form has been submitted.', 'captcha-for-contact-form-7' );
				break;
			case 'avada-protection':
				$log_title                             = __( 'Avada Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The protection for avada was verified. The form has been submitted.', 'captcha-for-contact-form-7' );
				break;
			case 'timer-protection':
				$log_title                             = __( 'Timer Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The time verification failed. This is caused if the form is submitted to fast after page load.', 'captcha-for-contact-form-7' );
				break;
			case 'captcha-protection':
				$log_title                             = __( 'Captcha Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The user could not complete the captcha. Validation failed.', 'captcha-for-contact-form-7' );
				break;
			case 'rule-protection':
				$log_title                             = __( 'Rule Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The form has been blocked by given rules (BBCode, Blacklist or URL).', 'captcha-for-contact-form-7' );
				break;
			case 'ip-protection':
				$log_title                             = __( 'IP Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The IP of the submitter has been blocked. This happens if the user has to often submitted forms or has been identified multiple times as spammer.', 'captcha-for-contact-form-7' );
				break;
			case 'javascript-protection':
				$log_title                             = __( 'JavaScript Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The JavaScript validation failed. This indicates that the form was submitted by a bot or a skript that could not run our validation.', 'captcha-for-contact-form-7' );
				break;
			case 'browser-protection':
				$log_title                             = __( 'Browser Protection', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The Browser has been identified as crawler/bot. Submission has been blocked.', 'captcha-for-contact-form-7' );
				break;
			case 'multiple-submission-protection-timer':
				$log_title                             = __( 'Multiple Submission Protection - Timer failed', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The defined timer failed. The form was submitted to often / to fast. This can be caused by bots trying to send the form with multiple data in a short period of time.', 'captcha-for-contact-form-7' );
				break;
			case 'multiple-submission-protection-missing':
				$log_title                             = __( 'Multiple Submission Protection - Mechanismus missing', 'captcha-for-contact-form-7' );
				$additional_information['Log Message'] = __( 'The field for validation is missing. That can be caused by bots not allowing javascript to work.', 'captcha-for-contact-form-7' );
				break;
			default:
				$log_title                             = $type;
				$additional_information['Log Message'] = $message;
		}

		/*
		 * Create the Post Title for Log entry
		 */
		$post_title = sprintf( '%s - %s', date( 'd.m.Y : H:i:s', time() ), wp_strip_all_tags( $log_title ) );

		/*
		 * Prepare the Post Content
		 */
		$post_content = Array_Formatter::to_string(
			array_merge(
				array_merge( $form_data, $additional_information )
			),
			'<br>',
			true
		);

		/*
		 * Set the timezone
		 */
		date_default_timezone_set( $this->get_timezone_id() );

		/*
		 * Prepare the post data
		 */
		$post_data = [
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_type'    => 'f12_captcha_log'
		];

		/*
		 * Insert the post into the database
		 */
		$post_id = wp_insert_post( $post_data );

		/*
		 * Store the last insert ind
		 */
		$this->last_insert_id = (int) $post_id;

		/*
		 * Check if the post has been created
		 */
		if ( ! is_numeric( $post_id ) || 0 === $post_id ) {
			$this->last_insert_id = 0;

			$this->get_logger()->error("Log konnte nicht gespeichert werden", [
				'plugin'   => 'f12-cf7-captcha',
				'type'     => $type,
				'title'    => $log_title
			]);
			return false;
		}

		/*
		 * Define the log status as string
		 */
		$log_status = 'verified';

		if ( $is_spam !== false ) {
			$log_status = 'spam';
		}

		/*
		 * Add Taxonomy Status
		 */
		wp_set_object_terms( $post_id, $log_status, 'log_status' );
		/*
		 * Write to technical log
		 */
		$this->get_logger()->info("Logeintrag erstellt", [
			'plugin'   => 'f12-cf7-captcha',
			'type'     => $type,
			'post_id'  => $post_id,
			'status'   => $log_status,
			'title'    => $log_title,
			'preview'  => mb_substr(strip_tags($post_content), 0, 100) . (strlen($post_content) > 100 ? '...' : '')
		]);
		return true;
	}

	/**
	 * Retrieve the last entry from the database.
	 *
	 * @return WP_Post|null The last entry as a WP_Post object, or null if no entry exists.
	 */
	public function get_last_entry(): ?WP_Post {
		if ( $this->last_insert_id == 0 ) {
			$this->get_logger()->debug("Kein letzter Log-Eintrag vorhanden", [
				'plugin' => 'f12-cf7-captcha'
			]);
			return null;
		}

		$post = get_post( $this->last_insert_id );

		if ( $post instanceof \WP_Post ) {
			$this->get_logger()->debug("Letzter Log-Eintrag geladen", [
				'plugin' => 'f12-cf7-captcha',
				'post_id'=> $this->last_insert_id,
				'title'  => $post->post_title ?? 'unknown'
			]);
			return $post;
		}

		$this->get_logger()->error("Fehler: Letzter Log-Eintrag konnte nicht geladen werden", [
			'plugin' => 'f12-cf7-captcha',
			'post_id'=> $this->last_insert_id
		]);
		return null;
	}


	/**
	 * @param Log_Item $Log_Item
	 *
	 * @return void
	 * @deprecated
	 * @use Log_WordPress::maybe_log()
	 *
	 */
	public static function store( $Log_Item ) {
		$logger = \Forge12\Shared\Logger::getInstance();
		$log_wp = Log_WordPress::get_instance();

		if ( ! $log_wp->is_logging_enabled() ) {
			$logger->debug("Logging übersprungen – deaktiviert", [
				'plugin'   => 'f12-cf7-captcha',
				'log_item' => method_exists($Log_Item, 'get_name') ? $Log_Item->get_name() : 'unknown'
			]);
			return;
		}

		$is_spam = $Log_Item->get_log_status_slug() === 'spam';

		$logger->info("Log Item wird gespeichert", [
			'plugin'   => 'f12-cf7-captcha',
			'name'     => $Log_Item->get_name(),
			'status'   => $is_spam ? 'spam' : 'verified'
		]);

		$log_wp->maybe_log(
			$Log_Item->get_name(),
			$Log_Item->get_properties(),
			$is_spam
		);
	}


	/**
	 * Retrieves the table name for posts from the global $wpdb object.
	 *
	 * @return string The table name for posts.
	 * @throws RuntimeException If the global $wpdb object is not defined.
	 *
	 */
	private function get_table_name(): string {
		global $wpdb;

		if ( ! $wpdb ) {
			$this->get_logger()->error("WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB is not defined');
		}

		$table = $wpdb->prefix . 'posts';

		$this->get_logger()->debug("Tabellenname ermittelt", [
			'plugin' => 'f12-cf7-captcha',
			'table'  => $table
		]);

		return $table;
	}


	/**
	 * Get the count of records in the database table.
	 *
	 * @return int The count of records in the table.
	 * @throws RuntimeException If WPDB is not defined.
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 */
	public function get_count(): int {
		global $wpdb;

		if ( ! $wpdb ) {
			$this->get_logger()->error("WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB is not defined');
		}

		$table_name = $this->get_table_name();

		$sql = sprintf(
			'SELECT count(*) AS counting FROM %s WHERE post_type = "%s"',
			$table_name,
			'f12_captcha_log'
		);

		$this->get_logger()->debug("Zähle Log-Einträge", [
			'plugin' => 'f12-cf7-captcha',
			'query'  => $sql
		]);

		$result = $wpdb->get_results($sql);

		if ( isset( $result[0] ) ) {
			$count = (int) $result[0]->counting;

			$this->get_logger()->info("Log-Einträge gezählt", [
				'plugin' => 'f12-cf7-captcha',
				'count'  => $count
			]);

			return $count;
		}

		$this->get_logger()->warning("Keine Log-Einträge gefunden", [
			'plugin' => 'f12-cf7-captcha'
		]);

		return 0;
	}


	/**
	 * Resets the table by deleting all rows where the post_type is "f12_captcha_log".
	 *
	 * @return int The number of rows deleted.
	 * @throws RuntimeException If WPDB is not defined.
	 */
	public function reset_table(): int {
		global $wpdb;

		if ( ! $wpdb ) {
			$this->get_logger()->error("WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB is not defined');
		}

		$table_name = $this->get_table_name();

		$sql = sprintf(
			'DELETE FROM %s WHERE post_type = "%s"',
			$table_name,
			'f12_captcha_log'
		);

		$this->get_logger()->warning("Alle Logs werden gelöscht", [
			'plugin' => 'f12-cf7-captcha',
			'query'  => $sql
		]);

		$deleted = (int) $wpdb->query($sql);

		$this->get_logger()->info("Logs gelöscht", [
			'plugin'  => 'f12-cf7-captcha',
			'deleted' => $deleted
		]);

		return $deleted;
	}


	/**
	 * Deletes records older than a specified create time from the database table.
	 *
	 * @param int $create_time The create time threshold. Only records created before this time will be deleted.
	 *
	 * @return string The number of records deleted. Format: Y-m-d H:i:s
	 * @throws RuntimeException When WPDB is not defined.
	 */
	public function delete_older_than( string $create_time ): int {
		global $wpdb;

		if ( ! $wpdb ) {
			$this->get_logger()->error("WPDB nicht definiert", [
				'plugin' => 'f12-cf7-captcha'
			]);
			throw new \RuntimeException('WPDB is not defined');
		}

		$table_name = $this->get_table_name();

		$sql = sprintf(
			'DELETE FROM %s WHERE post_type = "%s" AND post_date < "%s"',
			$table_name,
			'f12_captcha_log',
			esc_sql($create_time)
		);

		$this->get_logger()->warning("Lösche alte Logs", [
			'plugin' => 'f12-cf7-captcha',
			'before' => $create_time,
			'query'  => $sql
		]);

		$deleted = (int) $wpdb->query($sql);

		$this->get_logger()->info("Alte Logs gelöscht", [
			'plugin'  => 'f12-cf7-captcha',
			'deleted' => $deleted,
			'before'  => $create_time
		]);

		return $deleted;
	}

}