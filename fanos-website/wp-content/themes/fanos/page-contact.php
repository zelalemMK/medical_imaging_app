<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * Contact page: editorial intro, then a secure contact form (no health info collected).
 * Auto-selected for a page with the slug "contact".
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
		<div class="fanos-form-wrap">
			<?php fanos_render_form( 'contact' ); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
