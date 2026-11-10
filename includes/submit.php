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
 * @return array|null Attributes with defaults applied, plus the inner blocks under __inner.
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
					// Forms saved before 1.1.0 (no field blocks) had no "default" option: a missing type meant "message".
					if ( ! isset( $attrs['successType'] ) && empty( $block['innerBlocks'] ) ) {
						$attrs['successType'] = 'message';
					}
					$found            = $attrs;
					$found['__inner'] = $block['innerBlocks'];
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
 * Replace {tags} in a template. Values are escaped for HTML unless $plain. Unknown tags are removed.
 *
 * @param string $template Template.
 * @param array  $values   Field values keyed by field key, plus "name".
 * @param bool   $plain    Plain text (subjects).
 * @return string
 */
function dejafe_forms_tags( $template, $values, $plain = false ) {
	$map = array();
	foreach ( $values as $key => $value ) {
		$map[ '{' . $key . '}' ] = (string) $value;
	}
	$map['{site_name}'] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( $plain ) {
		$map = array_map(
			function ( $v ) {
				return str_replace( array( "\r", "\n" ), ' ', $v );
			},
			$map
		);
		$out = strtr( $template, $map );
	} else {
		$out = strtr( esc_html( $template ), array_map( 'esc_html', $map ) );
	}
	$out = preg_replace( '/\{[a-z0-9_]+\}/', '', $out );
	// Tidy what empty tags leave behind: "Thank you, ." becomes "Thank you."
	$out = preg_replace( array( '/[ \t]+([,.!?])/', '/,([.!?])/' ), '$1', $out );
	return $plain ? trim( preg_replace( '/[ \t]+/', ' ', $out ) ) : $out;
}

/**
 * What happens after a successful submission: the form's own choice or the site default.
 *
 * @param array $attrs Form attributes.
 * @return array type (message|page), message, page.
 */
function dejafe_forms_after_submit( $attrs ) {
	if ( 'default' === ( $attrs['successType'] ?? 'default' ) ) {
		$type    = dejafe_forms_setting( 'success_type' );
		$message = (string) dejafe_forms_setting( 'success_message' );
		$page    = (int) dejafe_forms_setting( 'success_page' );
	} else {
		$type    = $attrs['successType'];
		$message = (string) ( $attrs['successMessage'] ?? '' );
		$page    = (int) ( $attrs['successPage'] ?? 0 );
	}
	if ( '' === trim( $message ) ) {
		$message = (string) dejafe_forms_setting( 'success_message' );
	}
	if ( '' === trim( $message ) ) {
		$message = __( 'Thank you. Your message has been sent.', 'dejafe-forms' );
	}
	return array(
		'type'    => 'page' === $type ? 'page' : 'message',
		'message' => $message,
		'page'    => $page,
	);
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

	$fields = dejafe_forms_form_fields( $attrs['__inner'], $attrs );
	$after  = dejafe_forms_after_submit( $attrs );

	// 1. Honeypot: bots fill every field. Pretend it worked and do nothing.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'success' => true, 'message' => esc_html( wp_strip_all_tags( preg_replace( '/\{[a-z0-9_]+\}/', '', $after['message'] ) ) ) ), 200 );
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
	list( $values, $errors ) = dejafe_forms_read_fields( $fields, $request );
	$sender                  = dejafe_forms_sender( $fields, $values );
	$tags                    = array_merge( $values, array( 'name' => $sender['name'] ? $sender['name'] : $sender['email'] ) );
	$safe_tags               = $tags; // Without paragraph fields, for anything the visitor sees.
	foreach ( $fields as $field ) {
		if ( 'textarea' === $field['type'] ) {
			unset( $safe_tags[ $field['key'] ] );
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
	$entry_id = dejafe_forms_save_entry( $fields, $values, $sender, $post_id );

	// 7. Notification to the site owner.
	$to = array_filter( array_map( 'trim', explode( ',', (string) $attrs['recipient'] ) ), 'is_email' );
	if ( ! $to ) {
		$to = array( get_option( 'admin_email' ) );
	}
	$rows  = '';
	$long  = '';
	$label = 'padding:6px 16px 6px 0;color:#5e6b63;vertical-align:top';
	foreach ( $fields as $field ) {
		$value = (string) $values[ $field['key'] ];
		if ( '' === $value ) {
			continue;
		}
		if ( 'textarea' === $field['type'] ) {
			$long .= '<p style="margin:20px 0 6px;color:#5e6b63">' . esc_html( dejafe_forms_field_label( $field ) ) . '</p>'
				. '<div style="padding:14px 16px;background:#f3f5f1;border-radius:8px">' . wpautop( esc_html( $value ) ) . '</div>';
			continue;
		}
		$shown = 'email' === $field['type'] ? '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value );
		$rows .= '<tr><td style="' . $label . '">' . esc_html( wp_trim_words( dejafe_forms_field_label( $field ), 8 ) ) . '</td><td style="padding:6px 0">' . $shown . '</td></tr>';
	}
	$page_title = get_the_title( $post_id );
	$html       = '<p style="margin:0 0 16px;font-size:17px"><strong>' . esc_html__( 'New form submission', 'dejafe-forms' ) . '</strong></p>'
		. ( $rows ? '<table style="border-collapse:collapse;margin:0">' . $rows . '</table>' : '' )
		. $long
		/* translators: %s: page title. */
		. '<p style="margin:20px 0 0;font-size:13px;color:#7a857f">' . esc_html( sprintf( $sender['email'] ? __( 'Sent from the "%s" page. Reply to this email to answer directly.', 'dejafe-forms' ) : __( 'Sent from the "%s" page.', 'dejafe-forms' ), $page_title ) ) . '</p>';
	$headers    = array();
	if ( $sender['email'] ) {
		$headers[] = 'Reply-To: ' . ( $sender['name'] ? str_replace( array( '"', "\r", "\n", '<', '>', ',' ), '', $sender['name'] ) . ' <' . $sender['email'] . '>' : $sender['email'] );
	}
	$sent = dejafe_forms_send( $to, dejafe_forms_tags( (string) $attrs['subject'], $tags, true ), $html, $headers );
	if ( $entry_id ) {
		update_post_meta( $entry_id, '_dejafe_mail', $sent ? 'sent' : 'failed' );
	}

	// 8. Confirmation to the visitor (never repeats their message, which may be sensitive).
	if ( ! empty( $attrs['confirmationEnabled'] ) && $sender['email'] ) {
		dejafe_forms_send(
			$sender['email'],
			dejafe_forms_tags( (string) $attrs['confirmationSubject'], $safe_tags, true ),
			wpautop( dejafe_forms_tags( (string) $attrs['confirmationBody'], $safe_tags ) ),
			array( 'Reply-To: ' . array_values( $to )[0] )
		);
	}

	if ( ! $sent && ! $entry_id ) {
		return $fail( __( 'Sorry, your message could not be sent. Please try again later.', 'dejafe-forms' ), 500 );
	}

	$redirect = '';
	if ( 'page' === $after['type'] && $after['page'] && 'publish' === get_post_status( $after['page'] ) ) {
		$redirect = get_permalink( $after['page'] );
	}
	$success_message = dejafe_forms_tags( $after['message'], $safe_tags );

	return new WP_REST_Response(
		array(
			'success'  => true,
			'message'  => $success_message,
			'redirect' => $redirect,
		),
		200
	);
}
