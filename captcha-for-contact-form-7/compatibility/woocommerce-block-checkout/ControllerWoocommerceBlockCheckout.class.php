<?php

namespace f12_cf7_captcha\compatibility;

use f12_cf7_captcha\core\BaseController;
use f12_cf7_captcha\core\protection\javascript\Javascript_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Block Checkout.
 *
 * A separate integration from `woocommerce_checkout`, not an extension of it, because the two
 * checkouts share no seam whatsoever. `ControllerWoocommerceCheckout` hangs on
 * `woocommerce_review_order_before_submit` and `woocommerce_after_checkout_validation`, and the
 * block checkout fires neither: it is React, it places the order through the Store API, and until
 * this integration existed an order placed there went through completely untouched — with every
 * protection switched on and the settings screen reporting WooCommerce as protected. WooCommerce
 * has defaulted new installs to the block checkout since 8.3, so that gap was the normal case for
 * a modern shop rather than an edge one.
 *
 * Three problems had to be solved, and each has its own countermeasure:
 *
 * **1. The request never reached us.** `Whitelist_Validator` skipped all of `/wc/store/`, so even
 * a validation hook would have been short-circuited. The checkout route is now excluded from that
 * skip (see `Whitelist_Validator::is_store_api_checkout_route()`); the rest of the Store API stays
 * whitelisted, because a cart update carries none of our fields and would be read as spam.
 *
 * **2. There is no form to put fields in.** The block checkout builds its own JSON payload, so
 * hidden inputs in the markup would never be submitted. The Store API's supported answer is
 * `woocommerce_store_api_register_endpoint_data()`, the same mechanism WooCommerce core uses for
 * order attribution. Only *registered* property names survive the schema's recursive sanitiser,
 * which rules out sending our fields under their own names — they rotate per render by design.
 * They therefore travel JSON-encoded inside one fixed property, {@see self::PAYLOAD_PROPERTY}.
 *
 * **3. `$_POST` is empty.** The body is JSON, so `Javascript_Validator` — the one module that
 * snapshots the request in its constructor rather than taking the data as an argument — saw no
 * token and two zero timestamps, and would have rejected every visitor as a bot. It is re-fed
 * through `Javascript_Validator::hydrate_from_request()` before the check runs.
 *
 * Rejection is a `RouteException`, which the route turns into a 4xx the shopper actually sees.
 * Nothing is written to the order first: this runs on
 * `woocommerce_store_api_checkout_update_order_from_request`, i.e. before payment is attempted.
 */
class ControllerWoocommerceBlockCheckout extends BaseController {

	protected string $id = 'woocommerce_block_checkout';
	protected string $settings_key = 'protection_woocommerce_block_checkout_enable';

	/**
	 * Namespace under `extensions` in the Store API payload. Shared with the browser module.
	 */
	public const EXTENSION_NAMESPACE = 'f12-cf7-captcha';

	/**
	 * The single registered property our fields travel in, JSON-encoded.
	 *
	 * A property per field is impossible here. `AbstractSchema::get_recursive_sanitize_callback()`
	 * rebuilds the extensions payload from the registered property names alone and drops
	 * everything else, and our field names are HMAC-derived per render (see `Field_Token`) so
	 * there is no name to register. One fixed envelope keeps both properties: the names still
	 * rotate, and nothing is silently dropped on the way in.
	 */
	public const PAYLOAD_PROPERTY = 'fields';

	/**
	 * Upper bound on the decoded payload, in fields.
	 *
	 * The envelope is attacker-controlled — it is a JSON string from the browser — and every key
	 * in it is walked by each protection module. A cap keeps a hostile payload from turning one
	 * checkout request into unbounded work. Real submissions carry well under ten.
	 */
	private const MAX_PAYLOAD_FIELDS = 50;

	/**
	 * Order fields handed to the content-based modules.
	 *
	 * Gibberish detection and the blacklist rules judge what the visitor wrote, and on the block
	 * checkout that text is on the order object rather than in the payload we control. Address
	 * lines and the customer note are the parts a spammer actually fills; totals and IDs are not
	 * text and are left out.
	 */
	private const ORDER_TEXT_FIELDS = [
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_email',
		'billing_phone',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
	];

	protected array $hooks = [
		[ 'type' => 'action', 'hook' => 'init', 'method' => 'wp_register_store_api_data' ],
		[ 'type' => 'action', 'hook' => 'wp_enqueue_scripts', 'method' => 'wp_add_spam_protection', 'priority' => 20 ],
		[ 'type' => 'action', 'hook' => 'woocommerce_store_api_checkout_update_order_from_request', 'method' => 'wp_is_spam', 'priority' => 10, 'args' => 2 ],
	];

	public function get_name(): string {
		return __( 'WooCommerce Block Checkout', 'captcha-for-contact-form-7' );
	}

	public function is_installed(): bool {
		return class_exists( 'WooCommerce' )
		       && function_exists( 'woocommerce_store_api_register_endpoint_data' )
		       && class_exists( \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::class );
	}

	/**
	 * Declare our slot in the checkout endpoint's `extensions` payload.
	 *
	 * On `init` rather than on `woocommerce_blocks_loaded`, matching what WooCommerce core does
	 * for order attribution. Both run before `rest_api_init` builds the route schema, and `init`
	 * cannot have fired already by the time this module is wired up on `after_setup_theme`.
	 *
	 * @param mixed ...$args Unused.
	 *
	 * @return void
	 */
	public function wp_register_store_api_data( ...$args ): void {
		$result = woocommerce_store_api_register_endpoint_data( [
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace'       => self::EXTENSION_NAMESPACE,
			'schema_callback' => [ $this, 'get_extension_schema' ],
		] );

		if ( is_wp_error( $result ) ) {
			$this->get_logger()->error( 'Store API endpoint data could not be registered.', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $result->get_error_message(),
			] );
		}
	}

	/**
	 * Schema for our one envelope property.
	 *
	 * `context` is deliberately empty: this travels inbound only, and there is no reason for the
	 * captcha payload to be echoed back in any checkout response.
	 *
	 * @return array<string, mixed>
	 */
	public function get_extension_schema(): array {
		return [
			self::PAYLOAD_PROPERTY => [
				'description' => __( 'Spam protection fields, JSON encoded.', 'captcha-for-contact-form-7' ),
				'type'        => [ 'string', 'null' ],
				'context'     => [],
				'arg_options' => [
					'validate_callback' => static function ( $value ) {
						return is_string( $value ) || null === $value;
					},
					// No sanitize_callback: the value is a JSON envelope, and the individual
					// fields are sanitised by the modules that read them. Running
					// sanitize_text_field() over the encoded string would corrupt it.
					'sanitize_callback' => static function ( $value ) {
						return is_string( $value ) ? $value : null;
					},
				],
			],
		];
	}

	/**
	 * Hand the rendered captcha to the browser module.
	 *
	 * The block checkout renders client-side, so there is no server-side seam to echo into — the
	 * markup is produced here and mounted by `WooCommerceBlockCheckout.js` into a WooCommerce slot
	 * fill. Rendering it here rather than fetching it over REST keeps one token per page load, so
	 * the honeypot and the timing fields agree on their rotated names.
	 *
	 * @param mixed ...$args Unused.
	 *
	 * @return void
	 */
	public function wp_add_spam_protection( ...$args ) {
		if ( ! $this->is_block_checkout_page() ) {
			return;
		}

		// The plugin's own bundle carries the module; if asset loading decided this page needs
		// nothing, there is no script to attach to and nothing to do.
		if ( ! wp_script_is( 'f12-cf7-captcha-reload', 'enqueued' ) ) {
			$this->get_logger()->debug( 'Block checkout: main bundle not enqueued, skipping.', [
				'plugin' => 'f12-cf7-captcha',
			] );

			return;
		}

		// The globals the module needs — `wc.blocksCheckout` for the slot fill, `wp.plugins` to
		// register it. Both are already on a block checkout page via the checkout block itself;
		// requesting them explicitly means the module does not depend on that staying true.
		wp_enqueue_script( 'wc-blocks-checkout' );
		wp_enqueue_script( 'wp-plugins' );
		wp_enqueue_script( 'wp-element' );

		wp_localize_script( 'f12-cf7-captcha-reload', 'f12_cf7_captcha_blocks', [
			'namespace' => self::EXTENSION_NAMESPACE,
			'property'  => self::PAYLOAD_PROPERTY,
			'html'      => $this->get_captcha_html(),
		] );
	}

	/**
	 * Whether the page being rendered can show a block checkout.
	 *
	 * Two questions, because neither alone is enough. `is_checkout()` knows the page WooCommerce
	 * was configured with — which is the normal case but says nothing about which checkout it
	 * renders — while `has_block()` finds the block on any page a merchant put it on, including
	 * ones WooCommerce has never heard of. Being generous here costs a localised string on a page
	 * that turns out to use the classic checkout; being strict costs the protection entirely.
	 *
	 * The order-received endpoint is excluded: it lives under the checkout page, and a captcha
	 * on the thank-you screen would be pure noise.
	 *
	 * @return bool
	 */
	private function is_block_checkout_page(): bool {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return false;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		$post = get_post();

		return $post instanceof \WP_Post
		       && function_exists( 'has_block' )
		       && has_block( 'woocommerce/checkout', $post );
	}

	/**
	 * Judge the submission, and abort the order when it does not pass.
	 *
	 * @param mixed ...$args \WC_Order $order, \WP_REST_Request $request.
	 *
	 * @return void
	 * @throws \Exception A RouteException where WooCommerce provides one, so the shopper is told
	 *                    what went wrong instead of seeing a generic failure.
	 */
	public function wp_is_spam( ...$args ): void {
		$order   = $args[0] ?? null;
		$request = $args[1] ?? null;

		if ( ! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request ) {
			return;
		}

		$submitted = $this->extract_payload( $request );

		// Feed the one module that reads the request itself rather than the data it is given.
		// Without this the JS timing check sees nothing and fails every visitor.
		try {
			/** @var Javascript_Validator $javascript */
			$javascript = $this->get_protection()->get_module( 'javascript-validator' );
			$javascript->hydrate_from_request( $submitted );
		} catch ( \Exception $e ) {
			// The module is absent when the SilentShield API has taken over local protection.
			$this->get_logger()->debug( 'Block checkout: no javascript-validator to hydrate.', [
				'plugin' => 'f12-cf7-captcha',
				'error'  => $e->getMessage(),
			] );
		}

		$message = $this->check_spam(
			array_merge( $this->collect_order_text( $order ), $submitted ),
			(string) $order->get_id()
		);

		if ( $message === null ) {
			return;
		}

		$this->get_logger()->warning( 'Block checkout submission rejected.', [
			'plugin'   => 'f12-cf7-captcha',
			'order_id' => $order->get_id(),
			'reason'   => $message,
		] );

		$this->throw_rejection( $this->format_spam_message( $message ) );
	}

	/**
	 * Abort the checkout with a message the shopper can read.
	 *
	 * `RouteException` is what the Store API route catches to produce a 4xx with our wording. The
	 * plain-Exception fallback exists so a WooCommerce that no longer ships that class still
	 * *stops* the order — the route catches `\Exception` too and answers 500. An ugly failure
	 * beats a silent pass: this method is only ever reached for a submission already judged spam.
	 *
	 * @param string $message The message shown to the shopper.
	 *
	 * @return void
	 * @throws \Exception Always.
	 */
	private function throw_rejection( string $message ): void {
		$route_exception = \Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class;

		if ( class_exists( $route_exception ) ) {
			throw new $route_exception( 'f12_cf7_captcha_spam_detected', $message, 400 );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not HTML output
		throw new \Exception( $message );
	}

	/**
	 * Recover our fields from the request's `extensions` payload.
	 *
	 * @param \WP_REST_Request $request The checkout request.
	 *
	 * @return array<string, string> The submitted fields, empty when nothing usable arrived.
	 */
	private function extract_payload( \WP_REST_Request $request ): array {
		$extensions = $request->get_param( 'extensions' );

		if ( ! is_array( $extensions ) || ! isset( $extensions[ self::EXTENSION_NAMESPACE ] ) ) {
			return [];
		}

		$envelope = $extensions[ self::EXTENSION_NAMESPACE ];
		$encoded  = is_array( $envelope ) ? ( $envelope[ self::PAYLOAD_PROPERTY ] ?? null ) : null;

		if ( ! is_string( $encoded ) || $encoded === '' ) {
			return [];
		}

		$decoded = json_decode( $encoded, true );

		if ( ! is_array( $decoded ) ) {
			$this->get_logger()->warning( 'Block checkout: protection payload was not valid JSON.', [
				'plugin' => 'f12-cf7-captcha',
			] );

			return [];
		}

		$fields = [];

		foreach ( $decoded as $key => $value ) {
			if ( count( $fields ) >= self::MAX_PAYLOAD_FIELDS ) {
				$this->get_logger()->warning( 'Block checkout: protection payload truncated.', [
					'plugin' => 'f12-cf7-captcha',
					'limit'  => self::MAX_PAYLOAD_FIELDS,
				] );
				break;
			}

			// Scalars only. A nested structure is not something any of our fields produces, and
			// letting one through would hand the modules a shape they do not expect.
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$fields[ $key ] = (string) $value;
			}
		}

		return $fields;
	}

	/**
	 * The order's text fields, for the modules that judge content rather than mechanics.
	 *
	 * @param \WC_Order $order The draft order.
	 *
	 * @return array<string, string>
	 */
	private function collect_order_text( \WC_Order $order ): array {
		$data = [];

		foreach ( self::ORDER_TEXT_FIELDS as $field ) {
			$getter = 'get_' . $field;

			if ( ! method_exists( $order, $getter ) ) {
				continue;
			}

			$value = (string) $order->{$getter}();

			if ( $value !== '' ) {
				$data[ $field ] = $value;
			}
		}

		$note = (string) $order->get_customer_note();

		if ( $note !== '' ) {
			$data['customer_note'] = $note;
		}

		return $data;
	}
}
