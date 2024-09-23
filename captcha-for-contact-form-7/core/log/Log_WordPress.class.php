<?php

namespace f12_cf7_captcha\core;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\log\Array_Formatter;
use RuntimeException;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'Array_Formatter.class.php' );

class Log_WordPress {
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
	 * The constructor, ensure that only one instance could be created
	 */
	private function __construct() {
		/*
		 * Load Taxonomy
		 */
		add_action( 'init', [ $this, 'wp_register_taxonomy' ] );

		/*
		 * Load Post Type
		 */
		add_action( 'init', [ $this, 'wp_register_post_type' ] );

		/*
		 * Add Menu Entries for Logger
		 */
		add_action( 'admin_menu', [ $this, 'wp_set_admin_submenu_page' ] );
		add_filter( 'parent_file', [ $this, 'wp_set_admin_menu_active' ] );
	}

	/**
	 * Sets the admin submenu page for the "Log Entries" menu item.
	 *
	 * @return void
	 *
	 */
	public function wp_set_admin_submenu_page(): void {
		add_submenu_page( 'f12-cf7-captcha', __('Log Entries', 'captcha-for-contact-form-7'), __('Log Entries', 'captcha-for-contact-form-7'), 'edit_pages', 'edit.php?post_type=f12_captcha_log' );
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
			throw new RuntimeException( 'Current Screen is not defined' );
		}

		// Set correct active/current menu and submenu in the WordPress Admin menu for the "example_cpt" Add-New/Edit/List
		if ( $current_screen->post_type === 'f12_captcha_log' ) {
			$submenu_file = 'edit.php?post_type=f12_captcha_log';
			$parent_file  = 'f12-cf7-captcha';
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

		register_taxonomy( 'log_status', array( 'deals' ), array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'public'            => false,
		) );

		/*
		 * Create the default taxonomies if not exists
		 */
		$terms = get_terms( 'log_status' );

		$defaultTerms = [ 'spam' => 'Spam', 'verified' => 'Verified' ];

		foreach ( $terms as $term ) {
			foreach ( $defaultTerms as $slug => $l10n ) {
				if ( $term->slug == $slug ) {
					unset( $defaultTerms[ $slug ] );
				}
			}
		}

		if ( empty( $defaultTerms ) ) {
			return;
		}

		/*
		 * Add default data to the term
		 */
		foreach ( $defaultTerms as $slug => $l10n ) {
			wp_insert_term( $l10n, 'log_status', [ 'slug' => $slug ] );
		}
	}

	/**
	 * Check if the logging is enabled.
	 *
	 * @return int Return 1 for true, 0 for false.
	 *
	 * @since 1.12.3
	 */
	public function is_logging_enabled() {
		return (int) CF7Captcha::get_instance()->get_settings( 'protection_log_enable', 'global' );
	}

	/**
	 * Returns the current timezone of the server
	 *
	 * @return string, default: Europe/Berlin
	 *
	 * @since 1.12.3
	 */
	public function get_timezone_id() {
		$timezone_id = get_option( 'timezone_string' );

		if ( empty( $timezone_id ) ) {
			$timezone_id = 'Europe/Berlin';
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

		return true;
	}

	/**
	 * Retrieve the last entry from the database.
	 *
	 * @return WP_Post|null The last entry as a WP_Post object, or null if no entry exists.
	 */
	public function get_last_entry(): ?WP_Post {
		if ( $this->last_insert_id == 0 ) {
			return null;
		}

		return get_post( $this->last_insert_id );
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
		if ( ! Log_WordPress::get_instance()->is_logging_enabled() ) {
			return;
		}

		Log_WordPress::get_instance()->maybe_log(
			$Log_Item->get_name(),
			$Log_Item->get_properties(),
			$Log_Item->get_log_status_slug() == 'spam' ? true : false
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
			throw new RuntimeException( 'WPDB is not defined' );
		}

		return $wpdb->prefix . 'posts';
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
	public function get_count() {
		global $wpdb;

		if ( ! $wpdb ) {
			throw new RuntimeException( 'WPDB is not defined' );
		}

		$table_name = $this->get_table_name();

		$result = $wpdb->get_results(
			sprintf(
				'SELECT count(*) AS counting FROM %s WHERE post_type = "%s"',
				$table_name,
				'f12_captcha_log'
			)
		);

		if ( isset( $result[0] ) ) {
			return $result[0]->counting;
		}

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
			throw new RuntimeException( 'WPDB is not defined' );
		}

		$table_name = $this->get_table_name();

		return (int) $wpdb->query(
			sprintf( 'DELETE FROM %s WHERE post_type = "%s"', $table_name, 'f12_captcha_log' )
		);
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
			throw new RuntimeException( 'WPDB is not defined' );
		}

		$table_name = $this->get_table_name();

		return (int) $wpdb->query(
			sprintf( 'DELETE FROM %s WHERE post_type = "%s" AND post_date < "%s"', $table_name, 'f12_captcha_log', $create_time )
		);
	}
}