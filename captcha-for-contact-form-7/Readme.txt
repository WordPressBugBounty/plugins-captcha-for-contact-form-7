== SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce) ==
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: captcha, spam protection, honeypot, contact form 7, fluentform, wpforms, elementor, woocommerce, anti-spam
Requires at least: 5.2
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 2.3.1
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

SilentShield doesn't just protect forms.
It protects your time, your customers, your business.

---

## Core Features
- Invisible Captcha (Arithmetic, Honeypot, Image)
- Smart IP Blocking & Blacklists
- Spam filters for links, code & keywords
- Whitelisting for admins & customers
- GDPR-ready, no cookies, no tracking

---

## Supported Form Plugins & Integrations

SilentShield protects forms from all major WordPress form builders and core features:

**Form Builders:**
- Contact Form 7 (CF7)
- WPForms / WPForms Lite
- Elementor Pro Forms
- Gravity Forms
- Fluent Forms
- JetFormBuilder
- Avada (Fusion Builder) Forms

**WooCommerce:**
- Checkout (classic & PayPal Payments)
- Login
- Registration

**WordPress Core:**
- Login form (wp-login.php)
- Registration form
- Comment forms

**Other:**
- Ultimate Member (Login & Registration)
- WP Job Manager (Job Applications)

Each integration can be enabled or disabled individually under **Settings > Extended**.

---

## Protection Layers

SilentShield uses **10+ protection mechanisms** working together:

1. **Captcha** – Arithmetic math, honeypot, or image-based captcha
2. **JavaScript Protection** – Detects submissions from bots without JS support
3. **Browser Detection** – Validates User-Agent strings
4. **Timer Protection** – Blocks submissions faster than a human can type
5. **Multiple Submission Protection** – Prevents rapid duplicate submissions
6. **IP Rate Limiting** – Limits requests per IP and time window
7. **IP Blacklist** – Block known bad IPs
8. **Content Rules** – Limit URLs, block BBCode, keyword blacklist
9. **Whitelist** – Skip validation for admins, logged-in users, or specific emails/IPs
10. **SilentShield API** (Beta) – Cloud-based spam detection

---

## The Promise

SilentShield is not "just another plugin."
It's an invisible wall against the background noise of the internet.

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
2. Activate via WordPress "Plugins" menu.
3. Configure protection settings under **Settings > SilentShield**.

For detailed setup instructions, see [docs/installation.md](docs/installation.md).

---

== Frequently Asked Questions ==

= Will this stop all spam? =
Not all, but it drastically reduces it. SilentShield combines multiple detection layers (captcha, honeypot, IP blocking, JavaScript detection, timer, content rules) for maximum coverage.

= Is it GDPR compliant? =
Yes – no cookies, no tracking, only anonymized data. IPs are stored encrypted for max 2 months (only for spam defense). See the Privacy section below.

= Do I need coding skills? =
No. Everything is managed via WordPress Dashboard.

= Does it work with WooCommerce PayPal Payments? =
Yes. SilentShield automatically injects JavaScript protection timestamps into PayPal checkout requests. Both PayPal Standard Buttons and Card Fields are supported.

= Can I customize the captcha appearance? =
Yes. Choose from 3 built-in templates, customize the label and placeholder text, and select a reload icon color (black/white). Developers can further customize the output via filters.

= Can I disable specific protection layers? =
Yes. Every protection mechanism (captcha, timer, JavaScript, browser, IP, rules, etc.) can be individually enabled or disabled.

= How do I whitelist my admin users? =
Under **Settings > Extended > Whitelist**, enable "Whitelist Admin Users" and/or "Whitelist Logged-In Users". You can also whitelist specific emails and IPs.

= What data does telemetry collect and why? =
SilentShield includes **optional anonymous telemetry** (opt-out).
This helps us understand which features are used, so we can improve usability and remove unused complexity.

**We are a small independent team** – we don't earn money with this plugin, and we don't sell or share data.
Telemetry is used **only for optimization and maintenance purposes**.

= Where is the full documentation? =
See the [docs/](docs/) directory in the plugin folder for complete documentation of all settings, hooks, REST API, and developer reference.

---

== Privacy & Telemetry ==
- No cookies, no user tracking.
- Encrypted IP storage (max. 2 months, only for spam defense).
- Telemetry is optional and anonymized.
- You can disable telemetry anytime in plugin settings.

Collected fields:
- `plugin_slug`, `plugin_version`
- `snapshot_date`
- `settings_json` (anonymized config – only boolean/integer flags, no free-text)
- `features_json` (enabled features)
- `created_at`, `first_seen`, `last_seen`
- `counters_json` (spam events)
- `wp_version`, `php_version`, `locale`

**GDPR / DSGVO Compliance**
- Basis: *Art. 6 Abs. 1 lit. f DSGVO* (legitimate interest – plugin optimization).
- No personal data, no cookies, no user tracking.

---

== Changelog ==
= 2.3.0 =
- Fix [Security]: Closed mass-assignment vulnerability in IPBan and IPLog classes. Properties are now set via explicit allowlist instead of `property_exists()`, preventing overwrite of internal state like the logger or ID fields.
- Fix [Security]: Replaced `parse_str()` on raw POST data in API verification with targeted regex extraction, eliminating a potential denial-of-service vector via deeply nested keys.
- Fix [Security]: Added `esc_html()` to spam error messages in `format_spam_message()` as defense-in-depth against potential XSS if future modules include dynamic content in messages.
- Fix [Security]: Added `defined('ABSPATH')` guards to 10 PHP files that were missing them (BaseController, BaseModul, Api, Browser, IP_Blacklist_Validator, Whitelist_Validator, Javascript_Validator, Log_WordPress_Interface, Validator, Browser_User_Agent).
- Fix [WooCommerce / WordPress Login]: Resolved a cross-concern filter leak where WooCommerce registration validation could accidentally bypass WordPress login spam checks (and vice versa). Each integration now uses its own scoped filter (`f12_cf7_captcha_wc_login_validated`, `f12_cf7_captcha_wc_registration_validated`).
- Fix [WordPress Registration]: Changed error code from integer `500` to string `'spam'` for consistency with all other controllers.
- Fix [Comments]: Replaced abrupt `wp_die()` with a proper error page that includes the specific spam reason, a "Go Back" link, and HTTP 403 status.
- Fix [CF7]: Changed greedy regex `(.*)` to non-greedy `(.*?)` in submit button detection, preventing incorrect captcha placement when multiple input elements exist on the same line.
- Fix [Telemetry]: Counters now track request-local deltas and merge them with the current database values at shutdown, significantly reducing lost updates under concurrent requests.
- Fix [Database]: Standardized all `$wpdb` null-checks in IPBan and IPLog to use strict `null === $wpdb` comparison consistently.
- Fix [Core]: `set_blocked_time()` parameter type changed from `string` to `int` to match its semantic purpose (number of seconds).
- Improvement [API]: Server-side verification endpoint is now configurable via the `F12_CAPTCHA_API_URL` constant, matching the frontend configuration. This enables mock API servers in automated tests and self-hosted deployments.
- Improvement [API]: Network errors during API verification now respect a configurable fail mode via the `f12-cf7-captcha-api-fail-closed` filter. Default remains fail-open for backwards compatibility; set to `true` to block submissions when the API is unreachable.
- Improvement [Assets]: Added `FORGE12_CAPTCHA_VERSION` as cache-busting version parameter to all enqueued scripts and stylesheets, ensuring browsers load updated assets after plugin upgrades.
- Improvement [Code Quality]: Deprecated method aliases (`getInstance()`, `get_modul()`) now emit `_deprecated_function()` notices to help developers migrate to the current API.
- Improvement [Logging]: Standardized all log messages from mixed German/English to English for consistent log parsing and compatibility with international teams and log aggregation tools.
- Performance [Frontend]: Added `defer` script strategy (WP 6.3+) to frontend scripts, allowing the browser to continue parsing HTML while scripts load.
- Performance [Frontend]: Removed unnecessary jQuery dependency from the SilentShield API client loader (client.js uses only vanilla DOM APIs).
- Performance [Admin]: Moved admin toggle.js to the footer to eliminate render-blocking in the dashboard.
- Performance [Build]: Disabled source maps in production builds, removing the 160KB .map file reference from the deployed bundle.

= 2.2.76 =
- Fix [WooCommerce Checkout]: Resolved "Captcha nicht korrekt: Javascript-Schutz" error when checking out with PayPal Payments (Standard Buttons and Card Fields). The JavaScript protection timestamps are now injected into AJAX checkout requests automatically, fixing compatibility with payment gateways that submit the checkout without clicking the #place_order button.

= 2.2.75 =
- Fix [JetFormBuilder]: Captcha now renders correctly on JetFormBuilder v3.5+ where the legacy `before-start-form-row` hook no longer fires for the submit button. Added `before-end-form` filter as a reliable fallback that works across all versions.
- Fix [JetFormBuilder]: Registered `before-start-form` and `before-end-form` as filters (not actions) to match JetFormBuilder's `apply_filters()` API, ensuring captcha HTML is properly injected into the form output.
- Improvement [JetFormBuilder]: Added dedicated `JetFormBuilderForms` JavaScript handler with MutationObserver for dynamic form detection, captcha repositioning before the submit button, submit interception for captcha verification, and AJAX-aware captcha reload.
- Fix [JetFormBuilder]: Corrected Gutenberg block name in E2E test setup from `jet-forms/action-button` to `jet-forms/submit-field` to match the registered block name.

= 2.2.74 =
- Fix [Security]: Removed `$wpdb->prepare()` call without placeholders in Salt class, which triggered a PHP notice on WordPress 6.x+.
- Fix [Security]: Replaced `hash_pbkdf2()` with negligible 10-iteration count by `hash_hmac('sha512')` for IP hashing — clearer intent, no misleading key stretching.
- Fix [Code Quality]: Removed commented-out `debug_backtrace()` block from production code.
- Improvement [Security]: Added IP-based rate limiting (30 req/min) to `captcha/reload` and `timer/reload` REST endpoints, preventing table flooding by rapid unauthenticated requests.

= 2.2.73 =
- Improvement [Code Quality]: Standardized mixed German/English naming conventions across the entire codebase (`$_moduls` → `$_modules`, `init_moduls()` → `init_modules()`, `get_modul()` → `get_module()`). Deprecated wrapper methods preserve backwards compatibility.

= 2.2.72 =
- Improvement [Database]: Added index on `hash` column for IPBan, IPLog, Captcha, and CaptchaTimer tables. Existing installations receive the index automatically on update.
- Improvement [Resilience]: Database tables are now auto-created on first write if missing, removing the need to manually reactivate the plugin after table loss.

= 2.2.71 =
- Improvement [Performance]: Telemetry counters are now accumulated in memory and flushed once at shutdown, replacing per-submission `update_option()` calls that caused unnecessary database writes on high-traffic sites.

= 2.2.70 =
- Improvement [Performance]: Logger methods now check `F12_DEBUG` before any processing, eliminating unnecessary overhead (sanitization, glob, file I/O) when debug mode is off.

= 2.2.69 =
- Improvement [Core]: Replaced ~60 manual `require_once` calls with a custom PSR-4 autoloader (`autoload.php`). Classes are now loaded on demand, reducing upfront file I/O.

= 2.2.68 =
- Fix [Privacy]: Telemetry now only transmits boolean/integer feature flags. API keys, blacklist content, and all other free-text settings are stripped before transmission.

= 2.2.67 =
- Fix [Security]: Removed hardcoded development API URL from shipped plugin. Production URL (`api.silentshield.io`) is now the default.
- New [Configuration]: Added `F12_CAPTCHA_API_URL` constant to override the API endpoint for development or staging environments (define in `wp-config.php`).

= 2.2.66 =
- Fix [Security]: IP detection now defaults to `REMOTE_ADDR` only, preventing IP spoofing via forged `HTTP_CLIENT_IP` or `HTTP_X_FORWARDED_FOR` headers.
- New [Configuration]: Added `F12_TRUSTED_PROXY_HEADER` constant for sites behind a reverse proxy or load balancer (define in `wp-config.php`).
- Improvement [Validation]: IP addresses are now validated with `filter_var( FILTER_VALIDATE_IP )` instead of `sanitize_text_field()`.

= 2.2.65 =
- Fix [Security]: Added `wp_kses_post()` escaping to the plugin upgrade notice output to prevent potential XSS from unescaped update data.

= 2.2.64 =
- Fix [Security]: Added `wp_kses()` escaping for captcha HTML output in all captcha templates to prevent potential XSS.
- Fix [Templates]: Fixed double-escaping bug in template-2 where `esc_attr()` was incorrectly applied to a pre-escaped attribute string.

= 2.2.63 =
- Fix [Security]: Replaced all `sprintf` + `esc_sql` SQL queries with `$wpdb->prepare()` across IPBan, IPLog, and Captcha classes to prevent potential SQL injection.

= 2.2.62 =
- Improvement [Security]: Migrated all AJAX endpoints to WP REST API (`f12-cf7-captcha/v1`), adding built-in permission checks, schema validation, and proper HTTP responses.
- Fix [Security]: Blacklist sync endpoint now restricted to `manage_options` capability (was previously accessible to unauthenticated users).
- Improvement [API]: Captcha reload, timer reload, and blacklist sync now use REST routes with JSON request/response format.

= 2.2.61 =
- Fix [JavaScript]: Updated a bug causing the reload captcha to trigger infinite in some rare cases

= 2.2.60 =
- Improvement [JavaScript]: Switched Elementor form handling ensuring captcha logic is triggered reliably even when forms are dynamically destroyed and re-rendered by Elementor.

= 2.2.58 =
- Improvement [JavaScript]: Updated default form to exclude additional wordpress components which have be extracted to different components.

= 2.2.57 =
– Fix [WordPress Login / Registration]: Resolved an issue where enabling one protection feature incorrectly activated both.

= 2.2.56 =
- Fix [Form Detection] Resolved an issue where a recent script adjustment caused default WordPress/WooCommerce forms to be unintentionally registered as protected SilentShield forms. This led to conflicts with the WooCommerce "Add to cart" process for anonymous sessions. The form detection logic has been corrected to properly exclude default forms.

= 2.2.55 =
- Fix [JavaScript] Fixed a build issue where the minifier renamed classes to _, overwriting window._ (Underscore.js) and breaking WordPress/WooCommerce scripts by reserving _ during mangling and adding a safe global namespace.

= 2.2.54 =
- Fix [WP Job Manager]: Added reliable detection for WP Job Manager plugin presence before initializing compatibility hooks, preventing unnecessary execution and log noise when the plugin is not installed or active.
- Improvement [JavaScript]: Refactored JavaScript interception logic and updated compatibility with the latest plugin versions. This reduces the number of loaded scripts and improves overall page performance by using optimized, minified files.
- Improvement [API]: When using the beta API (SaaS service), the system now automatically disables legacy protection mechanisms to prevent potential conflicts.
- Fix [Avada / Contact Form 7]: Reworked the form submission script to prevent duplicate submissions.
- Fix [Elementor]: Updated integration with the latest Elementor version to ensure form submissions require valid CAPTCHA verification.
- Fix [Elementor]: Resolved an issue caused by Elementor's internal caching, which stored forms (including CAPTCHA data) in the database. The system now reloads CAPTCHA immediately after attaching it to the form to ensure proper validation.

= 2.2.53 =
- Improvement [Whitelist]: Added global AJAX/REST whitelist for WooCommerce and major payment gateways (PayPal, Stripe, Klarna, Mollie, Amazon Pay, Apple Pay, Google Pay, Link) to prevent false CAPTCHA validation during checkout.
- Improvement [Captcha Math Generator]: Improved numeric CAPTCHA validation logic to correctly handle 0 results (e.g., 5 − 5 = 0) and prevent false negatives from empty or non-numeric inputs.

= 2.2.52 =
- New [WooCommerce Checkout]: Now protected with Captcha. Enable or disable under Settings > Extended. More security. Less spam.
- Improvement [API v2]: Refactored server-side validation for greater consistency and reduced error rate.

= 2.2.51 =
- Improvement [API v2/JavaScript]: Refactored client-side validation for consistency and reduced error rate

= 2.2.50 =
- Fix [API v2]: Updated key and endpoint configuration.
- Fix [JavaScript]: Adjusted script to align with latest Chrome behavior, resolving issues with event forwarding from WooCommerce/WordPress.

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
- Fixed: Updated the comparison of `$setting_value` by adding an explicit `(int)` cast to ensure numeric strings like `'1'` are correctly converted to integers.

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
