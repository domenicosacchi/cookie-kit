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

global $wpdb;

// Drop custom tables.
$tables = array(
	$wpdb->prefix . 'ckit_consent_log',
	$wpdb->prefix . 'ckit_cookie_scan',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	delete_site_option( $o );
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'ckit_daily_cleanup' );
