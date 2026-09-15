<?php
/**
 * Fallback template — the blog / archive loop.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();
?>
<div class="fanos-container fanos-flow">
	<?php if ( have_posts() ) : ?>
		<header class="fanos-page-header">
			<h1><?php echo is_home() ? esc_html__( 'Updates', 'fanos' ) : esc_html( (string) get_the_archive_title() ); ?></h1>
		</header>
		<div class="fanos-post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'fanos-post-list__item' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="fanos-card__meta"><time datetime="<?php echo esc_attr( (string) get_the_date( 'c' ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time></p>
					<div class="fanos-excerpt"><?php the_excerpt(); ?></div>
				</article>
				<?php
			endwhile;
			?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing here yet — please check back soon.', 'fanos' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
