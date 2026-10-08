import { store, getContext } from '@wordpress/interactivity';

type FormStatus = 'idle' | 'submitting' | 'success' | 'error';

interface FormContext {
	status: FormStatus;
	/** Message of the last error a visitor can fix (also set by the server after a no-JS submission). */
	errorDetail: string;
}

/** Body of a WP_Error response: Members::field_error() names the field. */
interface ServerError {
	message?: string;
	data?: { field?: string };
}

/**
 * The control a visitor edits for a field name: not a hidden input, not
 * hidden or disabled (e.g. the city list or the typed city, whichever shows).
 * @param form
 * @param name
 */
const controlOf = (
	form: HTMLFormElement,
	name: string
): HTMLInputElement | undefined =>
	Array.from(
		form.querySelectorAll< HTMLInputElement >(
			`[name="${ CSS.escape( name ) }"]`
		)
	).find( ( el ) => el.type !== 'hidden' && ! el.hidden && ! el.disabled );

/** reCAPTCHA v3 of the forms (includes/class-recaptcha.php). */
interface RecaptchaState {
	siteKey: string;
	action: string;
}

/** Google's reCAPTCHA script (v3 execute, v2 reset). */
interface Grecaptcha {
	ready: ( callback: () => void ) => void;
	execute: (
		siteKey: string,
		options: { action: string }
	) => Promise< string >;
	reset: () => void;
}

/**
 * restUrl is provided by includes/class-form-block.php (wp_interactivity_state),
 * recaptcha by includes/class-recaptcha.php when v3 protects the forms. They
 * are not declared in the store below, so a client value cannot override them.
 */
const serverState = (): { restUrl: string; recaptcha?: RecaptchaState } =>
	state as unknown as { restUrl: string; recaptcha?: RecaptchaState };

const grecaptcha = (): Grecaptcha | undefined =>
	( window as unknown as { grecaptcha?: Grecaptcha } ).grecaptcha;

/**
 * A reCAPTCHA v3 token for a submission ('' when Google's script did not
 * load: the server then refuses it with its message).
 *
 * @param config Site key and action.
 */
const recaptchaToken = ( config: RecaptchaState ): Promise< string > =>
	new Promise( ( resolve ) => {
		const google = grecaptcha();
		if ( ! google ) {
			resolve( '' );
			return;
		}
		google.ready( () =>
			google
				.execute( config.siteKey, { action: config.action } )
				.then( resolve, () => resolve( '' ) )
		);
	} );

/**
 * An address typed without a scheme ("site.com.br") gets https://, as the
 * server stores it, so the field's own url check accepts it.
 *
 * @param input Field.
 */
const completeUrl = ( input: HTMLInputElement ) => {
	const value = input.value.trim();
	if ( value && ! /^[a-z][a-z0-9+.-]*:\/\//i.test( value ) ) {
		input.value = 'https://' + value.replace( /^\/+/, '' );
	}
};

const { state } = store( 'axell/form', {
	state: {
		get isSubmitting(): boolean {
			return getContext< FormContext >().status === 'submitting';
		},
		get isSuccess(): boolean {
			return getContext< FormContext >().status === 'success';
		},
		get isError(): boolean {
			return getContext< FormContext >().status === 'error';
		},
	},
	actions: {
		completeUrl( event: FocusEvent ) {
			const input = event.target as HTMLInputElement;
			if ( input instanceof HTMLInputElement && input.type === 'url' ) {
				completeUrl( input );
			}
		},

		*submit( event: SubmitEvent ): Generator< unknown, void, unknown > {
			event.preventDefault();

			const form = event.currentTarget as HTMLFormElement;
			const context = getContext< FormContext >();

			// Sent with Enter, before leaving the address field.
			form.querySelectorAll< HTMLInputElement >(
				'input[type="url"]'
			).forEach( completeUrl );

			if ( context.status === 'submitting' || ! form.reportValidity() ) {
				return;
			}

			context.status = 'submitting';
			context.errorDetail = '';

			const body = Object.fromEntries( new FormData( form ).entries() );

			// reCAPTCHA v3 (v2's box already put its token in the form data).
			const recaptcha = serverState().recaptcha;
			if ( recaptcha ) {
				body[ 'g-recaptcha-response' ] = ( yield recaptchaToken(
					recaptcha
				) ) as string;
			}

			try {
				const response = ( yield fetch( serverState().restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( body ),
				} ) ) as Response;

				if ( ! response.ok ) {
					const error =
						( ( yield response
							.json()
							.catch( () => ( {} ) ) ) as ServerError ) || {};
					const control = error.data?.field
						? controlOf( form, error.data.field )
						: undefined;
					if ( control && error.message ) {
						// A field the visitor can fix: report it there, as the browser does.
						context.status = 'idle';
						control.setCustomValidity( error.message );
						control.addEventListener(
							'input',
							() => control.setCustomValidity( '' ),
							{ once: true }
						);
						control.reportValidity();
						return;
					}
					context.errorDetail = error.message || '';
					throw new Error( response.statusText );
				}

				context.status = 'success';
				form.reset();
			} catch {
				context.status = 'error';
			} finally {
				// A v2 token is good for one submission only.
				if ( form.querySelector( '.g-recaptcha' ) ) {
					grecaptcha()?.reset();
				}
			}
		},
	},
} );

export { state };
