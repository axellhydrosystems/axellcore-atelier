import { store, getContext } from '@wordpress/interactivity';

type FormStatus = 'idle' | 'submitting' | 'success' | 'error';

interface FormContext {
	status: FormStatus;
}

/**
 * restUrl is provided by includes/class-form-block.php (wp_interactivity_state).
 * It is not declared in the store below, so a client value cannot override it.
 */
const serverState = (): { restUrl: string } =>
	state as unknown as { restUrl: string };

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
		*submit( event: SubmitEvent ): Generator< unknown, void, unknown > {
			event.preventDefault();

			const form = event.currentTarget as HTMLFormElement;
			const context = getContext< FormContext >();

			if ( context.status === 'submitting' || ! form.reportValidity() ) {
				return;
			}

			context.status = 'submitting';

			const body = Object.fromEntries( new FormData( form ).entries() );

			try {
				const response = ( yield fetch( serverState().restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( body ),
				} ) ) as Response;

				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}

				context.status = 'success';
				form.reset();
			} catch {
				context.status = 'error';
			}
		},
	},
} );

export { state };
