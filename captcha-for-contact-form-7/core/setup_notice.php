<?php
/**
 * Warns an administrator when the plugin is active but not protecting anything.
 *
 * Every integration is gated behind its own `protection_<id>_enable` flag and a
 * fresh install seeds none of them, so activating the plugin leaves every form
 * wide open while the plugin list happily reports "active". The settings that
 * look protective (captcha, JS, browser) only decide *how* a form is protected,
 * never *whether* an integration is hooked at all — see
 * BaseController::is_enabled().
 *
 * Deliberately no auto-enable: switching integrations on behind the user's back
 * would arm things like the WordPress login form without anyone asking, and a
 * fault there locks people out of their own site.
 */

add_action( 'admin_notices', 'f12_cf7_captcha_maybe_show_setup_notice' );
add_action( 'admin_init', 'f12_cf7_captcha_handle_setup_notice_dismiss' );

/**
 * Collect which detected integrations are switched off, and whether any is on.
 *
 * Mirrors the detection in RestController::handle_discover_forms() so this notice
 * and the Forms screen can never disagree about what is protected.
 *
 * @return array{disabled: string[], any_enabled: bool}
 */
function f12_cf7_captcha_get_setup_notice_state(): array {
	$state = [ 'disabled' => [], 'any_enabled' => false ];

	if ( ! class_exists( '\f12_cf7_captcha\CF7Captcha' ) ) {
		return $state;
	}

	try {
		$controller    = \f12_cf7_captcha\CF7Captcha::get_instance();
		$compatibility = $controller->get_module( 'compatibility' );
		$components    = $compatibility->get_components();
	} catch ( \Throwable $e ) {
		// A notice is never worth breaking wp-admin over.
		return $state;
	}

	foreach ( $components as $component ) {
		if ( ! isset( $component['object'] ) ) {
			continue;
		}

		$object = $component['object'];

		// get_components() also hands back class-strings for controllers that were
		// never instantiated; only a real controller can answer any of this.
		if ( ! $object instanceof \f12_cf7_captcha\core\BaseController ) {
			continue;
		}

		try {
			if ( ! $object->is_installed() ) {
				continue;
			}
		} catch ( \Throwable $e ) {
			continue;
		}

		$settings_key = $object->get_settings_key();
		if ( empty( $settings_key ) ) {
			continue;
		}

		$raw = $controller->get_settings( $settings_key, 'global' );
		// Unset means off — the same coercion BaseController::is_enabled() applies.
		$enabled = ( $raw === '' || $raw === null ) ? false : ( (int) $raw === 1 );

		if ( $enabled ) {
			$state['any_enabled'] = true;
		} else {
			$state['disabled'][] = $object->get_name();
		}
	}

	return $state;
}

/**
 * Render the notice when the site is detectably unprotected.
 */
function f12_cf7_captcha_maybe_show_setup_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// "Not now" snoozes rather than dismisses for good: the site is still
	// unprotected afterwards, so a permanent hide would quietly bury the one
	// thing the administrator needs to act on.
	$snoozed_until = (int) get_option( 'f12_cf7_captcha_setup_notice_snoozed_until', 0 );
	if ( $snoozed_until > 0 && time() < $snoozed_until ) {
		return;
	}

	$state = f12_cf7_captcha_get_setup_notice_state();

	// Only the "nothing at all is protected" case is worth interrupting someone
	// for. Once a single integration is on, the remaining ones are a deliberate
	// choice and nagging about them would just train people to ignore notices.
	if ( $state['any_enabled'] || empty( $state['disabled'] ) ) {
		return;
	}

	$forms_url = admin_url( 'admin.php?page=silentshield-forms' );

	?>
	<div class="notice notice-warning is-dismissible f12-cf7-captcha-setup-notice">
		<p>
			<?php echo wp_kses(
				__( '<strong>SilentShield is active but not protecting any form yet.</strong> Every form plugin has to be switched on individually.', 'captcha-for-contact-form-7' ),
				[ 'strong' => [] ]
			); ?>
		</p>
		<p>
			<?php printf(
				/* translators: %s: comma-separated list of detected form plugins */
				esc_html__( 'Detected on this site: %s', 'captcha-for-contact-form-7' ),
				esc_html( implode( ', ', $state['disabled'] ) )
			); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( $forms_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Choose what to protect', 'captcha-for-contact-form-7' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'f12_cf7_captcha_setup_dismiss', '1' ) ); ?>" class="button">
				<?php esc_html_e( 'Not now', 'captcha-for-contact-form-7' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Handle the "Not now" link — snoozes the notice for 30 days.
 */
function f12_cf7_captcha_handle_setup_notice_dismiss() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sets a display-only flag, changes no protection state.
	if ( isset( $_GET['f12_cf7_captcha_setup_dismiss'] ) && current_user_can( 'manage_options' ) ) {
		update_option( 'f12_cf7_captcha_setup_notice_snoozed_until', time() + ( DAY_IN_SECONDS * 30 ) );
	}
}
