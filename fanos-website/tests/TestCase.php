<?php
/**
 * Base test case wiring Brain Monkey and a set of realistic WordPress function shims.
 *
 * @package FANOS\Tests
 */

declare( strict_types=1 );

namespace FANOS\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfilledTestCase;

// Prefer the polyfilled base (setUp/tearDown signatures) when available.
if ( class_exists( PolyfilledTestCase::class ) ) {
	abstract class BaseTestCase extends PolyfilledTestCase {}
} else {
	abstract class BaseTestCase extends PHPUnitTestCase {}
}

abstract class TestCase extends BaseTestCase {

	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		// Realistic-enough shims for the sanitisers/helpers the pure logic relies on.
		Functions\stubs(
			array(
				'sanitize_text_field'     => static fn( $s ) => trim( (string) preg_replace( '/[\r\n\t]+/', ' ', wp_strip_tags_shim( (string) $s ) ) ),
				'sanitize_textarea_field' => static fn( $s ) => wp_strip_tags_shim( (string) $s ),
				'sanitize_email'          => static fn( $s ) => (string) preg_replace( '/[^a-zA-Z0-9.@_+\-]/', '', (string) $s ),
				'sanitize_key'            => static fn( $s ) => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ),
				'sanitize_title'          => static function ( $s ) {
					$s = strtolower( trim( (string) $s ) );
					$s = (string) preg_replace( '/[^a-z0-9]+/', '-', $s );
					return trim( $s, '-' );
				},
				'esc_url_raw'             => static fn( $s ) => (string) $s,
				'wp_strip_all_tags'       => static fn( $s ) => trim( wp_strip_tags_shim( (string) $s ) ),
				'wp_json_encode'          => static fn( $data, $options = 0, $depth = 512 ) => json_encode( $data, (int) $options, (int) $depth ),
				'is_email'                => static fn( $s ) => filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false,
			)
		);
	}

	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}
}

if ( ! function_exists( 'FANOS\\Tests\\wp_strip_tags_shim' ) ) {
	/**
	 * Minimal strip_tags used by the sanitiser shims.
	 */
	function wp_strip_tags_shim( string $value ): string {
		return strip_tags( $value );
	}
}
