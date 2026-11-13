<?php
/**
 * Uninstall script — eseguito quando il plugin viene eliminato (non solo disattivato).
 * Rimuove tabelle, opzioni e cookie residui.
 *
 * @package CookieKit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove CookieKit data for the current site.
 */
function ckit_uninstall_site() {
	global $wpdb;

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'ckit_consent_log',
		$wpdb->prefix . 'ckit_cookie_scan',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	// Remove options.
	$options = array(
		'ckit_settings',
		'ckit_db_version',
		'ckit_categories',
		'ckit_vendors',
		'ckit_texts',
	);
	foreach ( $options as $o ) {
		delete_option( $o );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'ckit_daily_cleanup' );
}

if ( is_multisite() ) {
	$ckit_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $ckit_site_ids as $ckit_site_id ) {
		switch_to_blog( $ckit_site_id );
		ckit_uninstall_site();
		restore_current_blog();
	}
	foreach ( array( 'ckit_settings', 'ckit_db_version', 'ckit_categories', 'ckit_vendors', 'ckit_texts' ) as $ckit_option ) {
		delete_site_option( $ckit_option );
	}
} else {
	ckit_uninstall_site();
}
