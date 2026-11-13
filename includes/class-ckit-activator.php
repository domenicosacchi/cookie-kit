<?php
/**
 * Activator — Creates the DB schema, stores default options, schedules cron.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Activator {

	public static function activate() {
		self::create_tables();
		self::set_default_options();
		if ( ! wp_next_scheduled( 'ckit_daily_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'ckit_daily_cleanup' );
		}
		update_option( 'ckit_db_version', CKIT_DB_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'ckit_daily_cleanup' );
	}

	private static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$consent_log = $wpdb->prefix . 'ckit_consent_log';
		$cookie_scan = $wpdb->prefix . 'ckit_cookie_scan';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_log = "CREATE TABLE {$consent_log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			consent_uuid CHAR(36) NOT NULL,
			action VARCHAR(20) NOT NULL,
			categories TEXT NOT NULL,
			policy_version VARCHAR(20) DEFAULT NULL,
			page_url VARCHAR(500) DEFAULT NULL,
			ip_hash CHAR(64) DEFAULT NULL,
			user_agent VARCHAR(500) DEFAULT NULL,
			language VARCHAR(10) DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY consent_uuid (consent_uuid),
			KEY created_at (created_at)
		) {$charset};";

		$sql_scan = "CREATE TABLE {$cookie_scan} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			cookie_name VARCHAR(255) NOT NULL,
			cookie_domain VARCHAR(255) DEFAULT NULL,
			category VARCHAR(50) DEFAULT 'unclassified',
			party VARCHAR(20) DEFAULT 'unknown',
			vendor VARCHAR(255) DEFAULT NULL,
			duration VARCHAR(50) DEFAULT NULL,
			description TEXT,
			source_url VARCHAR(500) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY cookie_name_domain (cookie_name, cookie_domain),
			KEY category (category)
		) {$charset};";

		dbDelta( $sql_log );
		dbDelta( $sql_scan );
	}

	private static function set_default_options() {
		if ( false === get_option( CKIT_OPTION_KEY ) ) {
			$defaults = array(
				'enabled'              => 1,
				'banner_position'      => 'bottom',     // bottom | center | top.
				'banner_layout'        => 'bar',         // bar (full-width strip) | box (centered box).
				'theme'                => 'light',       // light | dark.
				'primary_color'        => '#0a3d62',
				'accept_color'         => '#0a3d62',
				'reject_color'         => '#6c757d',
				'font_family'          => 'inherit',
				'show_logo'            => 0,
				'logo_url'             => '',
				'logo_max_height'      => 56,
				'consent_mode_v2'      => 1,
				'auto_block'           => 1,
				'reprompt_days'        => 180,
				'log_retention_days'   => 1825, // 5 years.
				// Empty: the translated default path is used at runtime (see CKIT_Core::privacy_policy_url()).
				'privacy_policy_url'   => '',
				'cookie_policy_url'    => '',
				'show_floating_button' => 1,
				'floating_position'    => 'bottom-left', // bottom-left | bottom-right.
				'block_iframes'        => 1,
				'policy_version'       => '1.0',
			);
			add_option( CKIT_OPTION_KEY, $defaults );
		}

		if ( false === get_option( 'ckit_categories' ) ) {
			// Names and descriptions are left empty: CKIT_Core::categories() fills them with the
			// translated defaults at runtime, so they follow the site language until edited.
			$categories = array();
			foreach ( array( 'necessary' => 1, 'preferences' => 0, 'analytics' => 0, 'marketing' => 0 ) as $id => $required ) {
				$categories[ $id ] = array(
					'name'        => '',
					'description' => '',
					'required'    => $required,
				);
			}
			add_option( 'ckit_categories', $categories );
		}

		if ( false === get_option( 'ckit_vendors' ) ) {
			$vendors = array(
				array(
					'name'      => 'Google Analytics 4',
					'category'  => 'analytics',
					'host'      => 'google-analytics.com',
					'pattern'   => '/(?:gtag|ga\.js|analytics\.js|googletagmanager\.com\/gtag\/js)/i',
					'cookies'   => '_ga,_ga_*,_gid,_gat',
				),
				array(
					'name'      => 'Google Tag Manager',
					'category'  => 'analytics',
					'host'      => 'googletagmanager.com',
					'pattern'   => '/googletagmanager\.com\/gtm\.js/i',
					'cookies'   => '_dc_gtm_*,_gcl_au,_gcl_aw,_gcl_dc',
				),
				array(
					'name'      => 'Google Ads / DoubleClick',
					'category'  => 'marketing',
					'host'      => 'pagead2.googlesyndication.com',
					'pattern'   => '/(?:adsbygoogle|pagead2\.googlesyndication\.com|googleadservices\.com\/pagead|googletagservices\.com\/tag\/js\/gpt\.js)/i',
					'cookies'   => 'IDE,test_cookie,DSID,1P_JAR,id,RUL',
				),
				array(
					'name'      => 'YouTube Embed',
					'category'  => 'marketing',
					'host'      => 'youtube.com',
					'pattern'   => '/(?:youtube\.com\/embed|youtube-nocookie\.com)/i',
					'cookies'   => 'VISITOR_INFO1_LIVE,VISITOR_PRIVACY_METADATA,YSC,PREF',
				),
				array(
					'name'      => 'Vimeo',
					'category'  => 'marketing',
					'host'      => 'vimeo.com',
					'pattern'   => '/player\.vimeo\.com/i',
					'cookies'   => 'vuid,player',
				),
				array(
					'name'      => 'Google Maps',
					'category'  => 'marketing',
					'host'      => 'maps.google.com',
					'pattern'   => '/(?:maps\.google\.com|maps\.googleapis\.com)/i',
					'cookies'   => 'NID,CONSENT',
				),
				array(
					'name'      => 'Cloudflare',
					'category'  => 'necessary',
					'host'      => 'cloudflare.com',
					'pattern'   => '/cloudflare\.com/i',
					'cookies'   => '__cf_bm,__cflb,__cfduid,cf_clearance,cf_chl_*,cf_ob_info',
				),
			);
			add_option( 'ckit_vendors', $vendors );
		}

		if ( false === get_option( 'ckit_texts' ) ) {
			// No stored texts: CKIT_Core::texts() uses the translated defaults until the admin
			// saves custom texts. Installs created before 1.2.0 keep their stored texts.
			add_option( 'ckit_texts', array() );
		}
	}
}
