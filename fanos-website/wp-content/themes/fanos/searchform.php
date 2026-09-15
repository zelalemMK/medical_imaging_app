<?php
/**
 * Search form.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );
?>
<form role="search" method="get" class="fanos-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="fanos-search-field" class="screen-reader-text"><?php esc_html_e( 'Search for:', 'fanos' ); ?></label>
	<input type="search" id="fanos-search-field" class="fanos-searchform__input" placeholder="<?php esc_attr_e( 'Search…', 'fanos' ); ?>" value="<?php echo esc_attr( (string) get_search_query() ); ?>" name="s">
	<button type="submit" class="fanos-button"><?php esc_html_e( 'Search', 'fanos' ); ?></button>
</form>
