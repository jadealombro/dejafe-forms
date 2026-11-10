/**
 * Form field block: editor preview and settings. No build step needed.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var c = wp.components;
	var __ = wp.i18n.__;

	// Must match dejafe_forms_field_key() in includes/fields.php.
	function keyFromLabel( label ) {
		var text = String( label || '' ).replace( /<[^>]*>/g, '' ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase();
		var key = text.replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' ).slice( 0, 40 ).replace( /_+$/, '' );
		return key || 'field';
	}

	function options( raw ) {
		return String( raw || '' ).split( '\n' ).map( function ( o ) { return o.trim(); } ).filter( Boolean );
	}

	var TYPES = [
		{ label: __( 'Text', 'dejafe-forms' ), value: 'text' },
		{ label: __( 'Email', 'dejafe-forms' ), value: 'email' },
		{ label: __( 'Phone', 'dejafe-forms' ), value: 'tel' },
		{ label: __( 'Paragraph', 'dejafe-forms' ), value: 'textarea' },
		{ label: __( 'Dropdown', 'dejafe-forms' ), value: 'select' },
		{ label: __( 'Multiple choice', 'dejafe-forms' ), value: 'radio' },
		{ label: __( 'Checkbox', 'dejafe-forms' ), value: 'checkbox' },
		{ label: __( 'Date', 'dejafe-forms' ), value: 'date' },
	];

	window.dejafeFormsKeyFromLabel = keyFromLabel;

	wp.blocks.registerBlockType( 'dejafe/field', {
		__experimentalLabel: function ( a ) {
			return String( a.label || '' ).replace( /<[^>]*>/g, '' ) || __( 'Form field', 'dejafe-forms' );
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var type = a.type;
			var isChoice = type === 'select' || type === 'radio';
			var canHalf = [ 'textarea', 'radio', 'checkbox' ].indexOf( type ) === -1;
			var width = canHalf ? a.width : 'full';
			var hasPlaceholder = [ 'text', 'email', 'tel', 'textarea', 'select' ].indexOf( type ) !== -1;

			var inspector = el(
				be.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: __( 'Field', 'dejafe-forms' ) },
					el( c.SelectControl, {
						label: __( 'Type', 'dejafe-forms' ),
						value: type,
						options: TYPES,
						onChange: function ( v ) { set( { type: v } ); },
						__nextHasNoMarginBottom: true,
					} ),
					el( 'div', { style: { height: 16 } } ),
					el( c.ToggleControl, {
						label: type === 'checkbox' ? __( 'Must be ticked', 'dejafe-forms' ) : __( 'Required', 'dejafe-forms' ),
						checked: !! a.required,
						onChange: function ( v ) { set( { required: v } ); },
						__nextHasNoMarginBottom: true,
					} ),
					canHalf
						? el(
							Fragment,
							null,
							el( 'div', { style: { height: 16 } } ),
							el( c.SelectControl, {
								label: __( 'Width', 'dejafe-forms' ),
								value: width,
								options: [
									{ label: __( 'Full width', 'dejafe-forms' ), value: 'full' },
									{ label: __( 'Half width', 'dejafe-forms' ), value: 'half' },
								],
								help: __( 'Two half-width fields in a row sit side by side. On phones every field is full width.', 'dejafe-forms' ),
								onChange: function ( v ) { set( { width: v } ); },
								__nextHasNoMarginBottom: true,
							} )
						)
						: null,
					isChoice
						? el(
							Fragment,
							null,
							el( 'div', { style: { height: 16 } } ),
							el( c.TextareaControl, {
								label: __( 'Options', 'dejafe-forms' ),
								help: __( 'One per line.', 'dejafe-forms' ),
								value: a.options,
								rows: 5,
								onChange: function ( v ) { set( { options: v } ); },
								__nextHasNoMarginBottom: true,
							} )
						)
						: null,
					hasPlaceholder
						? el(
							Fragment,
							null,
							el( 'div', { style: { height: 16 } } ),
							el( c.TextControl, {
								label: type === 'select' ? __( 'First option text', 'dejafe-forms' ) : __( 'Placeholder', 'dejafe-forms' ),
								help: type === 'select' ? __( 'Shown before a choice is made. Default: Choose an option', 'dejafe-forms' ) : '',
								value: a.placeholder,
								onChange: function ( v ) { set( { placeholder: v } ); },
								__nextHasNoMarginBottom: true,
							} )
						)
						: null
				),
				el(
					c.PanelBody,
					{ title: __( 'Email tag', 'dejafe-forms' ), initialOpen: false },
					el( c.TextControl, {
						label: __( 'Field key', 'dejafe-forms' ),
						help: __( 'Used for the email tag, e.g. {', 'dejafe-forms' ) + ( a.name ? keyFromLabel( a.name ) : keyFromLabel( a.label ) ) + '}. ' + __( 'Leave empty to build it from the label. Set it if you use the tag in an email, so editing the label later does not break it.', 'dejafe-forms' ),
						value: a.name,
						placeholder: keyFromLabel( a.label ),
						onChange: function ( v ) { set( { name: v } ); },
						__nextHasNoMarginBottom: true,
					} )
				)
			);

			var labelText = el( be.RichText, {
				tagName: 'span',
				value: a.label,
				placeholder: __( 'Label', 'dejafe-forms' ),
				allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
				onChange: function ( v ) { set( { label: v } ); },
			} );
			var optional = a.required || type === 'checkbox' ? null : el( 'span', { className: 'dejafe-form__optional' }, ' ' + __( '(optional)', 'dejafe-forms' ) );

			var control;
			if ( type === 'checkbox' ) {
				control = el(
					'div',
					{ className: 'dejafe-form__label-row' },
					el( 'span', { className: 'dejafe-form__choice' }, el( 'input', { type: 'checkbox', disabled: true } ), ' ', labelText )
				);
			} else {
				var input;
				if ( type === 'textarea' ) {
					input = el( 'textarea', { className: 'dejafe-form__input', disabled: true, rows: 5, placeholder: a.placeholder } );
				} else if ( type === 'select' ) {
					input = el( 'select', { className: 'dejafe-form__input', disabled: true }, el( 'option', null, a.placeholder || __( 'Choose an option', 'dejafe-forms' ) ) );
				} else if ( type === 'radio' ) {
					var list = options( a.options );
					input = el(
						'div',
						{ className: 'dejafe-form__choices' },
						( list.length ? list : [ __( 'Add options in the sidebar', 'dejafe-forms' ) ] ).map( function ( o, i ) {
							return el( 'span', { key: i, className: 'dejafe-form__choice' }, el( 'input', { type: 'radio', disabled: true } ), ' ', el( 'span', null, o ) );
						} )
					);
				} else {
					input = el( 'input', { className: 'dejafe-form__input', type: type, disabled: true, placeholder: a.placeholder } );
				}
				control = el(
					Fragment,
					null,
					el( 'div', { className: 'dejafe-form__label-row' }, el( 'span', { className: 'dejafe-form__label' }, labelText, optional ) ),
					input
				);
			}

			var notice = isChoice && ! options( a.options ).length
				? el( 'p', { className: 'dejafe-form__editor-note' }, __( 'Add at least one option in the block settings. Until then this field is hidden.', 'dejafe-forms' ) )
				: null;

			return el(
				Fragment,
				null,
				inspector,
				el( 'div', be.useBlockProps( { className: 'dejafe-form__field is-' + width + ' is-type-' + type } ), control, notice )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
