import { store, getElement } from '@wordpress/interactivity';

/**
 * Fraction of an element that must be visible before it reveals. Matches the
 * approved mockup's IntersectionObserver threshold.
 */
const THRESHOLD = 0.1;

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

let observer: IntersectionObserver | null = null;

const reveal = ( target: Element ) => {
	target.classList.add( 'is-in' );
};

/**
 * One observer for the whole page. Each target reveals once and is then
 * released, so the observer never keeps work for finished elements.
 */
const getObserver = () => {
	if ( ! observer && 'IntersectionObserver' in window ) {
		observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) {
						return;
					}
					reveal( entry.target );
					observer?.unobserve( entry.target );
				} );
			},
			{ threshold: THRESHOLD }
		);
	}
	return observer;
};

store( 'axell/reveal', {
	callbacks: {
		observe: () => {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}

			// `is-ready` is what turns the hidden state on (style.scss). Without
			// JavaScript the content stays visible.
			ref.classList.add( 'is-ready' );

			const targets =
				ref.getAttribute( 'data-axell-reveal' ) === 'items'
					? Array.from( ref.children )
					: [ ref ];

			const io = getObserver();
			if ( ! io || window.matchMedia( REDUCED_MOTION ).matches ) {
				targets.forEach( reveal );
				return;
			}
			targets.forEach( ( target ) => io.observe( target ) );
		},
	},
} );
