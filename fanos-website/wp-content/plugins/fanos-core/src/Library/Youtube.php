<?php
/**
 * YouTube helpers: normalise pasted URLs/ids and build privacy-friendly embeds.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Library;

/**
 * The CMS team may paste a full watch URL, a short youtu.be link, or a bare id.
 * This helper accepts all of them and produces a no-autoplay, privacy-enhanced embed.
 */
final class Youtube {

	/**
	 * Extract an 11-character YouTube id from a URL or bare id.
	 *
	 * @param string $input Raw value from the episode editor.
	 * @return string The id, or '' when none can be found.
	 */
	public static function extract_id( string $input ): string {
		$input = trim( $input );
		if ( '' === $input ) {
			return '';
		}

		// Bare id.
		if ( 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
			return $input;
		}

		$patterns = array(
			'~youtu\.be/([A-Za-z0-9_-]{11})~',
			'~[?&]v=([A-Za-z0-9_-]{11})~',
			'~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
			'~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
			'~youtube\.com/live/([A-Za-z0-9_-]{11})~',
		);
		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $input, $matches ) ) {
				return $matches[1];
			}
		}
		return '';
	}

	/**
	 * Build a privacy-enhanced embed URL (no autoplay, no related-channel videos).
	 *
	 * @param string $input Raw value or id.
	 * @return string Embed URL, or '' when the input is not a valid YouTube reference.
	 */
	public static function embed_url( string $input ): string {
		$id = self::extract_id( $input );
		if ( '' === $id ) {
			return '';
		}
		return sprintf(
			'https://www.youtube-nocookie.com/embed/%s?rel=0&modestbranding=1',
			$id
		);
	}
}
