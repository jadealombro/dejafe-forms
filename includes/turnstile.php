<?php
/**
 * Cloudflare Turnstile.
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site key when Turnstile is fully configured, else ''.
 *
 * @return string
 */
function dejafe_forms_turnstile_site_key() {
	$site   = (string) dejafe_forms_setting( 'turnstile_site_key' );
	$secret = (string) dejafe_forms_setting( 'turnstile_secret_key' );
	return ( '' !== $site && '' !== $secret ) ? $site : '';
}

/**
 * Verify a Turnstile token.
 *
 * @param string $token Token from the widget.
 * @return true|WP_Error
 */
function dejafe_forms_turnstile_verify( $token ) {
	$secret = (string) dejafe_forms_setting( 'turnstile_secret_key' );
	if ( '' === $token ) {
		return new WP_Error( 'turnstile_missing', __( 'Please complete the security check.', 'dejafe-forms' ) );
	}

	// Cloudflare's test secrets have fixed results; no need to call the API in local development.
	$test_secrets = array(
		'1x0000000000000000000000000000000AA' => true,
		'2x0000000000000000000000000000000AA' => false,
		'3x0000000000000000000000000000000AA' => false,
	);
	if ( isset( $test_secrets[ $secret ] ) ) {
		return ( $test_secrets[ $secret ] && 'XXXX.DUMMY.TOKEN.XXXX' === $token )
			? true
			: new WP_Error( 'turnstile_failed', __( 'The security check failed. Please try again.', 'dejafe-forms' ) );
	}

	$response = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => dejafe_forms_client_ip(),
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		error_log( 'Dejafe Forms Turnstile error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return new WP_Error( 'turnstile_unreachable', __( 'The security check could not be completed. Please try again in a moment.', 'dejafe-forms' ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return ! empty( $data['success'] )
		? true
		: new WP_Error( 'turnstile_failed', __( 'The security check failed. Please try again.', 'dejafe-forms' ) );
}

/**
 * Visitor IP (Cloudflare header when present).
 *
 * @return string
 */
function dejafe_forms_client_ip() {
	$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$ip = trim( (string) $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}
