<?php
/**
 * Form submission endpoint: spam checks, validation, entry backup, notification and confirmation emails.
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'dejafe-forms/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => 'dejafe_forms_handle_submit',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Find a form block's settings in a published post (looks inside synced patterns too).
 *
 * @param int    $post_id Post ID.
 * @param string $form_id Block formId.
 * @return array|null Attributes with defaults applied.
 */
function dejafe_forms_find_block( $post_id, $form_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
		return null;
	}
	$found = null;
	$walk  = function ( $blocks, $depth = 0 ) use ( &$walk, &$found, $form_id ) {
		foreach ( $blocks as $block ) {
			if ( $found ) {
				return;
			}
			if ( 'dejafe/contact-form' === $block['blockName'] ) {
				$attrs = (array) $block['attrs'];
				if ( '' === $form_id || ( $attrs['formId'] ?? '' ) === $form_id ) {
					$found = $attrs;
					return;
				}
			}
			if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) && $depth < 3 ) {
				$ref = get_post( (int) $block['attrs']['ref'] );
				if ( $ref && 'publish' === $ref->post_status ) {
					$walk( parse_blocks( $ref->post_content ), $depth + 1 );
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'], $depth );
			}
		}
	};
	$walk( parse_blocks( $post->post_content ) );
	if ( null === $found ) {
		return null;
	}
	$type     = WP_Block_Type_Registry::get_instance()->get_registered( 'dejafe/contact-form' );
	$defaults = array();
	foreach ( (array) ( $type ? $type->attributes : array() ) as $name => $schema ) {
		if ( array_key_exists( 'default', $schema ) ) {
			$defaults[ $name ] = $schema['default'];
		}
	}
	return array_merge( $defaults, $found );
}

/**
 * Replace {tags} in a template. Values are escaped for HTML unless $plain.
 *
 * @param string $template Template.
 * @param array  $fields   Fields.
 * @param bool   $plain    Plain text (subjects).
 * @return string
 */
function dejafe_forms_tags( $template, $fields, $plain = false ) {
	$values = array(
		'{first_name}' => $fields['first_name'] ?? '',
		'{last_name}'  => $fields['last_name'] ?? '',
		'{email}'      => $fields['email'] ?? '',
		'{phone}'      => $fields['phone'] ?? '',
		'{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
	);
	if ( $plain ) {
		$values = array_map(
			function ( $v ) {
				return str_replace( array( "\r", "\n" ), ' ', $v );
			},
			$values
		);
		return strtr( $template, $values );
	}
	return strtr( esc_html( $template ), array_map( 'esc_html', $values ) );
}

/**
 * Handle a submission.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function dejafe_forms_handle_submit( WP_REST_Request $request ) {
	$fail = function ( $message, $status = 400, $errors = array() ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
				'errors'  => (object) $errors,
			),
			$status
		);
	};

	$post_id = absint( $request->get_param( 'post_id' ) );
	$form_id = sanitize_key( (string) $request->get_param( 'form_id' ) );
	$attrs   = dejafe_forms_find_block( $post_id, $form_id );
	if ( ! $attrs ) {
		return $fail( __( 'This form is no longer available. Please reload the page.', 'dejafe-forms' ), 404 );
	}

	$success_message = dejafe_forms_tags( (string) $attrs['successMessage'], array( 'first_name' => sanitize_text_field( (string) $request->get_param( 'first_name' ) ) ) );

	// 1. Honeypot: bots fill every field. Pretend it worked and do nothing.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'success' => true, 'message' => $success_message ), 200 );
	}

	// 2. Too fast to be a person.
	if ( absint( $request->get_param( 'elapsed' ) ) < 3000 ) {
		return $fail( __( 'That was quick! Please take a moment and try again.', 'dejafe-forms' ), 400 );
	}

	// 3. Rate limit: 5 submissions per 10 minutes per visitor.
	$ip_key = 'dejafe_rl_' . md5( dejafe_forms_client_ip() . wp_salt() );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		return $fail( __( 'Too many messages in a short time. Please try again later.', 'dejafe-forms' ), 429 );
	}

	// 4. Validate.
	$fields = array(
		'first_name' => sanitize_text_field( (string) $request->get_param( 'first_name' ) ),
		'last_name'  => sanitize_text_field( (string) $request->get_param( 'last_name' ) ),
		'email'      => sanitize_email( (string) $request->get_param( 'email' ) ),
		'phone'      => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
		'message'    => sanitize_textarea_field( (string) $request->get_param( 'message' ) ),
	);
	$errors = array();
	if ( '' === $fields['first_name'] ) {
		$errors['first_name'] = __( 'Required', 'dejafe-forms' );
	}
	if ( ! is_email( $fields['email'] ) ) {
		$errors['email'] = __( 'Check the email', 'dejafe-forms' );
	}
	if ( '' === $fields['message'] ) {
		$errors['message'] = __( 'Required', 'dejafe-forms' );
	}
	if ( '' !== $fields['phone'] && ! preg_match( '/^[0-9+().\-\s]{7,25}$/', $fields['phone'] ) ) {
		$errors['phone'] = __( 'Check the number', 'dejafe-forms' );
	}
	foreach ( array( 'first_name' => 100, 'last_name' => 100, 'message' => 5000 ) as $key => $max ) {
		if ( mb_strlen( $fields[ $key ] ) > $max ) {
			/* translators: %d: max characters. */
			$errors[ $key ] = sprintf( __( 'Max %d characters', 'dejafe-forms' ), $max );
		}
	}
	if ( $errors ) {
		return $fail( __( 'Please check the highlighted fields.', 'dejafe-forms' ), 422, $errors );
	}

	// 5. Turnstile.
	if ( dejafe_forms_turnstile_site_key() ) {
		$check = dejafe_forms_turnstile_verify( (string) $request->get_param( 'cf-turnstile-response' ) );
		if ( is_wp_error( $check ) ) {
			return $fail( $check->get_error_message(), 403 );
		}
	}

	set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

	// 6. Backup.
	$entry_id = dejafe_forms_save_entry( $fields, $post_id );

	// 7. Notification to the site owner.
	$to = array_filter( array_map( 'trim', explode( ',', (string) $attrs['recipient'] ) ), 'is_email' );
	if ( ! $to ) {
		$to = array( get_option( 'admin_email' ) );
	}
	$name = trim( $fields['first_name'] . ' ' . $fields['last_name'] );
	$rows = '';
	foreach (
		array(
			__( 'Name', 'dejafe-forms' )  => $name,
			__( 'Email', 'dejafe-forms' ) => $fields['email'],
			__( 'Phone', 'dejafe-forms' ) => $fields['phone'],
		) as $label => $value
	) {
		if ( '' !== $value ) {
			$rows .= '<tr><td style="padding:6px 16px 6px 0;color:#5e6b63;vertical-align:top">' . esc_html( $label ) . '</td><td style="padding:6px 0">' . esc_html( $value ) . '</td></tr>';
		}
	}
	$page_title = get_the_title( $post_id );
	$html       = '<p style="margin:0 0 16px;font-size:17px"><strong>' . esc_html__( 'New form submission', 'dejafe-forms' ) . '</strong></p>'
		. '<table style="border-collapse:collapse;margin:0 0 20px">' . $rows . '</table>'
		. '<p style="margin:0 0 6px;color:#5e6b63">' . esc_html__( 'Message', 'dejafe-forms' ) . '</p>'
		. '<div style="padding:14px 16px;background:#f3f5f1;border-radius:8px">' . wpautop( esc_html( $fields['message'] ) ) . '</div>'
		/* translators: %s: page title. */
		. '<p style="margin:20px 0 0;font-size:13px;color:#7a857f">' . esc_html( sprintf( __( 'Sent from the "%s" page. Reply to this email to answer directly.', 'dejafe-forms' ), $page_title ) ) . '</p>';
	$sent       = dejafe_forms_send(
		$to,
		dejafe_forms_tags( (string) $attrs['subject'], $fields, true ),
		$html,
		array( 'Reply-To: ' . ( $name ? str_replace( array( '"', "\r", "\n" ), '', $name ) . ' <' . $fields['email'] . '>' : $fields['email'] ) )
	);
	if ( $entry_id ) {
		update_post_meta( $entry_id, '_dejafe_mail', $sent ? 'sent' : 'failed' );
	}

	// 8. Confirmation to the visitor (never repeats their message, which may be sensitive).
	if ( ! empty( $attrs['confirmationEnabled'] ) ) {
		$reply_to = array_values( $to )[0];
		dejafe_forms_send(
			$fields['email'],
			dejafe_forms_tags( (string) $attrs['confirmationSubject'], $fields, true ),
			wpautop( dejafe_forms_tags( (string) $attrs['confirmationBody'], $fields ) ),
			array( 'Reply-To: ' . $reply_to )
		);
	}

	if ( ! $sent && ! $entry_id ) {
		return $fail( __( 'Sorry, your message could not be sent. Please try again later.', 'dejafe-forms' ), 500 );
	}

	$redirect = '';
	if ( 'page' === $attrs['successType'] && ! empty( $attrs['successPage'] ) && 'publish' === get_post_status( (int) $attrs['successPage'] ) ) {
		$redirect = get_permalink( (int) $attrs['successPage'] );
	}

	return new WP_REST_Response(
		array(
			'success'  => true,
			'message'  => $success_message,
			'redirect' => $redirect,
		),
		200
	);
}
