# Changelog

All notable changes to CookieKit are documented in this file.

## [1.2.0] - 2026-11-13

First public release on GitHub.

### Changed
- Default re-prompt interval raised from 180 to 183 days (about six months). Sites that already saved 180 keep their setting.

### Security
- Settings are now sanitized: allowed values for selects, hex colors, numeric ranges, URLs, and a policy version of at most 20 characters.
- REST `POST /cookiekit/v1/consent`: `consent_uuid` must be a valid UUID, only existing category IDs are stored, and `page_url` and `language` are clamped to their column sizes.
- REST `POST /cookiekit/v1/snapshot` and scanner: only string cookie names are accepted. Pages are fetched only on the site's own host, with `wp_safe_remote_get()` (no server-side requests to arbitrary URLs). SSL verification follows core's `https_local_ssl_verify` filter. The scanner uses a neutral user agent.
- CSV export: built with `fputcsv()` and protected against spreadsheet formula injection; export responses send no-cache headers.
- Admin: escaped the logo preview URL and vendor row indexes; external links use `rel="noopener noreferrer"`; POST arrays are type-checked; only known text keys are saved; invalid vendor regex patterns are dropped.
- Browser scan overlay: error messages are rendered as text, not HTML.
- The vendor list (regex patterns) is no longer printed on every front-end page.

### Fixed
- Unchecking a checkbox in Settings had no effect: the unchecked key was dropped and the default (enabled) applied again. Checkboxes now show the value actually in use and store `0` when unchecked.
- Google Consent Mode "update" was never sent when auto-blocking was disabled. The consent script now always loads.
- An expired consent, or one given under an older policy version, could still send "granted" Consent Mode signals on page load.
- The scanner's HTML enrichment listed every configured vendor's cookies on every page, because it matched the plugin's own configuration and Consent Mode snippet.
- Admin-only WordPress cookies (`wp-settings-*`, `wordpress_test_cookie`) were saved by the browser scan.
- The browser scan stopped with a `no_cookies` error on a page without first-party cookies; such a page is now a valid result.
- The CSV export broke columns when a value contained a comma.
- `dns-prefetch` / `preconnect` hints for blocked hosts are removed, so the browser does not contact those hosts before consent.
- Uninstall now cleans every site of a multisite network.
- Invalid `<th>` attribute in the consent log table.

### Changed
- Code comments and source strings are in English, with the `cookie-kit` text domain. An Italian translation (`languages/cookie-kit-it_IT.po/.mo`) and a POT template are included.
- Default texts, category names and descriptions, and policy URLs are resolved at runtime from translations. New installs follow the site language. Values saved by earlier versions, or edited by the admin, are kept unchanged. An empty text field now falls back to the default text instead of showing an empty string.
- The iframe placeholder shows the category name instead of its ID.
- The text domain is loaded on `init`.
- Plugin header: author, GitHub URIs, neutral wording on legal compliance.
- Added `README.md`, `CHANGELOG.md`, `LICENSE` (GPL-2.0) and `.gitignore`; rewrote `readme.txt` in English.

## [1.1.10] and earlier

Versions 1.1.3 to 1.1.10 were not documented in a changelog. The entries below are translated from the previous `readme.txt`.

## [1.1.2]
- The client-side scanner also captures HttpOnly cookies (such as `PHPSESSID`), by merging `$_COOKIE` on the server with the list from `document.cookie`.

## [1.1.1]
- Snapshot mode: the blocker and iframe replacement are disabled during the browser scan, so third-party scripts and iframes load natively.
- Multi-page snapshot: home page, 2 pages and 2 random posts.
- The classifier also matches the cookie domain against the vendor host (for example `.youtube.com` is classified as YouTube).
- Added `VISITOR_PRIVACY_METADATA` to the cookie dictionary and to the default YouTube vendor.

## [1.1.0]
- Client-side browser scanner ("Start browser scan" button in the Scanner tab).
- REST endpoint `POST /cookiekit/v1/snapshot`, restricted to `manage_options`.
- The banner lists only cookies found by the scanner, instead of the cookies declared in the vendor settings.

## [1.0.3]
- Removed Microsoft Clarity, Hotjar and Facebook Pixel from the default vendors, to avoid listing services that are not in use.

## [1.0.2]
- In the preferences modal, each category has an expandable "Show cookies (N)" table (name, provider, duration, purpose).
- Internal dictionary with duration and description of common cookies.
- First-party / third-party badge on cookies in the modal.

## [1.0.1]
- The classifier recognizes Cloudflare cookies, CSRF/form security tokens and WooCommerce cookies as technical.
- Added Cloudflare to the default vendors.

## [1.0.0]
- First release: banner, auto-blocking, passive scanner, consent log, Consent Mode v2, admin panel, REST endpoint, shortcode.
