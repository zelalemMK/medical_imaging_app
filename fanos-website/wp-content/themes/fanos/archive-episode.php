<?php
/**
 * Alchemizing Stories Hub — library landing with browse/filter/search.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();
?>
<div class="fanos-container fanos-flow">
	<header class="fanos-page-header">
		<h1><?php esc_html_e( 'Alchemizing Stories', 'fanos' ); ?></h1>
		<p class="fanos-page-header__lede"><?php esc_html_e( 'Browse the library by series, topic, and audience — or search for what you need.', 'fanos' ); ?></p>
	</header>

	<?php fanos_library_filters(); ?>

	<?php if ( have_posts() ) : ?>
		<p class="fanos-results-count">
			<?php
			global $wp_query;
			printf(
				/* translators: %d: number of matching stories. */
				esc_html( _n( '%d story', '%d stories', (int) $wp_query->found_posts, 'fanos' ) ),
				(int) $wp_query->found_posts
			);
			?>
		</p>
		<div class="fanos-grid fanos-grid--3 fanos-library-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				fanos_episode_card( get_the_ID() );
			endwhile;
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 2,
				'prev_text' => __( '&larr; Newer', 'fanos' ),
				'next_text' => __( 'Older &rarr;', 'fanos' ),
			)
		);
		?>
	<?php else : ?>
		<div class="fanos-empty">
			<p><?php esc_html_e( 'No stories match those filters yet.', 'fanos' ); ?></p>
			<p><a class="fanos-button fanos-button--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( 'episode' ) ); ?>"><?php esc_html_e( 'Clear filters', 'fanos' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
