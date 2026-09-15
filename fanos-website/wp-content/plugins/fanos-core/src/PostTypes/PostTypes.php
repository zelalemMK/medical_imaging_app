<?php
/**
 * Custom post types for the Alchemizing Stories library.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\PostTypes;

/**
 * Registers the "episode" and "document" post types.
 *
 * Episodes are the centrepiece of the library (YouTube embed, takeaways, transcript,
 * sources, downloads). Documents are downloadable resources that can be attached to
 * episodes or browsed on their own. Both are exposed to the REST API so the block
 * editor / CMS team can manage them without a developer.
 */
final class PostTypes {

	public const EPISODE  = 'episode';
	public const DOCUMENT = 'document';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	public function register_post_types(): void {
		register_post_type( self::EPISODE, $this->episode_args() );
		register_post_type( self::DOCUMENT, $this->document_args() );
	}

	/**
	 * Post-type arguments for episodes. Pure array so it can be asserted in tests.
	 *
	 * @return array<string, mixed>
	 */
	public function episode_args(): array {
		return array(
			'labels'        => array(
				'name'          => __( 'Episodes', 'fanos' ),
				'singular_name' => __( 'Episode', 'fanos' ),
				'add_new_item'  => __( 'Add New Episode', 'fanos' ),
				'edit_item'     => __( 'Edit Episode', 'fanos' ),
				'menu_name'     => __( 'Alchemizing Stories', 'fanos' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-video-alt3',
			'menu_position' => 5,
			'rewrite'       => array(
				'slug'       => 'stories',
				'with_front' => false,
			),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'taxonomies'    => array( 'story_series', 'story_topic', 'story_audience' ),
		);
	}

	/**
	 * Post-type arguments for downloadable documents.
	 *
	 * @return array<string, mixed>
	 */
	public function document_args(): array {
		return array(
			'labels'        => array(
				'name'          => __( 'Documents', 'fanos' ),
				'singular_name' => __( 'Document', 'fanos' ),
				'add_new_item'  => __( 'Add New Document', 'fanos' ),
				'menu_name'     => __( 'Documents', 'fanos' ),
			),
			'public'        => true,
			'has_archive'   => false,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-media-document',
			'menu_position' => 6,
			'rewrite'       => array(
				'slug'       => 'documents',
				'with_front' => false,
			),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'taxonomies'    => array( 'story_topic', 'story_audience' ),
		);
	}

	/**
	 * Registers typed post meta so it is validated and exposed in the REST API.
	 */
	public function register_meta(): void {
		foreach ( $this->episode_meta() as $key => $args ) {
			register_post_meta( self::EPISODE, $key, $args );
		}
		foreach ( $this->document_meta() as $key => $args ) {
			register_post_meta( self::DOCUMENT, $key, $args );
		}
	}

	/**
	 * Meta definitions for episodes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function episode_meta(): array {
		$string = array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'default'      => '',
		);
		return array(
			'fanos_youtube_id'  => $string,
			'fanos_transcript'  => $string,
			'fanos_language'    => array_merge( $string, array( 'default' => 'en' ) ),
			'fanos_sources'     => $string,
			'fanos_takeaways'   => $string,
			'fanos_episode_date' => $string,
		);
	}

	/**
	 * Meta definitions for documents.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function document_meta(): array {
		return array(
			'fanos_file_url'  => array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			),
			'fanos_file_type' => array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			),
		);
	}
}
