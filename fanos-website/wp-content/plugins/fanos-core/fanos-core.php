<?php
/**
 * Plugin Name:       FANOS Core
 * Plugin URI:        https://fanosconsulting.com
 * Description:       Content types, library filtering, forms, newsletter, analytics, and SEO foundations for the FANOS public website (Phase 1). Presentation lives in the FANOS theme; this plugin owns the data model so it survives theme changes.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Zelalem Mekonnen
 * License:           Proprietary
 * Text Domain:       fanos
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'FANOS_CORE_VERSION', '1.0.0' );
define( 'FANOS_CORE_FILE', __FILE__ );
define( 'FANOS_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'FANOS_CORE_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (present in the deployed build) with a graceful fallback
// to a lightweight PSR-4 loader so the plugin also runs from a bare checkout.
$fanos_autoload = FANOS_CORE_DIR . 'vendor/autoload.php';
if ( is_readable( $fanos_autoload ) ) {
	require_once $fanos_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'FANOS\\Core\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$path     = FANOS_CORE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Boot the plugin on plugins_loaded so every module hooks in at a predictable time.
 */
function bootstrap(): Plugin {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new Plugin();
		$plugin->register();
	}
	return $plugin;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

// Content types and rewrite rules must exist at (de)activation so permalinks flush cleanly.
register_activation_hook(
	__FILE__,
	static function (): void {
		( new PostTypes\PostTypes() )->register();
		( new Taxonomies\Taxonomies() )->register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
