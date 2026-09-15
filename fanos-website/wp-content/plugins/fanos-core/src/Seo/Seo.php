<?php
/**
 * SEO foundations: titles, meta descriptions, Open Graph, and episode JSON-LD.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Seo;

use FANOS\Core\PostTypes\PostTypes;

/**
 * Provides the search-engine foundations from the proposal without a heavyweight SEO
 * plugin: clean meta descriptions, Open Graph tags, and VideoObject structured data
 * for episodes. WordPress core already supplies the sitemap (wp-sitemap.xml) and title
 * tag via theme support; this class fills the descriptive gaps.
 */
final class Seo {

	private const DESCRIPTION_LENGTH = 155;

	public function register(): void {
		add_action( 'wp_head', array( $this, 'render_meta' ), 5 );
		add_action( 'wp_head', array( $this, 'render_episode_schema' ), 6 );
	}

	/**
	 * Truncate text to a clean meta-description length on a word boundary.
	 *
	 * @param string $text   Source text.
	 * @param int    $length Max length.
	 */
	public function meta_description( string $text, int $length = self::DESCRIPTION_LENGTH ): string {
		$text = trim( wp_strip_all_tags( $text ) );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		$truncated = mb_substr( $text, 0, $length );
		$last_space = mb_strrpos( $truncated, ' ' );
		if ( false !== $last_space && $last_space > 0 ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}
		return rtrim( $truncated, " ,.;:!-" ) . '…';
	}

	/**
	 * Resolve the best description for the current queried object.
	 */
	public function current_description(): string {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$source = has_excerpt( $post ) ? get_the_excerpt( $post ) : (string) $post->post_content;
				return $this->meta_description( $source );
			}
		}
		return $this->meta_description( (string) get_bloginfo( 'description' ) );
	}

	/**
	 * Render the meta description and Open Graph basics.
	 */
	public function render_meta(): void {
		$description = $this->current_description();
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
		}
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( (string) get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:type" content="%s">' . "\n", is_singular() ? 'article' : 'website' );
		if ( is_singular() ) {
			printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( (string) get_the_title() ) );
			printf( '<meta property="og:url" content="%s">' . "\n", esc_url( (string) get_permalink() ) );
		}
	}

	/**
	 * Build a VideoObject schema array for an episode.
	 *
	 * @param array<string, mixed> $episode {
	 *     Episode fields.
	 *     @type string $title       Episode title.
	 *     @type string $description Plain-text description.
	 *     @type string $youtube_id  YouTube video id.
	 *     @type string $date        ISO 8601 upload date.
	 *     @type string $thumbnail   Thumbnail URL (optional).
	 *     @type string $url         Canonical episode URL.
	 * }
	 * @return array<string, mixed>
	 */
	public function episode_schema( array $episode ): array {
		$youtube_id = (string) ( $episode['youtube_id'] ?? '' );
		$thumbnail  = (string) ( $episode['thumbnail'] ?? '' );
		if ( '' === $thumbnail && '' !== $youtube_id ) {
			$thumbnail = sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $youtube_id );
		}

		$schema = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => (string) ( $episode['title'] ?? '' ),
			'description'  => $this->meta_description( (string) ( $episode['description'] ?? '' ), 300 ),
			'uploadDate'   => (string) ( $episode['date'] ?? '' ),
			'thumbnailUrl' => $thumbnail,
			'url'          => (string) ( $episode['url'] ?? '' ),
		);
		if ( '' !== $youtube_id ) {
			$schema['embedUrl'] = sprintf( 'https://www.youtube.com/embed/%s', $youtube_id );
		}

		return array_filter(
			$schema,
			static fn( $value ) => '' !== $value && array() !== $value
		);
	}

	/**
	 * Render episode structured data on single episode views.
	 */
	public function render_episode_schema(): void {
		if ( ! is_singular( PostTypes::EPISODE ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$schema = $this->episode_schema(
			array(
				'title'       => (string) get_the_title( $post ),
				'description' => (string) $post->post_content,
				'youtube_id'  => (string) get_post_meta( $post->ID, 'fanos_youtube_id', true ),
				'date'        => (string) get_the_date( 'c', $post ),
				'url'         => (string) get_permalink( $post ),
			)
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema )
		);
	}
}
