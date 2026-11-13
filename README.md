# CookieKit

A lightweight, self-hosted cookie consent banner for WordPress. It is designed around the Italian Data Protection Authority guidelines (Garante Privacy, provvedimento 231/2021, 10 June 2021) and Google Consent Mode v2.

CookieKit shows a consent banner and blocks known third-party scripts and embeds until the visitor consents. It sends Google Consent Mode v2 signals, scans the site for the cookies it actually sets, and keeps an exportable log of consents. Everything runs on your own WordPress install: no external service and no account needed.

> **Disclaimer.** This plugin does not provide legal advice and does not by itself guarantee compliance; site owners remain responsible for their cookie policy and configuration.

## Features

**Banner and preferences**

- Banner with **Accept all**, **Reject all** and **Customize** buttons, all with the same visual weight.
- A close button (X). Closing the banner is recorded as a rejection: only necessary cookies are used.
- Preferences modal with one toggle per category. The `necessary` category is always on.
- Under each category, an expandable table of the cookies the scanner found on the site: name, provider, duration, purpose, and a first/third-party badge.
- A floating "Manage cookies" button (bottom-left or bottom-right). The `[ckit_button]` shortcode and any element with the `data-ckit-open-prefs` attribute also reopen the preferences.
- Layout: full-width bar or centered box. Position: bottom, top or center. Light or dark theme, custom colors, optional logo.
- Editable texts. Messages support inline links with the `[link text](https://example.com/page/)` syntax.
- Configurable categories: name, description, required flag.

**Consent storage and re-prompt**

- The choice is stored in a first-party cookie, `ckit_consent`. It holds a JSON object: consent UUID, action, categories, policy version, timestamp, expiry.
- The banner comes back after a configurable number of days (default 183, range 30–365). Italian sites should keep at least 6 months, as required by the Garante guidelines.
- It also comes back when you change the **policy version** setting, so visitors are asked again after you update your cookie policy.

**Script and iframe blocking** (auto-blocking can be turned off)

- Scripts enqueued through WordPress (`wp_enqueue_script`) whose URL matches a configured vendor are printed as `type="text/plain"`. They run only after the visitor accepts that vendor's category.
- Iframes in post content (`the_content`) that match a vendor, such as YouTube, Vimeo or Google Maps, are replaced by a placeholder. The placeholder offers **Load once** and **Manage preferences**, and the iframe is restored automatically after consent.
- `dns-prefetch` / `preconnect` hints for blocked hosts are removed, so the browser does not contact those hosts before consent.
- You can block scripts by hand. See [Blocking scripts manually](#blocking-scripts-manually).

**Google Consent Mode v2.** See [Google Consent Mode v2](#google-consent-mode-v2).

**Cookie scanner**

- **Browser scan (recommended).** It opens the site as a logged-in administrator with `?ckit-snapshot=1`. It visits the home page, up to 2 pages and 2 random posts, waits 5 seconds on each, and collects the cookie names the browser sees, including HttpOnly cookies sent with the request. The server then reads each page's HTML to find configured vendors, such as a YouTube iframe or a GTM script, and adds the cookies declared for them as third-party cookies, because browsers never expose third-party cookies to JavaScript.
- **Passive scan.** It sends server-side requests to the home page, up to 5 pages and 3 posts, and reads `Set-Cookie` headers.
- **Classification.** It uses the vendor cookie lists (with `*` wildcards) and the cookie domain. Built-in rules cover WordPress/PHP/CookieKit, Cloudflare, CSRF tokens and WooCommerce cookies.
- **What the banner lists.** The banner lists only cookies the scanner found, plus CookieKit's own `ckit_consent` cookie. A built-in dictionary adds duration and purpose for common cookies. These durations and descriptions are indicative: providers change them over time, so check them against each provider's documentation before relying on them in your cookie policy.

**Consent log**

- Each choice is stored in the `{prefix}ckit_consent_log` table. The record holds the UTC time, consent UUID, action, accepted categories, policy version, page URL and language. It also holds the user agent, truncated to 500 characters, and a salted SHA-256 hash of the IP address. The plain IP is never stored.
- The admin page shows the latest 200 entries and totals per action.
- Export as CSV or JSON. The CSV export guards against spreadsheet formula injection.
- A daily cron job deletes entries older than the retention period (default 1825 days, configurable).

**Other**

- **Default vendors.** Google Analytics 4, Google Tag Manager, Google Ads / DoubleClick, YouTube Embed, Vimeo, Google Maps and Cloudflare. You can edit, remove or add vendors. A vendor has a name, category, host, regex pattern and cookie list.
- **Translations.** English (source) and Italian (`languages/cookie-kit-it_IT.po`).
- **Clean uninstall.** Deleting the plugin removes its tables, options and scheduled event, on every site of a multisite network.

## What CookieKit does not do

- **No IAB TCF.** CookieKit is not an IAB Transparency & Consent Framework (TCF) CMP. Google requires a Google-certified CMP integrated with the TCF for personalized ads served with AdSense, Ad Manager or AdMob in the EEA and the UK (since 16 January 2024) and in Switzerland (since 31 July 2024). See [Google's help page](https://support.google.com/adsense/answer/13554116). With CookieKit, those ads can be limited or non-personalized.
- **Scripts outside the WordPress enqueue system.** Scripts hard-coded in theme templates, inline snippets, or tags injected by other plugins without `wp_enqueue_script` are not blocked automatically. Mark them by hand (see below).
- **Iframes outside post content.** Iframes in widgets, templates or builder output that does not go through `the_content` are not replaced.
- **Withdrawn consent.** If a visitor withdraws consent, scripts already loaded on the current page keep running until the page is reloaded.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later

Version 1.2.0 was tested with WordPress 7.1.4 and PHP 8.5.9 (SQLite Database Integration). Older WordPress/PHP versions within the requirements were not tested for this release.

## Installation

1. Download the repository as a ZIP, or clone it into `wp-content/plugins/cookie-kit`.
2. Activate **CookieKit** in **Plugins**.
3. Open **Cookie Consent** in the admin menu.
4. **Settings:** set the URLs of your privacy policy and cookie policy pages, then choose the layout, colors and features.
5. **Vendors:** check that the vendor list matches the services your site really uses.
6. **Scanner:** run the browser scan, so the banner can list the cookies your site really sets.
7. **Texts:** adjust the banner texts if needed. Empty fields use the default text in the site language.

## Configuration

| Tab | What you can set |
| --- | --- |
| Settings | Enable/disable, auto-blocking, iframe blocking, Consent Mode v2, floating button and its position, re-prompt days, log retention days, policy version, policy URLs, banner position/layout/theme, colors, logo |
| Categories | Name, description and "required" flag of each category |
| Vendors | Name, category, host, regex pattern and cookie names (comma-separated, `*` wildcard) |
| Scanner | Browser scan, passive scan, clear results, list of detected cookies |
| Consent log | Totals, latest 200 entries, CSV/JSON export |
| Texts | Banner title and message, button labels, modal title/intro, footer button label, link labels |

Texts, category names and policy URLs left empty use built-in defaults. Those defaults are translated into the site language. Values saved by earlier versions of CookieKit are kept as they are.

## Google Consent Mode v2

When the Consent Mode option is on, CookieKit prints this `default` command at the top of `<head>`, before any tracking script:

```js
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'functionality_storage': 'granted',
  'personalization_storage': 'denied',
  'security_storage': 'granted',
  'wait_for_update': 500
});
gtag('set', 'ads_data_redaction', true);
gtag('set', 'url_passthrough', true);
```

After a choice, and on every page load when a valid consent is stored, it sends `gtag('consent', 'update', …)` with this mapping:

| Category accepted | Signals set to `granted` |
| --- | --- |
| `marketing` | `ad_storage`, `ad_user_data`, `ad_personalization` |
| `analytics` | `analytics_storage` |
| `preferences` | `personalization_storage` |

`functionality_storage` and `security_storage` are always `granted`.

If auto-blocking is on and your Google tag is enqueued through WordPress, the tag itself does not load until the matching category is accepted. If you want Google tags to load before consent, with denied signals, turn auto-blocking off or remove the Google vendors. The Consent Mode signals are sent either way.

## Blocking scripts manually

Any script with `type="text/plain"` and a `data-ckit-category` attribute is activated after the visitor accepts that category:

```html
<!-- External script -->
<script type="text/plain" data-ckit-category="analytics" data-ckit-src="https://example.com/tracker.js"></script>

<!-- Inline script -->
<script type="text/plain" data-ckit-category="marketing">
  console.log('runs only after marketing consent');
</script>
```

## Developer reference

CookieKit has no custom PHP actions or filters yet. These are the integration points:

| Type | Name | Notes |
| --- | --- | --- |
| Shortcode | `[ckit_button label="…" class="…"]` | Link that opens the preferences modal |
| HTML attribute | `data-ckit-open-prefs` | Click opens the preferences modal |
| HTML attributes | `type="text/plain"` + `data-ckit-category` (+ `data-ckit-src`) | Manual script blocking |
| JS object | `window.CKIT.consent` | `{ given, categories, uuid, policyVersion, timestamp }` |
| JS function | `window.CKIT.applyConsent(categories)` | Sends the Consent Mode update and unblocks scripts/iframes for the given `{ category: true/false }` map |
| Cookie | `ckit_consent` | JSON with the stored choice |
| REST | `POST /wp-json/cookiekit/v1/consent` | Public; records a choice in the log (input is validated) |
| REST | `POST /wp-json/cookiekit/v1/snapshot` | Requires `manage_options`; used by the browser scan |
| Options | `ckit_settings`, `ckit_categories`, `ckit_vendors`, `ckit_texts`, `ckit_db_version` | Removed on uninstall |
| Tables | `{prefix}ckit_consent_log`, `{prefix}ckit_cookie_scan` | Removed on uninstall |
| Cron | `ckit_daily_cleanup` | Purges old log entries |

CookieKit makes no requests to external services. The only HTTP requests it sends are the scanner's requests to your own site.

## FAQ

**Does CookieKit make my site GDPR / ePrivacy compliant?**
No plugin can guarantee that by itself. CookieKit gives you the tools: a banner with equal accept/reject options, prior blocking, consent records and Consent Mode signals. Compliance also depends on your configuration, on the services you use, and on your privacy and cookie policies. This plugin does not provide legal advice.

**Can I use it with Google AdSense?**
Yes, but not for personalized ads in the EEA, the UK or Switzerland. Those require a Google-certified CMP integrated with the IAB TCF, which CookieKit is not (see [What CookieKit does not do](#what-cookiekit-does-not-do)).

**The banner lists no cookies in the categories. Why?**
The list comes from the scanner. Run the **browser scan** from the Scanner tab while logged in as an administrator.

**How do I ask every visitor for consent again?**
Change the **policy version** in Settings, for example from `1.0` to `1.1`. Consents given under another version are ignored and the banner is shown again.

**A tracking script still loads before consent.**
It is probably not enqueued through WordPress, or its URL does not match any vendor's pattern or host. Add or adjust the vendor in the Vendors tab, or mark the script by hand (see above).

**Is the IP address stored?**
No. Only a salted SHA-256 hash of the IP is stored, together with the other fields listed above.

## Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/domenicosacchi/cookie-kit/issues).

- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).
- Escape all output, sanitize all input, and check capabilities and nonces on every admin action.
- Write UI strings in English with the `cookie-kit` text domain. After changing strings, regenerate `languages/cookie-kit.pot` with `wp i18n make-pot . languages/cookie-kit.pot --exclude=languages`.
- Describe how you tested your change.

## License

CookieKit is free software, released under the [GNU General Public License v2.0 or later](LICENSE).

## Disclaimer

This plugin does not provide legal advice and does not by itself guarantee compliance; site owners remain responsible for their cookie policy and configuration. References to the Garante Privacy guidelines and to Google Consent Mode describe the design goals of the plugin, not a certification or an endorsement.
