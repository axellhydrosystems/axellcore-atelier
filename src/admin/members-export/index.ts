/**
 * Members export page (Atelier > Exportar): column/filter picker, batch
 * progress and download. Ported from axellcore's import/export screens
 * (assets/js/admin/import-export.js), export only, with its own names
 * (aaMembersExport, aa-export-*) so both plugins can be active.
 */
import './style.scss';

interface ExportConfig {
	ajaxUrl: string;
	nonce: string;
	action: string;
	i18n: { error: string };
}

interface BatchResult {
	total: number;
	done: number;
	percent: number;
	token: string;
	url?: string;
}

type Params = Record< string, string | number | string[] >;

declare global {
	interface Window {
		aaMembersExport?: ExportConfig;
	}
}

const cfg = window.aaMembersExport;

function post( data: Params ): Promise< BatchResult > {
	const body = new URLSearchParams();
	Object.keys( data ).forEach( ( key ) => {
		const value = data[ key ];
		if ( Array.isArray( value ) ) {
			value.forEach( ( item ) => body.append( key + '[]', item ) );
		} else {
			body.append( key, String( value ) );
		}
	} );
	body.append( 'security', cfg!.nonce );
	return fetch( cfg!.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
		.then( ( response ) => response.json() )
		.then( ( json ) => {
			if ( ! json.success ) {
				throw new Error( json.data && json.data.message ? json.data.message : cfg!.i18n.error );
			}
			return json.data as BatchResult;
		} );
}

function byId< T extends HTMLElement = HTMLElement >( id: string ): T {
	return document.getElementById( id ) as T;
}

function setBar( root: HTMLElement, percent: number ) {
	const bar = root.querySelector( '.aa-export-bar' ) as HTMLElement;
	bar.setAttribute( 'aria-valuenow', String( percent ) );
	( bar.firstElementChild as HTMLElement ).style.width = percent + '%';
}

function el< K extends keyof HTMLElementTagNameMap >( tag: K, text?: string, className?: string ): HTMLElementTagNameMap[ K ] {
	const node = document.createElement( tag );
	if ( text !== undefined ) {
		node.textContent = text;
	}
	if ( className ) {
		node.className = className;
	}
	return node;
}

/* ---------- Column picker (chips over a real <select multiple>) ---------- */
function enhanceColumns( select: HTMLSelectElement ) {
	const field = el( 'div', undefined, 'aa-export-tokens' );
	const list = el( 'ul', undefined, 'aa-export-tokens__chips' );
	const placeholder = el( 'span', select.dataset.placeholder, 'aa-export-tokens__placeholder' );
	const menu = el( 'ul', undefined, 'aa-export-tokens__menu' );
	menu.hidden = true;
	menu.setAttribute( 'role', 'listbox' );
	const toggle = el( 'button', undefined, 'aa-export-tokens__toggle' );
	toggle.type = 'button';
	toggle.setAttribute( 'aria-haspopup', 'listbox' );
	toggle.setAttribute( 'aria-expanded', 'false' );
	toggle.appendChild( list );
	toggle.appendChild( placeholder );
	const label = select.id ? document.querySelector( 'label[for="' + select.id + '"]' ) : null;
	if ( label ) {
		label.id = select.id + '-label';
		toggle.setAttribute( 'aria-labelledby', label.id );
	}
	const setMenu = ( open: boolean ) => {
		menu.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};
	const render = () => {
		list.textContent = '';
		menu.textContent = '';
		let selected = 0;
		Array.from( select.options ).forEach( ( option ) => {
			if ( option.selected ) {
				selected++;
				const chip = el( 'li', undefined, 'aa-export-chip' );
				const remove = el( 'button', '×' );
				remove.type = 'button';
				remove.setAttribute( 'aria-label', option.text );
				remove.addEventListener( 'click', ( event ) => {
					event.stopPropagation();
					option.selected = false;
					render();
				} );
				chip.appendChild( remove );
				chip.appendChild( el( 'span', option.text ) );
				list.appendChild( chip );
				return;
			}
			const item = el( 'li', option.text );
			item.setAttribute( 'role', 'option' );
			item.tabIndex = 0;
			const pick = () => {
				option.selected = true;
				render();
			};
			item.addEventListener( 'click', pick );
			item.addEventListener( 'keydown', ( event ) => {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					pick();
				}
			} );
			menu.appendChild( item );
		} );
		placeholder.hidden = selected > 0;
		if ( ! menu.children.length ) {
			setMenu( false );
		}
	};
	toggle.addEventListener( 'click', () => setMenu( menu.hidden ) );
	document.addEventListener( 'click', ( event ) => {
		if ( ! field.contains( event.target as Node ) ) {
			setMenu( false );
		}
	} );
	field.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			setMenu( false );
			toggle.focus();
		}
	} );
	field.appendChild( toggle );
	field.appendChild( menu );
	select.hidden = true;
	select.parentNode!.insertBefore( field, select.nextSibling );
	render();
}

/* ---------- Export ---------- */
const exportForm = document.getElementById( 'aa-export' ) as HTMLFormElement | null;
if ( cfg && exportForm ) {
	document.querySelectorAll< HTMLSelectElement >( 'select.aa-export-columns' ).forEach( enhanceColumns );

	const exportProgress = byId( 'aa-export-progress' );
	const status = byId( 'aa-export-status' );
	const runExport = ( params: Params, page: number, token: string ): Promise< unknown > =>
		post( Object.assign( {}, params, { page, token } ) ).then( ( result ) => {
			setBar( exportProgress, result.percent );
			if ( result.url ) {
				window.location.href = result.url;
				return new Promise( ( resolve ) => {
					window.setTimeout( resolve, 1500 );
				} );
			}
			return runExport( params, page + 1, result.token );
		} );

	exportForm.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		const fields = new FormData( exportForm );
		const list = ( name: string ) => fields.getAll( name + '[]' ).map( String );
		const params: Params = {
			action: cfg.action,
			columns: list( 'columns' ),
			statuses: list( 'statuses' ),
			states: list( 'states' ),
			cities: list( 'cities' ),
			focuses: list( 'focuses' ),
			since: String( fields.get( 'since' ) || '' ),
		};
		const button = exportForm.querySelector( 'button[type="submit"]' ) as HTMLButtonElement;
		button.disabled = true;
		exportForm.classList.add( 'is-running' );
		exportProgress.hidden = false;
		setBar( exportProgress, 0 );
		runExport( params, 1, '' )
			.then( () => {
				exportForm.classList.remove( 'is-running' );
				exportProgress.hidden = true;
				status.textContent = '';
			} )
			.catch( ( error: Error ) => {
				// Keep the progress visible with the message; bring the fields back.
				exportForm.classList.remove( 'is-running' );
				exportProgress.hidden = false;
				setBar( exportProgress, 0 );
				status.textContent = error.message;
			} )
			.then( () => {
				button.disabled = false;
			} );
	} );
}
