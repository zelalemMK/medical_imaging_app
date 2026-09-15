<?php
/**
 * Site header.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#fanos-main"><?php esc_html_e( 'Skip to content', 'fanos' ); ?></a>

<header class="fanos-header">
	<div class="fanos-header__inner">
		<div class="fanos-branding">
			<?php
			if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
				the_custom_logo();
			} else {
				printf(
					'<a class="fanos-branding__title" href="%s">%s</a>',
					esc_url( home_url( '/' ) ),
					esc_html( (string) get_bloginfo( 'name' ) )
				);
			}
			?>
		</div>

		<button class="fanos-nav-toggle" aria-expanded="false" aria-controls="fanos-primary-menu">
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle menu', 'fanos' ); ?></span>
			<span class="fanos-nav-toggle__bar" aria-hidden="true"></span>
		</button>

		<nav class="fanos-nav" aria-label="<?php esc_attr_e( 'Primary', 'fanos' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'fanos-primary-menu',
					'menu_class'     => 'fanos-menu',
					'container'      => false,
					'fallback_cb'    => 'fanos_primary_menu_fallback',
				)
			);
			?>
		</nav>
	</div>
</header>

<main id="fanos-main" class="fanos-main">
