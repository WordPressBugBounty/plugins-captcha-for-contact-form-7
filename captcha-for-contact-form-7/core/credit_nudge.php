<?php
/**
 * The one-time ask: would you show a credit link under your forms?
 *
 * Asked once, after the plugin has actually done something, and never again either way.
 *
 * The shape of this notice is deliberate, because the tempting version is the one that gets a
 * plugin thrown out of the directory. Guideline 10 requires the choice to be made through
 * "clearly stated and understandable choices, not buried in the terms of use or
 * documentation" — so: both answers are ordinary buttons of the same weight, the wording says
 * plainly what will appear and where, and the preview shows the exact markup rather than
 * describing it. No pre-selection, no dark pattern, no second asking.
 *
 * What it does lean on is legitimate and true: the plugin has blocked a real, countable number
 * of spam submissions for this site before it asks for anything, and it says why the link
 * helps. That is the whole of the persuasion, and it is the only part worth having — a site
 * owner tricked into it removes it the moment they notice, and leaves a one-star review on the
 * way out.
 */

namespace f12_cf7_captcha;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spam blocks required before the question is asked at all.
 *
 * High enough that the plugin has demonstrably earned the ask, low enough that an active site
 * reaches it in a sensible time.
 */
const CREDIT_NUDGE_THRESHOLD = 100;

/**
 * Option holding the answer. Absent = not asked yet, 'accepted' / 'declined' = done.
 */
const CREDIT_NUDGE_ANSWER_OPTION = 'f12_cf7_captcha_credit_nudge_answer';

/**
 * How many submissions this install has turned away.
 *
 * The same counter the review notice uses, so the two never tell the site owner different
 * numbers for the same thing.
 */
function get_blocked_count(): int {
	$counters = get_option( 'f12_cf7_captcha_telemetry_counters', [] );

	return ( is_array( $counters ) && isset( $counters['checks_spam'] ) )
		? (int) $counters['checks_spam']
		: 0;
}

/**
 * Whether to ask on this screen.
 */
function should_show_credit_nudge(): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	// Answered once, in either direction: never ask again.
	if ( get_option( CREDIT_NUDGE_ANSWER_OPTION, '' ) !== '' ) {
		return false;
	}

	// Already on — nothing to ask for. Covers the site owner who found the setting themselves.
	if ( is_credit_enabled() ) {
		return false;
	}

	if ( get_blocked_count() < CREDIT_NUDGE_THRESHOLD ) {
		return false;
	}

	// Never alongside the review notice. Two requests in one screen is the nagging that earns
	// one-star reviews, and the review is the more valuable of the two.
	if ( ! empty( $GLOBALS['f12_cf7_captcha_review_notice_shown'] ) ) {
		return false;
	}

	return true;
}

/**
 * Render the notice.
 */
function render_credit_nudge(): void {
	if ( ! should_show_credit_nudge() ) {
		return;
	}

	$blocked = get_blocked_count();
	$accept  = wp_nonce_url( add_query_arg( 'f12_ss_credit', 'yes' ), 'f12_ss_credit' );
	$decline = wp_nonce_url( add_query_arg( 'f12_ss_credit', 'no' ), 'f12_ss_credit' );
	?>
	<div class="notice notice-info f12-ss-credit-nudge">
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: number of blocked spam submissions, already formatted. */
					__( 'SilentShield has blocked <strong>%s spam submissions</strong> on this site so far.', 'captcha-for-contact-form-7' ),
					[ 'strong' => [] ]
				),
				esc_html( number_format_i18n( $blocked ) )
			);
			?>
			<?php esc_html_e( 'Would you show a small credit under your forms? It helps other site owners find the plugin, and it is what keeps it free.', 'captcha-for-contact-form-7' ); ?>
		</p>

		<p style="margin:0 0 4px;"><?php esc_html_e( 'This is exactly what would appear, directly below the captcha:', 'captcha-for-contact-form-7' ); ?></p>
		<div style="padding:8px 12px;background:#fff;border:1px solid #dcdcde;border-radius:3px;display:inline-block;margin-bottom:8px;">
			<?php
			// Rendered, not described: the real objection is "will this clutter my site", and
			// only the actual thing answers it.
			echo wp_kses(
				get_credit_markup( true ),
				[
					'p' => [ 'class' => true ],
					'a' => [ 'href' => true, 'target' => true, 'rel' => true ],
				]
			);
			?>
		</div>

		<p>
			<a href="<?php echo esc_url( $accept ); ?>" class="button button-primary">
				<?php esc_html_e( 'Yes, show the link', 'captcha-for-contact-form-7' ); ?>
			</a>
			<a href="<?php echo esc_url( $decline ); ?>" class="button">
				<?php esc_html_e( 'No thanks', 'captcha-for-contact-form-7' ); ?>
			</a>
			<span style="margin-left:8px;color:#646970;">
				<?php esc_html_e( 'Asked once. You can change it any time under Advanced Settings.', 'captcha-for-contact-form-7' ); ?>
			</span>
		</p>
	</div>
	<?php
}

/**
 * Record the answer.
 *
 * Nonce-checked and capability-checked: unlike a dismiss flag, "yes" writes a setting that
 * changes what every visitor sees, so a stray link must not be able to trigger it.
 */
function handle_credit_nudge_answer(): void {
	if ( ! isset( $_GET['f12_ss_credit'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'f12_ss_credit' );

	$answer = sanitize_text_field( wp_unslash( $_GET['f12_ss_credit'] ) );

	if ( $answer === 'yes' ) {
		$settings = get_option( 'f12-cf7-captcha-settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		if ( ! isset( $settings['global'] ) || ! is_array( $settings['global'] ) ) {
			$settings['global'] = [];
		}
		$settings['global'][ CREDIT_SETTING_KEY ] = 1;
		update_option( 'f12-cf7-captcha-settings', $settings );

		update_option( CREDIT_NUDGE_ANSWER_OPTION, 'accepted' );
	} else {
		update_option( CREDIT_NUDGE_ANSWER_OPTION, 'declined' );
	}

	// Drop the parameters so a refresh does not replay the action.
	wp_safe_redirect( remove_query_arg( [ 'f12_ss_credit', '_wpnonce' ] ) );
	exit;
}

// Priority 20: after the review notice, so the guard in should_show_credit_nudge() can see
// whether that one already claimed this screen.
add_action( 'admin_notices', __NAMESPACE__ . '\render_credit_nudge', 20 );
add_action( 'admin_init', __NAMESPACE__ . '\handle_credit_nudge_answer' );
