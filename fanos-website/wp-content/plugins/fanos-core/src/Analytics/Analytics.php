<?php
/**
 * Privacy-conscious GA4 wiring for the success measures in the questionnaire.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core\Analytics;

/**
 * Emits the GA4 tag and the event bindings the proposal calls for: document downloads,
 * YouTube click-throughs, newsletter signups, and form submissions. The measurement id
 * is only rendered when configured, so analytics stays off until the team opts in.
 */
final class Analytics {

	/**
	 * Custom events fired by the front-end, mapped to the DOM signal that triggers them.
	 *
	 * @var array<string, string>
	 */
	public const EVENTS = array(
		'file_download'    => '[data-fanos-download]',
		'youtube_click'    => '[data-fanos-youtube]',
		'newsletter_signup' => '[data-fanos-newsletter]',
		'form_submit'      => '[data-fanos-form]',
	);

	public function register(): void {
		add_action( 'wp_head', array( $this, 'render_tag' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_events' ) );
	}

	/**
	 * The configured GA4 measurement id (e.g. "G-XXXXXXX"), or empty when unset.
	 */
	public function measurement_id(): string {
		$id = (string) apply_filters( 'fanos_ga4_measurement_id', (string) get_option( 'fanos_ga4_id', '' ) );
		return $this->is_valid_id( $id ) ? $id : '';
	}

	/**
	 * Validate a GA4 measurement id shape ("G-" followed by alphanumerics).
	 */
	public function is_valid_id( string $id ): bool {
		return 1 === preg_match( '/^G-[A-Z0-9]{4,}$/i', $id );
	}

	/**
	 * Build the gtag configuration object. IP anonymisation is on by default to keep
	 * the public site light on personal data.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return array(
			'anonymize_ip'      => true,
			'allow_google_signals' => false,
		);
	}

	/**
	 * Render the GA4 script tag, only when a valid id is configured.
	 */
	public function render_tag(): void {
		$id = $this->measurement_id();
		if ( '' === $id ) {
			return;
		}
		$config = wp_json_encode( $this->config() );
		printf(
			'<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>' . "\n",
			esc_attr( rawurlencode( $id ) )
		);
		printf(
			'<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());gtag(\'config\',%s,%s);</script>' . "\n",
			wp_json_encode( $id ),
			$config // Safe JSON from config().
		);
	}

	/**
	 * Enqueue the small event-binding script and hand it the event map.
	 */
	public function enqueue_events(): void {
		if ( '' === $this->measurement_id() ) {
			return;
		}
		wp_register_script( 'fanos-analytics', FANOS_CORE_URL . 'assets/analytics.js', array(), FANOS_CORE_VERSION, true );
		wp_localize_script( 'fanos-analytics', 'FANOS_ANALYTICS', array( 'events' => self::EVENTS ) );
		wp_enqueue_script( 'fanos-analytics' );
	}
}
