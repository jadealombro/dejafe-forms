<?php
/**
 * Mail delivery: SMTP (e.g. Mailgun) from wp-config.php constants, sender name and address.
 *
 * In wp-config.php on the live site:
 *
 *   define( 'DEJAFE_SMTP_HOST', 'smtp.mailgun.org' ); // smtp.eu.mailgun.org for EU domains
 *   define( 'DEJAFE_SMTP_PORT', 587 );
 *   define( 'DEJAFE_SMTP_SECURE', 'tls' );
 *   define( 'DEJAFE_SMTP_USER', 'postmaster@mg.example.com' );
 *   define( 'DEJAFE_SMTP_PASS', '...' );
 *   define( 'DEJAFE_MAIL_FROM', 'hello@example.com' );
 *   define( 'DEJAFE_MAIL_FROM_NAME', 'Example' );
 *
 * Without DEJAFE_SMTP_HOST the server's default mail is used (Local sends it to Mailpit).
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'phpmailer_init',
	function ( $mailer ) {
		if ( ! defined( 'DEJAFE_SMTP_HOST' ) || ! DEJAFE_SMTP_HOST ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host       = DEJAFE_SMTP_HOST; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->Port       = defined( 'DEJAFE_SMTP_PORT' ) ? (int) DEJAFE_SMTP_PORT : 587; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPSecure = defined( 'DEJAFE_SMTP_SECURE' ) ? DEJAFE_SMTP_SECURE : 'tls'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mailer->SMTPAuth   = defined( 'DEJAFE_SMTP_USER' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( defined( 'DEJAFE_SMTP_USER' ) ) {
			$mailer->Username = DEJAFE_SMTP_USER; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Password = defined( 'DEJAFE_SMTP_PASS' ) ? DEJAFE_SMTP_PASS : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
);

add_filter(
	'wp_mail_from',
	function ( $from ) {
		$custom = (string) dejafe_forms_setting( 'from_email' );
		return is_email( $custom ) ? $custom : $from;
	}
);

add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		$custom = (string) dejafe_forms_setting( 'from_name' );
		return '' !== $custom ? $custom : ( 'WordPress' === $name ? get_bloginfo( 'name' ) : $name );
	}
);

add_action(
	'wp_mail_failed',
	function ( $error ) {
		error_log( 'Dejafe Forms mail error: ' . $error->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
);

/**
 * Send an HTML email.
 *
 * @param string|array $to      Recipients.
 * @param string       $subject Subject.
 * @param string       $html    HTML body (already escaped).
 * @param array        $headers Extra headers.
 * @return bool
 */
function dejafe_forms_send( $to, $subject, $html, $headers = array() ) {
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$body      = '<!doctype html><html><body style="margin:0;padding:24px;background:#f6f6f3;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2a24">'
		. '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px">' . $html . '</div></body></html>';
	return wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers );
}
