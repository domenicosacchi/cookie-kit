<?php
/**
 * REST API — Endpoints used by the front-end scripts.
 *
 * POST /wp-json/cookiekit/v1/consent   (public: records a consent choice in the log)
 *   body: { action, categories, page_url, language, consent_uuid }
 *   header: X-WP-Nonce (wp_rest), checked by WordPress only for logged-in users
 *
 * POST /wp-json/cookiekit/v1/snapshot  (manage_options: client-side cookie scanner)
 *   body: { cookies: [ "name", ... ], page_url }
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Rest {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'cookiekit/v1',
			'/consent',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'log_consent' ),
					'permission_callback' => '__return_true', // Public on purpose: visitors are not logged in. Input is validated in log_consent().
				),
			)
		);
		register_rest_route(
			'cookiekit/v1',
			'/snapshot',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_snapshot' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	/**
	 * Receive a client-side snapshot: cookie names collected by the logged-in admin's browser.
	 *
	 * JSON body:
	 *  - cookies: array of cookie names (strings)
	 *  - page_url: URL of the visited page (must belong to this site to be fetched for enrichment)
	 *
	 * Administrators only (manage_options).
	 */
	public function save_snapshot( WP_REST_Request $request ) {
		$cookies = $request->get_param( 'cookies' );
		$page    = esc_url_raw( (string) $request->get_param( 'page_url' ) );

		if ( ! is_array( $cookies ) ) {
			$cookies = array();
		}
		// Accept only cookie names (strings); drop anything else.
		$cookies = array_values(
			array_filter(
				array_map(
					function ( $c ) {
						return is_string( $c ) ? sanitize_text_field( $c ) : '';
					},
					$cookies
				),
				'strlen'
			)
		);

		// Merge HttpOnly cookies (e.g. PHPSESSID): the browser sends them in the Cookie header
		// of this REST request, so they are available server-side in $_COOKIE even though
		// document.cookie cannot see them.
		$server_cookies = array();
		if ( is_array( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $name ) {
				$server_cookies[] = sanitize_text_field( $name );
			}
		}

		// Skip WordPress login/dashboard cookies: only the scanning admin has them, public visitors do not.
		$all = array_values(
			array_filter(
				array_unique( array_merge( $cookies, $server_cookies ) ),
				function ( $name ) {
					return ! preg_match( '/^(wordpress_(?:logged_in|sec|test_cookie)|wp-settings)/i', $name );
				}
			)
		);

		// A page without first-party cookies is a valid result: keep scanning (vendor enrichment below).
		$scanner = CKIT_Core::instance()->scanner;
		$saved   = $all ? $scanner->save_snapshot( $all, $page ) : 0;

		// Vendor catalog enrichment: detect third-party vendors in the page HTML
		// (YouTube iframes, GTM scripts, Vimeo embeds) and add their known cookies as third-party.
		$enriched = $scanner->enrich_from_html( $page );

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'count'      => $saved + $enriched,
				'js_count'   => count( $cookies ),
				'http_count' => count( $server_cookies ),
				'enriched'   => $enriched,
				'page'       => $page,
			),
			200
		);
	}

	public function log_consent( WP_REST_Request $request ) {
		$action       = sanitize_text_field( (string) $request->get_param( 'action' ) );
		$categories   = $request->get_param( 'categories' );
		$page_url     = substr( esc_url_raw( (string) $request->get_param( 'page_url' ) ), 0, 500 );
		$language     = substr( preg_replace( '/[^A-Za-z_-]/', '', (string) $request->get_param( 'language' ) ), 0, 10 );
		$consent_uuid = sanitize_text_field( (string) $request->get_param( 'consent_uuid' ) );

		$valid_actions = array( 'accept_all', 'reject_all', 'customize', 'revoke' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'invalid_action' ), 400 );
		}

		// Keep only category IDs that actually exist (prevents arbitrary data in the log).
		$known_categories = array_keys( CKIT_Core::categories() );
		if ( is_array( $categories ) ) {
			$categories = array_values(
				array_intersect(
					array_map( 'sanitize_key', array_filter( $categories, 'is_scalar' ) ),
					$known_categories
				)
			);
		} else {
			$categories = array();
		}

		// The column is CHAR(36): accept only a well-formed UUID, otherwise generate one.
		if ( ! wp_is_uuid( $consent_uuid ) ) {
			$consent_uuid = wp_generate_uuid4();
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( strlen( $ua ) > 500 ) {
			$ua = substr( $ua, 0, 500 );
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * absint( CKIT_Core::settings( 'reprompt_days', CKIT_REPROMPT_DAYS ) ) );

		$log = CKIT_Core::instance()->log;
		$id  = $log->insert(
			array(
				'consent_uuid'   => $consent_uuid,
				'action'         => $action,
				'categories'     => $categories,
				'policy_version' => CKIT_Core::settings( 'policy_version', '1.0' ),
				'page_url'       => $page_url,
				'user_agent'     => $ua,
				'language'       => $language,
				'expires_at'     => $expires_at,
			)
		);

		return new WP_REST_Response(
			array(
				'ok'           => true,
				'id'           => $id,
				'consent_uuid' => $consent_uuid,
				'expires_at'   => $expires_at,
			),
			200
		);
	}
}
