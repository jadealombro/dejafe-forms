/**
 * Contact form block: editor preview and settings. No build step needed.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var be = wp.blockEditor;
	var c = wp.components;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;

	var TAGS = '{first_name} {last_name} {email} {phone} {site_name}';

	function field( label, type ) {
		return el(
			'div',
			{ className: 'dejafe-form__field' },
			el( 'span', { className: 'dejafe-form__label' }, label ),
			type === 'textarea'
				? el( 'textarea', { className: 'dejafe-form__input', disabled: true, rows: 5 } )
				: el( 'input', { className: 'dejafe-form__input', type: type, disabled: true } )
		);
	}

	wp.blocks.registerBlockType( 'dejafe/contact-form', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			useEffect( function () {
				if ( ! a.formId ) {
					set( { formId: 'f' + props.clientId.replace( /-/g, '' ).slice( 0, 10 ) } );
				}
			}, [] );

			var pages = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'page', { per_page: 100, status: 'publish', _fields: 'id,title' } );
			}, [] );

			var pageOptions = [ { label: __( 'Select a page', 'dejafe-forms' ), value: '0' } ].concat(
				( pages || [] ).map( function ( p ) {
					return { label: ( p.title && p.title.rendered ) || __( '(no title)', 'dejafe-forms' ), value: String( p.id ) };
				} )
			);

			var inspector = el(
				be.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: __( 'Notification email', 'dejafe-forms' ), initialOpen: true },
					el( c.TextControl, {
						label: __( 'Send to', 'dejafe-forms' ),
						help: __( 'Separate multiple addresses with commas. Empty uses the site admin email.', 'dejafe-forms' ),
						value: a.recipient,
						type: 'text',
						onChange: function ( v ) { set( { recipient: v } ); },
					} ),
					el( c.TextControl, {
						label: __( 'Subject', 'dejafe-forms' ),
						help: __( 'Tags: ', 'dejafe-forms' ) + TAGS,
						value: a.subject,
						onChange: function ( v ) { set( { subject: v } ); },
					} )
				),
				el(
					c.PanelBody,
					{ title: __( 'After submitting', 'dejafe-forms' ), initialOpen: true },
					el( c.RadioControl, {
						selected: a.successType,
						options: [
							{ label: __( 'Show a thank-you message', 'dejafe-forms' ), value: 'message' },
							{ label: __( 'Go to a thank-you page', 'dejafe-forms' ), value: 'page' },
						],
						onChange: function ( v ) { set( { successType: v } ); },
					} ),
					a.successType === 'page'
						? el( c.SelectControl, {
							label: __( 'Thank-you page', 'dejafe-forms' ),
							value: String( a.successPage || 0 ),
							options: pageOptions,
							onChange: function ( v ) { set( { successPage: parseInt( v, 10 ) || 0 } ); },
						} )
						: null,
					el( c.TextareaControl, {
						label: a.successType === 'page' ? __( 'Message (used if the page is unavailable)', 'dejafe-forms' ) : __( 'Thank-you message', 'dejafe-forms' ),
						help: __( 'Tag: {first_name}', 'dejafe-forms' ),
						value: a.successMessage,
						rows: 3,
						onChange: function ( v ) { set( { successMessage: v } ); },
					} )
				),
				el(
					c.PanelBody,
					{ title: __( 'Confirmation email to the visitor', 'dejafe-forms' ), initialOpen: false },
					el( c.ToggleControl, {
						label: __( 'Send a confirmation email', 'dejafe-forms' ),
						checked: !! a.confirmationEnabled,
						onChange: function ( v ) { set( { confirmationEnabled: v } ); },
					} ),
					a.confirmationEnabled
						? el(
							Fragment,
							null,
							el( c.TextControl, {
								label: __( 'Subject', 'dejafe-forms' ),
								value: a.confirmationSubject,
								onChange: function ( v ) { set( { confirmationSubject: v } ); },
							} ),
							el( c.TextareaControl, {
								label: __( 'Message', 'dejafe-forms' ),
								help: __( 'Tags: ', 'dejafe-forms' ) + TAGS + '. ' + __( 'The visitor\'s own message is never included, for privacy.', 'dejafe-forms' ),
								value: a.confirmationBody,
								rows: 8,
								onChange: function ( v ) { set( { confirmationBody: v } ); },
							} )
						)
						: null
				),
				el(
					c.PanelBody,
					{ title: __( 'Form', 'dejafe-forms' ), initialOpen: false },
					el( c.TextControl, {
						label: __( 'Button text', 'dejafe-forms' ),
						value: a.submitLabel,
						onChange: function ( v ) { set( { submitLabel: v } ); },
					} ),
					el( c.ToggleControl, {
						label: __( 'Ask for a phone number', 'dejafe-forms' ),
						checked: !! a.showPhone,
						onChange: function ( v ) { set( { showPhone: v } ); },
					} )
				)
			);

			var preview = el(
				'div',
				be.useBlockProps( { className: 'dejafe-form is-preview' } ),
				el(
					'div',
					{ className: 'dejafe-form__fields' },
					el( 'div', { className: 'dejafe-form__row' }, field( __( 'First Name', 'dejafe-forms' ), 'text' ), field( __( 'Last Name', 'dejafe-forms' ), 'text' ) ),
					field( __( 'Email', 'dejafe-forms' ), 'email' ),
					a.showPhone ? field( __( 'Phone Number', 'dejafe-forms' ), 'tel' ) : null,
					field( __( 'Message', 'dejafe-forms' ), 'textarea' ),
					el( 'div', { className: 'dejafe-form__actions' }, el( 'span', { className: 'dejafe-form__submit wp-element-button' }, a.submitLabel ) )
				)
			);

			return el( Fragment, null, inspector, preview );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
