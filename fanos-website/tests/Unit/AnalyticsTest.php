<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use Brain\Monkey\Functions;
use FANOS\Core\Analytics\Analytics;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Analytics\Analytics
 */
final class AnalyticsTest extends TestCase {

	private Analytics $analytics;

	protected function set_up(): void {
		parent::set_up();
		$this->analytics = new Analytics();
	}

	/**
	 * @dataProvider id_examples
	 */
	public function test_measurement_id_validation( string $id, bool $valid ): void {
		$this->assertSame( $valid, $this->analytics->is_valid_id( $id ) );
	}

	/**
	 * @return array<string, array{0:string,1:bool}>
	 */
	public static function id_examples(): array {
		return array(
			'valid'        => array( 'G-ABC1234', true ),
			'valid mixed'  => array( 'G-1a2b3c4d', true ),
			'too short'    => array( 'G-AB', false ),
			'ua legacy'    => array( 'UA-12345-1', false ),
			'empty'        => array( '', false ),
		);
	}

	public function test_config_anonymises_ip_and_disables_signals(): void {
		$config = $this->analytics->config();
		$this->assertTrue( $config['anonymize_ip'] );
		$this->assertFalse( $config['allow_google_signals'] );
	}

	public function test_measurement_id_returns_configured_valid_id(): void {
		Functions\when( 'get_option' )->justReturn( 'G-TEST123' );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertSame( 'G-TEST123', $this->analytics->measurement_id() );
	}

	public function test_measurement_id_blank_when_invalid(): void {
		Functions\when( 'get_option' )->justReturn( 'not-a-ga-id' );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertSame( '', $this->analytics->measurement_id() );
	}

	public function test_event_map_covers_questionnaire_success_measures(): void {
		$events = Analytics::EVENTS;
		$this->assertArrayHasKey( 'file_download', $events );
		$this->assertArrayHasKey( 'youtube_click', $events );
		$this->assertArrayHasKey( 'newsletter_signup', $events );
		$this->assertArrayHasKey( 'form_submit', $events );
	}
}
