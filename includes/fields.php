<?php
/**
 * Form fields: the schema shared by the form markup, validation, entries and emails.
 *
 * Each field is a dejafe/field block inside the form. Forms saved before 1.1.0 have no
 * field blocks and use the original five fields.
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field types and their defaults.
 *
 * @return array
 */
function dejafe_forms_field_types() {
	return array( 'text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'date' );
}

/**
 * Turn a label into a field key, e.g. "Preferred Day" becomes preferred_day.
 * Must match keyFromLabel() in blocks/field/editor.js.
 *
 * @param string $label Label.
 * @return string
 */
function dejafe_forms_field_key( $label ) {
	$key = strtolower( remove_accents( wp_strip_all_tags( (string) $label ) ) );
	$key = trim( preg_replace( '/[^a-z0-9]+/', '_', $key ), '_' );
	$key = trim( substr( $key, 0, 40 ), '_' );
	return '' === $key ? 'field' : $key;
}

/**
 * Options from the one-per-line options setting.
 *
 * @param string $options Raw options.
 * @return array
 */
function dejafe_forms_field_options( $options ) {
	$list = array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $options ) ) );
	$list = array_map( 'sanitize_text_field', $list );
	return array_values( array_unique( array_filter( $list, 'strlen' ) ) );
}

/**
 * The fields of a form, in order, with unique keys.
 *
 * @param array $inner_blocks Parsed inner blocks of the form block.
 * @param array $attrs        Form attributes (used for forms saved before field blocks).
 * @return array List of fields: key, type, label, required, width, placeholder, options, autocomplete.
 */
function dejafe_forms_form_fields( $inner_blocks, $attrs = array() ) {
	$raw = array();
	foreach ( (array) $inner_blocks as $block ) {
		if ( 'dejafe/field' === ( $block['blockName'] ?? '' ) ) {
			$raw[] = (array) $block['attrs'];
		}
	}

	if ( ! $raw ) {
		$raw = array(
			array( 'name' => 'first_name', 'label' => __( 'First Name', 'dejafe-forms' ), 'required' => true, 'width' => 'half' ),
			array( 'name' => 'last_name', 'label' => __( 'Last Name', 'dejafe-forms' ), 'width' => 'half' ),
			array( 'name' => 'email', 'type' => 'email', 'label' => __( 'Email', 'dejafe-forms' ), 'required' => true ),
		);
		if ( ! isset( $attrs['showPhone'] ) || $attrs['showPhone'] ) {
			$raw[] = array( 'name' => 'phone', 'type' => 'tel', 'label' => __( 'Phone Number', 'dejafe-forms' ) );
		}
		$raw[] = array( 'name' => 'message', 'type' => 'textarea', 'label' => __( 'Message', 'dejafe-forms' ), 'required' => true );
	}

	// Names the endpoint uses for itself.
	$used   = array_fill_keys( array( 'post_id', 'form_id', 'website', 'elapsed', 'rest_route', '_method', '_envelope', '_jsonp', '_fields', '_embed' ), true );
	$fields = array();
	foreach ( array_slice( $raw, 0, 40 ) as $a ) {
		$type  = in_array( $a['type'] ?? 'text', dejafe_forms_field_types(), true ) ? ( $a['type'] ?? 'text' ) : 'text';
		$label = trim( wp_kses( (string) ( $a['label'] ?? '' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array() ) ) );
		$base  = ! empty( $a['name'] ) ? dejafe_forms_field_key( $a['name'] ) : dejafe_forms_field_key( $label ? $label : $type );
		$key   = $base;
		for ( $i = 2; isset( $used[ $key ] ); $i++ ) {
			$key = $base . '_' . $i;
		}
		$used[ $key ] = true;

		$options = in_array( $type, array( 'select', 'radio' ), true ) ? dejafe_forms_field_options( $a['options'] ?? '' ) : array();
		if ( in_array( $type, array( 'select', 'radio' ), true ) && ! $options ) {
			continue; // A choice with nothing to choose.
		}

		$autocomplete = array(
			'first_name' => 'given-name',
			'last_name'  => 'family-name',
			'name'       => 'name',
			'full_name'  => 'name',
		);
		if ( isset( $autocomplete[ $key ] ) ) {
			$auto = $autocomplete[ $key ];
		} elseif ( 'email' === $type || 'tel' === $type ) {
			$auto = $type;
		} else {
			$auto = 'off';
		}

		$fields[] = array(
			'key'          => $key,
			'type'         => $type,
			'label'        => $label ? $label : ucfirst( str_replace( '_', ' ', $key ) ),
			'required'     => ! empty( $a['required'] ),
			'width'        => ( 'half' === ( $a['width'] ?? '' ) && ! in_array( $type, array( 'textarea', 'radio', 'checkbox' ), true ) ) ? 'half' : 'full',
			'placeholder'  => sanitize_text_field( (string) ( $a['placeholder'] ?? '' ) ),
			'options'      => $options,
			'autocomplete' => $auto,
		);
	}
	return $fields;
}

/**
 * Plain-text label for emails and the entries screen.
 *
 * @param array $field Field.
 * @return string
 */
function dejafe_forms_field_label( $field ) {
	return trim( wp_specialchars_decode( wp_strip_all_tags( $field['label'] ), ENT_QUOTES ) );
}

/**
 * Field markup.
 *
 * @param array  $field Field from dejafe_forms_form_fields().
 * @param string $uid   Unique form id.
 * @return string
 */
function dejafe_forms_render_field( $field, $uid ) {
	$key      = $field['key'];
	$type     = $field['type'];
	$id       = $uid . '-' . $key;
	$required = $field['required'];
	$label    = wp_kses( $field['label'], array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array() ) );
	$optional = $required || 'checkbox' === $type ? '' : ' <span class="dejafe-form__optional">' . esc_html__( '(optional)', 'dejafe-forms' ) . '</span>';
	$error    = '<span class="dejafe-form__error" id="' . esc_attr( $id ) . '-error" hidden></span>';
	$req      = $required ? ' required aria-required="true"' : '';

	$html = '<div class="dejafe-form__field is-' . esc_attr( $field['width'] ) . ' is-type-' . esc_attr( $type ) . '" data-field="' . esc_attr( $key ) . '" data-type="' . esc_attr( $type ) . '">';

	if ( 'checkbox' === $type ) {
		$html .= '<div class="dejafe-form__label-row">';
		$html .= '<label class="dejafe-form__choice"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="1"' . $req . ' aria-describedby="' . esc_attr( $id ) . '-error"> <span>' . $label . '</span></label>';
		$html .= $error . '</div></div>';
		return $html;
	}

	if ( 'radio' === $type ) {
		$html .= '<div class="dejafe-form__label-row"><span class="dejafe-form__label" id="' . esc_attr( $id ) . '-label">' . $label . $optional . '</span>' . $error . '</div>';
		$html .= '<div class="dejafe-form__choices" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-label" aria-describedby="' . esc_attr( $id ) . '-error"' . ( $required ? ' aria-required="true"' : '' ) . '>';
		foreach ( $field['options'] as $i => $option ) {
			$html .= '<label class="dejafe-form__choice"><input type="radio" name="' . esc_attr( $key ) . '" value="' . esc_attr( $option ) . '"' . ( $required && 0 === $i ? ' required' : '' ) . '> <span>' . esc_html( $option ) . '</span></label>';
		}
		$html .= '</div></div>';
		return $html;
	}

	$attr = sprintf(
		'id="%1$s" name="%2$s" class="dejafe-form__input" autocomplete="%3$s"%4$s aria-describedby="%1$s-error"%5$s',
		esc_attr( $id ),
		esc_attr( $key ),
		esc_attr( $field['autocomplete'] ),
		$req,
		$field['placeholder'] && 'select' !== $type && 'date' !== $type ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''
	);

	$html .= '<div class="dejafe-form__label-row"><label class="dejafe-form__label" for="' . esc_attr( $id ) . '">' . $label . $optional . '</label>' . $error . '</div>';
	if ( 'textarea' === $type ) {
		$html .= '<textarea ' . $attr . ' rows="5" maxlength="5000"></textarea>';
	} elseif ( 'select' === $type ) {
		$html .= '<select ' . $attr . '>';
		$html .= '<option value="">' . esc_html( $field['placeholder'] ? $field['placeholder'] : __( 'Choose an option', 'dejafe-forms' ) ) . '</option>';
		foreach ( $field['options'] as $option ) {
			$html .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
		}
		$html .= '</select>';
	} else {
		$html .= '<input type="' . esc_attr( $type ) . '" ' . $attr . ( in_array( $type, array( 'text', 'email' ), true ) ? ' maxlength="200"' : '' ) . '>';
	}
	return $html . '</div>';
}

/**
 * Read, sanitize and validate a submission.
 *
 * @param array           $fields  Fields.
 * @param WP_REST_Request $request Request.
 * @return array [ values keyed by field key, errors keyed by field key ]
 */
function dejafe_forms_read_fields( $fields, $request ) {
	$values = array();
	$errors = array();
	foreach ( $fields as $field ) {
		$key = $field['key'];
		$raw = $request->get_param( $key );
		$raw = is_scalar( $raw ) ? (string) $raw : '';

		switch ( $field['type'] ) {
			case 'email':
				$value = sanitize_email( $raw );
				if ( '' !== trim( $raw ) && ! is_email( $value ) ) {
					$errors[ $key ] = __( 'Check the email', 'dejafe-forms' );
				}
				break;
			case 'tel':
				$value = sanitize_text_field( $raw );
				if ( '' !== $value && ! preg_match( '/^[0-9+().\-\s]{7,25}$/', $value ) ) {
					$errors[ $key ] = __( 'Check the number', 'dejafe-forms' );
				}
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $raw );
				if ( mb_strlen( $value ) > 5000 ) {
					/* translators: %d: max characters. */
					$errors[ $key ] = sprintf( __( 'Max %d characters', 'dejafe-forms' ), 5000 );
				}
				break;
			case 'select':
			case 'radio':
				$value = sanitize_text_field( $raw );
				if ( '' !== $value && ! in_array( $value, $field['options'], true ) ) {
					$errors[ $key ] = __( 'Choose an option', 'dejafe-forms' );
				}
				break;
			case 'checkbox':
				$value = '' !== $raw ? __( 'Yes', 'dejafe-forms' ) : '';
				break;
			case 'date':
				$value = sanitize_text_field( $raw );
				$date  = DateTime::createFromFormat( '!Y-m-d', $value );
				if ( '' !== $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) {
					$errors[ $key ] = __( 'Check the date', 'dejafe-forms' );
				}
				break;
			default:
				$value = sanitize_text_field( $raw );
				if ( mb_strlen( $value ) > 200 ) {
					/* translators: %d: max characters. */
					$errors[ $key ] = sprintf( __( 'Max %d characters', 'dejafe-forms' ), 200 );
				}
		}

		if ( $field['required'] && '' === $value && ! isset( $errors[ $key ] ) ) {
			$errors[ $key ] = 'checkbox' === $field['type'] ? __( 'Please tick this box', 'dejafe-forms' ) : ( 'email' === $field['type'] && '' !== trim( $raw ) ? __( 'Check the email', 'dejafe-forms' ) : __( 'Required', 'dejafe-forms' ) );
		}
		$values[ $key ] = $value;
	}
	return array( $values, $errors );
}

/**
 * Who sent it: name, email and phone, picked from the fields.
 *
 * @param array $fields Fields.
 * @param array $values Values.
 * @return array name, email, phone.
 */
function dejafe_forms_sender( $fields, $values ) {
	$name = trim( ( $values['first_name'] ?? '' ) . ' ' . ( $values['last_name'] ?? '' ) );
	if ( '' === $name ) {
		$name = (string) ( $values['name'] ?? $values['full_name'] ?? $values['your_name'] ?? '' );
	}
	$email = '';
	$phone = '';
	foreach ( $fields as $field ) {
		if ( 'email' === $field['type'] && '' === $email ) {
			$email = (string) ( $values[ $field['key'] ] ?? '' );
		}
		if ( 'tel' === $field['type'] && '' === $phone ) {
			$phone = (string) ( $values[ $field['key'] ] ?? '' );
		}
	}
	return array(
		'name'  => $name,
		'email' => $email,
		'phone' => $phone,
	);
}
