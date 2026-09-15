<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use Brain\Monkey\Functions;
use FANOS\Core\Forms\FormHandler;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Forms\FormHandler
 */
final class FormHandlerTest extends TestCase {

	private FormHandler $handler;

	protected function set_up(): void {
		parent::set_up();
		$this->handler = new FormHandler();

		// Recipient resolution used by the send path.
		Functions\when( 'get_option' )->justReturn( 'team@fanos.test' );
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	public function test_unknown_form_is_rejected(): void {
		$result = $this->handler->process( 'nope', array() );
		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( '_form', $result['errors'] );
	}

	public function test_honeypot_silently_succeeds_without_sending(): void {
		Functions\expect( 'wp_mail' )->never();

		$result = $this->handler->process(
			'contact',
			array(
				'name'     => 'Bot',
				'email'    => 'bot@spam.test',
				'message'  => 'buy cheap things now please',
				'fanos_hp' => 'gotcha',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array(), $result['data'] );
	}

	public function test_invalid_contact_submission_returns_errors_and_does_not_send(): void {
		Functions\expect( 'wp_mail' )->never();

		$result = $this->handler->process(
			'contact',
			array(
				'name'    => '',
				'email'   => 'bad',
				'message' => 'short',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
		$this->assertArrayHasKey( 'message', $result['errors'] );
	}

	public function test_valid_contact_submission_sends_email(): void {
		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'team@fanos.test',
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				\Mockery::type( 'array' )
			)
			->andReturn( true );

		$result = $this->handler->process(
			'contact',
			array(
				'name'    => 'Selam',
				'email'   => 'selam@fanos.test',
				'message' => 'I would like to talk about the launch.',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array(), $result['errors'] );
	}

	public function test_partnership_requires_organization(): void {
		Functions\expect( 'wp_mail' )->never();

		$result = $this->handler->process(
			'partnership',
			array(
				'name'    => 'Dr. Alem',
				'email'   => 'alem@hospital.test',
				'message' => 'Our hospital would like to collaborate.',
				// organization missing
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'organization', $result['errors'] );
	}

	public function test_failed_mail_reports_error(): void {
		Functions\expect( 'wp_mail' )->once()->andReturn( false );

		$result = $this->handler->process(
			'share',
			array(
				'name'    => 'Rahel',
				'email'   => 'rahel@fanos.test',
				'message' => 'I am interested in SHARE when it opens.',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( '_form', $result['errors'] );
	}

	public function test_subject_lines_are_form_specific(): void {
		$this->assertStringContainsString( 'contact', strtolower( $this->handler->subject( 'contact' ) ) );
		$this->assertStringContainsString( 'share', strtolower( $this->handler->subject( 'share' ) ) );
		$this->assertStringContainsString( 'partnership', strtolower( $this->handler->subject( 'partnership' ) ) );
	}

	public function test_body_renders_consent_as_yes_no(): void {
		$body = $this->handler->body(
			'contact',
			array(
				'name'    => 'Selam',
				'consent' => true,
			)
		);
		$this->assertStringContainsString( 'Consent: yes', $body );
		$this->assertStringContainsString( 'Name: Selam', $body );
	}

	public function test_schemas_and_rules_cover_all_forms(): void {
		$this->assertSame(
			array( 'contact', 'share', 'partnership' ),
			array_keys( FormHandler::schemas() )
		);
		$this->assertSame(
			array( 'contact', 'share', 'partnership' ),
			array_keys( FormHandler::rules() )
		);
	}
}
