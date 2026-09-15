<?php
/**
 * Validation for FANOS forms, including the no-PHI safeguard from the proposal.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Forms;

/**
 * Validates sanitised submissions. Returns a structured result rather than throwing,
 * so callers (and tests) can inspect every field error at once.
 *
 * The proposal is explicit that the public site must not collect protected health
 * information (PHI). We cannot detect PHI perfectly, but we can reject the obvious
 * cases (national IDs, insurance/medical-record numbers) and warn the visitor, which
 * is a meaningful, testable safeguard.
 */
final class Validator {

	/**
	 * Patterns that strongly indicate someone is pasting protected health / identity
	 * information into a public form. Deliberately conservative to avoid false rejects.
	 *
	 * @var array<int, string>
	 */
	private const PHI_PATTERNS = array(
		'/\b\d{3}-\d{2}-\d{4}\b/',              // US Social Security Number.
		'/\bMRN[:#]?\s*\w+/i',                  // Medical record number.
		'/\bmedical\s+record\s+(number|no)\b/i',
		'/\b(policy|insurance)\s*(number|no|#)\s*[:#]?\s*\w+/i',
	);

	/**
	 * Validate a sanitised submission.
	 *
	 * @param array<string, mixed> $data     Sanitised values.
	 * @param array<string, mixed> $rules    Map of field => rule set. A rule set is an
	 *                                        array that may contain: 'required' (bool),
	 *                                        'type' ('email'), 'max' (int), 'min' (int).
	 * @return array{valid: bool, errors: array<string, string>}
	 */
	public function validate( array $data, array $rules ): array {
		$errors = array();

		foreach ( $rules as $field => $rule ) {
			$value = $data[ $field ] ?? '';
			$error = $this->validate_field( (string) $value, is_array( $rule ) ? $rule : array() );
			if ( null !== $error ) {
				$errors[ $field ] = $error;
			}
		}

		// PHI scan runs across every free-text value, independent of per-field rules.
		if ( ! isset( $errors['_phi'] ) && $this->contains_phi( $this->text_values( $data ) ) ) {
			$errors['_phi'] = __( 'For your privacy, please do not include Social Security numbers, medical record numbers, or insurance identifiers. This is a public form and does not collect health information.', 'fanos' );
		}

		return array(
			'valid'  => array() === $errors,
			'errors' => $errors,
		);
	}

	/**
	 * Validate a single field, returning an error message or null when valid.
	 *
	 * @param string               $value Field value.
	 * @param array<string, mixed> $rule  Rule set.
	 */
	private function validate_field( string $value, array $rule ): ?string {
		$trimmed  = trim( $value );
		$required = ! empty( $rule['required'] );

		if ( '' === $trimmed ) {
			return $required ? __( 'This field is required.', 'fanos' ) : null;
		}

		if ( ( $rule['type'] ?? '' ) === 'email' && ! is_email( $trimmed ) ) {
			return __( 'Please enter a valid email address.', 'fanos' );
		}

		if ( isset( $rule['min'] ) && mb_strlen( $trimmed ) < (int) $rule['min'] ) {
			return sprintf(
				/* translators: %d: minimum number of characters. */
				__( 'Please enter at least %d characters.', 'fanos' ),
				(int) $rule['min']
			);
		}

		if ( isset( $rule['max'] ) && mb_strlen( $trimmed ) > (int) $rule['max'] ) {
			return sprintf(
				/* translators: %d: maximum number of characters. */
				__( 'Please keep this under %d characters.', 'fanos' ),
				(int) $rule['max']
			);
		}

		return null;
	}

	/**
	 * Whether a honeypot field was filled — a strong bot signal.
	 *
	 * @param array<string, mixed> $data         Raw or sanitised values.
	 * @param string               $honeypot_key Honeypot field name.
	 */
	public function is_spam( array $data, string $honeypot_key = 'fanos_hp' ): bool {
		return '' !== trim( (string) ( $data[ $honeypot_key ] ?? '' ) );
	}

	/**
	 * Detect obvious PHI / identity numbers in a blob of text.
	 *
	 * @param string $text Combined free text.
	 */
	public function contains_phi( string $text ): bool {
		foreach ( self::PHI_PATTERNS as $pattern ) {
			if ( 1 === preg_match( $pattern, $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Concatenate the string values of a submission for scanning.
	 *
	 * @param array<string, mixed> $data Submission values.
	 */
	private function text_values( array $data ): string {
		$parts = array();
		foreach ( $data as $value ) {
			if ( is_scalar( $value ) ) {
				$parts[] = (string) $value;
			}
		}
		return implode( ' ', $parts );
	}
}
