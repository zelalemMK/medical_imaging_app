<?php
/**
 * Template Name: SHARE
 * Template Post Type: page
 *
 * SHARE overview: plain-language description, a clear "in development" status, safety
 * boundaries, and an interest form. Auto-selected for a page with the slug "share".
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
			<?php fanos_share_status_badge(); ?>
		</header>

		<div class="fanos-content"><?php the_content(); ?></div>

		<aside class="fanos-callout" role="note">
			<h2><?php esc_html_e( 'Safety boundaries', 'fanos' ); ?></h2>
			<p><?php esc_html_e( 'SHARE is an educational and supportive initiative in development. It is not a medical, crisis, or emergency service, and it does not collect health information through this website. If you are in crisis, please contact your local emergency number or a crisis line.', 'fanos' ); ?></p>
		</aside>

		<div class="fanos-form-wrap">
			<?php fanos_render_form( 'share' ); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
