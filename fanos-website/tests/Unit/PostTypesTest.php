<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\PostTypes\PostTypes;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\PostTypes\PostTypes
 */
final class PostTypesTest extends TestCase {

	private PostTypes $post_types;

	protected function set_up(): void {
		parent::set_up();
		$this->post_types = new PostTypes();
	}

	public function test_episode_is_public_and_rest_enabled(): void {
		$args = $this->post_types->episode_args();

		$this->assertTrue( $args['public'], 'Episodes must be publicly visible.' );
		$this->assertTrue( $args['show_in_rest'], 'Episodes must be editable in the block editor / CMS.' );
		$this->assertTrue( $args['has_archive'], 'The library needs an episode archive.' );
	}

	public function test_episode_uses_stories_slug(): void {
		$args = $this->post_types->episode_args();
		$this->assertSame( 'stories', $args['rewrite']['slug'] );
	}

	public function test_episode_supports_the_three_browse_taxonomies(): void {
		$args = $this->post_types->episode_args();
		$this->assertSame(
			array( 'story_series', 'story_topic', 'story_audience' ),
			$args['taxonomies']
		);
	}

	public function test_document_is_not_archived_but_is_rest_enabled(): void {
		$args = $this->post_types->document_args();
		$this->assertFalse( $args['has_archive'] );
		$this->assertTrue( $args['show_in_rest'] );
	}

	public function test_episode_meta_defines_youtube_and_transcript_fields(): void {
		$meta = $this->post_types->episode_meta();

		$this->assertArrayHasKey( 'fanos_youtube_id', $meta );
		$this->assertArrayHasKey( 'fanos_transcript', $meta );
		$this->assertTrue( $meta['fanos_youtube_id']['show_in_rest'] );
		$this->assertTrue( $meta['fanos_youtube_id']['single'] );
	}

	public function test_language_meta_defaults_to_english(): void {
		$meta = $this->post_types->episode_meta();
		$this->assertSame( 'en', $meta['fanos_language']['default'] );
	}

	public function test_document_meta_exposes_file_url(): void {
		$meta = $this->post_types->document_meta();
		$this->assertArrayHasKey( 'fanos_file_url', $meta );
		$this->assertSame( 'string', $meta['fanos_file_url']['type'] );
	}
}
