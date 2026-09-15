<?php
/**
 * Template Name: Initiatives
 * Template Post Type: page
 *
 * Overview of the relationship between Alchemizing Stories, SHARE, and the reserved
 * third initiative. Keeps the three clearly distinct. Slug "initiatives".
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();

$stories_url = get_post_type_archive_link( 'episode' );
$share_page  = get_page_by_path( 'share' );
$share_url   = $share_page ? get_permalink( $share_page ) : home_url( '/share/' );

while ( have_posts() ) :
	the_post();
	?>
	<div class="fanos-container fanos-flow">
		<header class="fanos-page-header">
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="fanos-content"><?php the_content(); ?></div>

		<div class="fanos-grid fanos-grid--3">
			<article class="fanos-tile">
				<h2><?php esc_html_e( 'Alchemizing Stories', 'fanos' ); ?></h2>
				<p><?php esc_html_e( 'Our public storytelling library — live today.', 'fanos' ); ?></p>
				<a href="<?php echo esc_url( (string) $stories_url ); ?>"><?php esc_html_e( 'Explore stories', 'fanos' ); ?> &rarr;</a>
			</article>
			<article class="fanos-tile">
				<h2><?php esc_html_e( 'SHARE', 'fanos' ); ?></h2>
				<p><?php esc_html_e( 'A supportive program in development, with clearly stated boundaries.', 'fanos' ); ?></p>
				<a href="<?php echo esc_url( (string) $share_url ); ?>"><?php esc_html_e( 'Learn about SHARE', 'fanos' ); ?> &rarr;</a>
			</article>
			<article class="fanos-tile fanos-tile--reserved">
				<h2><?php esc_html_e( 'Third initiative', 'fanos' ); ?></h2>
				<p><?php esc_html_e( 'Reserved space. Not yet announced.', 'fanos' ); ?></p>
				<span class="fanos-tag"><?php esc_html_e( 'Coming later', 'fanos' ); ?></span>
			</article>
		</div>
	</div>
	<?php
endwhile;

get_footer();
