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

	var FIELD_TEMPLATE = function ( showPhone ) {
		var t = [
			[ 'dejafe/field', { name: 'first_name', label: __( 'First Name', 'dejafe-forms' ), required: true, width: 'half' } ],
			[ 'dejafe/field', { name: 'last_name', label: __( 'Last Name', 'dejafe-forms' ), width: 'half' } ],
			[ 'dejafe/field', { name: 'email', type: 'email', label: __( 'Email', 'dejafe-forms' ), required: true } ],
		];
		if ( showPhone !== false ) {
			t.push( [ 'dejafe/field', { name: 'phone', type: 'tel', label: __( 'Phone Number', 'dejafe-forms' ) } ] );
		}
		t.push( [ 'dejafe/field', { name: 'message', type: 'textarea', label: __( 'Message', 'dejafe-forms' ), required: true } ] );
		return t;
	};

	wp.blocks.registerBlockType( 'dejafe/contact-form', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			useEffect( function () {
				if ( ! a.formId ) {
					set( { formId: 'f' + props.clientId.replace( /-/g, '' ).slice( 0, 10 ) } );
				}
			}, [] );

			var fieldKeys = useSelect( function ( select ) {
				var key = window.dejafeFormsKeyFromLabel || function ( v ) { return v; };
				return select( 'core/block-editor' ).getBlocks( props.clientId ).map( function ( b ) {
					return { key: key( b.attributes.name || b.attributes.label || b.attributes.type ), type: b.attributes.type };
				} );
			}, [ props.clientId ] );
			var allTags = [ '{name}' ].concat( fieldKeys.map( function ( f ) { return '{' + f.key + '}'; } ) ).concat( [ '{site_name}' ] ).join( ' ' );
			var shortTags = [ '{name}' ].concat( fieldKeys.filter( function ( f ) { return f.type !== 'textarea'; } ).map( function ( f ) { return '{' + f.key + '}'; } ) ).concat( [ '{site_name}' ] ).join( ' ' );

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
						help: __( 'Tags: ', 'dejafe-forms' ) + allTags,
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
							{ label: __( 'Use the default from Form entries > Settings', 'dejafe-forms' ), value: 'default' },
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
					a.successType === 'default'
						? null
						: el( c.TextareaControl, {
							label: a.successType === 'page' ? __( 'Message (used if the page is unavailable)', 'dejafe-forms' ) : __( 'Thank-you message', 'dejafe-forms' ),
							help: __( 'Tags: ', 'dejafe-forms' ) + shortTags,
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
								help: __( 'Tags: ', 'dejafe-forms' ) + shortTags + '. ' + __( 'Paragraph fields are never included, for privacy. The email goes to the first Email field.', 'dejafe-forms' ),
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
					} )
				)
			);

			var blockProps = be.useBlockProps( { className: 'dejafe-form is-preview' } );
			var innerProps = be.useInnerBlocksProps(
				{ className: 'dejafe-form__fields' },
				{
					allowedBlocks: [ 'dejafe/field' ],
					template: FIELD_TEMPLATE( a.showPhone ),
					renderAppender: be.InnerBlocks.ButtonBlockAppender,
				}
			);
			var preview = el(
				'div',
				blockProps,
				el( 'div', innerProps ),
				el( 'div', { className: 'dejafe-form__actions', style: { marginTop: 20 } }, el( 'span', { className: 'dejafe-form__submit wp-element-button' }, a.submitLabel ) )
			);

			return el( Fragment, null, inspector, preview );
		},
		save: function () {
			return el( be.InnerBlocks.Content );
		},
	} );
} )( window.wp );
