== SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce) ==
Contributors: forge12
Donate link: https://www.paypal.com/donate?hosted_button_id=MGZTVZH3L5L2G
Tags: captcha, spam protection, honeypot, contact form 7, fluentform, wpforms, elementor, woocommerce, anti-spam
Requires at least: 5.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.15.3
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
- **Universal support** – Works with Contact Form 7, WPForms, Elementor, Formidable, Ninja Forms, Forminator, Kadence, WooCommerce, and more.
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
- Elementor Pro Forms (classic widget and v4 "atomic" forms)
- Gravity Forms
- Fluent Forms
- Formidable Forms
- Ninja Forms
- Forminator
- JetFormBuilder
- Kadence Blocks (Advanced Form)
- Jetpack Forms (contact form block and shortcode)
- Avada (Fusion Builder) Forms

**Newsletter:**
- MC4WP – Mailchimp for WordPress (signup forms)

**WooCommerce:**
- Checkout – block (the default for new shops since WooCommerce 8.3)
- Checkout – classic (incl. PayPal Payments)
- Login
- Registration
- Lost password
- Account details

**WordPress Core:**
- Login form (wp-login.php)
- Registration form
- Lost password form
- Comment forms (including WooCommerce product reviews)

**Communities & Forums:**
- bbPress (new topics and replies)
- BuddyPress (member registration)

**Donations:**
- GiveWP (classic donation form; the visual-builder form is not yet covered)

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
9. **Gibberish Detection** – Recognises submissions filled with random characters, the kind a bot writes when it only needs the form to go through. Unlike every other check it does not depend on the sender's browser, so a bot driving a real browser cannot pass it by playing along. Starts in observation mode and blocks nothing until you switch it on.
10. **Whitelist** – Skip validation for admins, logged-in users, or specific emails/IPs
11. **SilentShield API** – Cloud-based spam detection ([silentshield.io](https://silentshield.io/?utm_source=wp-org&utm_medium=readme&utm_campaign=feature-list))

---

## The Promise

SilentShield is not "just another plugin."
It's an invisible wall against the background noise of the internet.

Activate once – and your forms are human again.

---

## Want more? SilentShield API

Everything above is free and stays free. No feature is held back, no submission limit, no account needed.

What the free plugin cannot do is recognise a bot that behaves like a person — one driving a real browser, solving the captcha, typing at human speed. Rules can only catch what looks wrong, and those do not.

The **SilentShield API** answers that with behaviour analysis and browser fingerprinting, scored in the cloud, and it usually decides without showing anyone a captcha at all. Switch it on and the local protections stay exactly where they are as a fallback — if the API is ever unreachable, your forms are still protected.

There is a free plan and a trial, and you can see what it would have caught before you pay for anything: turn on Comparison Mode and the plugin logs what the API *would* have decided, alongside what your local rules actually did.

👉 [Plans and free trial at silentshield.io](https://silentshield.io/pricing?utm_source=wp-org&utm_medium=readme&utm_campaign=upsell-section)

---

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
- Every transmission described below is optional and can be switched off in the plugin settings.
- The plugin's built-in Privacy page shows which of these are active on your site, what that means, and gives you ready-made privacy-policy snippets in 25 languages.

**1. Plugin statistics** (setting "Telemetry")
Anonymous, no personal data, sent at most once a day:
- `plugin_slug`, `plugin_version`
- `snapshot_date`
- `settings_json` (anonymized config – only boolean/integer flags, no free-text)
- `features_json` (enabled features)
- `created_at`, `first_seen`, `last_seen`
- `counters_json` (spam events)
- `wp_version`, `php_version`, `locale`

**2. AI-crawler observation** (setting "Observe AI crawlers", on by default; `SILENTSHIELD_OBSERVER` to force off)
Sent only for requests identified as an AI crawler — never for your human visitors. Delivered after the page has already been sent to the visitor:
- `ua` (the crawler's User-Agent), `ip`, `path` (without query string), `method`
- The IP address is pseudonymised on the server (daily keyed hash) and never stored in the clear.

**3. Blocked-request reports** (only with "Block AI crawlers (enforce)" on; follows the observation setting above)
Same fields as (2), plus the outcome (`deny` / `throttle`), for every request enforcement turned away. Note that a block rule which is not restricted to a specific crawler can also catch a human visitor — that request is then reported in the same way.

**4. Form assessment** (only with the SilentShield API enabled)
See the API snippet on the plugin's Privacy page for the full description.

---

== Changelog ==
= 2.15.3 =
- Fix [Privacy]: **The plugin's own settings screens loaded a font from Google's servers.** Opening any SilentShield page in the WordPress admin fetched the "Inter" typeface from `fonts.googleapis.com`, and a request to Google's servers carries the IP address of whoever made it. On a plugin whose purpose is data protection this should never have been the case, and under the GDPR it is the kind of transfer that needs a legal basis nobody had established. The font now ships inside the plugin and is loaded from your own server; not a single request leaves your site any more. Only administrators opening SilentShield's settings were affected — never visitors, and never anyone filling in one of your forms, because the file was only ever loaded inside the admin area. Nothing changes in how the settings look, and nothing needs to be configured. The font has been part of the plugin since version 2.10.0, so any site running that version or later was affected; if your data protection documentation lists the services your site contacts, this entry can be removed from it.

= 2.15.2 =
- Fix [Protection]: **On some hosts, every form submission failed with a server error.** The gibberish detection used PHP's `mbstring` extension, which is optional and which WordPress itself does not require — it supplies replacements for the two functions it needs and no more. Where the extension was missing, the check ran until it reached a function nobody had replaced and stopped the request dead. The visitor pressed Send and got an error page; no email arrived, and nothing in the plugin's own logs said why. It made no difference that the detection ships in monitoring mode and was not entitled to reject anything: it never got as far as a verdict. The check no longer uses the extension at all. Separately, the gibberish detection can now no longer end a submission by failing, whatever the reason — if it cannot finish, the submission is let through and the reason is written to the log. Only hosts without `mbstring` were affected; sites where forms have been working are unaffected.
- Improvement [Protection]: While rewriting the above, six characters turned out to have been counted as consonants: the Turkish `ı` and `İ`, the Nordic `ø`, and long vowels such as `ā` and `ū`. Names written with them looked slightly less pronounceable to the detection than they are — a small bias against Turkish, Baltic and Scandinavian names, in the one direction that costs a real enquiry rather than a spam. They now count as the vowels they are.
- Fix [Forminator, Ninja Forms]: When a submission was refused, the explanation was attached to the form's first field so it would appear next to it — but "first" was taken literally, and a form that begins with a hidden field (a tracking value, a pre-filled ID) had the message attached to something nobody can see. The visitor pressed Send, no email arrived, and nothing at all appeared on screen. Hidden fields are now skipped. This is the same silent failure fixed for Avada in 2.15.0, arrived at by a different route; it was found by checking whether that bug could exist elsewhere, and these two are where it could.
- Fix [Elementor]: The same check on a form built entirely from hidden fields left nowhere to put the message, and it was dropped. It is now shown above the form instead.

= 2.15.1 =
- Fix [Translations]: **French sites were not seeing the plugin's own translations at all.** When WordPress.org publishes a community translation for a plugin, WordPress uses it *instead of* the one the plugin ships — not in addition to it. Anything the community translation happens not to cover then falls back to English, even where the plugin has a complete translation for that language sitting right there. French has had a community translation since 7 August, so French sites had quietly been showing English wherever it had a gap. Both are now used together: the community translation still comes first, and the plugin's own fills whatever is left. Nothing changes for languages without a community translation. This affects Persian in the same way, and would have hit any other language the moment one appeared.
- Fix [Translations]: The list of integrations under Forms was in English no matter what language your site is in — "WordPress Comments", "WooCommerce Checkout", "Password Reset (WordPress & WooCommerce)" and the other twenty-four. The names had never been marked as translatable, so no translation of them existed in any language, and there was nothing a translator could have done about it. They are translated now in all twenty-five languages the plugin ships. Product names stay as they are: bbPress is still bbPress, only the part in brackets is translated.
- Improvement [Admin]: The settings screen for the SilentShield API was still called "Beta" in the menu, which read as a warning about the feature rather than a label. It is now "API / SilentShield", the same name the newer admin interface has used for a while. Only the name changed — the page, its address and your settings are untouched.
- Fix [Translations]: The plugin's own menu was in English too — Dashboard, Analytics, Audit Log, Beta, Extended and Forms. Help and Upgrade were translated, which is what made it look like a partial translation rather than a missing one.
- Fix [Translations]: On the default captcha template, the line under the puzzle ("Please enter the characters shown in the CAPTCHA…") and the reload button's label were always English — for your visitors, on every site, in every language. They had been written in a way that made them invisible to the translation files, so no language ever had them. This is the only one of these fixes your visitors will notice rather than you.
- Fix [Translations]: Eight messages on the dashboard, the ones confirming that logs, timers, captchas or IP bans have been cleared, were spelled with a text domain the plugin does not use. They could not be translated in any language and never had been. Fixed and translated.
- Fix [Translations]: Around ninety further texts had never reached the translation files at all: the audit log, the setup notice, the AI-crawler settings and their data-protection notes, the weekly report, the feedback question shown on deactivation, and the descriptions under most of the protection settings. If you have ever wondered why a settings page was half in your language and half in English, this is why. All of them are translated now.
- Fix [Translations]: One admin notice was in German for everyone, in every language, including on German sites where it only looked correct by accident — the one that appears when the SilentShield API cannot be reached and the local protection modules take over. It is now written in English and translated like everything else.
- Fix [Translations]: "%d Overrides" and "%d forms found" on the Forms screen could not be translated because of a gap in the tooling that reads the source code: it never looked for texts that change with a number. Both are translated now, in each language's own plural forms — three of them in Polish and Czech, four in Slovenian and Maltese.

= 2.15.0 =
- New [Integrations]: Jetpack Forms is now supported — both the contact form block and the older [contact-form] shortcode, which are the same form underneath. Jetpack is installed on several million sites, and where one of them has a contact page, this is usually the form on it. **You have to switch this on**, as with every integration: it appears under Forms as "Jetpack Forms".
- New [Integrations]: bbPress is now supported, for new topics and for replies. Forums that allow guests to post are among the most reliably spammed things on a WordPress site, because every post is public, permanent and carries links — and unlike a contact form, nobody has to read the spam for it to do damage.
- New [Integrations]: BuddyPress member registration is now supported. An open community signup collects fake profiles rather than emails: they stay on your site, they are indexed, and clearing them out later means going through your member list by hand.
- New [Integrations]: GiveWP donation forms are now supported. Donation forms are not spammed the way a contact form is — they are used for card testing, where somebody runs stolen card numbers through a small donation to find out which ones still work. You do not notice it in an inbox; you notice it in chargebacks and in a payment processor asking questions. This is worth switching on even on a site nobody would bother sending spam to.
- Fix [Protection]: A refused submission could tell the visitor that the captcha was not correct without saying which check had refused it — the message stopped at the colon and nothing followed it. The protection itself worked correctly throughout; only its name was missing, and only on some forms, which is why it went unnoticed for so long. It was most likely to appear exactly where it costs the most: on a donation or payment form, where somebody who simply mistyped is left with a refusal and nothing to correct. Every rejection now names the check that made it.
- Fix [Protection]: On a form that has no captcha field, every submission after the first was turned away as a duplicate. The duplicate-submission check hands the form a one-time token and discards it the moment it is used, so the browser has to be given a fresh one after each submission — and it only ever was as a side effect of the captcha being redrawn. Where there was no captcha to redraw, nothing replaced the token, and the form kept sending the used one. On forms that submit without reloading the page, which is most of them now, the effect was immediate: the first enquiry arrived, the second was refused, and the mail log recorded it as a duplicate submission although the visitor had written something entirely different. Refreshing the page cleared it, so it looked intermittent. The token is now renewed independently of whether a captcha is present.
- Fix [Protection]: The same token is also renewed on pages served from a full-page cache, and after a browser back/forward restore. A cached page hands every visitor the same token, so the first person to submit used it up and everyone after them was refused — on a cached site with this check enabled, that meant one successful submission per cache refresh. Both cases now fetch a fresh token when the page opens. This also restores the intended behaviour of the minimum-time check on cached pages, where the countdown previously began when the cache was written rather than when the visitor arrived.
- Fix [Avada]: A submission Avada refused could fail completely silently — the visitor pressed Send, nothing appeared, and no email was sent. The refusal message is attached to one of the form's fields for Avada to display next to it, and the field chosen was worked out by discarding everything recognisable as hidden. On a site running a second anti-spam plugin, the field that survived was that plugin's honeypot, which is positioned off-screen by design, so the message was placed somewhere no one could see it. The form's own field list is now used to make that choice, which cannot pick a field the form does not visibly contain; where no visible field exists, the message is shown above the form instead of being lost.
- Improvement [Logging]: "Duplicate submission" in the mail log stood for three unrelated things — a genuinely reused token, a token this site never issued, and a form sent back faster than the configured minimum. The commonest of the three was the stale token described above, which is not a duplicate at all, so the log confirmed a diagnosis that was wrong. Each is now recorded under its own reason, with a line saying what actually happened and what to look at. Nothing changes about which submissions are blocked.

- Note [GiveWP]: This covers the classic donation form, not the newer one built in GiveWP's visual form builder, which has been the default for new forms since GiveWP 3.0. Both still ship, and which one you have depends on when the form was made. **If your form was built in the visual builder, it is not protected by this** — please check rather than assume, because we would rather tell you plainly than let you believe a form is covered when it is not. The newer form assembles what it sends in the browser and only includes fields it knows about, so the timing checks that catch automated donations cannot travel with it. We are working on it.
- Note [bbPress]: Whether a rejected post shows a reason depends on your theme. bbPress hands error messages to the theme to display, and not every theme does — the one we tested against shows nothing, for bbPress's own errors just as much as for the captcha's. If a post is refused and nothing appears to happen, that is what you are seeing; the post is not created either way.
- Note [Jetpack]: A submission that fails the captcha is turned away with a message the sender can read, rather than being quietly filed as spam. Jetpack offers both, and the quiet option is the wrong one here: the person who mistyped a captcha is usually a customer, and filing their enquiry away while showing them a success message means they believe they have written to you and you never find out that they did.

= 2.14.1 =
- Fix [Protection]: On English-language sites, a blocked submission named its reason with an internal identifier rather than words — "Captcha not correct: captcha-protection", or "rule-protection: blacklist" where one of your own filter rules had matched. The person reading that has usually just mistyped a captcha, and to them it looks like the website is broken rather than like an explanation. Every other language the plugin ships already had proper wording; English was the one that did not. The nine messages now read as plain labels: "Captcha check", "IP check", "Timing check", "Duplicate submission", "Filter rule: ..." and so on. Nothing changes about which submissions are blocked — only about what the visitor is told. It became more noticeable in 2.14.0, because the message for a rate-limited address is now shown for the whole duration of the block instead of only on the submission that triggered it.

= 2.14.0 =
- New [WooCommerce]: The block checkout is now protected. This is the checkout WooCommerce gives every new shop since version 8.3, and until now it received no protection at all — not a weakened version, none. Captcha, honeypot, timing checks and blacklists were all switched on and doing their work everywhere else on the site, while an order placed at the checkout itself went through untouched. Nothing indicated this: the plugin listed WooCommerce as protected, because it was — the older, classic checkout was. The two are separate pieces of software that happen to sell the same basket, and the block checkout offers none of the places the classic one does for a plugin to step in. **You have to switch this on.** It appears as its own entry, "WooCommerce Block Checkout", under Forms, next to the existing "WooCommerce Checkout" — turning that one on does not cover it, and never did. If you are unsure which checkout your shop uses: open your checkout page in the editor, and if it shows a single "Checkout" block rather than a shortcode, it is the block one.
- Improvement [WooCommerce]: If your checkout block sits on a page other than the one WooCommerce has been told is your checkout — a landing page, a one-page shop, a custom funnel — the plugin previously loaded nothing there at all, so no protection could run even where it was configured. It now recognises the checkout block wherever it is placed.
- Fix [Protection]: When the rate limit blocked an address, only the submission that triggered the block said so. Every attempt for the rest of the block — an hour by default — was turned away with whatever generic wording your form plugin uses when it is given no reason, so a site owner testing their own form a few times in a row locked themselves out and then had a form that refused everything and explained nothing. One person spent hours switching protections off one at a time to find out which one it was. The block now names itself for its whole duration, exactly as the first rejection always did.
- Fix [Logging]: The anonymous measurements the new content check records were being written to the same place as the log of blocked submissions. Those two are governed by separate switches with opposite defaults — measuring is on, the block log is off until you ask for it — so on a normal installation that table filled up with measurements and contained not a single actual block. Anyone opening it while looking for the reason a submission was turned away found only rows belonging to submissions that had been let *through*, and at least one person reasonably concluded the content check had rejected their enquiry when it had done the opposite. Measurements now have their own place. Existing rows are moved across on update; nothing is lost, and the figures shown on the Analytics screen were correct throughout and do not change.
- Improvement [Protection]: The new content check judged a field holding a single long word by that word alone, which is a poor basis for a decision when the field is a name, a town, or a German sentence whose only long word is something like "Rechnungsanschrift". A lone word now has to look far more clearly machine-generated before it counts against the field; where a second long word is present, nothing changes. Real spam is unaffected — it fills whole fields with generated text, and every one of those was rechecked against this. The explanation written alongside each measurement also says plainly what happened to the submission and what it counted, instead of "1 of 1 words", which meant "one of the one words long enough to judge" and was read, understandably, as a word count.

= 2.13.0 =
- Fix [Avada]: Notification emails from Avada forms arrived with the plugin's own hidden fields listed underneath the enquiry — rows like "F12 Timer" followed by a long string of characters. Nothing was broken and no data was at risk, but to the person reading the email it looked like a malfunctioning website, which is a poor thanks for protection that was otherwise doing its job. Avada is the only form plugin that emails back everything a form sent rather than a message you wrote yourself, which is why it happened there and nowhere else. Those fields are now removed before Avada builds the email, and they no longer appear in the stored submission or in Avada's own entries list either. Two of them had also been kept in the plugin's mail log all along, where nobody happened to look; that is fixed by the same change.
- New [Protection]: A new check reads what was actually typed into your forms and recognises the kind of spam that fills every field with random characters — a name like "Cowqv Exnjznedy", a town like "JPnuTSVqMlNmdwoFObq". This catches something the other checks cannot: they all work by giving the visitor's browser something to return, which a spam program driving a real browser simply hands back correctly. Judging the text instead does not care how good the program is at pretending to be a person, because writing nonsense is the whole point of what it is doing. It starts in observation mode and blocks nothing until you switch it on under Protection, so it cannot turn a real enquiry away while you are still deciding. It never looks at more than one field in isolation: a single unusual name or product code is normal, and only a submission where several fields are nonsense counts. Non-Latin alphabets are skipped entirely rather than guessed at.
- New [Integrations]: Formidable Forms is now supported. It was the one major form plugin that every comparable captcha plugin protected and this one did not.
- New [Integrations]: Ninja Forms and Forminator are now supported.
- New [Integrations]: Kadence Blocks is now supported, for its Advanced Form block. Kadence's older classic form block cannot be protected — it offers no way for any plugin to stop a submission — and Kadence have said they are retiring it.
- New [Integrations]: MC4WP (Mailchimp for WordPress) newsletter signup forms are now supported. Junk signups do more damage than junk email: they fill your mailing list with addresses that bounce, and that in turn harms the delivery of every newsletter you send afterwards.
- New [Integrations]: The "lost password" form is now protected, both WordPress's own and WooCommerce's. Left open, that form can be used to send password emails to any address a spammer cares to type in — the people receiving them have never heard of your site, and the resulting complaints damage the reputation of your domain, which then costs you every other email you try to send. Nothing about this shows up as spam on your own site, which is why it usually goes unnoticed.
- New [Integrations]: The WooCommerce "account details" form is now protected. Note that it is only ever shown to logged-in customers, and the plugin skips logged-in visitors by default, so this only takes effect if you have turned that off.
- Improvement [Support]: The support link inside the plugin now leads to the SilentShield support board for this plugin instead of a general page.

= 2.12.1 =
- New [Elementor]: Elementor's new v4 forms — the "atomic" forms, built on the new element system — are now protected. They were not before, and not because the protection failed on them: these forms assemble what they send entirely in the browser and only include the fields Elementor itself placed, so everything this plugin adds was dropped on the way out and the form arrived looking like an ordinary, unprotected submission. Its fields now travel with the request. Nothing of ours appears in your notification email or in the submissions table, and the classic Elementor form widget is unaffected.
- Fix [Captcha]: On sites with page caching the reload button stopped working, and did so silently. The button sent a security token that WordPress stamps into the page itself and only accepts for about a day; a cached page keeps serving that token long after it has expired, and WordPress then turned every click away before the plugin ever saw it. The button no longer sends that token. It does not need one — the address the request comes from is checked instead, which a cache cannot invalidate. This affects the same on every form plugin, not only Contact Form 7.
- Fix [Captcha]: If your site sends visitors' browsers the instruction not to disclose which page they came from — a common privacy setting, and one some privacy extensions apply on their own — the reload button, the audio button and the timing refresh were refused outright. They were relying on exactly the information that setting withholds. They now use signals the browser sends regardless, so the setting no longer costs you a working captcha.
- Fix [Captcha]: The limit on how often a new captcha could be requested was counted per address as your server reports it. Behind a CDN or load balancer that is one and the same address for everybody, so all your visitors together shared a single allowance of thirty requests a minute — busy enough sites simply ran out, and the reload button then stopped working for everyone at once. The limit now counts each visitor separately, using the proxy setting you may already have configured.
- Fix [Captcha]: A reload that failed used to leave no trace whatsoever: the old captcha stayed on screen, nothing was said, and nothing was written to the browser console unless you knew about an undocumented debug switch. There was no way to tell a refused request from a broken connection, and nothing useful a visitor could report. A failed reload now says so next to the captcha and records the reason in the browser console.
- Fix [Admin]: The SilentShield navigation column could disappear entirely, leaving no way to reach any of its screens — the sidebar was still on the page, but hidden, and the button meant to bring it back did nothing. The plugin's own styling was competing on equal footing with a rule WordPress itself ships, and which of the two won came down to the order the stylesheets happened to load in. A theme, another plugin, or anything that combines stylesheets could tip it. The sidebar no longer depends on winning that race.

= 2.12.0 =
- Fix [Admin]: On sites whose permalinks are set to "Plain", several screens inside the plugin were simply empty — the Audit Log, the Mail Log and the Analytics figures showed nothing at all, no matter how much had actually been recorded. The records were never lost; the plugin was asking WordPress for them with a malformed address and getting nothing back. All of those screens fill in again after this update. Sites using any other permalink setting were never affected.
- New [Setup]: A newly installed plugin protects nothing until you switch on the form plugins you use, and until now nothing said so — it sat in your plugin list marked "active" while every form on the site was still wide open. There is now a notice in the WordPress admin, and a panel on the SilentShield dashboard, naming the form plugins found on your site and linking straight to the screen where you turn them on. Nothing is enabled for you: switching on something like the WordPress login form uninvited is how people end up locked out of their own site. Both disappear as soon as anything is protected.
- New [Logging]: When the SilentShield API is in use, its answer to each check is now written to the Audit Log — verdict, confidence and the server's actual reply — so a decision can be examined afterwards instead of being taken on trust. Failed calls are always recorded, including what came back; recording the successful ones as well is a new switch under Advanced → Logging & Tracking, off by default because it writes one entry per submission. Submissions turned away for carrying no behaviour token are logged now too: that is the most common reason a form is blocked, and it previously left no trace at all.

= 2.11.0 =
- New [Protection]: The hidden fields the plugin adds to your forms — the honeypot and the two JavaScript timing fields — no longer carry the same names on every site. They are now named per page load, derived from a signed token, so a spam script can no longer be built once against the fixed names and pointed at every site running this plugin.
- Fix [JavaScript protection]: The page age a submission claimed was taken from a hidden field that anyone could set to any value, which meant a form could be fetched once and re-submitted for as long as the spammer liked. That age now comes from a token signed with your site's own secret and is rejected once it is older than 24 hours. Submissions that carry no token at all — form HTML held in a page cache, or rendered before this update — keep being accepted as before, so nothing breaks when you update.
- Fix [JavaScript protection]: A submission whose end time was *before* its start time counted as valid, because the difference was rounded to whole milliseconds and only compared against zero. Such submissions are now rejected, as are those where both timestamps were written in the same instant.
- Fix [Honeypot]: A bot that wrote "0" into every field it found passed the trap, because a zero counted as "left empty". It no longer does.
- Fix [Honeypot]: The trap field was marked `visibility:hidden` in its style attribute — a reliable signal for a scraper looking for the one field it must not touch. It is now moved off-screen instead, and is properly kept out of the tab order and away from screen readers and browser autofill.
- Fix [Blacklist]: The words you typed into "Blacklist Words" never blocked anything. The rule reads its list from WordPress's own Comment Blocklist, and the current settings screen was saving your words somewhere else — the switch reported itself as on, the list looked saved, and nothing was ever caught. Saving now writes through to that list, and the field is filled back in from it, so what you see is what is actually in use. **If you had the blacklist switched on, it starts working after this update** — which is what the setting promised all along.
- Fix [Elementor]: On Elementor pages the captcha could freeze. Elementor stores a page's rendered markup for 24 hours, and everything the plugin adds to a form was being stored with it — the same arithmetic question and the same captcha session for every visitor from then on, which is no captcha at all. Worse, whatever your settings were at that moment was frozen in too, so turning a protection on afterwards changed nothing until the cache expired. The form is now excluded from that cache; everything else on the page still benefits from it.
- Fix [Captcha]: "5 - 4 = ?" has the answer 0, and a zero was being stored as "no answer at all". Roughly one arithmetic captcha in sixty was therefore unsolvable: a visitor typed the correct 0 and was turned away as a spammer.
- Fix [JavaScript protection]: With this protection switched on, several integrations rejected every real visitor. WP Job Manager applications, the Ultimate Member login and the WordPress registration form never recorded the timing the check needs, so a genuine person looked exactly like a bot with no browser. All of them work now. If you tried this setting before and turned it back off because forms stopped working, it is worth another look.
- Fix [Maintenance]: The cleanup buttons under Database Maintenance reported the wrong number of removed entries — "0 deleted" after clearing all logs, and exactly "1 deleted" after clearing the IP log or the IP bans, however many there had been. The deleting itself was always correct; only the count was wrong, in the confirmation and in the audit trail.
- Fix [Logging]: The Status taxonomy the log entries use was registered for a post type this plugin does not have, left over from older code. It worked, but it also stayed attached to that stray name — so if another plugin ever registered a post type called "deals", a Status column from us would have appeared in its list.
- New [Support]: There is now a way to reach us from inside the plugin. A "Feedback & Support" entry in the menu, a section at the end of the Help page, and a second button on the review notice for when something is not working — until now that notice only offered a public review, which is a poor place to report a problem. Deactivating the plugin also asks once why; answering is optional and opens our form, nothing is sent from your site.

= 2.10.0 =
- Fix [Comments]: With comment protection enabled, the captcha was applied to every comment WordPress creates — not just the ones a visitor types into the comment form. A comment added by an importer, by the WordPress app or block editor, by WP-CLI, by a scheduled task or by another plugin carries no captcha field, was therefore treated as spam, and the request was answered with "403 Forbidden" — which could take down whatever feature that other plugin was in the middle of. The captcha now applies only to real comment-form submissions. Nothing changes for the comment form itself: spam is still blocked exactly as before.
- Fix [AI-Agent Enforcement]: Two kinds of rule you can set in your SilentShield dashboard were saved and displayed as active, but the plugin never applied them. Rules by purpose were only matched against a crawler's older category label, so blocking "AI agents" had no effect at all — the agent-type crawlers (ChatGPT-User, Claude-User, Perplexity-User, Meta, Mistral and others) are filed under a different label. And rules against faked identities were skipped entirely. Both now work. **If you already had either rule switched on, those requests will start being blocked after this update** — which is what the setting promised all along. Nothing else changes: rules that were working keep working exactly as before.
- Fix [AI-Agent Enforcement]: A crawler is only treated as a forgery when we can actually see where the request came from, its operator publishes IP ranges for the same address type (IPv4/IPv6), and the request comes from outside all of them. A crawler we cannot check stays "unverified" and is never accused — so a genuine bot is not blocked because we happen to hold only part of its address list.
- Fix [AI-Agent Enforcement]: Sites behind Cloudflare, an nginx front-end or any other reverse proxy are no longer at risk of turning away real crawlers. Your server sees the proxy's address there, not the crawler's, which would make every well-behaved bot look like a forgery. The plugin now recognises that situation and withholds the "forged" verdict; if you have set `F12_TRUSTED_PROXY_HEADER` in wp-config.php, it uses that header to find the real address instead — which also makes "verified" work behind a proxy for the first time.
- New [AI-Agent Enforcement]: The plugin now reports to your dashboard which kinds of rule this version can carry out. If you save a rule that needs a newer plugin, the dashboard says so instead of showing it as active.
- New [AI-Agent Enforcement]: Blocked requests are reported to your dashboard, so the "blocked bots" report has data. The report is sent after the visitor's response has already been delivered, so it costs no page speed, and it follows the "Observe AI crawlers" setting: switch that off and blocking keeps working while the reporting stops. Transmitted are user agent, IP address, path and method of the blocked request; the IP is pseudonymised on the server.

= 2.9.0 =
- New [AI-Agent Enforcement]: The plugin can now actually enforce the block rules you set in your SilentShield dashboard — previously it could only observe. When enabled, disallowed AI bots receive a 403 (throttled ones a 429) based on the signed policy your dashboard publishes. The policy is fetched and its Ed25519 signature verified server-side (libsodium), cached, and refreshed off the request path, so pages are not slowed down. Bots are identified by their User-Agent and only treated as "verified" when their source IP is in the operator's published range. Toggle under Advanced → "Block AI crawlers (enforce)" (OFF by default — a deliberate choice; define `SILENTSHIELD_ENFORCER` as `false` in wp-config.php to force it off). Fail-open by design: any error, an unverifiable policy, or "observe" mode never blocks a request.

= 2.8.0 =
- New [AI-Agent Observation]: The plugin can now record which AI agents/crawlers (GPTBot, ClaudeBot, PerplexityBot and others) visit your site and show them in your SilentShield dashboard. It runs entirely server-side, sets no cookies, blocks nothing, and only sends data for detected bots — never for your human visitors. IP addresses are pseudonymised server-side. The telemetry is sent after the page has already been delivered to the visitor, so page speed is unaffected. Toggle under Advanced → "Observe AI crawlers" (on by default; define `SILENTSHIELD_OBSERVER` as `false` in wp-config.php to force it off). Legal basis: legitimate interest (Art. 6(1)(f) GDPR).
- New [AI-Agent Observation]: The list of known AI crawlers refreshes itself once a day from the SilentShield bot directory (off the request path, with an embedded fallback list), so new crawlers are recognised without a plugin update.
- New [AI-Agent Observation]: A one-time, dismissible admin notice announces the feature and links to the setting — no silent telemetry.

= 2.7.7 =
- Fix [Translations]: Resolved the `_load_textdomain_just_in_time` notice (WordPress 6.7+) for the `captcha-for-contact-form-7` domain. Four protection validators (behavior/API, captcha, multiple-submission and timer) translated their failure message inside their constructor, which runs on `after_setup_theme` — before `init` — and therefore triggered translation loading too early. The message is now resolved on the `init` hook via the new `set_message_on_init()` helper, while keeping the literal `__()` strings visible to the translation extractor.

= 2.7.6 =
- Security [Audio Captcha]: The accessibility audio endpoint (`/captcha/audio`) no longer returns the captcha solution for math challenges. The math answer was never used by the frontend (math formulas are read aloud directly from the page), so this code path only disclosed the solution to direct API callers. Image captchas still spell out their characters — that is the intended purpose of the audio accessibility feature and remains protected by the existing per-IP rate limit.
- Security [SilentShield]: Documented that the frontend `beta_captcha_api_key` is a publishable, domain-bound client key (comparable to a reCAPTCHA site key), intentionally exposed to the browser so the behavioral client script can run. It carries no administrative or sensitive authority.

= 2.7.5 =
- Fix [Forms]: Enabling WordPress Comments, JetFormBuilder or Ultimate Member protection no longer attaches the captcha submit interceptor to unrelated forms — most notably the WooCommerce "Add to cart" form (`form.cart`), which could be blocked or delayed. These three integrations relied on the generic default-forms handler, which bound to *every* form on the page that wasn't explicitly excluded (an exclusion list that could never be complete). Each integration now has its own dedicated module that targets only its own forms (comment form, JetFormBuilder forms, Ultimate Member login/registration), and the generic handler is no longer activated as a side effect.

= 2.7.4 =
- New [Analytics]: When the SilentShield API is active, the Analytics page now shows an "API Exclusive Blocks" section with measured numbers — how many spam submissions the API blocked that none of the local rules (Captcha, Timer, IP, Honeypot, …) would have stopped, plus the overlap and total API blocks. Both the recording and the section are only active while the API is enabled and reachable. This complements Shadow Mode, which shows an estimate while the API is off.
- New [Analytics]: Bots that submit a protected form without ever loading the widget (no behavior nonce — typically scripts that POST directly without running JavaScript) are now reported to the SilentShield API so they are counted in the "bots blocked" statistics instead of being silently dropped. The submission is still blocked locally exactly as before; the report is fire-and-forget and never delays the request.
- Maintenance: Removed temporary debug logging from the SilentShield API validator (no longer writes diagnostic lines, including nonce/API-key prefixes, to the PHP error log).

= 2.7.3 =
- Fix [WooCommerce Checkout]: The JavaScript protection could wrongly reject legitimate checkouts ("JavaScript protection not correct") and require several clicks on "Place order". The captcha and its timing fields (`js_start_time`/`js_end_time`) are rendered inside the order review block, which WooCommerce re-renders on every `updated_checkout` AJAX (address, shipping or payment changes). The page-load init only ran once, so after a refresh `js_start_time` was empty and the timestamp fallback collapsed start and end to the same value (zero duration). The checkout now re-seeds `js_start_time` on every `updated_checkout` and falls back to the stable page-load time, so the submitted duration is always valid.
- Fix [Compatibility]: Resolved a conflict with Germanized for WooCommerce where enabling "WooCommerce Login" or "WooCommerce Registration" protection broke the order withdrawal/Widerruf form (`?wc-ajax=eu_owb_woocommerce_order_withdrawal_request`), which failed with an HTTP 500 error and no success/error message. The frontend submit interceptor was bound to the generic `form.woocommerce-form` class and therefore also hijacked third-party WooCommerce forms that ship their own AJAX handling. It now only targets the WooCommerce login (`woocommerce-form-login`) and registration (`woocommerce-form-register`) forms it actually protects.

= 2.7.2 =
- Fix [Forms]: Comments and other default WordPress forms now set the JavaScript timing field (`js_end_time`) at the moment of submission instead of on page load. Previously the timestamp was pre-filled during init, making it identical to `js_start_time` and rendering the time-based bot detection ineffective for these forms.
- Fix [Forms]: Default forms (comments, JetForm, Ultimate Member, generic forms) no longer ran the captcha workflow on page load. The workflow is now correctly triggered by a native submit listener, so the captcha is verified only when the user actually submits.
- Fix [Forms]: Resolved an issue where default forms with a submit control named `submit` (e.g. the WordPress comment form's "Post Comment" button) could not be submitted, because the control shadowed the form's `submit()` method. The form is now submitted natively via the prototype method, which also avoids re-entrant submit events.
- Fix [IP Protection]: IP rate limiting no longer wrongly blocks legitimate visitors from the third submission onward. The time check compared the gap between the two *previous* submissions instead of the time since the last one, so the current request's actual timing was ignored — once a visitor's first two submissions were close together, every following submission (e.g. the third comment on a post) was rejected with "IP protection" regardless of how long they waited. The check now correctly measures the time elapsed since the visitor's last submission.

= 2.7.1 =
- Improved [Translations]: French (fr_FR) translations overhauled — replaced anglicisms with correct French terminology throughout ("spam" → "indésirables", "bots" → "robots", "plan" → "offre", "plugin" → "extension", "clé API" → "clé d'API", "paramètres" → "réglages", "analyse comportementale IA" → "analyse comportementale par IA", "espace réservé" → "texte indicatif", "étiquette" → "libellé"). Fixed typos and grammar errors in community-contributed translations. Regenerated MO and JSON files.

= 2.7.0 =
- Fix [Admin UI]: Resolved sidebar/navigation not rendering on sites with WooCommerce or other React-based plugins. The plugin now uses WordPress' built-in React instead of bundling its own copy, preventing duplicate React instance conflicts that broke context providers.
- Fix [Cron]: "Daily Telemetry" cron job no longer runs when telemetry is disabled. Previously, disabling telemetry in the admin UI only took effect on the next page load; the cron could still fire in between. Cron state is now synced immediately when settings are saved.
- Fix [Cron]: Audit log no longer shows "Daily Telemetry completed" entries when telemetry is disabled. The audit hook for the telemetry cron is now only registered when telemetry is active.
- Fix [Forms]: Integration names (Avada, WooCommerce, Elementor, etc.) are no longer passed through WordPress translation. This caused brand names to be incorrectly translated by community language packs — e.g. "Avada" was displayed as "Optionen" on German sites.

= 2.6.12 =
- Fix [Settings]: Plugin action link ("Settings" in plugin list) now correctly opens the new React admin UI instead of the removed legacy page.
- Fix [Dashboard]: "View Audit Log" link in the dashboard widget now points to the new Audit Log page instead of the removed legacy page.
- Fix [Navigation]: Old admin page URLs (e.g. `admin.php?page=f12-cf7-captcha`, `f12-cf7-captcha-extended`, `f12-cf7-captcha-audit-log`) now redirect to their React equivalents instead of showing a permissions error.
- Fix [Forms]: Integration presets (WooCommerce, Fluent Forms, JetForm, etc.) no longer default to "enabled" when the setting has not been explicitly saved. Previously, unsaved settings defaulted to enabled, making it appear as though integrations were active even when the corresponding plugin was not installed.
- Fix [Dashboard]: Internal telemetry errors (e.g. `TELEMETRY_UNEXPECTED_RESPONSE`) are no longer shown in the "Recent Issues" section of the dashboard widget. These technical messages are not actionable by end users.
- Improved [Dashboard]: Protection Score widget is now more compact — score circle reduced from 120px to 72px, stats displayed beside the circle instead of below, and module list uses smaller type for a tighter layout.
- Improved [Settings]: Added descriptive help text to all numeric fields in Advanced Settings (IP Rate Limiting, Content Rules, Mail Log Retention, Block Log Retention, Audit Log Retention) so users understand what each value controls.
- Improved [Cleanup]: Every cleanup action now shows a description below the button label explaining exactly what it does (e.g. "Removes log entries older than 3 weeks").

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
