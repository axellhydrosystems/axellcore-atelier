import { store, getContext } from '@wordpress/interactivity';

/**
 * Scroll position (px) past which the header switches to its compact state.
 * Matches the approved mockup's `nav.classList.add('scrolled')` threshold.
 */
const SCROLL_THRESHOLD = 40;

interface StickyHeaderContext {
	scrolled: boolean;
}

store( 'axell/sticky-header', {
	actions: {
		onScroll: () => {
			const context = getContext< StickyHeaderContext >();
			context.scrolled = window.scrollY > SCROLL_THRESHOLD;
		},
	},
} );
