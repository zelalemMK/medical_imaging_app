<?php
/**
 * PHPUnit bootstrap for the FANOS core unit tests.
 *
 * These are fast, isolated unit tests: WordPress is not loaded. Brain Monkey provides
 * the WP function shims (see TestCase), so the plugin's business logic is exercised
 * directly. Constants the plugin expects are defined here.
 *
 * @package FANOS\Tests
 */

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	// Marker so the plugin's guard clauses treat us as "inside WordPress".
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'FANOS_CORE_VERSION' ) ) {
	define( 'FANOS_CORE_VERSION', 'test' );
}
if ( ! defined( 'FANOS_CORE_URL' ) ) {
	define( 'FANOS_CORE_URL', 'https://example.test/wp-content/plugins/fanos-core/' );
}
