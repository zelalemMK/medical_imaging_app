<?php
/**
 * FANOS theme setup, assets, and helpers.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FANOS_THEME_VERSION', '1.0.0' );

/**
 * Theme supports and menus.
 */
function fanos_setup(): void {
	load_theme_textdomain( 'fanos', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'fanos' ),
			'footer'  => __( 'Footer Menu', 'fanos' ),
		)
	);
}
add_action( 'after_setup_theme', 'fanos_setup' );

/**
 * Front-end styles and scripts.
 */
function fanos_assets(): void {
	wp_enqueue_style( 'fanos-style', get_stylesheet_uri(), array(), FANOS_THEME_VERSION );
	wp_enqueue_style( 'fanos-main', get_template_directory_uri() . '/assets/css/main.css', array( 'fanos-style' ), FANOS_THEME_VERSION );

	wp_enqueue_script( 'fanos-main', get_template_directory_uri() . '/assets/js/main.js', array(), FANOS_THEME_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'fanos_assets' );

/**
 * Content width for embeds.
 */
function fanos_content_width(): void {
	$GLOBALS['content_width'] = 720;
}
add_action( 'after_setup_theme', 'fanos_content_width', 0 );

require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/forms.php';

/**
 * Register a widget-ready footer area for social links and contact details.
 */
function fanos_widgets_init(): void {
	register_sidebar(
		array(
			'name'          => __( 'Footer', 'fanos' ),
			'id'            => 'footer-1',
			'description'   => __( 'Shown in the site footer.', 'fanos' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'fanos_widgets_init' );
