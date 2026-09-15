<?php
/**
 * Search results.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();
?>
<div class="fanos-container fanos-flow">
	<header class="fanos-page-header">
		<h1>
			<?php
			printf(
				/* translators: %s: search query. */
				esc_html__( 'Search results for “%s”', 'fanos' ),
				esc_html( (string) get_search_query() )
			);
			?>
		</h1>
		<?php get_search_form(); ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="fanos-post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'fanos-post-list__item' ); ?>>
					<p class="fanos-card__eyebrow"><?php echo esc_html( (string) get_post_type() ); ?></p>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="fanos-excerpt"><?php the_excerpt(); ?></div>
				</article>
				<?php
			endwhile;
			?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No results found. Try a different search.', 'fanos' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
