<?php
/**
 * Template Name: Updates
 * Template Post Type: page
 *
 * Blog-style updates list with the newsletter signup. Use this template on a page, or
 * set a static Posts page — index.php renders that case. Slug "updates".
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="fanos-container fanos-flow">
		<header class="fanos-page-header">
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="fanos-content"><?php the_content(); ?></div>

		<?php
		$updates = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
			)
		);
		if ( $updates->have_posts() ) :
			?>
			<div class="fanos-post-list">
				<?php
				while ( $updates->have_posts() ) :
					$updates->the_post();
					?>
					<article <?php post_class( 'fanos-post-list__item' ); ?>>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="fanos-card__meta"><time datetime="<?php echo esc_attr( (string) get_the_date( 'c' ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time></p>
						<div class="fanos-excerpt"><?php the_excerpt(); ?></div>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No updates yet — subscribe below to hear about new stories and news.', 'fanos' ); ?></p>
		<?php endif; ?>

		<div class="fanos-signup fanos-signup--inline">
			<?php fanos_newsletter_form(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
