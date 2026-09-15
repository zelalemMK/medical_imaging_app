<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Forms\Sanitizer;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Forms\Sanitizer
 */
final class SanitizerTest extends TestCase {

	private Sanitizer $sanitizer;

	protected function set_up(): void {
		parent::set_up();
		$this->sanitizer = new Sanitizer();
	}

	public function test_text_strips_tags_and_trims(): void {
		$this->assertSame( 'Hello there', $this->sanitizer->sanitize_value( '  <b>Hello</b> there ', 'text' ) );
	}

	public function test_textarea_preserves_newlines(): void {
		$out = $this->sanitizer->sanitize_value( "line one\nline two", 'textarea' );
		$this->assertStringContainsString( "\n", $out );
	}

	public function test_checkbox_becomes_boolean(): void {
		$this->assertTrue( $this->sanitizer->sanitize_value( 'on', 'checkbox' ) );
		$this->assertFalse( $this->sanitizer->sanitize_value( '', 'checkbox' ) );
	}

	public function test_sanitize_applies_schema_to_all_fields(): void {
		$clean = $this->sanitizer->sanitize(
			array(
				'name'    => ' <i>Selam</i> ',
				'email'   => 'selam@fanos.test',
				'consent' => '1',
				'ignored' => 'dropped',
			),
			array(
				'name'    => 'text',
				'email'   => 'email',
				'consent' => 'checkbox',
			)
		);

		$this->assertSame( 'Selam', $clean['name'] );
		$this->assertSame( 'selam@fanos.test', $clean['email'] );
		$this->assertTrue( $clean['consent'] );
		$this->assertArrayNotHasKey( 'ignored', $clean, 'Fields outside the schema are dropped.' );
	}

	public function test_missing_field_defaults_to_empty(): void {
		$clean = $this->sanitizer->sanitize( array(), array( 'message' => 'textarea' ) );
		$this->assertSame( '', $clean['message'] );
	}
}
