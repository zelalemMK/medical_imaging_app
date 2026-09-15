<?php
/**
 * Site footer.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );
?>
</main><!-- #fanos-main -->

<footer class="fanos-footer">
	<div class="fanos-footer__inner">
		<div class="fanos-footer__brand">
			<p class="fanos-footer__title"><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></p>
			<p class="fanos-footer__tagline"><?php echo esc_html( (string) get_bloginfo( 'description' ) ); ?></p>
			<p class="fanos-footer__note"><?php esc_html_e( 'FANOS is an educational initiative. This site does not provide medical advice and does not collect health information.', 'fanos' ); ?></p>
		</div>

		<nav class="fanos-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'fanos' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'menu_class'     => 'fanos-menu fanos-menu--footer',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
			<div class="fanos-footer__widgets"><?php dynamic_sidebar( 'footer-1' ); ?></div>
		<?php endif; ?>
	</div>

	<div class="fanos-footer__legal">
		<p>&copy; <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> <?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'fanos' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
