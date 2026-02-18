<?php

namespace f12_cf7_captcha\core\settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers available forms per integration for the admin UI.
 *
 * Each integration type can either have individual discoverable forms
 * (e.g. CF7, WPForms) or be a system-level integration without individual
 * forms (e.g. WordPress Login, WooCommerce Checkout).
 */
class Form_Discovery {

	/**
	 * Mapping of integration IDs to their discovery configuration.
	 *
	 * @var array
	 */
	private const INTEGRATIONS = [
		'cf7' => [
			'name'           => 'Contact Form 7',
			'has_forms'      => true,
			'post_type'      => 'wpcf7_contact_form',
			'installed_check' => 'wpcf7',
		],
		'wpforms' => [
			'name'           => 'WPForms',
			'has_forms'      => true,
			'post_type'      => 'wpforms',
			'installed_check' => 'WPForms',
		],
		'gravityforms' => [
			'name'           => 'Gravity Forms',
			'has_forms'      => true,
			'post_type'      => null,
			'installed_check' => 'GFCommon',
		],
		'avada' => [
			'name'           => 'Avada',
			'has_forms'      => true,
			'post_type'      => 'fusion_form',
			'installed_check' => 'Avada',
		],
		'fluentform' => [
			'name'           => 'Fluent Forms',
			'has_forms'      => true,
			'post_type'      => null,
			'installed_check' => 'FLUENTFORM',
		],
		'jetform' => [
			'name'           => 'JetFormBuilder',
			'has_forms'      => true,
			'post_type'      => 'jet-form-builder',
			'installed_check' => '\Jet_Form_Builder\Plugin',
		],
		'elementor' => [
			'name'           => 'Elementor',
			'has_forms'      => false,
			'installed_check' => '\Elementor\Plugin',
		],
		'wordpress' => [
			'name'           => 'WordPress Login',
			'has_forms'      => false,
			'installed_check' => null,
		],
		'wordpress_registration' => [
			'name'           => 'WordPress Registration',
			'has_forms'      => false,
			'installed_check' => null,
		],
		'wordpress_comments' => [
			'name'           => 'WordPress Comments',
			'has_forms'      => false,
			'installed_check' => null,
		],
		'woocommerce' => [
			'name'           => 'WooCommerce Login',
			'has_forms'      => false,
			'installed_check' => 'WooCommerce',
		],
		'woocommerce_checkout' => [
			'name'           => 'WooCommerce Checkout',
			'has_forms'      => false,
			'installed_check' => 'WooCommerce',
		],
		'woocommerce_registration' => [
			'name'           => 'WooCommerce Registration',
			'has_forms'      => false,
			'installed_check' => 'WooCommerce',
		],
		'ultimatemember' => [
			'name'           => 'Ultimate Member',
			'has_forms'      => false,
			'installed_check' => 'UM',
		],
		'wpjobmanager_applications' => [
			'name'           => 'WP Job Manager Applications',
			'has_forms'      => false,
			'installed_check' => 'WP_Job_Manager',
		],
	];

	/**
	 * Get all known integrations with their installation status.
	 *
	 * @return array Array of integration info:
	 *               [
	 *                   'id'        => string,
	 *                   'name'      => string,
	 *                   'has_forms' => bool,
	 *                   'installed' => bool,
	 *               ]
	 */
	public function get_integrations(): array {
		$result = [];

		foreach ( self::INTEGRATIONS as $id => $config ) {
			$result[] = [
				'id'        => $id,
				'name'      => $config['name'],
				'has_forms' => $config['has_forms'],
				'installed' => $this->is_installed( $id ),
			];
		}

		return $result;
	}

	/**
	 * Check if an integration's underlying plugin is installed.
	 *
	 * @param string $integration_id
	 *
	 * @return bool
	 */
	public function is_installed( string $integration_id ): bool {
		if ( ! isset( self::INTEGRATIONS[ $integration_id ] ) ) {
			return false;
		}

		$check = self::INTEGRATIONS[ $integration_id ]['installed_check'];

		if ( $check === null ) {
			// Core WordPress features are always installed
			return true;
		}

		// Check if it's a function, class, or constant
		if ( function_exists( $check ) ) {
			return true;
		}
		if ( class_exists( $check ) ) {
			return true;
		}
		if ( defined( $check ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an integration supports individual form discovery.
	 *
	 * @param string $integration_id
	 *
	 * @return bool
	 */
	public function has_forms( string $integration_id ): bool {
		return self::INTEGRATIONS[ $integration_id ]['has_forms'] ?? false;
	}

	/**
	 * Get the display name for an integration.
	 *
	 * @param string $integration_id
	 *
	 * @return string
	 */
	public function get_integration_name( string $integration_id ): string {
		return self::INTEGRATIONS[ $integration_id ]['name'] ?? $integration_id;
	}

	/**
	 * Discover individual forms for a given integration.
	 *
	 * @param string $integration_id
	 *
	 * @return array Array of forms: [ ['id' => string, 'title' => string], ... ]
	 */
	public function get_forms( string $integration_id ): array {
		if ( ! $this->has_forms( $integration_id ) || ! $this->is_installed( $integration_id ) ) {
			return [];
		}

		$config = self::INTEGRATIONS[ $integration_id ];

		// Post-type-based discovery
		if ( ! empty( $config['post_type'] ) ) {
			return $this->get_forms_by_post_type( $config['post_type'] );
		}

		// Custom discovery per integration
		switch ( $integration_id ) {
			case 'gravityforms':
				return $this->get_gravity_forms();
			case 'fluentform':
				return $this->get_fluent_forms();
			default:
				return [];
		}
	}

	/**
	 * Discover forms stored as a custom post type.
	 *
	 * @param string $post_type
	 *
	 * @return array
	 */
	private function get_forms_by_post_type( string $post_type ): array {
		$posts = get_posts( [
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$forms = [];
		foreach ( $posts as $post ) {
			$forms[] = [
				'id'    => (string) $post->ID,
				'title' => $post->post_title ?: sprintf( '#%d', $post->ID ),
			];
		}

		return $forms;
	}

	/**
	 * Discover Gravity Forms via API.
	 *
	 * @return array
	 */
	private function get_gravity_forms(): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return [];
		}

		$gf_forms = \GFAPI::get_forms();
		$forms    = [];

		foreach ( $gf_forms as $form ) {
			$forms[] = [
				'id'    => (string) $form['id'],
				'title' => $form['title'] ?? sprintf( '#%d', $form['id'] ),
			];
		}

		return $forms;
	}

	/**
	 * Discover Fluent Forms via database query.
	 *
	 * @return array
	 */
	private function get_fluent_forms(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time admin query
		$results = $wpdb->get_results(
			"SELECT id, title FROM {$table} ORDER BY title ASC",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return [];
		}

		$forms = [];
		foreach ( $results as $row ) {
			$forms[] = [
				'id'    => (string) $row['id'],
				'title' => $row['title'] ?: sprintf( '#%d', $row['id'] ),
			];
		}

		return $forms;
	}
}
