<?php
/**
 * REST API — Endpoint per la registrazione del consenso lato JS.
 *
 * POST /wp-json/cookiekit/v1/consent
 *   body: { action, categories, page_url, language, consent_uuid }
 *   nonce header: X-WP-Nonce (wp_rest)
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
					'permission_callback' => '__return_true', // l'endpoint accetta consenso anche da utenti non loggati.
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
	 * Riceve uno snapshot client-side: lista cookie raccolti dal browser dell'admin loggato.
	 *
	 * Body JSON:
	 *  - cookies: array di stringhe nome=valore  (oppure array di {name, value})
	 *  - page_url: URL della pagina visitata
	 *
	 * Solo admin (manage_options).
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

		// Integra cookie HttpOnly (es. PHPSESSID): il browser li include nell'header Cookie
		// della richiesta REST, così sono accessibili lato server in $_COOKIE.
		// document.cookie lato JS non li vede, ma noi li uniamo qui.
		$server_cookies = array();
		if ( is_array( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $name ) {
				// Filtra cookie tecnici WP loggato che si vedono solo lato admin (non sono installati per gli utenti pubblici).
				if ( preg_match( '/^(wordpress_(?:logged_in|sec)|wp-settings)/i', $name ) ) {
					continue;
				}
				$server_cookies[] = sanitize_text_field( $name );
			}
		}

		$all = array_values( array_unique( array_merge( $cookies, $server_cookies ) ) );

		if ( empty( $all ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'error' => 'no_cookies' ),
				400
			);
		}

		$scanner = CKIT_Core::instance()->scanner;
		$saved   = $scanner->save_snapshot( $all, $page );

		// Vendor catalog enrichment: rileva vendor third-party nell'HTML della pagina
		// (iframe YouTube, script GTM, embed Vimeo) e aggiunge i loro cookie noti come third-party.
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
