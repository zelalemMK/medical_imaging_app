<?php
/**
 * Input sanitisation for FANOS forms.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Forms;

/**
 * Sanitises raw form input into clean, typed values. Sanitising and validating are
 * kept separate so each is independently testable: sanitising never rejects, it only
 * cleans; validation decides acceptance.
 */
final class Sanitizer {

	/**
	 * Sanitise a full submission against a field schema.
	 *
	 * @param array<string, mixed>  $raw    Raw request values.
	 * @param array<string, string> $schema Map of field name => type (text|email|textarea|url|checkbox).
	 * @return array<string, mixed>
	 */
	public function sanitize( array $raw, array $schema ): array {
		$clean = array();
		foreach ( $schema as $field => $type ) {
			$clean[ $field ] = $this->sanitize_value( $raw[ $field ] ?? '', $type );
		}
		return $clean;
	}

	/**
	 * Sanitise a single value by type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type.
	 * @return mixed
	 */
	public function sanitize_value( $value, string $type ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'checkbox':
				return ! empty( $value );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
