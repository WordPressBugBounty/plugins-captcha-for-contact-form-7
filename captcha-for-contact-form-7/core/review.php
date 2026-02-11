<?php
add_action( 'admin_notices', 'f12_cf7_captcha_maybe_show_review_notice' );
add_action( 'admin_init', 'f12_cf7_captcha_handle_review_actions' );

/**
 * Checks whether the review notice should be displayed.
 */
function f12_cf7_captcha_maybe_show_review_notice() {
	// Only admins see the notice
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$installed_at  = (int) get_option( 'f12_cf7_captcha_installed_at', time() );
	$spam_counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );
	$spam_blocked  = isset( $spam_counters['checks_spam'] ) ? (int) $spam_counters['checks_spam'] : 0;

	$dismissed     = get_option( 'f12_cf7_captcha_review_dismissed', false );
	$remind_later  = (int) get_option( 'f12_cf7_captcha_review_remind_later', 0 );
	$remind_count  = (int) get_option( 'f12_cf7_captcha_review_remind_count', 0 );

	// Conditions:
	// - installed for at least 10 days
	// - at least 20 spam attempts blocked
	if ( ( time() - $installed_at ) < DAY_IN_SECONDS * 10 ) {
		return;
	}
	if ( $spam_blocked < 20 ) {
		return;
	}

	// Already permanently dismissed?
	if ( $dismissed ) {
		return;
	}

	// Reminder delay active?
	if ( $remind_later > 0 && ( time() < $remind_later ) ) {
		return;
	}

	// Maximum 2 repetitions
	if ( $remind_count >= 2 ) {
		return;
	}

	?>
	<div class="notice notice-info is-dismissible f12-cf7-captcha-review-notice">
		<p>
			<?php printf(
				wp_kses(
					__( '<strong>SilentShield – Captcha & Anti-Spam for WordPress</strong> has already blocked <strong>%d spam attempts</strong>. Would you help us with a quick review? ❤️', 'captcha-for-contact-form-7' ),
					array( 'strong' => array() )
				),
				intval( $spam_blocked )
			); ?>
		</p>
		<p>
			<a href="https://wordpress.org/support/plugin/captcha-for-contact-form-7/reviews/#new-post"
			   target="_blank"
			   class="button button-primary">
				<?php esc_html_e( 'Leave a review now', 'captcha-for-contact-form-7' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'f12_cf7_captcha_review_remind', '1' ) ); ?>"
			   class="button">
				<?php esc_html_e( 'Remind me later', 'captcha-for-contact-form-7' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'f12_cf7_captcha_review_dismiss', '1' ) ); ?>"
			   class="button">
				<?php esc_html_e( 'Don\'t ask again', 'captcha-for-contact-form-7' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Processes clicks on the review notice links.
 */
function f12_cf7_captcha_handle_review_actions() {
	if ( isset( $_GET['f12_cf7_captcha_review_dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple dismiss action, no data modification beyond a flag.
		update_option( 'f12_cf7_captcha_review_dismissed', true );
	}

	if ( isset( $_GET['f12_cf7_captcha_review_remind'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple remind-later action, no sensitive data modification.
		$remind_count = (int) get_option( 'f12_cf7_captcha_review_remind_count', 0 );
		update_option( 'f12_cf7_captcha_review_remind_later', time() + DAY_IN_SECONDS * 7 ); // Wait 7 days
		update_option( 'f12_cf7_captcha_review_remind_count', $remind_count + 1 );
	}
}
