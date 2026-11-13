<?php
/**
 * Core — Singleton bootstrap. Initializes components, registers hooks, exposes helpers.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Core {

	private static $instance = null;
	public $banner;
	public $blocker;
	public $scanner;
	public $log;
	public $rest;
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->log     = new CKIT_Log();
		$this->banner  = new CKIT_Banner();
		$this->blocker = new CKIT_Blocker();
		$this->scanner = new CKIT_Scanner();
		$this->rest    = new CKIT_Rest();

		if ( is_admin() ) {
			$this->admin = new CKIT_Admin();
		}

		// Daily cron cleanup.
		add_action( 'ckit_daily_cleanup', array( $this, 'daily_cleanup' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'cookie-kit', false, dirname( CKIT_PLUGIN_BASENAME ) . '/languages' );
	}

	public static function settings( $key = null, $default = null ) {
		$opts = get_option( CKIT_OPTION_KEY, array() );
		if ( null === $key ) {
			return $opts;
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	/**
	 * Default privacy policy URL (relative path), translatable.
	 *
	 * @return string
	 */
	public static function default_privacy_policy_url() {
		return _x( '/privacy-policy/', 'default privacy policy URL path', 'cookie-kit' );
	}

	/**
	 * Default cookie policy URL (relative path), translatable.
	 *
	 * @return string
	 */
	public static function default_cookie_policy_url() {
		return _x( '/cookie-policy/', 'default cookie policy URL path', 'cookie-kit' );
	}

	/**
	 * Effective privacy policy URL: saved setting, or the translated default.
	 *
	 * @return string
	 */
	public static function privacy_policy_url() {
		$url = self::settings( 'privacy_policy_url', '' );
		return $url ? $url : self::default_privacy_policy_url();
	}

	/**
	 * Effective cookie policy URL: saved setting, or the translated default.
	 *
	 * @return string
	 */
	public static function cookie_policy_url() {
		$url = self::settings( 'cookie_policy_url', '' );
		return $url ? $url : self::default_cookie_policy_url();
	}

	/**
	 * Built-in categories with translated names and descriptions.
	 *
	 * @return array
	 */
	public static function default_categories() {
		return array(
			'necessary'   => array(
				'name'        => __( 'Necessary', 'cookie-kit' ),
				'description' => __( 'Technical cookies strictly necessary for the site to work. Always active.', 'cookie-kit' ),
				'required'    => 1,
			),
			'preferences' => array(
				'name'        => __( 'Preferences', 'cookie-kit' ),
				'description' => __( 'Cookies that remember choices such as language or region, improving the user experience.', 'cookie-kit' ),
				'required'    => 0,
			),
			'analytics'   => array(
				'name'        => __( 'Analytics', 'cookie-kit' ),
				'description' => __( 'Cookies used to collect aggregated information about how the site is used.', 'cookie-kit' ),
				'required'    => 0,
			),
			'marketing'   => array(
				'name'        => __( 'Marketing', 'cookie-kit' ),
				'description' => __( 'Cookies used to deliver more relevant advertising and measure the effectiveness of campaigns.', 'cookie-kit' ),
				'required'    => 0,
			),
		);
	}

	/**
	 * Categories as saved in the options. For built-in categories an empty name or description
	 * falls back to the translated default, so new installs follow the site language, while
	 * texts saved by earlier versions (or edited by the admin) are kept as they are.
	 *
	 * @return array
	 */
	public static function categories() {
		$stored   = get_option( 'ckit_categories', array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::default_categories();
		foreach ( $stored as $id => $cat ) {
			if ( ! is_array( $cat ) ) {
				unset( $stored[ $id ] );
				continue;
			}
			foreach ( array( 'name', 'description' ) as $field ) {
				if ( ! isset( $cat[ $field ] ) || '' === trim( (string) $cat[ $field ] ) ) {
					$cat[ $field ] = isset( $defaults[ $id ] ) ? $defaults[ $id ][ $field ] : ( 'name' === $field ? (string) $id : '' );
				}
			}
			$cat['required'] = ! empty( $cat['required'] ) ? 1 : 0;
			$stored[ $id ]   = $cat;
		}
		return $stored;
	}

	public static function vendors() {
		return get_option( 'ckit_vendors', array() );
	}

	/**
	 * Default banner texts, translated in the current locale.
	 *
	 * @return array
	 */
	public static function default_texts() {
		return array(
			'banner_title'      => __( 'We respect your privacy', 'cookie-kit' ),
			'banner_message'    => sprintf(
				/* translators: 1: privacy policy URL, 2: cookie policy URL. Keep the [link text](URL) syntax. */
				__( 'We use technical cookies and, with your consent, analytics and third-party cookies to improve your browsing experience, analyse site traffic and provide embedded content. We share information about your use of the site with our analytics and advertising partners, who may combine it with other information you have provided to them. For more information, read our [Privacy Policy](%1$s) and [Cookie Policy](%2$s).', 'cookie-kit' ),
				self::privacy_policy_url(),
				self::cookie_policy_url()
			),
			'btn_accept_all'    => __( 'Accept all', 'cookie-kit' ),
			'btn_reject_all'    => __( 'Reject all', 'cookie-kit' ),
			'btn_customize'     => __( 'Customize', 'cookie-kit' ),
			'btn_save'          => __( 'Save preferences', 'cookie-kit' ),
			'btn_close'         => __( 'Close', 'cookie-kit' ),
			'modal_title'       => '', // Empty: the preferences modal reuses banner_title.
			'modal_intro'       => '', // Empty: the preferences modal reuses banner_message (inline links included).
			'floating_button'   => __( 'Manage cookies', 'cookie-kit' ),
			'privacy_link_text' => __( 'Privacy Policy', 'cookie-kit' ),
			'cookies_link_text' => __( 'Cookie Policy', 'cookie-kit' ),
		);
	}

	/**
	 * Banner texts: saved non-empty values override the translated defaults.
	 *
	 * @param string|null $key Optional text key.
	 * @return array|string
	 */
	public static function texts( $key = null ) {
		$stored = get_option( 'ckit_texts', array() );
		$texts  = self::default_texts();
		if ( is_array( $stored ) ) {
			foreach ( $stored as $k => $v ) {
				if ( is_string( $v ) && '' !== trim( $v ) ) {
					$texts[ $k ] = $v;
				}
			}
		}
		if ( null === $key ) {
			return $texts;
		}
		return isset( $texts[ $key ] ) ? $texts[ $key ] : '';
	}

	public function daily_cleanup() {
		$retention = absint( self::settings( 'log_retention_days', 1825 ) );
		if ( $retention > 0 && $this->log ) {
			$this->log->purge_older_than( $retention );
		}
	}

	/**
	 * Snapshot mode: ?ckit-snapshot=1 and a logged-in administrator.
	 * In this mode the banner and the blocker are disabled, third-party scripts run natively
	 * and the snapshot script reads the full document.cookie.
	 */
	public static function is_snapshot_mode() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['ckit-snapshot'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['ckit-snapshot'] ) ) ) {
			return false;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return true;
	}
}
