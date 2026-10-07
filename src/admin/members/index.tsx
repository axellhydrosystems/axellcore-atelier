import { createRoot } from '@wordpress/element';
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
// After the heading row (title and its Exportar button).
const heading =
	document.querySelector( '#wpbody-content .wrap > .wp-header-end' ) ||
	document.querySelector( '#wpbody-content .wrap > h1' );

if ( config && heading ) {
	if ( document.body.classList.contains( 'aa-members-list' ) ) {
		mount(
			heading,
			<MembersList
				states={ config.states }
				primaryFocus={ config.primaryFocus }
				statuses={ config.statuses }
				editUrl={ config.editUrl }
			/>
		);
	} else if ( document.body.classList.contains( 'aa-members-edit' ) ) {
		const id = config.memberId;
		if ( id > 0 ) {
			mount(
				heading,
				<MemberDetailView
					id={ id }
					states={ config.states }
					primaryFocus={ config.primaryFocus }
					statuses={ config.statuses }
					listUrl={ config.listUrl }
				/>
			);
		}
	}
}
