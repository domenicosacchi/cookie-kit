<?php
/**
 * Plugin Name:       CookieKit
 * Plugin URI:        https://github.com/domenicosacchi/cookie-kit
 * Description:       Self-hosted cookie consent banner designed around the Italian Data Protection Authority guidelines (Garante Privacy, provvedimento 231/2021, 10 June 2021) and Google Consent Mode v2. Third-party script auto-blocking, cookie scanner, exportable consent log.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Domenico Sacchi
 * Author URI:        https://github.com/domenicosacchi/cookie-kit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cookie-kit
 * Domain Path:       /languages
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'CKIT_VERSION', '1.2.1' );
define( 'CKIT_PLUGIN_FILE', __FILE__ );
define( 'CKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CKIT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CKIT_DB_VERSION', '1.0' );
define( 'CKIT_OPTION_KEY', 'ckit_settings' );
define( 'CKIT_CONSENT_COOKIE', 'ckit_consent' );
define( 'CKIT_REPROMPT_DAYS', 183 ); // Days before the banner is shown again.

// Autoloader for plugin classes (CKIT_* prefix).
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'CKIT_' ) !== 0 ) {
			return;
		}
		$slug      = strtolower( str_replace( array( 'CKIT_', '_' ), array( '', '-' ), $class ) );
		$filename  = 'class-ckit-' . $slug . '.php';
		$locations = array(
			CKIT_PLUGIN_DIR . 'includes/' . $filename,
			CKIT_PLUGIN_DIR . 'admin/' . $filename,
		);
		foreach ( $locations as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

// Activation / Deactivation.
register_activation_hook( __FILE__, array( 'CKIT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CKIT_Activator', 'deactivate' ) );

// Bootstrap.
add_action(
	'plugins_loaded',
	function () {
		CKIT_Core::instance();
	}
);
