<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Seo\Seo;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Seo\Seo
 */
final class SeoTest extends TestCase {

	private Seo $seo;

	protected function set_up(): void {
		parent::set_up();
		$this->seo = new Seo();
	}

	public function test_short_text_is_returned_untouched(): void {
		$this->assertSame( 'A short summary.', $this->seo->meta_description( 'A short summary.' ) );
	}

	public function test_long_text_is_truncated_on_word_boundary_with_ellipsis(): void {
		// A long word straddles the 50-char limit so we can prove it is not split.
		$text = 'FANOS builds warm accessible websites for community organizations everywhere';
		$out  = $this->seo->meta_description( $text, 50 );

		$this->assertLessThanOrEqual( 51, mb_strlen( $out ) ); // 50 + ellipsis.
		$this->assertStringEndsWith( '…', $out );
		// The 50-char cut lands inside "organizations"; the boundary logic must drop it whole.
		$this->assertStringNotContainsString( 'organiz', $out, 'Should not cut mid-word.' );
		foreach ( explode( ' ', rtrim( $out, '…' ) ) as $word ) {
			$this->assertStringContainsString( $word, $text, "Every kept word must be whole: $word" );
		}
	}

	public function test_html_and_whitespace_are_stripped(): void {
		$out = $this->seo->meta_description( "<p>Hello   <b>world</b></p>\n\nagain" );
		$this->assertSame( 'Hello world again', $out );
	}

	public function test_episode_schema_is_a_video_object(): void {
		$schema = $this->seo->episode_schema(
			array(
				'title'       => 'Healing Circles',
				'description' => 'A conversation about grief and community.',
				'youtube_id'  => 'abc123XYZ',
				'date'        => '2026-09-01T10:00:00+00:00',
				'url'         => 'https://fanos.test/stories/healing-circles/',
			)
		);

		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertSame( 'VideoObject', $schema['@type'] );
		$this->assertSame( 'Healing Circles', $schema['name'] );
		$this->assertSame( 'https://www.youtube.com/embed/abc123XYZ', $schema['embedUrl'] );
	}

	public function test_episode_schema_derives_thumbnail_from_youtube_id(): void {
		$schema = $this->seo->episode_schema( array( 'youtube_id' => 'zzz999' ) );
		$this->assertSame( 'https://i.ytimg.com/vi/zzz999/hqdefault.jpg', $schema['thumbnailUrl'] );
	}

	public function test_episode_schema_omits_empty_fields(): void {
		$schema = $this->seo->episode_schema( array( 'title' => 'Just a title' ) );
		$this->assertArrayNotHasKey( 'embedUrl', $schema );
		$this->assertArrayNotHasKey( 'uploadDate', $schema );
		$this->assertArrayHasKey( 'name', $schema );
	}
}
