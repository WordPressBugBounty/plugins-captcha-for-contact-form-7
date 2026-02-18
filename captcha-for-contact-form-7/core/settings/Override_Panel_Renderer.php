<?php

namespace f12_cf7_captcha\core\settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared renderer for slide-in override panels.
 *
 * Used by UI_Extended (integration panels) and UI_Forms (form panels).
 * All fields are rendered disabled; JS enables them when the panel opens.
 */
class Override_Panel_Renderer {

	/**
	 * Get setting groups for display.
	 *
	 * @return array
	 */
	public static function get_setting_groups(): array {
		return [
			__( 'Captcha', 'captcha-for-contact-form-7' ) => [
				'protection_captcha_enable'      => __( 'Captcha Enabled', 'captcha-for-contact-form-7' ),
				'protection_captcha_method'      => __( 'Captcha Method', 'captcha-for-contact-form-7' ),
				'protection_captcha_template'    => __( 'Captcha Template', 'captcha-for-contact-form-7' ),
				'protection_captcha_label'       => __( 'Captcha Label', 'captcha-for-contact-form-7' ),
				'protection_captcha_placeholder' => __( 'Captcha Placeholder', 'captcha-for-contact-form-7' ),
				'protection_captcha_reload_icon' => __( 'Reload Icon Style', 'captcha-for-contact-form-7' ),
			],
			__( 'Timer', 'captcha-for-contact-form-7' ) => [
				'protection_time_enable' => __( 'Timer Protection', 'captcha-for-contact-form-7' ),
				'protection_time_ms'     => __( 'Minimum Time (ms)', 'captcha-for-contact-form-7' ),
			],
			__( 'Validators', 'captcha-for-contact-form-7' ) => [
				'protection_javascript_enable'          => __( 'JavaScript Protection', 'captcha-for-contact-form-7' ),
				'protection_browser_enable'             => __( 'Browser Protection', 'captcha-for-contact-form-7' ),
				'protection_multiple_submission_enable' => __( 'Multiple Submission Protection', 'captcha-for-contact-form-7' ),
			],
			__( 'IP Protection', 'captcha-for-contact-form-7' ) => [
				'protection_ip_enable'                  => __( 'IP Rate Limiting', 'captcha-for-contact-form-7' ),
				'protection_ip_max_retries'             => __( 'Max Retries', 'captcha-for-contact-form-7' ),
				'protection_ip_max_retries_period'      => __( 'Max Retries Period (s)', 'captcha-for-contact-form-7' ),
				'protection_ip_period_between_submits'  => __( 'Period Between Submits (s)', 'captcha-for-contact-form-7' ),
				'protection_ip_block_time'              => __( 'Block Time (s)', 'captcha-for-contact-form-7' ),
			],
			__( 'Content Rules', 'captcha-for-contact-form-7' ) => [
				'protection_rules_url_enable'        => __( 'URL Limiter', 'captcha-for-contact-form-7' ),
				'protection_rules_url_limit'         => __( 'URL Limit', 'captcha-for-contact-form-7' ),
				'protection_rules_bbcode_enable'     => __( 'BBCode Filter', 'captcha-for-contact-form-7' ),
				'protection_rules_blacklist_enable'  => __( 'Blacklist Filter', 'captcha-for-contact-form-7' ),
				'protection_rules_blacklist_greedy'  => __( 'Blacklist Greedy Mode', 'captcha-for-contact-form-7' ),
			],
			__( 'Whitelist', 'captcha-for-contact-form-7' ) => [
				'protection_whitelist_role_admin'     => __( 'Skip for Admins', 'captcha-for-contact-form-7' ),
				'protection_whitelist_role_logged_in' => __( 'Skip for Logged-In', 'captcha-for-contact-form-7' ),
			],
			__( 'Logging', 'captcha-for-contact-form-7' ) => [
				'protection_log_enable' => __( 'Enable Logging', 'captcha-for-contact-form-7' ),
			],
		];
	}

	/**
	 * Render an integration-level override panel (hidden, disabled).
	 *
	 * @param string $id        Integration ID (e.g. 'cf7').
	 * @param string $name      Integration display name.
	 * @param array  $global    Global settings (flat key => value).
	 * @param array  $overrides Current integration overrides.
	 *
	 * @return string HTML string.
	 */
	public static function render_integration_panel( string $id, string $name, array $global, array $overrides ): string {
		ob_start();

		$is_enabled = ! empty( $overrides['_enabled'] );
		$panel_id   = 'f12-panel-integration-' . $id;

		?>
		<div id="<?php echo esc_attr( $panel_id ); ?>"
			 class="f12-override-panel-content"
			 data-panel-title="<?php echo esc_attr( $name ); ?>"
			 data-panel-type="integration"
			 data-integration-id="<?php echo esc_attr( $id ); ?>"
			 style="display:none;">

			<div class="f12-override-toggle-row">
				<label>
					<input type="checkbox"
						   data-override-enabled
						   disabled
						   <?php checked( $is_enabled ); ?> />
					<?php esc_html_e( 'Enable individual settings for this integration', 'captcha-for-contact-form-7' ); ?>
				</label>
			</div>

			<?php
			$groups = self::get_setting_groups();
			foreach ( $groups as $group_label => $keys ) {
				self::render_setting_group( $group_label, $keys, $overrides, $global );
			}
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a form-level override panel (hidden, disabled).
	 *
	 * @param string $int_id     Integration ID.
	 * @param string $form_id    Form ID.
	 * @param string $title      Form title.
	 * @param string $int_name   Integration display name.
	 * @param array  $effective  Effective (resolved) settings for this form.
	 * @param array  $overrides  Current form-level overrides.
	 * @param array  $sources    Source map: key => 'global'|'integration'|'form'.
	 *
	 * @return string HTML string.
	 */
	public static function render_form_panel( string $int_id, string $form_id, string $title, string $int_name, array $effective, array $overrides, array $sources ): string {
		ob_start();

		$is_enabled = ! empty( $overrides['_enabled'] );
		$panel_id   = 'f12-panel-form-' . $int_id . '-' . $form_id;

		$panel_title = sprintf(
			/* translators: 1: Form title, 2: Integration name, 3: Form ID */
			__( '%1$s (%2$s #%3$s)', 'captcha-for-contact-form-7' ),
			$title,
			$int_name,
			$form_id
		);

		?>
		<div id="<?php echo esc_attr( $panel_id ); ?>"
			 class="f12-override-panel-content"
			 data-panel-title="<?php echo esc_attr( $panel_title ); ?>"
			 data-panel-type="form"
			 data-integration-id="<?php echo esc_attr( $int_id ); ?>"
			 data-form-id="<?php echo esc_attr( $form_id ); ?>"
			 style="display:none;">

			<div class="f12-override-toggle-row">
				<label>
					<input type="checkbox"
						   data-override-enabled
						   disabled
						   <?php checked( $is_enabled ); ?> />
					<?php esc_html_e( 'Enable individual settings for this form', 'captcha-for-contact-form-7' ); ?>
				</label>
			</div>

			<?php
			$source_labels = [
				'global'      => __( 'Global', 'captcha-for-contact-form-7' ),
				'integration' => __( 'Integration', 'captcha-for-contact-form-7' ),
				'form'        => __( 'This Form', 'captcha-for-contact-form-7' ),
			];

			$groups = self::get_setting_groups();
			foreach ( $groups as $group_label => $keys ) {
				self::render_setting_group( $group_label, $keys, $overrides, $effective, $sources, $source_labels );
			}
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a group of settings.
	 *
	 * @param string     $label         Group label.
	 * @param array      $keys          Setting keys with labels.
	 * @param array      $overrides     Current overrides for this level.
	 * @param array      $global        Global/effective settings.
	 * @param array|null $sources       Source map (for form panels only).
	 * @param array|null $source_labels Source label translations.
	 */
	private static function render_setting_group( string $label, array $keys, array $overrides, array $global, ?array $sources = null, ?array $source_labels = null ): void {
		?>
		<h4><?php echo esc_html( $label ); ?></h4>
		<?php foreach ( $keys as $key => $field_label ) : ?>
			<?php
			$global_value   = $global[ $key ] ?? '';
			$override_value = $overrides[ $key ] ?? null;
			$has_override   = array_key_exists( $key, $overrides );
			?>
			<div class="f12-override-field-row">
				<div class="f12-override-field-label">
					<strong><?php echo esc_html( $field_label ); ?></strong>
					<?php if ( $sources !== null && $source_labels !== null ) : ?>
						<?php $source = $sources[ $key ] ?? 'global'; ?>
						<span class="f12-forms-source f12-forms-source--<?php echo esc_attr( $source ); ?>">
							<?php echo esc_html( $source_labels[ $source ] ?? $source ); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="f12-override-field-input">
					<?php self::render_setting_field( $key, $global_value, $override_value, $has_override ); ?>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Render a single setting field with "inherit" option.
	 *
	 * @param string $key            The setting key.
	 * @param mixed  $global_value   The global/effective value.
	 * @param mixed  $override_value The current override value (null if not set).
	 * @param bool   $has_override   Whether an override exists.
	 */
	public static function render_setting_field( string $key, $global_value, $override_value, bool $has_override ): void {
		$current_value = $has_override ? $override_value : '__inherit__';

		$boolean_keys = [
			'protection_captcha_enable',
			'protection_time_enable',
			'protection_javascript_enable',
			'protection_browser_enable',
			'protection_multiple_submission_enable',
			'protection_ip_enable',
			'protection_rules_url_enable',
			'protection_rules_bbcode_enable',
			'protection_rules_blacklist_enable',
			'protection_rules_blacklist_greedy',
			'protection_whitelist_role_admin',
			'protection_whitelist_role_logged_in',
			'protection_log_enable',
		];

		// Method selection
		if ( $key === 'protection_captcha_method' ) {
			$options = [
				'__inherit__' => __( 'Inherit from parent', 'captcha-for-contact-form-7' ),
				'honey'       => __( 'Honeypot', 'captcha-for-contact-form-7' ),
				'math'        => __( 'Arithmetic', 'captcha-for-contact-form-7' ),
				'image'       => __( 'Image', 'captcha-for-contact-form-7' ),
			];
			self::render_select( $key, $options, $current_value );
			return;
		}

		// Template selection
		if ( $key === 'protection_captcha_template' ) {
			$options = [
				'__inherit__' => __( 'Inherit from parent', 'captcha-for-contact-form-7' ),
				'0'           => __( 'Template 0', 'captcha-for-contact-form-7' ),
				'1'           => __( 'Template 1', 'captcha-for-contact-form-7' ),
				'2'           => __( 'Template 2', 'captcha-for-contact-form-7' ),
			];
			self::render_select( $key, $options, $current_value );
			return;
		}

		// Reload icon selection
		if ( $key === 'protection_captcha_reload_icon' ) {
			$options = [
				'__inherit__' => __( 'Inherit from parent', 'captcha-for-contact-form-7' ),
				'black'       => __( 'Black', 'captcha-for-contact-form-7' ),
				'white'       => __( 'White', 'captcha-for-contact-form-7' ),
			];
			self::render_select( $key, $options, $current_value );
			return;
		}

		// Boolean toggles
		if ( in_array( $key, $boolean_keys, true ) ) {
			$options = [
				'__inherit__' => __( 'Inherit from parent', 'captcha-for-contact-form-7' ),
				'1'           => __( 'Enabled', 'captcha-for-contact-form-7' ),
				'0'           => __( 'Disabled', 'captcha-for-contact-form-7' ),
			];
			self::render_select( $key, $options, (string) $current_value );
			return;
		}

		// Numeric fields
		$numeric_keys = [
			'protection_time_ms',
			'protection_ip_max_retries',
			'protection_ip_max_retries_period',
			'protection_ip_period_between_submits',
			'protection_ip_block_time',
			'protection_rules_url_limit',
		];

		if ( in_array( $key, $numeric_keys, true ) ) {
			$display_value = $has_override ? $override_value : '';
			echo '<input type="number" data-override-key="' . esc_attr( $key ) . '" '
				. 'value="' . esc_attr( $display_value ) . '" '
				. 'placeholder="' . esc_attr( sprintf( __( 'Inherit (%s)', 'captcha-for-contact-form-7' ), $global_value ) ) . '" '
				. 'disabled style="width:100%;" />';
			return;
		}

		// Text fields (label, placeholder)
		$display_value = $has_override ? $override_value : '';
		echo '<input type="text" data-override-key="' . esc_attr( $key ) . '" '
			. 'value="' . esc_attr( $display_value ) . '" '
			. 'placeholder="' . esc_attr( sprintf( __( 'Inherit (%s)', 'captcha-for-contact-form-7' ), mb_substr( (string) $global_value, 0, 30 ) ) ) . '" '
			. 'disabled style="width:100%;" />';
	}

	/**
	 * Render a select field with data-override-key attribute.
	 *
	 * @param string $key           Setting key.
	 * @param array  $options       Options array (value => label).
	 * @param string $current_value Currently selected value.
	 */
	private static function render_select( string $key, array $options, string $current_value ): void {
		echo '<select data-override-key="' . esc_attr( $key ) . '" disabled>';
		foreach ( $options as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current_value, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render the slide-in container shell (used on both Extended and Forms pages).
	 */
	public static function render_slide_in_container(): void {
		?>
		<div class="f12-slide-in-backdrop"></div>
		<div class="f12-slide-in">
			<div class="f12-slide-in-header">
				<h3></h3>
				<button type="button" class="f12-slide-in-close">&times;</button>
			</div>
			<div class="f12-slide-in-body"></div>
			<div class="f12-slide-in-footer">
				<button type="button" class="button f12-slide-in-cancel"><?php esc_html_e( 'Cancel', 'captcha-for-contact-form-7' ); ?></button>
				<button type="button" class="button button-primary f12-slide-in-save"><?php esc_html_e( 'Save', 'captcha-for-contact-form-7' ); ?></button>
			</div>
		</div>
		<div class="f12-slide-in-toast"></div>
		<?php
	}
}
