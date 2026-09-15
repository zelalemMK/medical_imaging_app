<?php
declare( strict_types=1 );

namespace FANOS\Tests\Unit;

use FANOS\Core\Library\QueryFilter;
use FANOS\Tests\TestCase;

/**
 * @covers \FANOS\Core\Library\QueryFilter
 */
final class QueryFilterTest extends TestCase {

	private QueryFilter $filter;

	protected function set_up(): void {
		parent::set_up();
		$this->filter = new QueryFilter();
	}

	public function test_defaults_query_episodes_newest_first(): void {
		$args = $this->filter->build_query_args( array() );

		$this->assertSame( 'episode', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( QueryFilter::PER_PAGE, $args['posts_per_page'] );
		$this->assertSame( 1, $args['paged'] );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertArrayNotHasKey( 'date_query', $args );
		$this->assertArrayNotHasKey( 's', $args );
	}

	public function test_single_series_produces_one_tax_clause(): void {
		$args = $this->filter->build_query_args( array( 'series' => 'faith-and-family' ) );

		$this->assertArrayHasKey( 'tax_query', $args );
		$this->assertSame( 'AND', $args['tax_query']['relation'] );
		$this->assertSame( 'story_series', $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( array( 'faith-and-family' ), $args['tax_query'][0]['terms'] );
	}

	public function test_comma_separated_topics_expand_to_multiple_terms(): void {
		$args = $this->filter->build_query_args( array( 'topic' => 'mental-health, housing , mental-health' ) );

		$clause = $args['tax_query'][0];
		$this->assertSame( 'story_topic', $clause['taxonomy'] );
		// Sanitised, de-duplicated, order preserved.
		$this->assertSame( array( 'mental-health', 'housing' ), $clause['terms'] );
	}

	public function test_all_three_dimensions_combine_with_and(): void {
		$args = $this->filter->build_query_args(
			array(
				'series'   => 'roots',
				'topic'    => 'grief',
				'audience' => 'clinicians',
			)
		);

		$taxonomies = array_column(
			array_filter( $args['tax_query'], 'is_array' ),
			'taxonomy'
		);
		$this->assertContains( 'story_series', $taxonomies );
		$this->assertContains( 'story_topic', $taxonomies );
		$this->assertContains( 'story_audience', $taxonomies );
	}

	public function test_date_filters_bound_year_and_month(): void {
		$args = $this->filter->build_query_args(
			array(
				'year'  => '2026',
				'month' => '9',
			)
		);
		$this->assertSame( array( 'year' => 2026, 'monthnum' => 9 ), $args['date_query'] );
	}

	public function test_out_of_range_month_is_ignored(): void {
		$args = $this->filter->build_query_args( array( 'month' => '13', 'year' => '1800' ) );
		$this->assertArrayNotHasKey( 'date_query', $args );
	}

	public function test_search_term_is_sanitised_and_applied(): void {
		$args = $this->filter->build_query_args( array( 'q' => '  healing <b>circles</b> ' ) );
		$this->assertSame( 'healing circles', $args['s'] );
	}

	public function test_s_param_is_accepted_as_search_fallback(): void {
		$args = $this->filter->build_query_args( array( 's' => 'sabbath' ) );
		$this->assertSame( 'sabbath', $args['s'] ?? 'sabbath' ); // sanitiser keeps text
		$this->assertArrayHasKey( 's', $args );
	}

	public function test_sort_oldest_flips_order(): void {
		$args = $this->filter->build_query_args( array( 'sort' => 'oldest' ) );
		$this->assertSame( 'ASC', $args['order'] );
	}

	public function test_sort_by_title(): void {
		$args = $this->filter->build_query_args( array( 'sort' => 'title' ) );
		$this->assertSame( 'title', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
	}

	public function test_unknown_sort_falls_back_to_newest(): void {
		$args = $this->filter->build_query_args( array( 'sort' => 'nonsense' ) );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
	}

	public function test_paged_prefers_explicit_request_value(): void {
		$args = $this->filter->build_query_args( array( 'paged' => '4' ), 2 );
		$this->assertSame( 4, $args['paged'] );
	}

	public function test_paged_never_below_one(): void {
		$args = $this->filter->build_query_args( array( 'paged' => '-3' ), 0 );
		$this->assertSame( 1, $args['paged'] );
	}

	public function test_empty_filters_do_not_create_clauses(): void {
		$args = $this->filter->build_query_args(
			array(
				'series'   => '',
				'topic'    => ' , , ',
				'audience' => '',
				'q'        => '   ',
			)
		);
		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertArrayNotHasKey( 's', $args );
	}
}
