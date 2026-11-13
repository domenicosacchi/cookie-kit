=== CookieKit ===
Tags: cookie, cookie banner, consent, consent mode v2, garante privacy
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted cookie consent banner designed around the Italian Garante Privacy guidelines (231/2021) and Google Consent Mode v2.

== Description ==

CookieKit is a lightweight, self-hosted cookie consent banner. It is designed around the Italian Data Protection Authority guidelines (Garante Privacy, provvedimento 231/2021, 10 June 2021) and Google Consent Mode v2.

This plugin does not provide legal advice and does not by itself guarantee compliance; site owners remain responsible for their cookie policy and configuration.

= Features =

* Banner with "Accept all", "Reject all" and "Customize" buttons, all with the same visual weight.
* A close button (X). Closing the banner is recorded as a rejection.
* Preferences modal with one toggle per category. Under each category, a table of the cookies the scanner found (name, provider, duration, purpose, first/third party).
* Floating "Manage cookies" button, the `[ckit_button]` shortcode, and the `data-ckit-open-prefs` attribute to reopen the preferences.
* Consent stored in the first-party `ckit_consent` cookie. The banner comes back after N days (default 183, about six months, the minimum interval in the Italian Garante guidelines) or when you change the policy version.
* Auto-blocking: enqueued scripts that match a configured vendor stay inactive until consent. Matching iframes in post content become click-to-load placeholders.
* Google Consent Mode v2: a `default` command with denied signals early in `<head>`, then an `update` command after the visitor's choice.
* Browser scan and passive server-side scan. The banner lists only the cookies actually found.
* Consent log (UUID, action, categories, policy version, page URL, language, truncated user agent, salted IP hash) with CSV/JSON export and automatic retention cleanup.
* Default vendors: Google Analytics 4, Google Tag Manager, Google Ads / DoubleClick, YouTube Embed, Vimeo, Google Maps, Cloudflare. All of them can be edited.
* English and Italian translations included.
* No requests to external services.

= What it does not do =

* It is not an IAB TCF CMP. Google requires a Google-certified CMP integrated with the TCF for personalized ads with AdSense, Ad Manager or AdMob in the EEA, UK and Switzerland.
* Scripts not loaded with `wp_enqueue_script` and iframes outside post content are not blocked automatically. You can mark scripts by hand with `type="text/plain" data-ckit-category="…"`.

Full documentation: https://github.com/domenicosacchi/cookie-kit

== Installation ==

1. Upload the `cookie-kit` folder to `/wp-content/plugins/`.
2. Activate the plugin in the "Plugins" menu.
3. Go to "Cookie Consent" in the admin menu.
4. Set the privacy policy and cookie policy URLs in "Settings".
5. Check the vendors in "Vendors".
6. Run the browser scan in "Scanner".
7. Adjust the texts in "Texts" if needed.

== Frequently Asked Questions ==

= Does CookieKit make my site GDPR compliant? =

No plugin can guarantee that by itself. CookieKit provides a banner, prior blocking, consent records and Consent Mode signals. Compliance also depends on your configuration, on the services you use and on your policies. This plugin does not provide legal advice.

= Can I use it with Google AdSense? =

Not for personalized ads in the EEA, the UK or Switzerland. Those require a Google-certified CMP integrated with the IAB TCF, which CookieKit is not.

= How does auto-blocking work? =

Scripts enqueued through WordPress whose URL matches a vendor's pattern or host are printed as `<script type="text/plain" data-ckit-src="…">`. After consent, CookieKit turns them back into active scripts. Matching iframes in post content are replaced with a click-to-load placeholder.

= Is the IP address stored? =

No. The log stores a salted SHA-256 hash of the IP address, never the address itself. Log entries older than the retention period (default 1825 days) are deleted daily.

= How do I ask every visitor for consent again? =

Change the policy version in Settings.

== Screenshots ==

1. Banner
2. Preferences modal
3. Admin: Settings
4. Admin: Scanner
5. Admin: Consent log

== Changelog ==

= 1.2.1 =
* Security: iframe placeholders only load http(s) URLs. Placeholder markup in post content could run JavaScript when the iframe was unblocked.
* Fix: consents from cached pages were not logged once the page's REST nonce expired. The banner no longer sends a nonce to the public consent endpoint.
* Fix: the preferences modal always showed "6 months" for the consent cookie, whatever the re-prompt setting.
* Fix: the Settings page showed 180 as the re-prompt default instead of 183.

= 1.2.0 =
* Security and data-handling fixes: settings sanitization, REST input validation, scanner limited to the site's own URLs, safe CSV export, escaping fixes.
* Fix: unchecking a checkbox in Settings had no effect.
* Fix: Consent Mode "update" was not sent when auto-blocking was disabled.
* Fix: the scanner listed every configured vendor's cookies on every page.
* English source strings and comments, Italian translation included. Default texts follow the site language. Texts saved by earlier versions are kept.
* Neutral legal wording, GPL-2.0 license file, documentation.

See CHANGELOG.md for the full history.

== Upgrade Notice ==

= 1.2.1 =
Security fix for iframe placeholders and a fix for consent logging on cached pages. Recommended for all sites.

= 1.2.0 =
Security and bug fixes. Saved settings and texts are kept. After updating, open Settings and check the checkboxes before saving: they now show and store the value actually in use.
