<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Forms\Validator;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Forms\Validator
 */
final class ValidatorTest extends TestCase {

	private Validator $validator;

	protected function set_up(): void {
		parent::set_up();
		$this->validator = new Validator();
	}

	public function test_valid_submission_passes(): void {
		$result = $this->validator->validate(
			array(
				'name'    => 'Abel',
				'email'   => 'abel@fanos.test',
				'message' => 'I would love to learn more about the stories.',
			),
			array(
				'name'    => array( 'required' => true ),
				'email'   => array( 'required' => true, 'type' => 'email' ),
				'message' => array( 'required' => true, 'min' => 10 ),
			)
		);

		$this->assertTrue( $result['valid'] );
		$this->assertSame( array(), $result['errors'] );
	}

	public function test_missing_required_field_is_reported(): void {
		$result = $this->validator->validate(
			array( 'name' => '' ),
			array( 'name' => array( 'required' => true ) )
		);
		$this->assertFalse( $result['valid'] );
		$this->assertArrayHasKey( 'name', $result['errors'] );
	}

	public function test_optional_empty_field_passes(): void {
		$result = $this->validator->validate(
			array( 'role' => '' ),
			array( 'role' => array( 'required' => false ) )
		);
		$this->assertTrue( $result['valid'] );
	}

	public function test_invalid_email_is_rejected(): void {
		$result = $this->validator->validate(
			array( 'email' => 'not-an-email' ),
			array( 'email' => array( 'required' => true, 'type' => 'email' ) )
		);
		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	public function test_min_and_max_length_enforced(): void {
		$short = $this->validator->validate(
			array( 'message' => 'hi' ),
			array( 'message' => array( 'min' => 10 ) )
		);
		$this->assertArrayHasKey( 'message', $short['errors'] );

		$long = $this->validator->validate(
			array( 'message' => str_repeat( 'x', 50 ) ),
			array( 'message' => array( 'max' => 20 ) )
		);
		$this->assertArrayHasKey( 'message', $long['errors'] );
	}

	public function test_honeypot_detects_spam(): void {
		$this->assertTrue( $this->validator->is_spam( array( 'fanos_hp' => 'i am a bot' ) ) );
		$this->assertFalse( $this->validator->is_spam( array( 'fanos_hp' => '' ) ) );
		$this->assertFalse( $this->validator->is_spam( array() ) );
	}

	/**
	 * @dataProvider phi_examples
	 */
	public function test_phi_detection( string $text, bool $expected ): void {
		$this->assertSame( $expected, $this->validator->contains_phi( $text ) );
	}

	/**
	 * @return array<string, array{0:string,1:bool}>
	 */
	public static function phi_examples(): array {
		return array(
			'ssn'             => array( 'My number is 123-45-6789 please help', true ),
			'mrn'             => array( 'Patient MRN: A99312 needs review', true ),
			'medical record'  => array( 'See my medical record number below', true ),
			'insurance'       => array( 'Insurance number: XZ8891', true ),
			'ordinary text'   => array( 'I want to partner with FANOS on housing', false ),
			'phone-like'      => array( 'Call me at 555 at noon', false ),
		);
	}

	public function test_phi_in_submission_blocks_validation(): void {
		$result = $this->validator->validate(
			array(
				'name'    => 'Sam',
				'message' => 'My SSN is 123-45-6789',
			),
			array(
				'name'    => array( 'required' => true ),
				'message' => array( 'required' => true ),
			)
		);
		$this->assertFalse( $result['valid'] );
		$this->assertArrayHasKey( '_phi', $result['errors'] );
	}
}
