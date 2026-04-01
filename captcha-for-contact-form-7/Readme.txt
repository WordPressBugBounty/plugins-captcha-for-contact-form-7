== SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce) ==
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: captcha, spam protection, honeypot, contact form 7, fluentform, wpforms, elementor, woocommerce, anti-spam
Requires at least: 5.2
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 2.6.11
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
= 2.6.11 =
- Fix [Settings]: Global settings (including integration enable/disable toggles) were not loaded on non-admin pages (wp-login.php, frontend). The settings cache only included values from the `f12-cf7-captcha_settings` filter defaults, which are only registered on admin pages. DB settings containers not covered by filter defaults were silently dropped. All `get_settings()` calls returned `null` on the login page, causing every protection module to fall back to its enabled default. This also meant integration toggle settings and per-module overrides were ignored on the login page.
- New [Forms]: Added master toggle to enable/disable entire integrations (WordPress Login, WooCommerce, Avada, CF7, etc.) directly from the Forms page. Previously, only per-module overrides were available — there was no way to completely deactivate protection for a specific integration via the UI.
- Fix [Cleanup]: Data Cleanup page showed all counts as zero. The `handle_cleanup_counts` endpoint called `get_count()` on Cleaner classes (`CaptchaCleaner`, `IPLogCleaner`, `IPBanCleaner`, `CaptchaTimerCleaner`) which do not have this method. The resulting `Error` was silently caught. Added `get_count()` delegate methods to all four Cleaner classes.
- New [API]: New REST endpoint `POST /integration/toggle` to programmatically enable or disable integrations by setting their global settings key.
- New [API]: The `/forms/discover` endpoint now returns `enabled` and `settings_key` per integration, so the UI can display and toggle the integration status.

= 2.6.10 =
- Fix [Telemetry]: Disabling telemetry in Advanced Settings no longer stops the daily telemetry cron job from running. The cron was scheduled unconditionally on every page load and `send_telemetry_snapshot()` never checked the setting — data was still sent to the API even when telemetry was turned off. Now the cron is only registered when telemetry is enabled, removed immediately when disabled, and the send function includes a guard check as defense-in-depth.

= 2.6.9 =
- Fix [Whitelist]: Email whitelist never matched — the `is_whitelisted_email()` method logged the match but was missing the `return true` statement, so whitelisted emails were still checked by all protection modules.
- Fix [Whitelist]: Admin role check caused early return that blocked IP and email whitelist checks. When admin whitelist was enabled and a non-admin user submitted a form, the method returned `false` immediately instead of continuing to check IP/email whitelists.
- Fix [Whitelist/Blacklist]: REST API settings save (`handle_settings_save`) used `sanitize_text_field()` for textarea fields (whitelist emails, whitelist IPs, blacklist IPs), which strips newlines. Entries saved via the React admin UI were merged into a single line and never matched. Now uses `sanitize_textarea_field()` for these fields, matching the PHP form handler behavior.
- Fix [Whitelist/Blacklist]: IP and email parsing now uses `preg_split('/[\s,]+/')` instead of `explode("\n")`, so entries separated by spaces or commas (e.g. from previously corrupted saves) are correctly recognized.
- Fix [Protection]: SilentShield API mode and local protection modules (JavaScript, Timer, Captcha, etc.) can now run simultaneously. Previously, enabling the API disabled all local modules and prevented the local JS from loading, causing false `NO_JAVASCRIPT` blocks on login and other forms.
- Fix [Assets]: Local protection script (`f12-cf7-captcha-cf7.js`) is now always loaded when a form is detected, even when the SilentShield API client (`client.js`) is also active. Previously the two were mutually exclusive.
- New [Documentation]: Added in-plugin Help page (SilentShield > Help) with full user guide covering all protection modules, integrations, whitelist/blacklist, per-form overrides, API mode, logging and FAQ.
- New [Documentation]: Contextual help links (info icon) added to all section headings on Settings, Dashboard, API and Forms pages, linking directly to the relevant documentation section.
- New [Documentation]: Inline tooltips on 14 key settings fields (whitelist, blacklist, IP protection, content rules, logging, asset loading) explaining each option on hover.
- New [Translations]: German (de_DE, de_DE_formal) and French (fr_FR) translations added for all documentation strings.

= 2.6.8 =
- Fix [API]: Unified all API endpoints to use `/api/v1` base path. The verify endpoint changed from `/v1/verify` to `/api/v1/captcha/verify-nonce`. Affects key validation, trial creation, telemetry, shadow mode, and blacklist retrieval.
- New [API]: Introduced separate `F12_CAPTCHA_CLIENT_URL` constant to decouple the behavior client script URL from the API base URL. The client.js loader now reads `client_url` from localized data with fallback to `url`.
- New [Mail-Log]: API response metadata (verdict, confidence, reason codes) is now forwarded to mail log entries for both blocked and passed submissions, enabling better audit trail and debugging.
- Fix [Settings]: Added `invalidate_settings_cache` hook at `init` priority 99 to ensure the settings cache is rebuilt after UI page filters register their defaults.
- New [Debug]: Added detailed debug logging in the API spam check flow for nonce detection, API request/response, and verdict evaluation. Temporary logging to `error_log` for troubleshooting integration issues.

= 2.6.7 =
- Fix [Translations]: Fixed 4 German strings that were mistakenly used in the French (fr_FR) translation files instead of French. Affected strings: "Enable Mail Logging…", "Also block partial matches…", "The analytics page…", "Synchronized with WordPress Disallowed Comment Keys".
- Fix [Translations]: Fixed incorrect French translation for relative time indicator "in" — changed from "dans" to "en" (e.g. "en 5 minutes").
- Fix [UI]: Fixed overflow-hidden on the individual forms list (FormsPage) which prevented scrolling when the list exceeded viewport height. Replaced with overflow-auto.
- Fix [Settings]: Fixed settings cache race condition where `Protection::init_modules()` called `get_settings()` before UI pages registered their filter defaults, caching an empty array. The REST API then returned `[]` instead of `{ global: {...}, beta: {...} }`, causing the admin UI to show empty settings. The cache is now invalidated on `init` (priority 99) after UI page filters are registered.

= 2.6.6 =
- Fix [Translations]: Fixed `_load_textdomain_just_in_time` notice introduced in WordPress 6.7. Translation loading for UI pages (e.g. Upgrade page) was triggered too early during plugin initialization. The `do_action('_ui_after_load_pages')` call in `UI_Manager` is now deferred to the `init` hook, ensuring `__()` is only called after translations are available.

= 2.6.5 =
- New [Templates]: Captcha image now uses transparent PNG background, blending seamlessly with all template styles (Standard, Compact, Clean, Dark Card, Gradient Dark). Dark templates (Gradient Dark) use light text colors for readability.
- New [Templates]: Classic templates (0–2) from v2.3.x are now visible and selectable in the template picker alongside the modern templates, ensuring backward compatibility for existing users after updates.
- New [Templates]: Template picker UI now groups templates into "Templates" (modern) and "Classic Templates" (legacy) sections with distinct preview styles.
- Fix [Templates]: Audio tooltip text ("Click to have the CAPTCHA read aloud") was rendered as visible text instead of a hover tooltip. Added global CSS rule to hide by default and show on hover.
- Fix [Templates]: Compact template (6) reload and audio icons were separated instead of grouped on the right side. Fixed flex layout so icons stay together.
- Fix [Templates]: Compact template (6) input field was too short and had no border. Added proper border styling and flex layout for hint text + input inline.
- Fix [Templates]: Audio button icon was misaligned vertically with reload icon across all templates. Added `line-height: 0; display: inline-flex; align-items: center` to audio buttons.
- Fix [Templates]: Removed `padding-right: 0` override on `.c-header > div` for all v2 templates (5–9) which caused math captcha question mark to stick to the container edge.
- Fix [Captcha Pool]: Pool entries now store the template ID they were generated for. On retrieval, only entries matching the current template are used, preventing stale images with wrong colors after template changes.

= 2.6.4 =
- Fix [Charts]: Fixed empty/blank Recharts charts on Dashboard and Analytics pages. MySQL returns `COUNT(*)` as strings via `$wpdb->get_results()`, but Recharts requires numeric values for `dataKey`. All chart data (LineChart, BarChart, PieChart) now casts `entry.count` to `Number()` before rendering.
- Fix [Admin UI]: Fixed `useSettingsContext must be used within a SettingsProvider` crash on API and other pages. The context hook now returns a safe loading-state fallback instead of throwing, preventing app crashes from stale browser cache or module loading race conditions.
- Fix [Admin UI]: Hidden "Kostenlose Trial starten" section on the API page when an API key is already configured. Previously clicking "Trial starten" with an active key returned a 409 error.
- Fix [Admin UI]: Replaced text-based status badges in the Mail-Log table with compact status icons (CheckCircle, ShieldAlert, RotateCw) and hover tooltips. Fixes "Erneut gesendet" badge text wrapping to a new line in narrow columns.
- New [Translations]: Built .po/.mo files for 12 previously missing locales: Bulgarian (bg_BG), Czech (cs_CZ), Danish (da_DK), Finnish (fi), Croatian (hr), Hungarian (hu_HU), Dutch (nl_NL), Polish (pl_PL), Romanian (ro_RO), Slovak (sk_SK), Slovenian (sl_SI), Swedish (sv_SE). All 25 languages now have compiled translation files at 100% coverage (492/492 strings).

= 2.6.3 =
- Fix [Type Safety]: Fixed `is_enabled()` type comparison bug in JavaScript, Browser, and Multiple Submission protection modules. Settings value was not cast to `(int)` before comparison, causing string `'0'` (disabled) to evaluate as truthy — these modules could not be reliably disabled via settings.
- Fix [Type Safety]: Fixed `Api::is_enabled()` default value from `1` (enabled) to `0` (disabled). Previously, if `beta_captcha_enable` was not explicitly set, the API mode defaulted to active, potentially bypassing all local protection modules.
- Fix [Timer]: `Timer_Validator::get_validation_time()` now reads the `protection_time_ms` setting instead of using a hardcoded 2000ms value. The UI default is 500ms — previously the setting had no effect.
- Fix [Multiple Submission]: `Multiple_Submission_Validator::get_validation_time()` now reads the `protection_time_ms` setting instead of using a hardcoded 2000ms value.
- Fix [Context]: Added missing `set_context()`/`clear_context()` calls in Elementor, Ultimate Member, and WP Job Manager controllers. Without context, spam blocks were logged with empty `form_plugin` and mail logging could not identify the source integration.
- Fix [Analytics]: Fixed protection module label mapping in the Analytics block log UI. The database stores module names with `-validator` suffix (e.g. `timer-validator`, `captcha-validator`), but the React UI was looking for short names without suffix (e.g. `timer`, `captcha`). All labels, badge variants, and pie chart entries now use the exact database values.
- Fix [BlockLog]: Block reason detail now uses the module's specific error message (`$modul->get_message()`) instead of the generic static map description. For content rules, this means the actual rule violation (e.g. "The word 'viagra' is blacklisted") is logged instead of the generic "Content matched a blacklist rule".
- Fix [Mail-Log]: CF7 sent mail logging now uses the universal `wp_mail` filter instead of the CF7-specific `wpcf7_mail_components` hook. This ensures all form plugins (CF7, WPForms, Elementor, Gravity Forms, Fluent Forms, Avada, JetFormBuilder, WooCommerce) are covered with a single hook.
- Fix [Mail-Log]: Sent mail logging now captures the actual resolved mail data (recipient, subject, body) from `wp_mail()` instead of raw CF7 templates with unresolved `[tags]`.
- Fix [Mail-Log]: Form data (posted fields) is now stored for sent mails, enabling proper review and resend from the admin UI.
- Fix [Mail-Log]: Added `table_exists()` check in `MailLog::log()` to prevent silent failures on fresh installations before the upgrade migration runs.

= 2.6.2 =
- New [Mail-Log]: Added complete mail logging system for tracking sent and blocked form submissions. Stores sender, recipient, subject, body, headers, attachments, form data, IP hash, and block reason in a dedicated database table (`f12_mail_log`).
- New [Mail-Log]: Blocked submissions are automatically logged from the central `Protection::is_spam()` method, capturing block reason and form data. Works across all supported integrations (CF7, WPForms, Elementor, Gravity Forms, Fluent Forms, Avada, WooCommerce, WordPress core).
- New [Mail-Log]: Successfully sent Contact Form 7 mails are logged via `wpcf7_mail_components` filter, capturing the fully resolved mail data (recipient, sender, subject, body with all [tags] replaced, headers, attachments). Previously used `wpcf7_before_send_mail` which only had raw templates with unresolved CF7 tags.
- New [Mail-Log]: Added "Resend" functionality — any mail log entry (sent, blocked, or previously resent) can be resent directly from the admin UI via `wp_mail()`. Attachments are only included if files still exist on disk. Status is updated to "resent" with audit log entry.
- New [Admin UI]: Added dedicated "Mail-Log" page with summary cards (total, sent, blocked, resent), filterable/searchable table (status, form plugin, free-text search with debounce), pagination, and auto-refresh controls.
- New [Admin UI]: Mail-Log detail dialog shows full message body, form data (JSON), block reason, IP hash, headers, and action buttons (resend with confirmation, delete with double-confirmation).
- New [Admin UI]: Bulk actions for Mail-Log — select individual entries via checkboxes or "select all" on the current page. Bulk resend (with confirmation dialog) and bulk delete (with toggle-switch double-confirmation) for multiple entries at once.
- New [Admin UI]: Delete confirmation uses a double-confirm pattern: a toggle switch "Ich verstehe, dass dieser Eintrag unwiderruflich gelöscht wird" must be activated before the delete button becomes clickable. Applied to both single and bulk delete.
- New [Admin UI]: Added Mail-Log sidebar navigation entry with Mail icon (between Analytics and Audit Log).
- New [Admin UI]: Added "Mail-Logging" settings section in Advanced Settings with GDPR warning banner, enable/disable toggle, sub-toggles for sent/blocked logging, and configurable retention period (1–365 days).
- New [Admin UI]: Added Mail-Log cleanup options in Data Cleanup page ("Alle Mail-Logs löschen", "Blockierte Mail-Logs löschen") with entry counts.
- New [REST API]: Added 5 admin-only Mail-Log REST endpoints: `GET /mail-log/entries` (paginated with filters), `GET /mail-log/summary` (counts by status), `GET /mail-log/entry/{id}` (full entry with body), `DELETE /mail-log/entry/{id}`, `POST /mail-log/resend/{id}`.
- New [Core]: `MailLog` PHP class (`core/log/MailLog.class.php`) with full CRUD operations, table existence checks, `suppress_errors` for resilient inserts, and separate `log_blocked()`/`log_sent()` convenience methods.
- New [Core]: Automatic Mail-Log cleanup integrated into `Log_Cleaner` cron job with configurable retention (`protection_mail_log_retention`, default 30 days).
- New [Settings]: 4 new settings: `protection_mail_log_enable` (default: off), `protection_mail_log_sent` (default: on), `protection_mail_log_blocked` (default: on), `protection_mail_log_retention` (default: 30 days).
- Fix [API Fallback]: Frontend assets (`client.js` vs local JS bundle) now respect the API health check transient. When the SilentShield API is unreachable, the local JS bundle (with JavaScriptProtection, SubmitGuard, form handlers) is loaded instead of the API client — fixing missing `js_end_time` timestamps, broken captcha reload, and CORS errors from offline API endpoints.
- Fix [REST API]: Increased admin endpoint rate limit from 10 to 60 requests per minute to prevent rate-limit errors when using auto-refresh or loading pages with multiple concurrent API calls.
- Improvement [Settings]: Changed default for `protection_global_asset_loading` from 0 to 1, ensuring frontend JS/CSS assets are loaded on all pages by default. Prevents issues where captcha fields render but JS handlers are not loaded.

= 2.6.1 =
- Fix [API]: Fixed settings type mismatch between PHP REST API and React admin UI. The REST save handler converted all values to strings via `sanitize_text_field()`, but the React frontend used strict equality (`=== 1`) to check toggle states. This caused API-related toggles (API enable, Shadow Mode) to always appear as "off" after saving, even though the value was correctly stored. The server now preserves native integer, float, and boolean types during save, and the React UI uses `Number()` coercion for defensive comparison.
- Fix [API Fallback]: When the SilentShield API is enabled but unreachable (e.g. dev/staging environment offline, network issues, server errors), the plugin now automatically falls back to all local protection modules (Captcha, Timer, JS detection, Browser detection, IP blocking, Content rules, etc.) instead of silently disabling all protection. Previously, an active API key with an unreachable API resulted in no captcha output and no spam protection at all.
- New [Admin Notice]: Added a dismissible admin warning that appears when the API fallback is active, informing administrators that the SilentShield API is unreachable and local protection modules have been automatically reactivated. Includes a link to the API settings page.
- New [API Health Check]: Added lightweight API reachability check with transient caching (5 min on success, 2 min on failure) to avoid hitting the API on every request. HTTP 2xx–4xx responses are treated as "reachable" (the API is up, even if the key is invalid); only connection errors and 5xx responses trigger the local fallback.
- New [Audit Log]: API health failures are now logged as `API_HEALTH_UNREACHABLE` (connection error) or `API_HEALTH_SERVER_ERROR` (5xx response) audit events with endpoint and error context.
- Fix [Database]: Added missing upgrade migration for BlockLog and AuditLog tables. Sites that upgraded to 2.6.0 without deactivating/reactivating the plugin had missing database tables, causing `wpdb` errors on the Audit Log and Analytics pages and cascading rate-limit failures.
- Fix [Database]: AuditLog and BlockLog query methods now gracefully return empty results when the underlying table does not exist, preventing HTML error output from leaking into REST API JSON responses.
- Fix [Database]: `$wpdb->suppress_errors()` is now used around AuditLog and BlockLog insert operations to prevent database error HTML from breaking REST responses when tables are missing.
- Fix [Admin UI]: Fixed IP hash string overflowing into adjacent columns in the block detail and audit event detail dialogs. Long hash strings now wrap automatically via `break-all`.
- New [Admin UI]: Added "Erweitertes Tracking" hint banner on the Analytics page. When detailed tracking is disabled (default), a dismissible warning explains that Analytics requires this setting and links directly to the Advanced settings page to enable it.
- New [Admin UI]: Added auto-refresh controls to both Analytics and Audit Log pages. A tab bar allows selecting refresh intervals (Aus / 5s / 15s / 30s) and a manual refresh button with spin animation is available for on-demand data reload.

= 2.6.0 =
- New [Audit Log]: Added always-active audit log system (`AuditLog` class) that records admin and system events (settings changes, cron runs, activation/deactivation, rate limiting, API errors, DB errors, trial events, i18n failures) to a dedicated database table with throttling, sensitive data masking, and error_log fallback.
- New [Admin UI]: Added Audit Log admin page (SilentShield → Audit Log) with summary cards, filterable/paginated event table, severity color-coding, and slide-out detail panel with JSON context viewer.
- New [Admin UI]: Dashboard widget now shows the 5 most recent warnings/errors/critical events with a direct link to the full Audit Log page.
- New [REST API]: Added 2 new admin-only REST endpoints (`/audit/entries`, `/audit/summary`) with filters for time range, event type, severity, and pagination.
- New [Core]: API verification errors (`Api.class.php`) now log `API_VERIFY_UNREACHABLE` audit events with endpoint and fail-mode context.
- New [Core]: Trial activation failures now log `TRIAL_API_UNREACHABLE`, `TRIAL_API_ERROR`, and `TRIAL_INVALID_RESPONSE` audit events.
- New [Core]: All 6 cron jobs now have bookend audit hooks that log start/completion with execution timing and catch/log failures as `CRON_FAILED` events.
- New [Core]: Telemetry, monthly report, and weekly report cron handlers now audit-log send failures and unexpected responses.
- New [Core]: Translation loading failures now log `TRANSLATION_LOAD_FAILED` audit events with locale and path context.
- New [Core]: BlockLog database operations (`log`, `get_entries`, `get_overview`, `cleanup`) now audit-log insert/query/cleanup failures as `BLOCKLOG_*` events.
- New [Settings]: Added configurable "Audit Log Retention" setting (7–365 days, default 90) under Settings → Extended. Log cleanup respects this setting automatically.
- Improvement [Core]: Log_Cleaner now also cleans up AuditLog and BlockLog tables during the weekly cron job, respecting their individual retention settings.
- New [Audit Log]: API key validation failures (`API_KEY_VALIDATION_UNREACHABLE`, `API_KEY_INVALID`) are now audit-logged when the SilentShield key validation endpoint is unreachable or returns invalid.
- New [Audit Log]: API key lifecycle changes are now audit-logged: key set (`API_KEY_SET`), key removed (`API_KEY_REMOVED`), key rotated (`API_KEY_CHANGED`).
- New [Audit Log]: API mode and Shadow Mode toggles are now audit-logged (`API_MODE_ENABLED/DISABLED`, `SHADOW_MODE_ENABLED/DISABLED`).
- New [Audit Log]: API verify HTTP error responses (4xx/5xx) and unparseable JSON are now audit-logged as `API_VERIFY_ERROR_RESPONSE`.
- New [Audit Log]: Trial expiration is now proactively audit-logged once as `TRIAL_EXPIRED` when the admin visits the Beta settings page after the trial period ends.

= 2.5.0 =
- New [F2P]: Added Shadow Mode for statistical estimation of API-blocked spam. Samples 30% of passed submissions and projects weekly totals. Enable under Settings > Beta. Dormant API call behind `F12_CAPTCHA_SHADOW_API_LIVE` constant.
- New [F2P]: Added Weekly Email Report (opt-in) with block statistics, top 3 reason codes, breakdown by protection type, and upgrade CTA with UTM tracking. Enable under Settings > Extended > Weekly Report.
- New [Analytics]: Shadow Mode comparison section on Analytics page showing estimated additional API catches with 4 stat cards and upgrade CTA.
- New [Beta]: Shadow Mode toggle added to Beta settings page.

= 2.4.0 =
- New [Analytics]: Added Analytics admin page (SilentShield → Analytics) with block statistics overview, timeline chart, protection module breakdown, reason code frequency, and paginated block log with detail drawer.
- New [Analytics]: 4 new REST API endpoints for analytics data (summary, timeline, reasons, log) with admin-only access and rate limiting.
- New [Analytics]: Score breakdown visualization for API-mode blocks showing 7 sub-score categories with color-coded progress bars.
- New [Analytics]: Time range selector (7/30/90 days) for all analytics views.
- New [Privacy]: Added "Disable Log Anonymization (Debug Mode)" toggle in Extended Settings → Detailed Tracking. When enabled, email addresses and IP addresses are stored in plain text in submission logs and the block log, allowing admins to identify blocked users. Disabled by default. Includes GDPR/DSGVO privacy warning. Passwords are always masked regardless of this setting.
- New [Core]: Added `Protection::has_module()` method to safely check module availability before access.
- Fix [Admin UI]: Fixed fatal error "Module captcha-validator does not exist" on Extended Settings page when SilentShield API mode is active. The Captcha management section now shows an informational message in API mode instead of crashing.

= 2.3.6 =
- New [Accessibility]: Added Audio CAPTCHA feature using the Web Speech API. A speaker button next to the CAPTCHA allows visually impaired users to have the challenge read aloud via browser-native text-to-speech. Privacy-first — no external API calls. Disabled by default, enable under Settings > Protection > Audio Accessibility.
- New [Accessibility]: Added hover/focus tooltip on the audio button ("Click to have the CAPTCHA read aloud") so users understand the button's purpose before clicking.
- New [REST API]: Added rate-limited `POST /captcha/audio` endpoint (5 req/min per IP) that returns spelled-out characters for image CAPTCHAs and the formula for math CAPTCHAs.
- Improvement [Image CAPTCHA]: When Audio CAPTCHA is enabled, the character pool is restricted to lowercase letters + digits to avoid ambiguity (TTS cannot distinguish upper/lowercase). Existing pooled CAPTCHAs with uppercase characters are automatically discarded and regenerated.
- Improvement [Translations]: Added all new Audio CAPTCHA strings to all language files (de_DE, de_DE_formal, es_ES, fr_FR, it_IT, pt_PT).

= 2.3.5 =
- Fix [Fluent Forms]: Fixed JavaScript protection failing for Conversational Forms (`[fluentform type="conversational"]`). Conversational Forms render as a Vue.js app inside a `<div>` instead of a `<form>` element, so the regular `render_item_submit_button` hook and the JS form discovery (`querySelectorAll("form")`) never fired. Timing fields (`php_start_time`, `js_start_time`, `js_end_time`) are now injected via `jQuery.ajaxPrefilter` directly into the inner `data` POST parameter where the PHP backend expects them. Hooks into both `wp_footer` (embedded forms) and `fluentform/conversational_frame_footer` (standalone pages).

= 2.3.4 =
- Fix [Templates]: Reload button inline styles were stripped by `wp_kses()` CSS property filtering (`safecss_filter_attr`), causing `display:inline-flex`, `align-items`, `box-sizing` etc. to be removed. Reload button HTML is now output directly (all values are escaped at construction via `esc_attr`/`esc_url`), ensuring per-form and per-integration style overrides work correctly.
- Fix [CSS]: Removed hardcoded `width:32px; height:32px; display:flex; background-color` from template-1 `.c-reload a` CSS rule that overrode per-form settings. All visual properties are now controlled exclusively via inline styles from `get_reload_button()`.
- Fix [CSS]: Removed `!important` declarations on reload button icon dimensions in template-1 CSS that prevented per-form icon size overrides from taking effect.
- Fix [CSS]: Removed redundant global inline CSS (`wp_add_inline_style`) for reload button styling that conflicted with the hierarchical settings resolution (form > module > global).
- Fix [CSS]: Reload button icon is now vertically centered using flexbox (`display:inline-flex; align-items:center`) instead of `margin-top:5px`.
- Improvement [CSS]: All reload button inline styles now use `!important` to prevent theme and plugin CSS from overriding configured values (background-color, padding, border-radius, display, icon dimensions, margin, max-width).
- Fix [Core]: Replaced deprecated `CF7Captcha::getInstance()` calls in UI_Extended with `CF7Captcha::get_instance()`.

= 2.3.3 =
- New [Admin UI]: Added full reload button styling options: background color, border color (color pickers), padding, border radius, and icon size (number inputs). All settings have backward-compatible defaults.
- New [Admin UI]: All reload button styling settings can be overridden per integration (CF7, Avada, WPForms, etc.) and per individual form via the existing override panel system.
- New [Admin UI]: Added live preview for the reload button in global settings and all override panels (integration + form level). Changes are reflected in real-time.
- New [Admin UI]: Added "Asset Loading" section with global toggle to force-load all plugin assets (CSS/JS) on every page, useful when automatic form detection fails.
- New [Admin UI]: Added custom URL path exceptions textarea. Define URL paths (one per line) where assets should always be loaded, e.g. for custom login pages (WPS Hide Login) or exotic page builders.
- Improvement [Core]: `should_load_assets()` now checks global asset loading toggle and custom URL paths before falling back to automatic form detection.

= 2.3.2 =
- Fix [Captcha]: Fixed reload button href being stripped by wp_kses. Changed `javascript:void(0)` to `#` to be compatible with WordPress HTML sanitization.

= 2.3.1 =
- New [Admin UI]: Added per-integration and per-form override settings. Protection settings can now be customized at the integration level (e.g. all CF7 forms) or for individual forms, with hierarchical inheritance (Global > Integration > Form).
- New [Admin UI]: Added slide-in configuration panels on the Extended and Forms admin pages. Click "Configure" next to any integration or form to open the override panel.
- New [Admin UI]: Added Forms admin page listing all discovered forms across installed integrations (CF7, WPForms, Elementor, Gravity Forms, etc.) with override status badges.
- New [REST API]: Added `POST /overrides/save` endpoint for persisting integration and form-level override settings via AJAX with admin permission checks and rate limiting.
- New [Core]: Added hierarchical settings resolution system (`Settings_Resolver`) that merges Global, Integration, and Form-level settings with proper inheritance.
- New [Core]: Added form discovery system (`Form_Discovery`) that detects forms across all supported integrations.
- New [Core]: Added `ProtectionContext` for per-form setting resolution during spam validation, enabling form-specific protection behavior.
- Fix [Compatibility]: Resolved "Translation loading triggered too early" PHP Notice on WordPress 6.7+ that caused "Cookies are blocked due to unexpected output" errors on login pages, breaking compatibility with plugins like SecuPress Move Login.
- Fix [JavaScript]: Resolved global scope collision where the bundled `WPForms` class overwrote `window.WPForms`, breaking the WPForms plugin. Build output is now wrapped in an IIFE.
- Improvement [Translations]: Added 57 new translatable strings for override panels and Forms page to all language files (de_DE, de_DE_formal, es_ES, fr_FR, it_IT, pt_PT).
- Improvement [Compatibility]: Updated integration controllers (Avada, CF7, FluentForms, Gravity Forms, WPForms) with form discovery support and per-form protection context.

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
