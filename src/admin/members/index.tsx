import { createRoot } from '@wordpress/element';
// Relative path on purpose: a bare '@wordpress/...' import would be rewritten
// by the WP dependency extraction into a script handle (wp-dataviews/...) that
// doesn't exist, breaking the bundle.
import '../../../node_modules/@wordpress/dataviews/build-style/style.css';
import './style.scss';
import MembersList from './list';
import MemberDetailView from './detail';

const APP_ID = 'aa-members-app';

function mount( anchor: Element, element: JSX.Element ) {
	const container = document.createElement( 'div' );
	container.id = APP_ID;
	anchor.after( container );
	createRoot( container ).render( element );
}

const config = window.aaMembers;
const heading = document.querySelector( '#wpbody-content .wrap > h1' );

if ( config && heading ) {
	if ( document.body.classList.contains( 'aa-members-list' ) ) {
		mount(
			heading,
			<MembersList
				states={ config.states }
				atuacao={ config.atuacao }
				editUrl={ config.editUrl }
			/>
		);
	} else if ( document.body.classList.contains( 'aa-members-edit' ) ) {
		const id = Number(
			new URLSearchParams( window.location.search ).get( 'post' )
		);
		if ( id > 0 ) {
			mount(
				heading,
				<MemberDetailView
					id={ id }
					states={ config.states }
					atuacao={ config.atuacao }
					listUrl={ config.listUrl }
				/>
			);
		}
	}
}
