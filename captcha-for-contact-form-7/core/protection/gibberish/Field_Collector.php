<?php

namespace f12_cf7_captcha\core\protection\gibberish;

use f12_cf7_captcha\core\protection\Protection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reduces a submitted payload to the values a person typed, keyed by where they were found.
 *
 * Form plugins do not agree on a shape. CF7, Gravity Forms or Forminator post every field as a
 * top-level key. Elementor posts `form_fields[name]`, WPForms `wpforms[fields][0]`, Formidable
 * `item_meta[12]`, Fluent Forms' name field `names[first_name]`, and Elementor's atomic forms a
 * list of records, `form_fields[0][id]` next to `form_fields[0][value]`.
 *
 * Until 2.15.11 the gibberish module read the top level only and skipped every array. On an
 * Elementor site that meant it scored `referer_title` and nothing else: a form filled with
 * nothing but generated strings passed in `block` mode, and nothing anywhere said so
 * (reported 2026-09-28). This class walks the nesting instead, and it is shared with the
 * status check on the Forms screen, so what that screen reports is what the module scores.
 *
 * ## Lists
 *
 * A top-level list of plain values is a checkbox or multi-select group of a flat form plugin: its
 * values are option labels from the form's own markup, so it is skipped, as it always was. A
 * top-level list of records (Elementor atomic) is walked. A nested list is
 * ambiguous — WPForms numbers its fields from 0, so `wpforms[fields]` *is* a list, and a very
 * different one from Elementor's `form_fields[check][]`. It is walked. Missing a field map costs
 * the whole form, while scoring an option label costs close to nothing: labels are words the
 * site owner wrote, and a field holding a single token needs all three signals to be flagged.
 */
class Field_Collector {

	/** Deepest nesting walked; WPForms' composite fields need three levels. */
	private const MAX_DEPTH = 5;

	/**
	 * Keys that never carry anything a person typed, whatever level they appear at.
	 *
	 * Protection::strip_internal_fields() already removes this plugin's own hidden fields.
	 */
	private const IGNORED_KEYS = [
		'action', 'post_id', 'form_id', 'referer', 'referrer', 'redirect_to', 'submit',
		'et_pb_contact_email_fields', 'g-recaptcha-response', 'h-captcha-response',
		'cf-turnstile-response', 'ajaxurl', 'nonce', 'security', 'locale', 'timezone',
		// Elementor.
		'referer_title', 'queried_id', 'form_name',
		// WPForms.
		'page_title', 'page_url', 'url_referer', 'page_id',
		// Formidable.
		'frm_action', 'form_key', 'item_key',
	];

	/**
	 * Keys that are bookkeeping only *inside* a plugin's own group, e.g. `wpforms[author]`.
	 *
	 * At the top level the same names can be what a person typed: the WordPress comment form
	 * posts the commenter's name as `author`.
	 */
	private const IGNORED_NESTED_KEYS = [ 'id', 'author', 'token', 'analytics' ];

	/**
	 * A machine token: one unbroken run of base64 / JWT characters, long, and with a digit in it.
	 *
	 * Form plugins post encoded state next to the fields — Jetpack a JWT of about 2,000
	 * characters, WPForms `wpforms[analytics]`, Gravity Forms `state_<id>`, all base64 of JSON.
	 * Their letter runs score as generated text, so one such value alone reached the five-word
	 * backstop: every Jetpack submission was rejected in block mode from 2.13.0 on, and every
	 * WPForms one once 2.15.11 started reading nested fields. Matched by shape, because the next
	 * plugin will name its token differently.
	 *
	 * The digit is what keeps spam in: generated words such as `WZesQBrZoVoRmyySRwUBQf` are
	 * letters only and much shorter.
	 */
	private const MACHINE_TOKEN = '/^(?=[^0-9]*[0-9])[A-Za-z0-9+\/=_.\-]{40,}$/';

	/**
	 * Key prefixes of form-plugin bookkeeping.
	 *
	 * `state_` is not cosmetic: Gravity Forms posts `state_<form id>`, a base64 blob whose letter
	 * runs score as generated text. Read as a field, it put one flagged field into every Gravity
	 * submission, so a threshold of two fields was one odd real field away from a block.
	 */
	private const IGNORED_PREFIXES = [
		'gform_', 'state_', 'is_submit_',   // Gravity Forms.
		'frm_',                             // Formidable (nonce, hidden-field list).
	];

	/**
	 * @param array<string, mixed> $post_data The submitted data, as the integration hands it over.
	 *
	 * @return array<string, string> Typed values keyed by path, e.g. `form_fields[message]`.
	 */
	public static function collect( array $post_data ): array {
		$fields = [];

		foreach ( Protection::strip_internal_fields( $post_data ) as $key => $value ) {
			if ( ! is_string( $key ) || self::is_ignored( $key ) ) {
				continue;
			}

			// A checkbox or multi-select group; see the class comment. A list of *records* is not
			// one: Elementor atomic posts its fields as `form_fields[0][value]`.
			if ( is_array( $value ) && self::is_list( $value ) && self::is_flat( $value ) ) {
				continue;
			}

			self::walk( $value, $key, 1, $fields );
		}

		return $fields;
	}

	/**
	 * @param mixed                 $value
	 * @param array<string, string> $fields Collected so far.
	 */
	private static function walk( $value, string $path, int $depth, array &$fields ): void {
		if ( is_scalar( $value ) ) {
			$value = trim( (string) $value );

			if ( $value !== '' && ! preg_match( self::MACHINE_TOKEN, $value ) ) {
				$fields[ $path ] = $value;
			}

			return;
		}

		if ( ! is_array( $value ) || $depth > self::MAX_DEPTH ) {
			return;
		}

		foreach ( $value as $key => $child ) {
			$key = (string) $key;

			if ( ! is_numeric( $key ) && ( self::is_ignored( $key ) || in_array( strtolower( $key ), self::IGNORED_NESTED_KEYS, true ) ) ) {
				continue;
			}

			// A field record (Elementor atomic): the value is the only part the sender typed;
			// id, type, label and options come from the form. Named after its id so the path
			// reads `form_fields[email]` rather than `form_fields[3]`.
			if ( is_array( $child ) && array_key_exists( 'value', $child ) ) {
				$name = self::record_name( $child, $key );
				$typed = $child['value'];

				// A record holding a list is a checkbox group: option labels, not input.
				if ( is_array( $typed ) && self::is_list( $typed ) ) {
					continue;
				}

				self::walk( $typed, $path . '[' . $name . ']', $depth + 1, $fields );
				continue;
			}

			self::walk( $child, $path . '[' . $key . ']', $depth + 1, $fields );
		}
	}

	/**
	 * @param array<mixed> $record
	 */
	private static function record_name( array $record, string $fallback ): string {
		foreach ( [ 'id', 'key', 'name' ] as $candidate ) {
			if ( isset( $record[ $candidate ] ) && is_scalar( $record[ $candidate ] ) && (string) $record[ $candidate ] !== '' ) {
				return (string) $record[ $candidate ];
			}
		}

		return $fallback;
	}

	private static function is_ignored( string $key ): bool {
		if ( $key === '' || $key[0] === '_' ) {
			// Form plugins prefix their own bookkeeping with an underscore.
			return true;
		}

		$key = strtolower( $key );

		if ( in_array( $key, self::IGNORED_KEYS, true ) ) {
			return true;
		}

		foreach ( self::IGNORED_PREFIXES as $prefix ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<mixed> $value
	 */
	private static function is_flat( array $value ): bool {
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * array_is_list() without requiring PHP 8.1.
	 *
	 * @param array<mixed> $value
	 */
	private static function is_list( array $value ): bool {
		return $value === [] || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
