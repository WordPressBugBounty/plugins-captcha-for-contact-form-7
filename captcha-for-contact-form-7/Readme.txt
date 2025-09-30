== SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce) ==
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: captcha, spam protection, honeypot, contact form 7, fluentform, wpforms, elementor, woocommerce, anti-spam
Requires at least: 5.2
Tested up to: 6.8.2
Requires PHP: 8.1
Stable tag: 2.2.49
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

**SilentShield** – the invisible shield against spam.
Spam is the weed of the internet. It clogs your forms, steals your time, and corrupts your data.

**SilentShield ends this.**
Protects WordPress forms with captcha, honeypot and blacklist technology – fully compatible with CF7, WPForms, Elementor, WooCommerce and more.

---

== Description ==
SilentShield is a **unified captcha and anti-spam plugin for WordPress**.
It works with the most popular form builders and protects login, registration, and comment forms – without slowing your site.

**Why choose SilentShield?**
- **Invisible defense** – Captcha, honeypot, and blacklists working silently.
- **Instant results** – Install, activate, and stop spam.
- **Universal support** – Works with Contact Form 7, WPForms, Elementor, WooCommerce, and more.
- **Privacy-first** – No cookies, no tracking, fully GDPR / DSGVO compliant.

SilentShield doesn’t just protect forms.
It protects your time, your customers, your business.

---

## Core Features
- Invisible Captcha (Arithmetic, Honeypot, Image)
- Smart IP Blocking & Blacklists
- Spam filters for links, code & keywords
- Whitelisting for admins & customers
- GDPR-ready, no cookies, no tracking

---

## The Promise

SilentShield is not “just another plugin.”
It’s an invisible wall against the background noise of the internet.

Activate once – and your forms are human again.


== Screenshots ==
1. IP Protection settings
2. Spam protection in comments
3. Contact Form 7 integration
4. Avada Forms integration
5. Image Captcha example
6. Arithmetic Captcha example
7. Honeypot Captcha example

---

== Installation ==
1. Upload to `/wp-content/plugins/`.
2. Activate via WordPress “Plugins” menu.
3. Configure protection settings.

---

== Frequently Asked Questions ==

= Will this stop all spam? =
Not all, but it drastically reduces it.

= Is it GDPR compliant? =
Yes – no cookies, no tracking, only anonymized data.

= Do I need coding skills? =
No. Everything is managed via WordPress Dashboard.

= What data does telemetry collect and why? =
SilentShield includes **optional anonymous telemetry** (opt-out).
This helps us understand which features are used, so we can improve usability and remove unused complexity.

**We are a small independent team** – we don’t earn money with this plugin, and we don’t sell or share data.
Telemetry is used **only for optimization and maintenance purposes**.

---

== Privacy & Telemetry ==
- No cookies, no user tracking.
- Encrypted IP storage (max. 2 months, only for spam defense).
- Telemetry is optional and anonymized.
- You can disable telemetry anytime in plugin settings.

Collected fields:
- `plugin_slug`, `plugin_version`
- `snapshot_date`
- `settings_json` (anonymized config)
- `features_json` (enabled features)
- `created_at`, `first_seen`, `last_seen`
- `counters_json` (spam events)
- `wp_version`, `php_version`, `locale`

**GDPR / DSGVO Compliance**
- Basis: *Art. 6 Abs. 1 lit. f DSGVO* (legitimate interest – plugin optimization).
- No personal data, no cookies, no user tracking.

---

== Changelog ==
= 2.2.49 =
- New [API v2]: Started implementing the new Captcha SaaS solution.
- Fix [JavaScript]: Fixed a bug that prevented WooCommerce from triggering its own events on form submit.
- Fix [Logger]: No longer tied to WP_DEBUG. Enable with F12_DEBUG.
- Fix [Core]: Updated paths for blacklist and set timeout value to 3s.

= 2.2.46 =
- Fix [Core]: Removed dynamic property creation in CaptchaTimer; explicit initialization.
- Fix [JS]: Validation now works when the submit button has an inline onclick; our callback is no longer blocked.
- Fix [Gravity Forms]: CAPTCHA now renders at the configured position; misplacement previously caused constant protection triggering.
- Fix [Logs]: Removed properties are no longer tracked; prevents excessive log size growth.


= 2.2.44 =
- Fixed: Updated the comparison of `$setting_value` by adding an explicit `(int)` cast to ensure numeric strings like `'1'` are correctly converted to integers. This fixes issues where the strict comparison `=== 1` would previously return `false`.

= 2.2.43 =
- Fixed: Adjusted hooks to clear database entries by user.
- Fixed: Fatal error caused by IPLogs using array_keys


= 2.2.4 =
- New: JetForm support
- New: IP Blacklist Validator
- New: Anonymous telemetry (opt-out)
- Improved: Simplified configuration defaults
- Improved: Reload & error handling for form plugins
- Fixed: Admin whitelist for Ajax forms

(Older versions trimmed – full changelog on plugin site.)