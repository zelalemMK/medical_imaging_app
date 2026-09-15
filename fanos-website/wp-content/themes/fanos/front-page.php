<?php
/**
 * Home page: what FANOS is, the three initiatives, a featured story, a SHARE preview,
 * and the newsletter signup — with Explore Alchemizing Stories as the primary action.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();

$stories_url = get_post_type_archive_link( 'episode' );
$share_page  = get_page_by_path( 'share' );
$share_url   = $share_page ? get_permalink( $share_page ) : home_url( '/share/' );
?>

<section class="fanos-hero">
	<div class="fanos-container">
		<p class="fanos-hero__eyebrow"><?php echo esc_html( get_theme_mod( 'fanos_hero_eyebrow', __( 'Fanos Enterprises', 'fanos' ) ) ); ?></p>
		<h1 class="fanos-hero__title"><?php echo esc_html( get_theme_mod( 'fanos_hero_title', __( 'Stories that help people take the next doable step.', 'fanos' ) ) ); ?></h1>
		<p class="fanos-hero__lede"><?php echo esc_html( get_theme_mod( 'fanos_hero_lede', __( 'FANOS shares real stories and practical resources so people feel seen, respected, and equipped — one clear step at a time.', 'fanos' ) ) ); ?></p>
		<p class="fanos-hero__actions">
			<a class="fanos-button" href="<?php echo esc_url( (string) $stories_url ); ?>"><?php esc_html_e( 'Explore Alchemizing Stories', 'fanos' ); ?></a>
			<a class="fanos-button fanos-button--ghost" href="<?php echo esc_url( (string) $share_url ); ?>"><?php esc_html_e( 'Learn about SHARE', 'fanos' ); ?></a>
		</p>
	</div>
</section>

<section class="fanos-section fanos-initiatives" aria-labelledby="fanos-initiatives-title">
	<div class="fanos-container">
		<h2 id="fanos-initiatives-title"><?php esc_html_e( 'Three initiatives, one mission', 'fanos' ); ?></h2>
		<div class="fanos-grid fanos-grid--3">
			<article class="fanos-tile">
				<h3><?php esc_html_e( 'Alchemizing Stories', 'fanos' ); ?></h3>
				<p><?php esc_html_e( 'A growing library of video stories and resources, browsable by series, topic, and audience.', 'fanos' ); ?></p>
				<a href="<?php echo esc_url( (string) $stories_url ); ?>"><?php esc_html_e( 'Browse the library', 'fanos' ); ?> &rarr;</a>
			</article>
			<article class="fanos-tile">
				<h3><?php esc_html_e( 'SHARE', 'fanos' ); ?></h3>
				<p><?php esc_html_e( 'A supportive program in development. Learn what it is, its boundaries, and how to register interest.', 'fanos' ); ?></p>
				<a href="<?php echo esc_url( (string) $share_url ); ?>"><?php esc_html_e( 'Learn about SHARE', 'fanos' ); ?> &rarr;</a>
			</article>
			<article class="fanos-tile fanos-tile--reserved">
				<h3><?php esc_html_e( 'A third initiative', 'fanos' ); ?></h3>
				<p><?php esc_html_e( 'Reserved for the future. Details will be announced when the time is right.', 'fanos' ); ?></p>
				<span class="fanos-tag"><?php esc_html_e( 'Coming later', 'fanos' ); ?></span>
			</article>
		</div>
	</div>
</section>

<?php
$featured = new WP_Query(
	array(
		'post_type'           => 'episode',
		'posts_per_page'      => 1,
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
	)
);
if ( $featured->have_posts() ) :
	while ( $featured->have_posts() ) :
		$featured->the_post();
		?>
		<section class="fanos-section fanos-featured" aria-labelledby="fanos-featured-title">
			<div class="fanos-container">
				<p class="fanos-eyebrow"><?php esc_html_e( 'Featured story', 'fanos' ); ?></p>
				<h2 id="fanos-featured-title"><?php the_title(); ?></h2>
				<div class="fanos-featured__body">
					<?php fanos_youtube_embed( get_the_ID() ); ?>
					<div class="fanos-featured__text">
						<p><?php echo esc_html( wp_trim_words( (string) get_the_excerpt(), 40 ) ); ?></p>
						<a class="fanos-button" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Watch and read', 'fanos' ); ?></a>
					</div>
				</div>
			</div>
		</section>
		<?php
	endwhile;
	wp_reset_postdata();
endif;
?>

<section class="fanos-section fanos-signup" aria-labelledby="fanos-signup-title">
	<div class="fanos-container">
		<h2 id="fanos-signup-title" class="screen-reader-text"><?php esc_html_e( 'Newsletter signup', 'fanos' ); ?></h2>
		<?php fanos_newsletter_form(); ?>
	</div>
</section>

<?php
get_footer();
