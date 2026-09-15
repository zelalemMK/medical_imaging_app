<?php
/**
 * Episode template: video, takeaways, transcript, sources, downloads, related episodes.
 * Supports English and Amharic content on the same page (authored in the body).
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) :
	the_post();
	$episode_id = get_the_ID();
	$takeaways  = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $episode_id, 'fanos_takeaways', true ) ) ) );
	$transcript = (string) get_post_meta( $episode_id, 'fanos_transcript', true );
	$sources    = (string) get_post_meta( $episode_id, 'fanos_sources', true );
	?>
	<article <?php post_class( 'fanos-container fanos-flow fanos-episode' ); ?>>
		<header class="fanos-page-header">
			<?php
			$series = get_the_term_list( $episode_id, 'story_series', '', ', ' );
			if ( ! is_wp_error( $series ) && $series ) {
				echo '<p class="fanos-eyebrow">' . wp_kses_post( $series ) . '</p>';
			}
			?>
			<h1><?php the_title(); ?></h1>
			<p class="fanos-card__meta"><time datetime="<?php echo esc_attr( (string) get_the_date( 'c' ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time></p>
		</header>

		<?php fanos_youtube_embed( $episode_id ); ?>

		<div class="fanos-content"><?php the_content(); ?></div>

		<?php if ( ! empty( $takeaways ) ) : ?>
			<section class="fanos-episode__takeaways" aria-labelledby="fanos-takeaways-title">
				<h2 id="fanos-takeaways-title"><?php esc_html_e( 'Key takeaways', 'fanos' ); ?></h2>
				<ul>
					<?php foreach ( $takeaways as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( '' !== trim( $transcript ) ) : ?>
			<section class="fanos-episode__transcript" aria-labelledby="fanos-transcript-title">
				<details>
					<summary id="fanos-transcript-title"><?php esc_html_e( 'Transcript', 'fanos' ); ?></summary>
					<div class="fanos-transcript-body"><?php echo wp_kses_post( wpautop( $transcript ) ); ?></div>
				</details>
			</section>
		<?php endif; ?>

		<?php fanos_episode_documents( $episode_id ); ?>

		<?php if ( '' !== trim( $sources ) ) : ?>
			<section class="fanos-episode__sources" aria-labelledby="fanos-sources-title">
				<h2 id="fanos-sources-title"><?php esc_html_e( 'Sources', 'fanos' ); ?></h2>
				<div class="fanos-content"><?php echo wp_kses_post( wpautop( $sources ) ); ?></div>
			</section>
		<?php endif; ?>

		<?php fanos_related_episodes( $episode_id ); ?>
	</article>
	<?php
endwhile;

get_footer();
