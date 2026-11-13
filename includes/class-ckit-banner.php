<?php
/**
 * Banner — Front-end banner and preferences modal output.
 * Injects JS/CSS configuration and handles the [ckit_button] shortcode.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Banner {

	public function __construct() {
		if ( ! CKIT_Core::settings( 'enabled', 1 ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'inline_consent_mode_default' ), 1 );
		add_action( 'wp_footer', array( $this, 'render' ), 99 );
		add_shortcode( 'ckit_button', array( $this, 'shortcode_button' ) );
	}

	public function enqueue() {
		wp_register_style(
			'ckit-banner',
			CKIT_PLUGIN_URL . 'public/css/ckit-banner.css',
			array(),
			CKIT_VERSION
		);
		wp_register_script(
			'ckit-banner',
			CKIT_PLUGIN_URL . 'public/js/ckit-banner.js',
			array(),
			CKIT_VERSION,
			true
		);

		$config = array(
			'cookieName'        => CKIT_CONSENT_COOKIE,
			'repromptDays'      => absint( CKIT_Core::settings( 'reprompt_days', CKIT_REPROMPT_DAYS ) ),
			'autoBlock'         => (bool) CKIT_Core::settings( 'auto_block', 1 ),
			'consentModeV2'     => (bool) CKIT_Core::settings( 'consent_mode_v2', 1 ),
			'policyVersion'     => CKIT_Core::settings( 'policy_version', '1.0' ),
			'restUrl'           => esc_url_raw( rest_url( 'cookiekit/v1/consent' ) ),
			'restNonce'         => wp_create_nonce( 'wp_rest' ),
			'categories'        => CKIT_Core::categories(),
			'vendors'           => CKIT_Core::vendors(),
			'cookiesByCategory' => $this->cookies_by_category(),
			'texts'             => CKIT_Core::texts(),
			'i18n'              => array(
				'close'           => __( 'Close', 'cookie-kit' ),
				'logo'            => __( 'Logo', 'cookie-kit' ),
				'privacyPolicy'   => __( 'Privacy Policy', 'cookie-kit' ),
				'cookiePolicy'    => __( 'Cookie Policy', 'cookie-kit' ),
				'acceptAll'       => __( 'Accept all', 'cookie-kit' ),
				'rejectAll'       => __( 'Reject all', 'cookie-kit' ),
				'customize'       => __( 'Customize', 'cookie-kit' ),
				'savePreferences' => __( 'Save preferences', 'cookie-kit' ),
				'yourPreferences' => __( 'Your preferences', 'cookie-kit' ),
				'showCookies'     => __( 'Show cookies', 'cookie-kit' ),
				'colName'         => __( 'Name', 'cookie-kit' ),
				'colVendor'       => __( 'Provider', 'cookie-kit' ),
				'colDuration'     => __( 'Duration', 'cookie-kit' ),
				'colDescription'  => __( 'Purpose', 'cookie-kit' ),
				'firstParty'      => __( '1st party', 'cookie-kit' ),
				'thirdParty'      => __( '3rd party', 'cookie-kit' ),
				'manageCookies'   => __( 'Manage cookies', 'cookie-kit' ),
			),
			'showFloating'      => (bool) CKIT_Core::settings( 'show_floating_button', 1 ),
			'floatingPos'       => CKIT_Core::settings( 'floating_position', 'bottom-left' ),
			'theme'             => CKIT_Core::settings( 'theme', 'light' ),
			'primaryColor'      => CKIT_Core::settings( 'primary_color', '#0a3d62' ),
			'acceptColor'       => CKIT_Core::settings( 'accept_color', '#0a3d62' ),
			'rejectColor'       => CKIT_Core::settings( 'reject_color', '#6c757d' ),
			'showLogo'          => (bool) CKIT_Core::settings( 'show_logo', 0 ),
			'logoUrl'           => esc_url_raw( CKIT_Core::settings( 'logo_url', '' ) ),
			'logoMaxHeight'     => absint( CKIT_Core::settings( 'logo_max_height', 40 ) ),
			'banner'            => array(
				'position' => CKIT_Core::settings( 'banner_position', 'bottom' ),
				'layout'   => CKIT_Core::settings( 'banner_layout', 'box' ),
			),
			'privacyPolicyUrl'  => esc_url_raw( CKIT_Core::privacy_policy_url() ),
			'cookiePolicyUrl'   => esc_url_raw( CKIT_Core::cookie_policy_url() ),
			'lang'              => substr( determine_locale(), 0, 2 ),
		);
		wp_localize_script( 'ckit-banner', 'CKIT_CFG', $config );

		wp_enqueue_style( 'ckit-banner' );
		wp_enqueue_script( 'ckit-banner' );

		// Client-side scanner: only for a logged-in administrator with ?ckit-snapshot=1 in the URL.
		if ( CKIT_Core::is_snapshot_mode() ) {
			wp_register_script(
				'ckit-snapshot',
				CKIT_PLUGIN_URL . 'public/js/ckit-snapshot.js',
				array(),
				CKIT_VERSION,
				true
			);
			$cats = array_keys( CKIT_Core::categories() );
			// URLs scanned in sequence: home + 2 pages + 2 random posts, to catch cookies set only
			// on specific templates (e.g. posts with a YouTube embed).
			$urls  = array( home_url( '/' ) );
			$pages = get_pages( array( 'number' => 2 ) );
			foreach ( $pages as $p ) {
				$urls[] = get_permalink( $p );
			}
			$posts = get_posts(
				array(
					'numberposts' => 2,
					'orderby'     => 'rand',
				)
			);
			foreach ( $posts as $p ) {
				$urls[] = get_permalink( $p );
			}
			$urls = array_values( array_unique( array_filter( $urls ) ) );
			// Add the snapshot parameter to every URL.
			$urls = array_map(
				function ( $u ) {
					return add_query_arg( 'ckit-snapshot', '1', $u );
				},
				$urls
			);
			wp_localize_script(
				'ckit-snapshot',
				'CKIT_SNAP',
				array(
					'restUrl'       => esc_url_raw( rest_url( 'cookiekit/v1/snapshot' ) ),
					'restNonce'     => wp_create_nonce( 'wp_rest' ),
					'cookieName'    => CKIT_CONSENT_COOKIE,
					'policyVersion' => CKIT_Core::settings( 'policy_version', '1.0' ),
					'categories'    => $cats,
					'waitSeconds'   => 5,
					'adminUrl'      => esc_url_raw( admin_url( 'admin.php?page=cookie-kit&tab=scanner' ) ),
					'pages'         => array_values( $urls ),
					'i18n'          => array(
						/* translators: Status shown when the browser scan starts. */
						'starting'    => __( 'Starting...', 'cookie-kit' ),
						/* translators: 1: current page number, 2: total pages. */
						'progress'    => __( 'Page %1$s / %2$s', 'cookie-kit' ),
						/* translators: 1: page number, 2: seconds to wait. */
						'loading'     => __( 'Loading page %1$s. Waiting %2$ss for tracking scripts...', 'cookie-kit' ),
						/* translators: 1: page number, 2: elapsed seconds, 3: total seconds. */
						'waiting'     => __( 'Page %1$s — waiting for cookies (%2$ss / %3$ss)', 'cookie-kit' ),
						/* translators: 1: page number, 2: number of cookies found. */
						'found'       => __( 'Page %1$s — found %2$s cookies. Sending to the server...', 'cookie-kit' ),
						/* translators: 1: total cookies saved, 2: number of pages. */
						'done'        => __( 'Snapshot completed — %1$s cookies saved in total across %2$s pages.', 'cookie-kit' ),
						/* translators: %s: page number. */
						'next'        => __( 'Page %s done. Moving to the next one in 1s...', 'cookie-kit' ),
						'backToPanel' => __( 'Back to the scanner panel', 'cookie-kit' ),
						/* translators: %s: error code or message. */
						'sendError'   => __( 'Send error: %s', 'cookie-kit' ),
						'badResponse' => __( 'invalid response', 'cookie-kit' ),
						/* translators: %s: error message. */
						'netError'    => __( 'Network error: %s', 'cookie-kit' ),
					),
				)
			);
			wp_enqueue_script( 'ckit-snapshot' );
		}

		// Inline CSS variables (theming).
		$css = sprintf(
			':root{--ckit-primary:%s;--ckit-accept:%s;--ckit-reject:%s;}',
			esc_attr( CKIT_Core::settings( 'primary_color', '#0a3d62' ) ),
			esc_attr( CKIT_Core::settings( 'accept_color', '#0a3d62' ) ),
			esc_attr( CKIT_Core::settings( 'reject_color', '#6c757d' ) )
		);
		wp_add_inline_style( 'ckit-banner', $css );
	}

	/**
	 * Print Google Consent Mode v2 defaults (denied) before GTM/GA.
	 * Must run very early in <head>, before any tracking script.
	 */
	public function inline_consent_mode_default() {
		if ( ! CKIT_Core::settings( 'consent_mode_v2', 1 ) ) {
			return;
		}
		// Snapshot mode: every signal starts as granted so tracking scripts set their cookies natively.
		if ( CKIT_Core::is_snapshot_mode() ) {
			?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage': 'granted',
  'ad_user_data': 'granted',
  'ad_personalization': 'granted',
  'analytics_storage': 'granted',
  'functionality_storage': 'granted',
  'personalization_storage': 'granted',
  'security_storage': 'granted'
});
</script>
			<?php
			return;
		}
		?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
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
</script>
		<?php
	}

	public function render() {
		// The banner is built by ckit-banner.js inside this container.
		// In snapshot mode the snapshot script works without banner interference.
		echo '<div id="ckit-root" aria-hidden="true"></div>';
	}

	/**
	 * Build, for each category, the list of cookies shown in the preferences modal.
	 * Source of truth: the wp_ckit_cookie_scan table (REAL cookies detected by the scanner).
	 * The internal dictionary (cookie_meta_dictionary) only adds duration and purpose for known cookies.
	 * No cookies "declared" in vendors: the banner only lists cookies actually detected.
	 *
	 * @return array { cat_id => [ {name, vendor, duration, description, party}, ... ] }
	 */
	public function cookies_by_category() {
		$categories = CKIT_Core::categories();
		$meta_dict  = $this->cookie_meta_dictionary();
		$out        = array();

		foreach ( $categories as $cid => $cat ) {
			$out[ $cid ] = array();
		}

		// Single source: cookies actually detected by the scanner (server-side + client-side).
		global $wpdb;
		$scan_table = $wpdb->prefix . 'ckit_cookie_scan';
		$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $scan_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		if ( $exists ) {
			$rows = $wpdb->get_results( "SELECT cookie_name, cookie_domain, category, vendor, duration, party FROM {$scan_table} ORDER BY category, cookie_name" ); // phpcs:ignore WordPress.DB
			if ( $rows ) {
				$seen = array();
				foreach ( $rows as $r ) {
					$cid = $r->category;
					if ( ! isset( $out[ $cid ] ) || 'unclassified' === $cid ) {
						continue;
					}
					$key = $cid . '|' . $r->cookie_name;
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ]  = true;
					$lookup        = $this->lookup_cookie_meta( $r->cookie_name, $meta_dict );
					$out[ $cid ][] = array(
						'name'        => $r->cookie_name,
						'vendor'      => $r->vendor ? $r->vendor : ( $r->cookie_domain ? $r->cookie_domain : '—' ),
						'duration'    => $r->duration && '—' !== $r->duration ? $r->duration : $lookup['duration'],
						'description' => $lookup['description'],
						'party'       => $r->party ? $r->party : 'first',
					);
				}
			}
		}

		// Necessary fallback: the plugin's own consent cookie must always be listed.
		if ( isset( $out['necessary'] ) && ! $this->has_cookie( $out['necessary'], CKIT_CONSENT_COOKIE ) ) {
			$out['necessary'][] = array(
				'name'        => CKIT_CONSENT_COOKIE,
				'vendor'      => __( 'Site', 'cookie-kit' ),
				'duration'    => __( '6 months', 'cookie-kit' ),
				'description' => __( 'Stores the cookie preferences expressed by the user.', 'cookie-kit' ),
				'party'       => 'first',
			);
		}

		return $out;
	}

	private function has_cookie( $list, $name ) {
		foreach ( $list as $c ) {
			if ( $c['name'] === $name ) {
				return true;
			}
		}
		return false;
	}

	private function lookup_cookie_meta( $name, $dict ) {
		// Exact match first, then * wildcard patterns.
		if ( isset( $dict[ $name ] ) ) {
			return $dict[ $name ];
		}
		foreach ( $dict as $pattern => $meta ) {
			if ( false === strpos( $pattern, '*' ) ) {
				continue;
			}
			$regex = '/^' . str_replace( '\\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';
			if ( preg_match( $regex, $name ) ) {
				return $meta;
			}
		}
		return array(
			'duration'    => '—',
			'description' => '',
		);
	}

	/**
	 * Known cookies: duration and purpose shown in the preferences modal.
	 * Values come from CookieKit 1.1.x; vendors change their cookies over time,
	 * so site owners should check them against their cookie policy.
	 *
	 * @return array
	 */
	private function cookie_meta_dictionary() {
		$session = __( 'Session', 'cookie-kit' );
		return array(
			// Technical / WordPress / site.
			'PHPSESSID'                => array( 'duration' => $session, 'description' => __( 'Technical identifier of the PHP session.', 'cookie-kit' ) ),
			'wordpress_logged_in_*'    => array( 'duration' => __( '48 hours', 'cookie-kit' ), 'description' => __( 'Keeps registered users logged in to the reserved area.', 'cookie-kit' ) ),
			'wordpress_sec_*'          => array( 'duration' => $session, 'description' => __( 'Security cookie for the reserved area.', 'cookie-kit' ) ),
			'wp-settings-*'            => array( 'duration' => __( '1 year', 'cookie-kit' ), 'description' => __( 'Stores WordPress dashboard preferences.', 'cookie-kit' ) ),
			'wp-settings-time-*'       => array( 'duration' => __( '1 year', 'cookie-kit' ), 'description' => __( 'Timestamp of WordPress dashboard preferences.', 'cookie-kit' ) ),
			'ckit_consent'             => array( 'duration' => __( '6 months', 'cookie-kit' ), 'description' => __( 'Stores the cookie preferences expressed by the user.', 'cookie-kit' ) ),
			// Cloudflare.
			'__cf_bm'                  => array( 'duration' => __( '30 minutes', 'cookie-kit' ), 'description' => __( 'Cloudflare Bot Management: distinguishes human traffic from bots.', 'cookie-kit' ) ),
			'__cflb'                   => array( 'duration' => __( '1 day', 'cookie-kit' ), 'description' => __( 'Cloudflare Load Balancing.', 'cookie-kit' ) ),
			'__cfduid'                 => array( 'duration' => __( '30 days', 'cookie-kit' ), 'description' => __( 'Cloudflare: identifies the client to apply security rules.', 'cookie-kit' ) ),
			'cf_clearance'             => array( 'duration' => __( '30 days', 'cookie-kit' ), 'description' => __( 'Cloudflare: an anti-bot challenge was passed.', 'cookie-kit' ) ),
			'cf_chl_*'                 => array( 'duration' => $session, 'description' => __( 'Cloudflare: state of an anti-bot challenge in progress.', 'cookie-kit' ) ),
			// Google Analytics.
			'_ga'                      => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'Google Analytics: uniquely identifies the user.', 'cookie-kit' ) ),
			'_ga_*'                    => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'Google Analytics 4: persists the session state.', 'cookie-kit' ) ),
			'_gid'                     => array( 'duration' => __( '24 hours', 'cookie-kit' ), 'description' => __( 'Google Analytics: distinguishes users within 24 hours.', 'cookie-kit' ) ),
			'_gat'                     => array( 'duration' => __( '1 minute', 'cookie-kit' ), 'description' => __( 'Google Analytics: limits the request rate (throttling).', 'cookie-kit' ) ),
			// Google Tag Manager.
			'_dc_gtm_*'                => array( 'duration' => __( '1 minute', 'cookie-kit' ), 'description' => __( 'Google Tag Manager: request throttling.', 'cookie-kit' ) ),
			// Google Ads / Marketing Platform.
			'IDE'                      => array( 'duration' => __( '13 months', 'cookie-kit' ), 'description' => __( 'Google Ads: personalised advertising and remarketing.', 'cookie-kit' ) ),
			'test_cookie'              => array( 'duration' => __( '15 minutes', 'cookie-kit' ), 'description' => __( 'Google Ads: checks whether the browser supports cookies.', 'cookie-kit' ) ),
			'DSID'                     => array( 'duration' => __( '14 days', 'cookie-kit' ), 'description' => __( 'Google: cross-device advertising identifier.', 'cookie-kit' ) ),
			'id'                       => array( 'duration' => __( '13 months', 'cookie-kit' ), 'description' => __( 'Google DoubleClick: unique identifier for advertising.', 'cookie-kit' ) ),
			'RUL'                      => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Google DoubleClick: tracking of advertising redirects.', 'cookie-kit' ) ),
			'_gcl_au'                  => array( 'duration' => __( '3 months', 'cookie-kit' ), 'description' => __( 'Google Conversion Linker: links visits and Google Ads conversions.', 'cookie-kit' ) ),
			'_gcl_aw'                  => array( 'duration' => __( '3 months', 'cookie-kit' ), 'description' => __( 'Google Ads: click tracking for advertising campaigns.', 'cookie-kit' ) ),
			'_gcl_dc'                  => array( 'duration' => __( '3 months', 'cookie-kit' ), 'description' => __( 'Google Display Network: click tracking.', 'cookie-kit' ) ),
			'1P_JAR'                   => array( 'duration' => __( '1 month', 'cookie-kit' ), 'description' => __( 'Google: statistics collection and ad personalisation.', 'cookie-kit' ) ),
			// Facebook / Meta.
			'_fbp'                     => array( 'duration' => __( '90 days', 'cookie-kit' ), 'description' => __( 'Meta Pixel: conversion and advertising campaign tracking.', 'cookie-kit' ) ),
			'_fbc'                     => array( 'duration' => __( '90 days', 'cookie-kit' ), 'description' => __( 'Meta Pixel: advertising click tracking.', 'cookie-kit' ) ),
			'fr'                       => array( 'duration' => __( '90 days', 'cookie-kit' ), 'description' => __( 'Facebook: personalised advertising.', 'cookie-kit' ) ),
			// LinkedIn.
			'bcookie'                  => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'LinkedIn: browser identifier.', 'cookie-kit' ) ),
			'li_sugr'                  => array( 'duration' => __( '90 days', 'cookie-kit' ), 'description' => __( 'LinkedIn: user identification for advertising.', 'cookie-kit' ) ),
			// X / Twitter.
			'guest_id'                 => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'X: visitor identifier.', 'cookie-kit' ) ),
			'personalization_id'       => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'X: content and advertising personalisation.', 'cookie-kit' ) ),
			// YouTube.
			'VISITOR_INFO1_LIVE'       => array( 'duration' => __( '6 months', 'cookie-kit' ), 'description' => __( 'YouTube: bandwidth estimation and video statistics.', 'cookie-kit' ) ),
			'VISITOR_PRIVACY_METADATA' => array( 'duration' => __( '6 months', 'cookie-kit' ), 'description' => __( 'YouTube: stores the user\'s privacy preferences in the video player.', 'cookie-kit' ) ),
			'YSC'                      => array( 'duration' => $session, 'description' => __( 'YouTube: tracks views within the session.', 'cookie-kit' ) ),
			'PREF'                     => array( 'duration' => __( '8 months', 'cookie-kit' ), 'description' => __( 'YouTube/Google: user preferences.', 'cookie-kit' ) ),
			// Google Maps.
			'NID'                      => array( 'duration' => __( '6 months', 'cookie-kit' ), 'description' => __( 'Google: ad and content personalisation.', 'cookie-kit' ) ),
			'CONSENT'                  => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'Google: user consent status.', 'cookie-kit' ) ),
			// Microsoft Clarity.
			'_clck'                    => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: user identifier for heatmaps and session replay.', 'cookie-kit' ) ),
			'_clsk'                    => array( 'duration' => __( '24 hours', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: session identifier.', 'cookie-kit' ) ),
			'CLID'                     => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: client identifier.', 'cookie-kit' ) ),
			'MUID'                     => array( 'duration' => __( '13 months', 'cookie-kit' ), 'description' => __( 'Microsoft: unique cross-property identifier.', 'cookie-kit' ) ),
			// Hotjar.
			'_hjSessionUser_*'         => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Hotjar: user identifier for heatmaps.', 'cookie-kit' ) ),
			'_hjSession_*'             => array( 'duration' => __( '30 minutes', 'cookie-kit' ), 'description' => __( 'Hotjar: session identifier.', 'cookie-kit' ) ),
			'_hjFirstSeen'             => array( 'duration' => $session, 'description' => __( 'Hotjar: the user\'s first visit.', 'cookie-kit' ) ),
			// Vimeo.
			'vuid'                     => array( 'duration' => __( '24 months', 'cookie-kit' ), 'description' => __( 'Vimeo: user identifier for video analytics.', 'cookie-kit' ) ),
			'player'                   => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Vimeo: video player preferences (quality, volume, size).', 'cookie-kit' ) ),
			'continuous_play_v3'       => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Vimeo: continuous video playback.', 'cookie-kit' ) ),
			// Amazon affiliate.
			'session-id'               => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Amazon: user session identifier.', 'cookie-kit' ) ),
			'ubid-main'                => array( 'duration' => __( '12 months', 'cookie-kit' ), 'description' => __( 'Amazon: unique identifier.', 'cookie-kit' ) ),
		);
	}

	public function shortcode_button( $atts = array() ) {
		$atts  = shortcode_atts(
			array(
				'label' => CKIT_Core::texts( 'floating_button' ),
				'class' => 'ckit-manage-link',
			),
			$atts
		);
		$label = $atts['label'] ? $atts['label'] : __( 'Manage cookies', 'cookie-kit' );
		return sprintf(
			'<a href="#" class="%s" data-ckit-open-prefs="1">%s</a>',
			esc_attr( $atts['class'] ),
			esc_html( $label )
		);
	}
}
