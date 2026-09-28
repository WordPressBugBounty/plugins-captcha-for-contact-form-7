<?php

namespace f12_cf7_captcha\core\protection\gibberish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the site owner whether the plugin actually sees what their visitors type.
 *
 * Shown per integration on the Forms screen. Built after an Elementor site ran gibberish
 * detection in `block` mode for weeks while it scored nothing but `referer_title` — the module
 * worked, the toggles were on, and nothing on any screen could have told the owner otherwise.
 *
 * Two independent answers:
 *
 * - **Self-test** — a sample submission in each form plugin's own shape is run through
 *   {@see Field_Collector}. Needs no traffic, and it is what would have caught that bug in code.
 * - **Last submission** — the field *names* the collector found in the most recent real
 *   submission per integration. Catches what a sample cannot: a form built in a way nobody
 *   anticipated, or a test run from a whitelisted admin account, which is skipped before any
 *   module looks at it and is the most common reason "the protection does nothing".
 *
 * Only names and counts are stored, never a submitted value.
 */
class Field_Detection {

	public const OPTION = 'f12_cf7_captcha_field_detection';

	public const STATUS_OK      = 'ok';
	public const STATUS_WARNING = 'warning';
	public const STATUS_ERROR   = 'error';
	public const STATUS_UNKNOWN = 'unknown';

	/** An unchanged result is rewritten at most this often, to keep a write off every submit. */
	private const REFRESH_SECONDS = 3600;

	/** Field names kept per integration; enough to recognise a form, bounded for the option. */
	private const MAX_NAMES = 25;

	/**
	 * Record what the collector saw in a real submission.
	 *
	 * Never throws: this runs inside the spam check, and a diagnostic must not be able to take a
	 * submission down with it.
	 *
	 * @param array<string, mixed> $post_data
	 */
	public static function record( string $integration_id, array $post_data, bool $whitelisted ): void {
		if ( $integration_id === '' || ! function_exists( 'get_option' ) ) {
			return;
		}

		try {
			$names = array_keys( Field_Collector::collect( $post_data ) );

			$entry = [
				'time'        => time(),
				'whitelisted' => $whitelisted,
				'count'       => count( $names ),
				'fields'      => array_slice( array_map( 'strval', $names ), 0, self::MAX_NAMES ),
			];

			$stored = get_option( self::OPTION, [] );
			$stored = is_array( $stored ) ? $stored : [];

			$previous = $stored[ $integration_id ] ?? null;

			if ( is_array( $previous )
			     && ( $previous['whitelisted'] ?? null ) === $entry['whitelisted']
			     && ( $previous['count'] ?? null ) === $entry['count']
			     && ( $previous['fields'] ?? null ) === $entry['fields']
			     && $entry['time'] - (int) ( $previous['time'] ?? 0 ) < self::REFRESH_SECONDS ) {
				return;
			}

			$stored[ $integration_id ] = $entry;

			update_option( self::OPTION, $stored, false );
		} catch ( \Throwable $e ) {
			// Fail open: a missing status line costs nothing, a failed submission costs a customer.
		}
	}

	/**
	 * Status for one integration, as the Forms screen shows it.
	 *
	 * @return array{status: string, self_test: array<string, mixed>|null, last: array<string, mixed>|null}
	 */
	public static function report( string $integration_id ): array {
		$self_test = self::run_self_test( $integration_id );
		$last      = self::last_submission( $integration_id );

		if ( $self_test !== null && ! $self_test['passed'] ) {
			$status = self::STATUS_ERROR;
		} elseif ( $last !== null && ! $last['whitelisted'] && $last['count'] === 0 ) {
			$status = self::STATUS_ERROR;
		} elseif ( $last !== null && $last['whitelisted'] ) {
			$status = self::STATUS_WARNING;
		} elseif ( $last !== null || $self_test !== null ) {
			$status = self::STATUS_OK;
		} else {
			$status = self::STATUS_UNKNOWN;
		}

		return [
			'status'    => $status,
			'self_test' => $self_test,
			'last'      => $last,
		];
	}

	/**
	 * Run every sample for an integration; null when there is no sample for it.
	 *
	 * @return array{passed: bool, samples: array<int, array<string, mixed>>}|null
	 */
	public static function run_self_test( string $integration_id ): ?array {
		$samples = self::samples()[ $integration_id ] ?? null;

		if ( $samples === null ) {
			return null;
		}

		$results = [];
		$passed  = true;

		foreach ( $samples as $sample ) {
			$found      = array_keys( Field_Collector::collect( $sample['payload'] ) );
			$missing    = array_values( array_diff( $sample['expected'], $found ) );
			$unexpected = array_values( array_intersect( $sample['forbidden'] ?? [], $found ) );
			$ok         = $missing === [] && $unexpected === [];

			$passed    = $passed && $ok;
			$results[] = [
				'label'      => $sample['label'],
				'passed'     => $ok,
				'expected'   => $sample['expected'],
				'missing'    => $missing,
				'unexpected' => $unexpected,
			];
		}

		return [
			'passed'  => $passed,
			'samples' => $results,
		];
	}

	/**
	 * @return array{time: int, whitelisted: bool, count: int, fields: array<int, string>}|null
	 */
	private static function last_submission( string $integration_id ): ?array {
		$stored = get_option( self::OPTION, [] );
		$entry  = is_array( $stored ) ? ( $stored[ $integration_id ] ?? null ) : null;

		if ( ! is_array( $entry ) ) {
			return null;
		}

		return [
			'time'        => (int) ( $entry['time'] ?? 0 ),
			'whitelisted' => (bool) ( $entry['whitelisted'] ?? false ),
			'count'       => (int) ( $entry['count'] ?? 0 ),
			'fields'      => array_values( array_filter( (array) ( $entry['fields'] ?? [] ), 'is_string' ) ),
		];
	}

	/**
	 * One sample submission per form plugin, in the shape the integration hands to the check.
	 *
	 * `expected` must be found, `forbidden` must not be — the latter holds bookkeeping that once
	 * leaked into the scoring. Values are illustrative; only the shape matters.
	 *
	 * @return array<string, array<int, array{label: string, payload: array<string, mixed>, expected: array<int, string>, forbidden?: array<int, string>}>>
	 */
	private static function samples(): array {
		return [
			'cf7'          => [ [
				'label'    => 'Contact Form 7',
				'payload'  => [
					'_wpcf7'         => '5',
					'_wpcf7_version' => '6.0',
					'your-name'      => 'Anna Beispiel',
					'your-email'     => 'anna@example.com',
					'your-message'   => 'Bitte rufen Sie mich zurück.',
					'checkbox-1'     => [ 'Newsletter' ],
				],
				'expected' => [ 'your-name', 'your-email', 'your-message' ],
			] ],
			'elementor'    => [
				[
					'label'     => 'Elementor Form',
					'payload'   => [
						'action'        => 'elementor_pro_forms_send_form',
						'post_id'       => '12',
						'form_id'       => '3f1a2b4',
						'referer_title' => 'Kontakt',
						'queried_id'    => '12',
						'form_fields'   => [
							'name'    => 'Anna Beispiel',
							'email'   => 'anna@example.com',
							'message' => 'Bitte rufen Sie mich zurück.',
						],
					],
					'expected'  => [ 'form_fields[name]', 'form_fields[email]', 'form_fields[message]' ],
					'forbidden' => [ 'referer_title' ],
				],
				[
					'label'    => 'Elementor Atomic Form',
					'payload'  => [
						'action'      => 'elementor_pro_atomic_forms_send_form',
						'_nonce'      => 'a1b2c3d4e5',
						'post_id'     => '12',
						'form_id'     => '7c9d0e1',
						'form_fields' => [
							[ 'id' => 'name', 'type' => 'text', 'label' => 'Name', 'value' => 'Anna Beispiel' ],
							[ 'id' => 'email', 'type' => 'email', 'label' => 'E-Mail', 'value' => 'anna@example.com' ],
							[ 'id' => 'message', 'type' => 'textarea', 'label' => 'Nachricht', 'value' => 'Bitte rufen Sie mich zurück.' ],
						],
					],
					'expected' => [ 'form_fields[name]', 'form_fields[email]', 'form_fields[message]' ],
				],
			],
			'wpforms'      => [ [
				'label'     => 'WPForms',
				'payload'   => [
					'wpforms'    => [
						'fields'  => [
							0 => [ 'first' => 'Anna', 'last' => 'Beispiel' ],
							1 => 'anna@example.com',
							2 => 'Bitte rufen Sie mich zurück.',
						],
						'id'        => '12',
						'author'    => '1',
						'post_id'   => '5',
						'token'     => 'd41d8cd98f00b204e9800998ecf8427e',
						'analytics' => 'eyJzZXNzaW9uX2lkIjoiNTY2MTI5MmYtNTNkZi00YjE3LWI0ZjMtOWU0ZDk2ZTc2OGUxIiwicGFnZV92aWV3cyI6M30=',
					],
					'page_title' => 'Kontakt',
					'page_url'   => 'https://example.com/kontakt/',
				],
				'expected'  => [ 'wpforms[fields][0][first]', 'wpforms[fields][0][last]', 'wpforms[fields][1]', 'wpforms[fields][2]' ],
				'forbidden' => [ 'wpforms[token]', 'wpforms[analytics]' ],
			] ],
			'jetpack'      => [ [
				'label'     => 'Jetpack Forms',
				'payload'   => [
					'jetpack_contact_form_jwt' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJqZXRwYWNrIiwiZm9ybSI6NDksImV4cCI6MTc5MDU3MzM3OH0.Qm9vN2x3a2ZxS2xYZ0RqV1pTWkF5dFZ4',
					'g49-name'                 => 'Anna Beispiel',
					'g49-email'                => 'anna@example.com',
					'g49-nachricht'            => 'Bitte rufen Sie mich zurück.',
					'contact-form-id'          => '49',
				],
				'expected'  => [ 'g49-name', 'g49-email', 'g49-nachricht' ],
				'forbidden' => [ 'jetpack_contact_form_jwt' ],
			] ],
			'wordpress_comments' => [ [
				'label'    => 'WordPress comments',
				'payload'  => [
					'comment'         => 'Danke für den hilfreichen Beitrag.',
					'author'          => 'Anna Beispiel',
					'email'           => 'anna@example.com',
					'comment_post_ID' => '5',
				],
				'expected' => [ 'comment', 'author', 'email' ],
			] ],
			'formidable'   => [ [
				'label'     => 'Formidable Forms',
				'payload'   => [
					'frm_action'         => 'create',
					'form_id'            => '2',
					'form_key'           => 'contact-form',
					'item_meta'          => [
						0 => '',
						7 => 'Anna Beispiel',
						8 => 'anna@example.com',
						9 => 'Bitte rufen Sie mich zurück.',
					],
					'frm_submit_entry_2' => 'a1b2c3d4e5',
					'_wp_http_referer'   => '/kontakt/',
				],
				'expected'  => [ 'item_meta[7]', 'item_meta[8]', 'item_meta[9]' ],
				'forbidden' => [ 'frm_submit_entry_2' ],
			] ],
			'fluentform'   => [ [
				'label'    => 'Fluent Forms',
				'payload'  => [
					'__fluent_form_embded_post_id'  => '5',
					'_fluentform_3_fluentformnonce' => 'a1b2c3d4e5',
					'names'                         => [ 'first_name' => 'Anna', 'last_name' => 'Beispiel' ],
					'email'                         => 'anna@example.com',
					'message'                       => 'Bitte rufen Sie mich zurück.',
				],
				'expected' => [ 'names[first_name]', 'names[last_name]', 'email', 'message' ],
			] ],
			'gravityforms' => [ [
				'label'     => 'Gravity Forms',
				'payload'   => [
					'input_1'                    => 'Anna Beispiel',
					'input_2'                    => 'anna@example.com',
					'input_3'                    => 'Bitte rufen Sie mich zurück.',
					'is_submit_1'                => '1',
					'gform_submit'               => '1',
					'state_1'                    => 'WyJbXSIsIjk2N2ZmYjM2ZTNhODI0NmQzNmM5ZTk2NTMyZGYzNGNjIl0=',
					'gform_target_page_number_1' => '0',
				],
				'expected'  => [ 'input_1', 'input_2', 'input_3' ],
				'forbidden' => [ 'state_1' ],
			] ],
			'forminator'   => [ [
				'label'    => 'Forminator',
				'payload'  => [
					'name-1'           => 'Anna Beispiel',
					'email-1'          => 'anna@example.com',
					'textarea-1'       => 'Bitte rufen Sie mich zurück.',
					'form_id'          => '9',
					'action'           => 'forminator_submit_form_custom-forms',
					'_wp_http_referer' => '/kontakt/',
				],
				'expected' => [ 'name-1', 'email-1', 'textarea-1' ],
			] ],
		];
	}
}
