<?php
/**
 * Default page template.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'fanos-container fanos-flow' ); ?>>
		<header class="fanos-page-header">
			<h1><?php the_title(); ?></h1>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="fanos-feature-image"><?php the_post_thumbnail( 'large' ); ?></div>
		<?php endif; ?>
		<div class="fanos-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
