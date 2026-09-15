<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Library\Youtube;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Library\Youtube
 */
final class YoutubeTest extends TestCase {

	/**
	 * @dataProvider url_examples
	 */
	public function test_extract_id( string $input, string $expected ): void {
		$this->assertSame( $expected, Youtube::extract_id( $input ) );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function url_examples(): array {
		return array(
			'bare id'      => array( 'dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'watch url'    => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'watch extra'  => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ' ),
			'short url'    => array( 'https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'embed url'    => array( 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'shorts url'   => array( 'https://youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'empty'        => array( '', '' ),
			'garbage'      => array( 'not a youtube link', '' ),
		);
	}

	public function test_embed_url_is_privacy_enhanced_and_no_autoplay(): void {
		$url = Youtube::embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
		$this->assertStringStartsWith( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $url );
		$this->assertStringContainsString( 'rel=0', $url );
		$this->assertStringNotContainsString( 'autoplay=1', $url );
	}

	public function test_embed_url_empty_for_invalid_input(): void {
		$this->assertSame( '', Youtube::embed_url( 'https://vimeo.com/12345' ) );
	}
}
