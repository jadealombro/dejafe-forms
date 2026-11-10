/**
 * Contact form: validation, submission without reload, thank-you message or page.
 */
( function () {
	document.querySelectorAll( '[data-dejafe-form]' ).forEach( function ( form ) {
		var started = Date.now();
		var fields = form.querySelector( '.dejafe-form__fields' );
		var status = form.querySelector( '.dejafe-form__status' );
		var success = form.querySelector( '.dejafe-form__success' );
		var button = form.querySelector( '.dejafe-form__submit' );
		var label = button.textContent;

		function setError( name, message ) {
			var wrap = form.querySelector( '[data-field="' + name + '"]' );
			if ( ! wrap ) {
				return;
			}
			var error = wrap.querySelector( '.dejafe-form__error' );
			wrap.classList.toggle( 'has-error', !! message );
			wrap.querySelectorAll( 'input, textarea, select' ).forEach( function ( input ) {
				input.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
			} );
			error.textContent = message || '';
			error.hidden = ! message;
		}

		function showStatus( message ) {
			status.textContent = message || '';
			status.hidden = ! message;
		}

		function clientCheck() {
			var first = null;
			form.querySelectorAll( '[data-field]' ).forEach( function ( wrap ) {
				var type = wrap.dataset.type;
				var inputs = wrap.querySelectorAll( 'input, textarea, select' );
				var input = inputs[ 0 ];
				var required = wrap.querySelector( '[required]' );
				var message = '';
				if ( type === 'radio' ) {
					var picked = wrap.querySelector( 'input:checked' );
					if ( required && ! picked ) {
						message = 'Required';
					}
				} else if ( type === 'checkbox' ) {
					if ( required && ! input.checked ) {
						message = 'Please tick this box';
					}
				} else if ( required && ! input.value.trim() ) {
					message = 'Required';
				} else if ( type === 'email' && input.value && ! input.checkValidity() ) {
					message = 'Check the email';
				} else if ( type === 'date' && input.value && ! input.checkValidity() ) {
					message = 'Check the date';
				}
				setError( wrap.dataset.field, message );
				if ( message && ! first ) {
					first = input;
				}
			} );
			if ( first ) {
				first.focus();
			}
			return ! first;
		}

		function clearOnChange( event ) {
			var wrap = event.target.closest( '[data-field]' );
			if ( wrap && wrap.classList.contains( 'has-error' ) ) {
				setError( wrap.dataset.field, '' );
			}
		}

		form.addEventListener( 'input', clearOnChange );
		form.addEventListener( 'change', clearOnChange );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			showStatus( '' );
			if ( ! clientCheck() ) {
				return;
			}

			var data = new FormData( form );
			data.append( 'elapsed', String( Date.now() - started ) );

			button.disabled = true;
			button.textContent = 'Sending…';
			form.classList.add( 'is-sending' );

			fetch( form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } } )
				.then( function ( response ) {
					return response.json().then( function ( json ) {
						return { ok: response.ok, json: json };
					} );
				} )
				.then( function ( result ) {
					var json = result.json || {};
					if ( result.ok && json.success ) {
						if ( json.redirect ) {
							window.location.assign( json.redirect );
							return;
						}
						success.innerHTML = '<p>' + json.message + '</p>';
						fields.hidden = true;
						success.hidden = false;
						form.classList.add( 'is-sent' );
						success.focus();
						return;
					}
					Object.keys( json.errors || {} ).forEach( function ( name ) {
						setError( name, json.errors[ name ] );
					} );
					showStatus( json.message || 'Something went wrong. Please try again.' );
					if ( window.turnstile ) {
						var widget = form.querySelector( '.cf-turnstile' );
						if ( widget ) {
							window.turnstile.reset( widget );
						}
					}
				} )
				.catch( function () {
					showStatus( 'Something went wrong. Please check your connection and try again.' );
				} )
				.finally( function () {
					button.disabled = false;
					button.textContent = label;
					form.classList.remove( 'is-sending' );
				} );
		} );
	} );
} )();
