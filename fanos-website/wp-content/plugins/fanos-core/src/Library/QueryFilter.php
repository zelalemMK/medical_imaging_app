<?php
/**
 * Translates library browse/filter requests into WP_Query arguments.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Library;

use FANOS\Core\PostTypes\PostTypes;
use FANOS\Core\Taxonomies\Taxonomies;

/**
 * Builds the tax/date/search query for the Alchemizing Stories Hub.
 *
 * The heavy lifting lives in build_query_args(), a pure function that maps a request
 * array to WP_Query arguments. This keeps the filtering behaviour fully unit-testable
 * without a running WordPress, while apply_filters() wires it onto the archive query.
 */
final class QueryFilter {

	public const PER_PAGE = 12;

	/**
	 * Public request keys the library understands, mapped to their taxonomy.
	 *
	 * @var array<string, string>
	 */
	private const TAX_PARAMS = array(
		'series'   => Taxonomies::SERIES,
		'topic'    => Taxonomies::TOPIC,
		'audience' => Taxonomies::AUDIENCE,
	);

	/**
	 * Sort options exposed to visitors, mapped to WP_Query orderby/order pairs.
	 *
	 * @var array<string, array{orderby:string, order:string}>
	 */
	private const SORTS = array(
		'newest' => array(
			'orderby' => 'date',
			'order'   => 'DESC',
		),
		'oldest' => array(
			'orderby' => 'date',
			'order'   => 'ASC',
		),
		'title'  => array(
			'orderby' => 'title',
			'order'   => 'ASC',
		),
	);

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_archive' ) );
	}

	/**
	 * Apply the request filters to the main episode-archive query.
	 *
	 * @param \WP_Query $query The query being prepared.
	 */
	public function filter_archive( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( PostTypes::EPISODE ) && ! $query->is_search() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public filters.
		$args = $this->build_query_args( wp_unslash( $_GET ), (int) $query->get( 'paged' ) );
		foreach ( $args as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Map a raw request array to WP_Query arguments.
	 *
	 * @param array<string, mixed> $request Raw request (typically $_GET), unslashed.
	 * @param int                  $paged   Current page, if already resolved by WP.
	 * @return array<string, mixed>
	 */
	public function build_query_args( array $request, int $paged = 0 ): array {
		$args = array(
			'post_type'      => PostTypes::EPISODE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $this->resolve_paged( $request, $paged ),
		);

		$tax_query = $this->build_tax_query( $request );
		if ( array() !== $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$date_query = $this->build_date_query( $request );
		if ( array() !== $date_query ) {
			$args['date_query'] = $date_query;
		}

		$search = $this->clean_text( $request['q'] ?? $request['s'] ?? '' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$args += $this->resolve_sort( $request );

		return $args;
	}

	/**
	 * Build the tax_query clauses from series/topic/audience params.
	 *
	 * Each param accepts a single slug or a comma-separated list of slugs.
	 *
	 * @param array<string, mixed> $request Request array.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_tax_query( array $request ): array {
		$clauses = array();
		foreach ( self::TAX_PARAMS as $param => $taxonomy ) {
			$slugs = $this->clean_slugs( $request[ $param ] ?? '' );
			if ( array() === $slugs ) {
				continue;
			}
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}
		return $clauses;
	}

	/**
	 * Build a date_query from year and/or month params.
	 *
	 * @param array<string, mixed> $request Request array.
	 * @return array<string, int>
	 */
	private function build_date_query( array $request ): array {
		$clause = array();
		$year   = (int) ( $request['year'] ?? 0 );
		$month  = (int) ( $request['month'] ?? 0 );

		if ( $year >= 1970 && $year <= 2100 ) {
			$clause['year'] = $year;
		}
		if ( $month >= 1 && $month <= 12 ) {
			$clause['monthnum'] = $month;
		}
		return $clause;
	}

	/**
	 * Resolve the requested sort to WP_Query orderby/order, defaulting to newest first.
	 *
	 * @param array<string, mixed> $request Request array.
	 * @return array{orderby:string, order:string}
	 */
	private function resolve_sort( array $request ): array {
		$key = (string) ( $request['sort'] ?? 'newest' );
		return self::SORTS[ $key ] ?? self::SORTS['newest'];
	}

	/**
	 * Resolve the current page number, preferring an explicit request value.
	 *
	 * @param array<string, mixed> $request Request array.
	 * @param int                  $paged   Fallback resolved by WP.
	 */
	private function resolve_paged( array $request, int $paged ): int {
		$requested = (int) ( $request['paged'] ?? $request['page'] ?? 0 );
		$resolved  = max( $requested, $paged, 1 );
		return $resolved;
	}

	/**
	 * Normalise a single value or comma list into an array of clean slugs.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	private function clean_slugs( $value ): array {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = explode( ',', (string) $value );
		}

		$slugs = array();
		foreach ( $parts as $part ) {
			$slug = sanitize_title( (string) $part );
			if ( '' !== $slug ) {
				$slugs[ $slug ] = $slug; // De-duplicate while preserving order.
			}
		}
		return array_values( $slugs );
	}

	/**
	 * Trim and sanitise a free-text value.
	 *
	 * @param mixed $value Raw value.
	 */
	private function clean_text( $value ): string {
		return trim( sanitize_text_field( (string) $value ) );
	}
}
