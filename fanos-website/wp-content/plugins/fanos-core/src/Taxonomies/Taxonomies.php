<?php
/**
 * Taxonomies powering library filtering by series, topic, and audience.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Taxonomies;

use FANOS\Core\PostTypes\PostTypes;

/**
 * Registers the three browse dimensions from the proposal: series, topic, audience.
 * (The fourth dimension, date, is a built-in post field and handled by QueryFilter.)
 */
final class Taxonomies {

	public const SERIES   = 'story_series';
	public const TOPIC    = 'story_topic';
	public const AUDIENCE = 'story_audience';

	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	public function register_taxonomies(): void {
		register_taxonomy( self::SERIES, array( PostTypes::EPISODE ), $this->series_args() );
		register_taxonomy( self::TOPIC, array( PostTypes::EPISODE, PostTypes::DOCUMENT ), $this->topic_args() );
		register_taxonomy( self::AUDIENCE, array( PostTypes::EPISODE, PostTypes::DOCUMENT ), $this->audience_args() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function series_args(): array {
		return $this->base_args( __( 'Series', 'fanos' ), __( 'Series', 'fanos' ), 'series', true );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function topic_args(): array {
		return $this->base_args( __( 'Topics', 'fanos' ), __( 'Topic', 'fanos' ), 'topic', false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function audience_args(): array {
		return $this->base_args( __( 'Audiences', 'fanos' ), __( 'Audience', 'fanos' ), 'audience', false );
	}

	/**
	 * Shared taxonomy arguments.
	 *
	 * @param string $plural      Plural label.
	 * @param string $singular    Singular label.
	 * @param string $slug        Rewrite slug.
	 * @param bool   $hierarchical Whether terms nest (series behave like categories).
	 * @return array<string, mixed>
	 */
	private function base_args( string $plural, string $singular, string $slug, bool $hierarchical ): array {
		return array(
			'labels'            => array(
				'name'          => $plural,
				'singular_name' => $singular,
				'menu_name'     => $plural,
			),
			'public'            => true,
			'hierarchical'      => $hierarchical,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		);
	}
}
