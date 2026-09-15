<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use Brain\Monkey\Functions;
use FANOS\Core\Newsletter\NewsletterSubscriber;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Newsletter\NewsletterSubscriber
 */
final class NewsletterSubscriberTest extends TestCase {

	private NewsletterSubscriber $subscriber;

	protected function set_up(): void {
		parent::set_up();
		$this->subscriber = new NewsletterSubscriber();
	}

	public function test_mailchimp_data_center_is_extracted_from_key(): void {
		$this->assertSame( 'us21', $this->subscriber->mailchimp_data_center( 'abcdef1234567890-us21' ) );
		$this->assertSame( '', $this->subscriber->mailchimp_data_center( 'nodash' ), 'A key without a dash has no data centre.' );
	}

	public function test_mailchimp_request_targets_correct_endpoint_and_payload(): void {
		$request = $this->subscriber->build_request(
			'reader@fanos.test',
			'Reader',
			array(
				'provider' => NewsletterSubscriber::PROVIDER_MAILCHIMP,
				'api_key'  => 'key123-us21',
				'list_id'  => 'abc123list',
				'status'   => 'pending',
			)
		);

		$this->assertSame( 'https://us21.api.mailchimp.com/3.0/lists/abc123list/members', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( 'reader@fanos.test', $body['email_address'] );
		$this->assertSame( 'pending', $body['status'] );
		$this->assertSame( 'Reader', $body['merge_fields']['FNAME'] );

		$this->assertStringStartsWith( 'Basic ', $request['args']['headers']['Authorization'] );
	}

	public function test_mailchimp_request_without_name_omits_merge_fields(): void {
		$request = $this->subscriber->build_request(
			'reader@fanos.test',
			'',
			array(
				'provider' => NewsletterSubscriber::PROVIDER_MAILCHIMP,
				'api_key'  => 'key123-us5',
				'list_id'  => 'list',
			)
		);
		$body = json_decode( $request['args']['body'], true );
		$this->assertArrayNotHasKey( 'merge_fields', $body );
	}

	public function test_mailerlite_request_uses_bearer_and_connect_endpoint(): void {
		$request = $this->subscriber->build_request(
			'reader@fanos.test',
			'Reader',
			array(
				'provider' => NewsletterSubscriber::PROVIDER_MAILERLITE,
				'api_key'  => 'ml-token',
				'list_id'  => 'group42',
			)
		);

		$this->assertSame( 'https://connect.mailerlite.com/api/subscribers', $request['url'] );
		$this->assertSame( 'Bearer ml-token', $request['args']['headers']['Authorization'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( 'reader@fanos.test', $body['email'] );
		$this->assertSame( 'Reader', $body['fields']['name'] );
		$this->assertSame( array( 'group42' ), $body['groups'] );
	}

	public function test_missing_api_key_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->subscriber->build_request( 'reader@fanos.test', '', array( 'api_key' => '' ) );
	}

	public function test_malformed_mailchimp_key_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->subscriber->build_request(
			'reader@fanos.test',
			'',
			array(
				'provider' => NewsletterSubscriber::PROVIDER_MAILCHIMP,
				'api_key'  => 'nodashkey',
				'list_id'  => 'list',
			)
		);
	}

	public function test_unknown_provider_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->subscriber->build_request(
			'reader@fanos.test',
			'',
			array(
				'provider' => 'sendgrid',
				'api_key'  => 'whatever',
			)
		);
	}

	public function test_subscribe_rejects_invalid_email_before_network(): void {
		Functions\expect( 'wp_remote_post' )->never();
		$result = $this->subscriber->subscribe( 'not-an-email' );
		$this->assertFalse( $result['success'] );
	}

	public function test_interpret_response_success_on_2xx(): void {
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );

		$result = $this->subscriber->interpret_response( array( 'body' => '{}' ) );
		$this->assertTrue( $result['success'] );
	}

	public function test_interpret_response_treats_member_exists_as_success(): void {
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 400 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"title":"Member Exists"}' );

		$result = $this->subscriber->interpret_response( array() );
		$this->assertTrue( $result['success'] );
	}

	public function test_interpret_response_error_on_wp_error(): void {
		Functions\when( 'is_wp_error' )->justReturn( true );
		$result = $this->subscriber->interpret_response( 'wp-error-sentinel' );
		$this->assertFalse( $result['success'] );
	}

	public function test_interpret_response_error_on_other_4xx(): void {
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 401 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"title":"API Key Invalid"}' );

		$result = $this->subscriber->interpret_response( array() );
		$this->assertFalse( $result['success'] );
	}
}
