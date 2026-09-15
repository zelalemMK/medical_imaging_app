<?php
/**
 * Template tags shared across theme templates.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a responsive, no-autoplay YouTube embed for an episode.
 *
 * @param int $post_id Episode post id.
 */
function fanos_youtube_embed( int $post_id ): void {
	$raw = (string) get_post_meta( $post_id, 'fanos_youtube_id', true );
	$src = '';
	if ( class_exists( '\FANOS\Core\Library\Youtube' ) ) {
		$src = \FANOS\Core\Library\Youtube::embed_url( $raw );
	}
	if ( '' === $src ) {
		return;
	}

	printf(
		'<div class="fanos-video" data-fanos-youtube data-fanos-label="%s"><iframe src="%s" title="%s" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>',
		esc_attr( (string) get_the_title( $post_id ) ),
		esc_url( $src ),
		esc_attr( sprintf( /* translators: %s: episode title. */ __( 'Video: %s', 'fanos' ), get_the_title( $post_id ) ) )
	);
}

/**
 * Render an episode card for library / related lists.
 *
 * @param int $post_id Episode post id.
 */
function fanos_episode_card( int $post_id ): void {
	$series = get_the_term_list( $post_id, 'story_series', '', ', ' );
	?>
	<article class="fanos-card">
		<a class="fanos-card__link" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>">
			<?php if ( has_post_thumbnail( $post_id ) ) : ?>
				<div class="fanos-card__media"><?php echo get_the_post_thumbnail( $post_id, 'medium_large', array( 'loading' => 'lazy' ) ); ?></div>
			<?php endif; ?>
			<div class="fanos-card__body">
				<?php if ( ! is_wp_error( $series ) && $series ) : ?>
					<p class="fanos-card__eyebrow"><?php echo wp_kses_post( $series ); ?></p>
				<?php endif; ?>
				<h3 class="fanos-card__title"><?php echo esc_html( (string) get_the_title( $post_id ) ); ?></h3>
				<p class="fanos-card__excerpt"><?php echo esc_html( wp_trim_words( (string) get_the_excerpt( $post_id ), 22 ) ); ?></p>
				<p class="fanos-card__meta"><time datetime="<?php echo esc_attr( (string) get_the_date( 'c', $post_id ) ); ?>"><?php echo esc_html( (string) get_the_date( '', $post_id ) ); ?></time></p>
			</div>
		</a>
	</article>
	<?php
}

/**
 * Render the library filter bar (series, topic, audience, date, sort, search).
 *
 * Uses GET so filtered views are shareable and work without JavaScript.
 */
function fanos_library_filters(): void {
	$archive = get_post_type_archive_link( 'episode' );
	$current = static function ( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public filters.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';
	};
	?>
	<form class="fanos-filters" method="get" action="<?php echo esc_url( (string) $archive ); ?>" role="search" aria-label="<?php esc_attr_e( 'Filter the Alchemizing Stories library', 'fanos' ); ?>">
		<p class="fanos-filters__search">
			<label for="fanos-q"><?php esc_html_e( 'Search stories', 'fanos' ); ?></label>
			<input type="search" id="fanos-q" name="q" value="<?php echo esc_attr( $current( 'q' ) ); ?>" placeholder="<?php esc_attr_e( 'Search by keyword…', 'fanos' ); ?>">
		</p>
		<?php
		fanos_taxonomy_select( 'story_series', 'series', __( 'Series', 'fanos' ), $current( 'series' ) );
		fanos_taxonomy_select( 'story_topic', 'topic', __( 'Topic', 'fanos' ), $current( 'topic' ) );
		fanos_taxonomy_select( 'story_audience', 'audience', __( 'Audience', 'fanos' ), $current( 'audience' ) );
		?>
		<p class="fanos-filters__field">
			<label for="fanos-sort"><?php esc_html_e( 'Sort', 'fanos' ); ?></label>
			<select id="fanos-sort" name="sort">
				<?php
				$sorts = array(
					'newest' => __( 'Newest first', 'fanos' ),
					'oldest' => __( 'Oldest first', 'fanos' ),
					'title'  => __( 'Title A–Z', 'fanos' ),
				);
				foreach ( $sorts as $value => $text ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $value ),
						selected( $current( 'sort' ), $value, false ),
						esc_html( $text )
					);
				}
				?>
			</select>
		</p>
		<p class="fanos-filters__actions">
			<button type="submit" class="fanos-button"><?php esc_html_e( 'Apply', 'fanos' ); ?></button>
			<a class="fanos-button fanos-button--ghost" href="<?php echo esc_url( (string) $archive ); ?>"><?php esc_html_e( 'Reset', 'fanos' ); ?></a>
		</p>
	</form>
	<?php
}

/**
 * Render a labelled taxonomy dropdown for the filter bar.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param string $param    Query parameter name.
 * @param string $label    Visible label.
 * @param string $selected Currently selected term slug.
 */
function fanos_taxonomy_select( string $taxonomy, string $param, string $label, string $selected ): void {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	$id = 'fanos-' . $param;
	?>
	<p class="fanos-filters__field">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $param ); ?>">
			<option value=""><?php esc_html_e( 'All', 'fanos' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $selected, $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * Render downloadable documents that share a topic with this episode.
 *
 * @param int $episode_id Episode post id.
 */
function fanos_episode_documents( int $episode_id ): void {
	$topics = wp_get_post_terms( $episode_id, 'story_topic', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $topics ) || empty( $topics ) ) {
		return;
	}

	$docs = new WP_Query(
		array(
			'post_type'      => 'document',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'story_topic',
					'field'    => 'term_id',
					'terms'    => $topics,
				),
			),
		)
	);

	if ( ! $docs->have_posts() ) {
		wp_reset_postdata();
		return;
	}
	?>
	<section class="fanos-episode__downloads" aria-labelledby="fanos-downloads-title">
		<h2 id="fanos-downloads-title"><?php esc_html_e( 'Downloads', 'fanos' ); ?></h2>
		<ul class="fanos-downloads">
			<?php
			while ( $docs->have_posts() ) :
				$docs->the_post();
				$file = (string) get_post_meta( get_the_ID(), 'fanos_file_url', true );
				$url  = '' !== $file ? $file : (string) get_permalink();
				printf(
					'<li><a href="%s" data-fanos-download data-fanos-label="%s"%s>%s</a></li>',
					esc_url( $url ),
					esc_attr( (string) get_the_title() ),
					'' !== $file ? ' download' : '',
					esc_html( (string) get_the_title() )
				);
			endwhile;
			?>
		</ul>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * Render up to three related episodes from the same series.
 *
 * @param int $episode_id Episode post id.
 */
function fanos_related_episodes( int $episode_id ): void {
	$series = wp_get_post_terms( $episode_id, 'story_series', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $series ) || empty( $series ) ) {
		return;
	}

	$related = new WP_Query(
		array(
			'post_type'      => 'episode',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'post__not_in'   => array( $episode_id ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'story_series',
					'field'    => 'term_id',
					'terms'    => $series,
				),
			),
		)
	);

	if ( ! $related->have_posts() ) {
		wp_reset_postdata();
		return;
	}
	?>
	<section class="fanos-episode__related" aria-labelledby="fanos-related-title">
		<h2 id="fanos-related-title"><?php esc_html_e( 'Related episodes', 'fanos' ); ?></h2>
		<div class="fanos-grid fanos-grid--3">
			<?php
			while ( $related->have_posts() ) :
				$related->the_post();
				fanos_episode_card( get_the_ID() );
			endwhile;
			?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * Fallback primary menu shown before the client assigns one in the customizer.
 * Lists the launch pages from the proposal so navigation works from day one.
 */
function fanos_primary_menu_fallback(): void {
	$links = array(
		home_url( '/' )                               => __( 'Home', 'fanos' ),
		get_post_type_archive_link( 'episode' ) ?: '' => __( 'Alchemizing Stories', 'fanos' ),
	);
	echo '<ul class="fanos-menu">';
	foreach ( $links as $url => $label ) {
		if ( '' === $url ) {
			continue;
		}
		printf( '<li><a href="%s">%s</a></li>', esc_url( (string) $url ), esc_html( (string) $label ) );
	}
	echo '</ul>';
}

/**
 * A simple status badge for SHARE, stating clearly that it is in development.
 */
function fanos_share_status_badge(): void {
	echo '<p class="fanos-status-badge" role="note">' . esc_html__( 'Status: In development — SHARE is not yet operating. This page is informational only.', 'fanos' ) . '</p>';
}
