<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Taxonomies\Taxonomies;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Taxonomies\Taxonomies
 */
final class TaxonomiesTest extends TestCase {

	private Taxonomies $taxonomies;

	protected function set_up(): void {
		parent::set_up();
		$this->taxonomies = new Taxonomies();
	}

	public function test_series_is_hierarchical(): void {
		$args = $this->taxonomies->series_args();
		$this->assertTrue( $args['hierarchical'], 'Series behave like categories and should nest.' );
	}

	public function test_topic_and_audience_are_flat(): void {
		$this->assertFalse( $this->taxonomies->topic_args()['hierarchical'] );
		$this->assertFalse( $this->taxonomies->audience_args()['hierarchical'] );
	}

	public function test_all_taxonomies_are_rest_enabled_and_public(): void {
		foreach ( array( 'series_args', 'topic_args', 'audience_args' ) as $method ) {
			$args = $this->taxonomies->{$method}();
			$this->assertTrue( $args['public'], "$method should be public" );
			$this->assertTrue( $args['show_in_rest'], "$method should be REST enabled" );
			$this->assertTrue( $args['show_admin_column'], "$method should show an admin column" );
		}
	}

	public function test_rewrite_slugs(): void {
		$this->assertSame( 'series', $this->taxonomies->series_args()['rewrite']['slug'] );
		$this->assertSame( 'topic', $this->taxonomies->topic_args()['rewrite']['slug'] );
		$this->assertSame( 'audience', $this->taxonomies->audience_args()['rewrite']['slug'] );
	}
}
