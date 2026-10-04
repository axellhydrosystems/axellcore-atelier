/**
 * Atelier Axell Club — shared frontend behavior.
 * Ported 1:1 from the approved atelier-axell-club.html mockup's inline <script>,
 * adapted to work against class/data-attribute hooks instead of hardcoded element IDs
 * so it works regardless of which block instance renders the markup.
 */
( function () {
	'use strict';

	/* ---------------------------------------------------------------------
	 * Input masks — driven by data-aac-mask="cpf-cnpj|cep|phone" on the
	 * <input>. A cpf-cnpj field additionally reads a sibling <select> named
	 * via data-aac-mask-source="<field name>" within the same <form> to
	 * decide which format to apply (mirrors the source's handleDocTypeChange()
	 * cross-field behavior).
	 * ------------------------------------------------------------------- */
	function digitsOnly( v ) {
		return v.replace( /\D/g, '' );
	}

	// Uppercased alphanumeric only — the first 12 characters of a Receita
	// Federal "CNPJ alfanumérico" (rolling out 2026) may be digits or
	// uppercase letters; only the 2 trailing check digits stay numeric.
	function alnumUpper( v ) {
		return v.toUpperCase().replace( /[^0-9A-Z]/g, '' );
	}

	function maskCpfCnpj( input, docType ) {
		if ( docType === 'cnpj' ) {
			var raw = alnumUpper( input.value ).slice( 0, 14 );
			var v = raw.slice( 0, 12 ) + digitsOnly( raw.slice( 12 ) ).slice( 0, 2 );
			v = v
				.replace( /^([0-9A-Z]{2})([0-9A-Z])/, '$1.$2' )
				.replace( /^([0-9A-Z]{2})\.([0-9A-Z]{3})([0-9A-Z])/, '$1.$2.$3' )
				.replace( /\.([0-9A-Z]{3})([0-9A-Z])/, '.$1/$2' )
				.replace( /([0-9A-Z]{4})(\d)/, '$1-$2' );
			input.value = v;
		} else {
			var v = digitsOnly( input.value ).slice( 0, 11 );
			v = v
				.replace( /(\d{3})(\d)/, '$1.$2' )
				.replace( /(\d{3})(\d)/, '$1.$2' )
				.replace( /(\d{3})(\d{1,2})$/, '$1-$2' );
			input.value = v;
		}
	}

	// Shared mod-11 check-digit rule (CPF and CNPJ both use it — only the
	// weights and each character's numeric value differ).
	function checkDigit( values, weights ) {
		var sum = 0;
		for ( var i = 0; i < values.length; i++ ) {
			sum += values[ i ] * weights[ i ];
		}
		var mod = sum % 11;
		return mod < 2 ? 0 : 11 - mod;
	}

	function isValidCPF( v ) {
		var d = digitsOnly( v );
		if ( d.length !== 11 || /^(\d)\1{10}$/.test( d ) ) {
			return false; // wrong length, or all-repeated-digit (never a real CPF).
		}
		var nums = d.split( '' ).map( function ( c ) {
			return parseInt( c, 10 );
		} );
		var dv1 = checkDigit( nums.slice( 0, 9 ), [ 10, 9, 8, 7, 6, 5, 4, 3, 2 ] );
		var dv2 = checkDigit( nums.slice( 0, 9 ).concat( dv1 ), [ 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 ] );
		return nums[ 9 ] === dv1 && nums[ 10 ] === dv2;
	}

	// Character value for the CNPJ check-digit algorithm: charCode - 48, so
	// '0'-'9' → 0-9 and 'A'-'Z' → 17-42 (Receita Federal Nota Técnica
	// COTEC/RFB nº 33/2024). This is exactly the classic all-numeric CNPJ
	// algorithm when every character happens to be a digit, so the same
	// validator covers both the old and the new (alphanumeric) format.
	function cnpjCharValue( c ) {
		return c.charCodeAt( 0 ) - 48;
	}

	function isValidCNPJ( v ) {
		var d = alnumUpper( v );
		if ( ! /^[0-9A-Z]{12}\d{2}$/.test( d ) || /^(.)\1{13}$/.test( d ) ) {
			return false; // wrong shape, or all-repeated-character.
		}
		var values = d.split( '' ).map( cnpjCharValue );
		var dv1 = checkDigit( values.slice( 0, 12 ), [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ] );
		var dv2 = checkDigit( values.slice( 0, 12 ).concat( dv1 ), [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ] );
		return values[ 12 ] === dv1 && values[ 13 ] === dv2;
	}

	function maskCep( input ) {
		var v = digitsOnly( input.value ).slice( 0, 8 );
		if ( v.length > 5 ) {
			v = v.slice( 0, 5 ) + '-' + v.slice( 5 );
		}
		input.value = v;
	}

	function maskPhone( input ) {
		var v = digitsOnly( input.value ).slice( 0, 11 );
		if ( v.length > 10 ) {
			v = v.replace( /^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3' );
		} else if ( v.length > 6 ) {
			v = v.replace( /^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3' );
		} else if ( v.length > 2 ) {
			v = v.replace( /^(\d{2})(\d{0,5}).*/, '($1) $2' );
		} else if ( v.length > 0 ) {
			v = v.replace( /^(\d*)/, '($1' );
		}
		input.value = v;
	}

	function docTypePlaceholder( docType ) {
		if ( docType === 'cpf' ) {
			return { placeholder: '000.000.000-00', maxlength: '14' };
		}
		if ( docType === 'cnpj' ) {
			return { placeholder: '12.ABC.345/01DE-35', maxlength: '18' };
		}
		return { placeholder: '000.000.000-00 / 00.000.000/0000-00', maxlength: null };
	}

	document.querySelectorAll( '[data-aac-mask="cpf-cnpj"]' ).forEach( function ( input ) {
		var form = input.closest( 'form' );
		var sourceName = input.getAttribute( 'data-aac-mask-source' );
		var source = sourceName && form ? form.elements.namedItem( sourceName ) : null;

		var applyDocType = function () {
			var docType = source ? source.value : '';
			var cfg = docTypePlaceholder( docType );
			input.placeholder = cfg.placeholder;
			if ( cfg.maxlength ) {
				input.setAttribute( 'maxlength', cfg.maxlength );
			} else {
				input.removeAttribute( 'maxlength' );
			}
			input.value = '';
			input.setCustomValidity( '' );
		};

		// Real check-digit validation (not just formatting) — reported via
		// the input's native validity state, so the existing
		// form.reportValidity() call in the submit handler below already
		// surfaces it with zero extra UI.
		var validate = function () {
			var docType = source ? source.value : '';
			if ( ! input.value.trim() ) {
				input.setCustomValidity( '' );
				return;
			}
			var valid = docType === 'cnpj' ? isValidCNPJ( input.value ) : isValidCPF( input.value );
			input.setCustomValidity( valid ? '' : ( docType === 'cnpj' ? 'CNPJ inválido.' : 'CPF inválido.' ) );
		};

		if ( source ) {
			source.addEventListener( 'change', applyDocType );
		}

		input.addEventListener( 'input', function () {
			maskCpfCnpj( input, source ? source.value : '' );
			input.setCustomValidity( '' );
		} );
		input.addEventListener( 'blur', validate );
	} );

	document.querySelectorAll( '[data-aac-mask="cep"]' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			maskCep( input );
		} );
	} );

	document.querySelectorAll( '[data-aac-mask="phone"]' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			maskPhone( input );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * State → city cascading select — driven by data-aac-cities-source="…"
	 * (the name of the sibling <select> whose value is a 2-letter UF) on a
	 * <select data-aac-cities-source>, fetching from the REST cities
	 * endpoint (includes/class-rest.php; data adapted from
	 * fervidum/f9brcities — see includes/data/br-*.php). Starts disabled;
	 * enabled once populated.
	 * ------------------------------------------------------------------- */
	document.querySelectorAll( '[data-aac-cities-source]' ).forEach( function ( citySelect ) {
		var form = citySelect.closest( 'form' );
		var sourceName = citySelect.getAttribute( 'data-aac-cities-source' );
		var source = sourceName && form ? form.elements.namedItem( sourceName ) : null;
		if ( ! source || ! window.aacRest || ! window.aacRest.root ) {
			return;
		}

		var placeholder = citySelect.querySelector( 'option[value=""]' );
		var placeholderText = placeholder ? placeholder.textContent : '';

		source.addEventListener( 'change', function () {
			var uf = source.value;
			citySelect.innerHTML = '';
			citySelect.disabled = true;

			if ( ! uf ) {
				citySelect.appendChild( new Option( placeholderText, '' ) );
				return;
			}

			citySelect.appendChild( new Option( 'Carregando cidades…', '' ) );

			fetch( window.aacRest.root + 'cities?uf=' + encodeURIComponent( uf ) )
				.then( function ( response ) {
					return response.ok ? response.json() : Promise.reject( response );
				} )
				.then( function ( cities ) {
					citySelect.innerHTML = '';
					citySelect.appendChild( new Option( placeholderText || 'Selecione', '' ) );
					cities.forEach( function ( city ) {
						citySelect.appendChild( new Option( city.label, city.value ) );
					} );
					citySelect.disabled = false;
				} )
				.catch( function () {
					citySelect.innerHTML = '';
					citySelect.appendChild( new Option( 'Não foi possível carregar as cidades.', '' ) );
				} );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Success/error feedback — toggles the matching
	 * axellcore/form-submission-notification block (data-aac-notice-type)
	 * instead of a blocking window.alert(). See this plugin's CLAUDE.md for
	 * why this differs from the reference core/form-submission-notification
	 * block's full-page-reload + ?wp-form-result= approach: this form never
	 * navigates away, so visibility is just a class toggle after fetch()
	 * resolves.
	 * ------------------------------------------------------------------- */
	function showFormNotice( form, type, detail ) {
		form.querySelectorAll( '[data-aac-notice-type]' ).forEach( function ( notice ) {
			var isMatch = notice.getAttribute( 'data-aac-notice-type' ) === type;
			notice.classList.toggle( 'is-active', isMatch );
			if ( isMatch ) {
				var existingDetail = notice.querySelector( '.aac-notice-detail' );
				if ( detail ) {
					if ( ! existingDetail ) {
						existingDetail = document.createElement( 'p' );
						existingDetail.className = 'aac-notice-detail';
						notice.appendChild( existingDetail );
					}
					existingDetail.textContent = detail;
				} else if ( existingDetail ) {
					existingDetail.remove();
				}
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Submit handler — POSTs to the real REST endpoint
	 * (includes/class-rest.php's /members route), which creates the
	 * aac_member post and resolves the Country > State > City taxonomy term.
	 * ------------------------------------------------------------------- */
	document.querySelectorAll( 'form[data-aac-club-form]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! form.checkValidity() ) {
				form.reportValidity();
				return;
			}
			if ( ! window.aacRest || ! window.aacRest.root ) {
				return;
			}

			var submitBtn = form.querySelector( 'button[type="submit"]' );
			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			var data = Object.fromEntries( new FormData( form ).entries() );

			fetch( window.aacRest.root + 'members', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						throw new Error( ( result.body && result.body.message ) || 'Erro ao enviar.' );
					}
					form.reset();
					showFormNotice( form, 'success' );
				} )
				.catch( function ( error ) {
					showFormNotice( form, 'error', error.message );
				} )
				.finally( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
				} );
		} );
	} );
} )();
