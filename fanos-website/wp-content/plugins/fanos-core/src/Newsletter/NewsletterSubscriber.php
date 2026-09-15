<?php
/**
 * Newsletter signup, connecting to one configured email platform.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Newsletter;

/**
 * Subscribes an email address to the configured provider (Mailchimp or MailerLite).
 *
 * The request-building methods are pure and unit-tested; the network call is a thin
 * wrapper around wp_remote_post so provider payloads can be verified without hitting
 * a live API.
 */
final class NewsletterSubscriber {

	public const PROVIDER_MAILCHIMP  = 'mailchimp';
	public const PROVIDER_MAILERLITE = 'mailerlite';

	public function register(): void {
		add_action( 'admin_post_nopriv_fanos_subscribe', array( $this, 'handle_request' ) );
		add_action( 'admin_post_fanos_subscribe', array( $this, 'handle_request' ) );
	}

	/**
	 * Current provider settings, filterable so credentials can come from wp-config/env.
	 *
	 * @return array<string, string>
	 */
	public function settings(): array {
		$defaults = array(
			'provider' => self::PROVIDER_MAILCHIMP,
			'api_key'  => '',
			'list_id'  => '',
			'status'   => 'pending', // Double opt-in by default.
		);
		$stored = get_option( 'fanos_newsletter', array() );
		return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Build the HTTP request (url + args) for a subscribe call.
	 *
	 * @param string                $email    Subscriber email.
	 * @param string                $name     Subscriber name (optional).
	 * @param array<string, string> $settings Provider settings.
	 * @return array{url: string, args: array<string, mixed>}
	 *
	 * @throws \InvalidArgumentException When settings are incomplete or the provider is unknown.
	 */
	public function build_request( string $email, string $name, array $settings ): array {
		if ( '' === trim( $settings['api_key'] ?? '' ) ) {
			throw new \InvalidArgumentException( 'Missing newsletter API key.' );
		}

		$provider = $settings['provider'] ?? self::PROVIDER_MAILCHIMP;
		switch ( $provider ) {
			case self::PROVIDER_MAILCHIMP:
				return $this->build_mailchimp_request( $email, $name, $settings );
			case self::PROVIDER_MAILERLITE:
				return $this->build_mailerlite_request( $email, $name, $settings );
			default:
				throw new \InvalidArgumentException( sprintf( 'Unknown newsletter provider: %s', $provider ) );
		}
	}

	/**
	 * @param array<string, string> $settings Provider settings.
	 * @return array{url: string, args: array<string, mixed>}
	 */
	private function build_mailchimp_request( string $email, string $name, array $settings ): array {
		$data_center = $this->mailchimp_data_center( $settings['api_key'] );
		if ( '' === $data_center ) {
			throw new \InvalidArgumentException( 'Malformed Mailchimp API key (missing data-center suffix).' );
		}
		if ( '' === trim( $settings['list_id'] ?? '' ) ) {
			throw new \InvalidArgumentException( 'Missing Mailchimp list/audience id.' );
		}

		$url  = sprintf( 'https://%s.api.mailchimp.com/3.0/lists/%s/members', $data_center, rawurlencode( $settings['list_id'] ) );
		$body = array(
			'email_address' => $email,
			'status'        => $settings['status'] ?? 'pending',
		);
		if ( '' !== trim( $name ) ) {
			$body['merge_fields'] = array( 'FNAME' => $name );
		}

		return array(
			'url'  => $url,
			'args' => array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $settings['api_key'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			),
		);
	}

	/**
	 * @param array<string, string> $settings Provider settings.
	 * @return array{url: string, args: array<string, mixed>}
	 */
	private function build_mailerlite_request( string $email, string $name, array $settings ): array {
		$body = array( 'email' => $email );
		if ( '' !== trim( $name ) ) {
			$body['fields'] = array( 'name' => $name );
		}
		if ( '' !== trim( $settings['list_id'] ?? '' ) ) {
			$body['groups'] = array( $settings['list_id'] );
		}

		return array(
			'url'  => 'https://connect.mailerlite.com/api/subscribers',
			'args' => array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			),
		);
	}

	/**
	 * Extract the Mailchimp data-center suffix (e.g. "us21") from an API key.
	 */
	public function mailchimp_data_center( string $api_key ): string {
		$pos = strrpos( $api_key, '-' );
		if ( false === $pos ) {
			return '';
		}
		return substr( $api_key, $pos + 1 );
	}

	/**
	 * Subscribe an address using the configured provider.
	 *
	 * @param string $email Subscriber email.
	 * @param string $name  Subscriber name.
	 * @return array{success: bool, message: string}
	 */
	public function subscribe( string $email, string $name = '' ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid email address.', 'fanos' ),
			);
		}

		try {
			$request = $this->build_request( $email, sanitize_text_field( $name ), $this->settings() );
		} catch ( \InvalidArgumentException $e ) {
			return array(
				'success' => false,
				'message' => __( 'The newsletter is not fully configured yet. Please try again later.', 'fanos' ),
			);
		}

		$response = wp_remote_post( $request['url'], $request['args'] );
		return $this->interpret_response( $response );
	}

	/**
	 * Interpret a wp_remote_post response into a user-facing result.
	 *
	 * @param mixed $response WP_Error or response array.
	 * @return array{success: bool, message: string}
	 */
	public function interpret_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'We could not reach the newsletter service. Please try again later.', 'fanos' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// 2xx = created; Mailchimp returns 400 with title "Member Exists" for duplicates,
		// which we treat as a success from the visitor's point of view.
		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'message' => __( 'Thank you — please check your inbox to confirm your subscription.', 'fanos' ),
			);
		}

		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( (string) $raw, true );
		if ( is_array( $data ) && isset( $data['title'] ) && 'Member Exists' === $data['title'] ) {
			return array(
				'success' => true,
				'message' => __( 'You are already subscribed — thank you.', 'fanos' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'We could not add you to the list. Please try again later.', 'fanos' ),
		);
	}

	/**
	 * WordPress entry point for the newsletter form.
	 */
	public function handle_request(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fanos_newsletter_nonce' ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'fanos' ), 403 );
		}

		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		$result = $this->subscribe( $email, $name );

		$redirect = add_query_arg(
			array( 'fanos_news' => $result['success'] ? 'ok' : 'error' ),
			wp_get_referer() ? wp_get_referer() : home_url( '/' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
