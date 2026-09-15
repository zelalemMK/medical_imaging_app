<?php
/**
 * Handles the three public forms: contact, SHARE interest, and partnership interest.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Forms;

/**
 * Receives form submissions, sanitises and validates them (including spam and PHI
 * checks), then emails the result to a configured address. Submissions are never
 * stored, keeping the public site free of health information at rest.
 */
final class FormHandler {

	public const ACTION = 'fanos_submit_form';
	public const NONCE  = 'fanos_form_nonce';

	private Sanitizer $sanitizer;
	private Validator $validator;

	public function __construct( ?Sanitizer $sanitizer = null, ?Validator $validator = null ) {
		$this->sanitizer = $sanitizer ?? new Sanitizer();
		$this->validator = $validator ?? new Validator();
	}

	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
	}

	/**
	 * Field schema (name => sanitiser type) for each form.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function schemas(): array {
		$common = array(
			'name'    => 'text',
			'email'   => 'email',
			'message' => 'textarea',
			'consent' => 'checkbox',
		);
		return array(
			'contact'     => $common,
			'share'       => $common + array( 'role' => 'text' ),
			'partnership' => $common + array(
				'organization' => 'text',
				'org_type'     => 'text',
			),
		);
	}

	/**
	 * Validation rules for each form.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function rules(): array {
		$common = array(
			'name'    => array(
				'required' => true,
				'max'      => 120,
			),
			'email'   => array(
				'required' => true,
				'type'     => 'email',
			),
			'message' => array(
				'required' => true,
				'min'      => 10,
				'max'      => 4000,
			),
		);
		return array(
			'contact'     => $common,
			'share'       => $common,
			'partnership' => $common + array(
				'organization' => array(
					'required' => true,
					'max'      => 160,
				),
			),
		);
	}

	/**
	 * Process a submission end to end without touching global request/response state.
	 *
	 * @param string               $form_key Form identifier (contact|share|partnership).
	 * @param array<string, mixed> $request  Raw request values.
	 * @return array{success: bool, errors: array<string, string>, data: array<string, mixed>}
	 */
	public function process( string $form_key, array $request ): array {
		$schemas = self::schemas();
		$rules   = self::rules();

		if ( ! isset( $schemas[ $form_key ] ) ) {
			return array(
				'success' => false,
				'errors'  => array( '_form' => __( 'Unknown form.', 'fanos' ) ),
				'data'    => array(),
			);
		}

		// Silently drop obvious bots (honeypot). Report success so we do not tip them off.
		if ( $this->validator->is_spam( $request ) ) {
			return array(
				'success' => true,
				'errors'  => array(),
				'data'    => array(),
			);
		}

		$clean  = $this->sanitizer->sanitize( $request, $schemas[ $form_key ] );
		$result = $this->validator->validate( $clean, $rules[ $form_key ] );

		if ( ! $result['valid'] ) {
			return array(
				'success' => false,
				'errors'  => $result['errors'],
				'data'    => $clean,
			);
		}

		$sent = $this->send( $form_key, $clean );

		return array(
			'success' => $sent,
			'errors'  => $sent ? array() : array( '_form' => __( 'We could not send your message. Please try again or email us directly.', 'fanos' ) ),
			'data'    => $clean,
		);
	}

	/**
	 * Email a validated submission to the configured recipient.
	 *
	 * @param string               $form_key Form identifier.
	 * @param array<string, mixed> $data     Clean, valid data.
	 */
	private function send( string $form_key, array $data ): bool {
		$to      = $this->recipient();
		$subject = $this->subject( $form_key );
		$body    = $this->body( $form_key, $data );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( ! empty( $data['email'] ) && is_email( (string) $data['email'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( (string) $data['email'] );
		}

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Recipient address, filterable and defaulting to the site admin email.
	 */
	private function recipient(): string {
		$default = (string) get_option( 'admin_email' );
		/** This filter lets the team route each form to a chosen inbox. */
		return (string) apply_filters( 'fanos_form_recipient', $default );
	}

	/**
	 * Human-readable subject line per form.
	 */
	public function subject( string $form_key ): string {
		$map = array(
			'contact'     => __( 'New contact message — FANOS website', 'fanos' ),
			'share'       => __( 'New SHARE interest — FANOS website', 'fanos' ),
			'partnership' => __( 'New partnership interest — FANOS website', 'fanos' ),
		);
		return $map[ $form_key ] ?? __( 'New website submission — FANOS', 'fanos' );
	}

	/**
	 * Compose the plain-text email body from the submission.
	 *
	 * @param string               $form_key Form identifier.
	 * @param array<string, mixed> $data     Clean data.
	 */
	public function body( string $form_key, array $data ): string {
		$lines = array( sprintf( 'Form: %s', $form_key ), '' );
		foreach ( $data as $field => $value ) {
			if ( 'consent' === $field ) {
				$value = $value ? 'yes' : 'no';
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			}
			$label   = ucwords( str_replace( '_', ' ', $field ) );
			$lines[] = sprintf( '%s: %s', $label, (string) $value );
		}
		return implode( "\n", $lines );
	}

	/**
	 * WordPress entry point: verify nonce, process, and redirect back with a status.
	 */
	public function handle_request(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and submit the form again.', 'fanos' ), 403 );
		}

		$form_key = isset( $_POST['fanos_form'] ) ? sanitize_key( wp_unslash( (string) $_POST['fanos_form'] ) ) : '';
		$request  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$result   = $this->process( $form_key, $request );

		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$redirect = add_query_arg(
			array(
				'fanos_form'   => $form_key,
				'fanos_status' => $result['success'] ? 'ok' : 'error',
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
