<?php
/**
 * Core — Singleton bootstrap. Inizializza componenti, registra hook, espone helper.
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
		load_plugin_textdomain( 'cookie-kit', false, dirname( CKIT_PLUGIN_BASENAME ) . '/languages' );

		$this->log     = new CKIT_Log();
		$this->banner  = new CKIT_Banner();
		$this->blocker = new CKIT_Blocker();
		$this->scanner = new CKIT_Scanner();
		$this->rest    = new CKIT_Rest();

		if ( is_admin() ) {
			$this->admin = new CKIT_Admin();
		}

		// Cron daily cleanup.
		add_action( 'ckit_daily_cleanup', array( $this, 'daily_cleanup' ) );
	}

	public static function settings( $key = null, $default = null ) {
		$opts = get_option( CKIT_OPTION_KEY, array() );
		if ( null === $key ) {
			return $opts;
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	public static function categories() {
		return get_option( 'ckit_categories', array() );
	}

	public static function vendors() {
		return get_option( 'ckit_vendors', array() );
	}

	public static function texts( $key = null ) {
		$texts = get_option( 'ckit_texts', array() );
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
	 * Modalità snapshot: ?ckit-snapshot=1 + admin loggato.
	 * In questa modalità banner e blocker sono disattivati, gli script terzi partono nativi,
	 * lo snapshot legge document.cookie completo.
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
