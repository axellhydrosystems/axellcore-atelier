import { createRoot } from '@wordpress/element';
import './style.scss';
import ResellersList from './list';
import ResellerDetailView from './detail';

const APP_ID = 'aa-resellers-app';

function mount( anchor: Element, element: JSX.Element ) {
	const container = document.createElement( 'div' );
	container.id = APP_ID;
	anchor.after( container );
	createRoot( container ).render( element );
}

const config = window.aaResellers;
const heading =
	document.querySelector( '#wpbody-content .wrap > .wp-header-end' ) ||
	document.querySelector( '#wpbody-content .wrap > h1' );

if ( config && heading ) {
	if ( document.body.classList.contains( 'aa-resellers-list' ) ) {
		mount( heading, <ResellersList config={ config } /> );
	} else if (
		document.body.classList.contains( 'aa-resellers-edit' ) &&
		( config.isNew || config.resellerId > 0 )
	) {
		mount( heading, <ResellerDetailView config={ config } /> );
	}
}
