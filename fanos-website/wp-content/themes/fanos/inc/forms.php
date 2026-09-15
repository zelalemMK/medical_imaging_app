<?php
/**
 * Front-end form rendering helpers (contact, SHARE, partnership, newsletter).
 *
 * Markup only — sanitising, validation, and delivery live in the FANOS Core plugin.
 * Each form ships a nonce, a honeypot, and a screen-reader-friendly status region.
 *
 * @package FANOS\Theme
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a success/error notice after a form round-trip.
 *
 * @param string $form_key Form identifier to match against the redirect query args.
 */
function fanos_form_notice( string $form_key ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only status display.
	$posted = isset( $_GET['fanos_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['fanos_form'] ) ) : '';
	$status = isset( $_GET['fanos_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['fanos_status'] ) ) : '';
	// phpcs:enable

	if ( $posted !== $form_key || '' === $status ) {
		return;
	}

	$is_ok   = 'ok' === $status;
	$message = $is_ok
		? __( 'Thank you — your message has been sent. We will be in touch.', 'fanos' )
		: __( 'Sorry, something needs another look. Please check the form and try again.', 'fanos' );

	printf(
		'<div class="fanos-notice fanos-notice--%s" role="alert">%s</div>',
		esc_attr( $is_ok ? 'ok' : 'error' ),
		esc_html( $message )
	);
}

/**
 * Render a labelled field.
 *
 * @param array<string, mixed> $field Field definition (name, label, type, required, help, options).
 */
function fanos_field( array $field ): void {
	$name     = (string) ( $field['name'] ?? '' );
	$label    = (string) ( $field['label'] ?? '' );
	$type     = (string) ( $field['type'] ?? 'text' );
	$required = ! empty( $field['required'] );
	$help     = (string) ( $field['help'] ?? '' );
	$id       = 'fanos-' . $name;
	$help_id  = $help ? $id . '-help' : '';
	$req_attr = $required ? ' required aria-required="true"' : '';
	$desc     = $help ? sprintf( ' aria-describedby="%s"', esc_attr( $help_id ) ) : '';

	echo '<p class="fanos-field">';
	printf(
		'<label for="%s">%s%s</label>',
		esc_attr( $id ),
		esc_html( $label ),
		$required ? ' <span class="fanos-required" aria-hidden="true">*</span>' : ''
	);

	if ( 'textarea' === $type ) {
		printf(
			'<textarea id="%s" name="%s" rows="6"%s%s></textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			$req_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute strings.
			$desc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	} elseif ( 'select' === $type ) {
		printf( '<select id="%s" name="%s"%s%s>', esc_attr( $id ), esc_attr( $name ), $req_attr, $desc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( (array) ( $field['options'] ?? array() ) as $value => $text ) {
			printf( '<option value="%s">%s</option>', esc_attr( (string) $value ), esc_html( (string) $text ) );
		}
		echo '</select>';
	} else {
		printf(
			'<input type="%s" id="%s" name="%s"%s%s>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			$req_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$desc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	if ( $help ) {
		printf( '<span id="%s" class="fanos-help">%s</span>', esc_attr( $help_id ), esc_html( $help ) );
	}
	echo '</p>';
}

/**
 * Render one of the public forms.
 *
 * @param string $form_key One of contact|share|partnership.
 */
function fanos_render_form( string $form_key ): void {
	$forms = array(
		'contact'     => array(
			'heading' => __( 'Send us a message', 'fanos' ),
			'fields'  => array(
				array( 'name' => 'name', 'label' => __( 'Your name', 'fanos' ), 'required' => true ),
				array( 'name' => 'email', 'label' => __( 'Email', 'fanos' ), 'type' => 'email', 'required' => true ),
				array( 'name' => 'message', 'label' => __( 'Message', 'fanos' ), 'type' => 'textarea', 'required' => true, 'help' => __( 'Please do not include health information. This form does not collect it.', 'fanos' ) ),
			),
		),
		'share'       => array(
			'heading' => __( 'Register your interest in SHARE', 'fanos' ),
			'fields'  => array(
				array( 'name' => 'name', 'label' => __( 'Your name', 'fanos' ), 'required' => true ),
				array( 'name' => 'email', 'label' => __( 'Email', 'fanos' ), 'type' => 'email', 'required' => true ),
				array( 'name' => 'role', 'label' => __( 'Your role (optional)', 'fanos' ) ),
				array( 'name' => 'message', 'label' => __( 'How would you like to be involved?', 'fanos' ), 'type' => 'textarea', 'required' => true, 'help' => __( 'SHARE is in development. Please do not include any health information.', 'fanos' ) ),
			),
		),
		'partnership' => array(
			'heading' => __( 'Explore a partnership', 'fanos' ),
			'fields'  => array(
				array( 'name' => 'name', 'label' => __( 'Your name', 'fanos' ), 'required' => true ),
				array( 'name' => 'email', 'label' => __( 'Email', 'fanos' ), 'type' => 'email', 'required' => true ),
				array( 'name' => 'organization', 'label' => __( 'Organization', 'fanos' ), 'required' => true ),
				array(
					'name'    => 'org_type',
					'label'   => __( 'Type of organization', 'fanos' ),
					'type'    => 'select',
					'options' => array(
						''            => __( 'Please choose…', 'fanos' ),
						'faith'       => __( 'Faith or community organization', 'fanos' ),
						'college'     => __( 'College or university', 'fanos' ),
						'agency'      => __( 'Agency', 'fanos' ),
						'hospital'    => __( 'Hospital or clinic', 'fanos' ),
						'other'       => __( 'Other', 'fanos' ),
					),
				),
				array( 'name' => 'message', 'label' => __( 'Tell us about your interest', 'fanos' ), 'type' => 'textarea', 'required' => true ),
			),
		),
	);

	if ( ! isset( $forms[ $form_key ] ) ) {
		return;
	}
	$config = $forms[ $form_key ];

	fanos_form_notice( $form_key );

	printf( '<form class="fanos-form" method="post" action="%s" data-fanos-form data-fanos-label="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $form_key ) );
	echo '<h2>' . esc_html( (string) $config['heading'] ) . '</h2>';

	wp_nonce_field( 'fanos_form_nonce' );
	printf( '<input type="hidden" name="action" value="%s">', esc_attr( 'fanos_submit_form' ) );
	printf( '<input type="hidden" name="fanos_form" value="%s">', esc_attr( $form_key ) );

	// Honeypot: hidden from people, tempting to bots.
	echo '<div class="fanos-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="fanos_hp" tabindex="-1" autocomplete="off"></label></div>';

	foreach ( $config['fields'] as $field ) {
		fanos_field( $field );
	}

	echo '<p class="fanos-consent"><label><input type="checkbox" name="consent" value="1"> ' . esc_html__( 'I understand this is an educational website and not a medical or emergency service.', 'fanos' ) . '</label></p>';
	echo '<button type="submit" class="fanos-button">' . esc_html__( 'Send', 'fanos' ) . '</button>';
	echo '</form>';
}

/**
 * Render the newsletter signup form.
 */
function fanos_newsletter_form(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status display.
	$status = isset( $_GET['fanos_news'] ) ? sanitize_key( wp_unslash( (string) $_GET['fanos_news'] ) ) : '';

	echo '<div class="fanos-newsletter">';
	echo '<h2>' . esc_html__( 'Stay in the loop', 'fanos' ) . '</h2>';
	echo '<p>' . esc_html__( 'Occasional updates on new stories and FANOS news. No spam, unsubscribe anytime.', 'fanos' ) . '</p>';

	if ( 'ok' === $status ) {
		echo '<div class="fanos-notice fanos-notice--ok" role="alert">' . esc_html__( 'Thank you — please check your inbox to confirm.', 'fanos' ) . '</div>';
	} elseif ( 'error' === $status ) {
		echo '<div class="fanos-notice fanos-notice--error" role="alert">' . esc_html__( 'Sorry, we could not sign you up. Please try again.', 'fanos' ) . '</div>';
	}

	printf( '<form class="fanos-form fanos-form--inline" method="post" action="%s" data-fanos-newsletter>', esc_url( admin_url( 'admin-post.php' ) ) );
	wp_nonce_field( 'fanos_newsletter_nonce' );
	echo '<input type="hidden" name="action" value="fanos_subscribe">';
	echo '<p class="fanos-field"><label for="fanos-news-email">' . esc_html__( 'Email', 'fanos' ) . '</label><input type="email" id="fanos-news-email" name="email" required aria-required="true"></p>';
	echo '<div class="fanos-hp" aria-hidden="true"><label>Leave empty<input type="text" name="fanos_hp" tabindex="-1" autocomplete="off"></label></div>';
	echo '<button type="submit" class="fanos-button">' . esc_html__( 'Subscribe', 'fanos' ) . '</button>';
	echo '</form>';
	echo '</div>';
}
