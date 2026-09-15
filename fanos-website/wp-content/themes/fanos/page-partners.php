<?php
/**
 * Template Name: Partners / Get Involved
 * Template Post Type: page
 *
 * Explains how faith and community organizations, colleges, agencies, and hospitals can
 * partner, with a partnership-interest form. Auto-selected for the slug "partners".
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

		<section class="fanos-grid fanos-grid--2 fanos-partners-grid" aria-label="<?php esc_attr_e( 'Ways to partner', 'fanos' ); ?>">
			<article class="fanos-tile"><h2><?php esc_html_e( 'Faith &amp; community organizations', 'fanos' ); ?></h2><p><?php esc_html_e( 'Share stories with your community and co-create resources.', 'fanos' ); ?></p></article>
			<article class="fanos-tile"><h2><?php esc_html_e( 'Colleges &amp; universities', 'fanos' ); ?></h2><p><?php esc_html_e( 'Collaborate on education, research, and student engagement.', 'fanos' ); ?></p></article>
			<article class="fanos-tile"><h2><?php esc_html_e( 'Agencies', 'fanos' ); ?></h2><p><?php esc_html_e( 'Connect programs and referrals in a way that respects boundaries.', 'fanos' ); ?></p></article>
			<article class="fanos-tile"><h2><?php esc_html_e( 'Hospitals &amp; clinics', 'fanos' ); ?></h2><p><?php esc_html_e( 'Explore culturally grounded educational materials for patients and families.', 'fanos' ); ?></p></article>
		</section>

		<div class="fanos-form-wrap">
			<?php fanos_render_form( 'partnership' ); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
