<?php
/**
 * 404 template.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();
?>
<div class="fanos-container fanos-flow">
	<header class="fanos-page-header">
		<h1><?php esc_html_e( 'Page not found', 'fanos' ); ?></h1>
	</header>
	<p><?php esc_html_e( 'Sorry, we could not find that page. It may have moved.', 'fanos' ); ?></p>
	<p>
		<a class="fanos-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go home', 'fanos' ); ?></a>
		<a class="fanos-button fanos-button--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( 'episode' ) ); ?>"><?php esc_html_e( 'Browse stories', 'fanos' ); ?></a>
	</p>
	<?php get_search_form(); ?>
</div>
<?php
get_footer();
