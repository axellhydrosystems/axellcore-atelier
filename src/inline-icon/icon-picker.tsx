/**
 * Icon library modal, the same interface as the Icon block's picker
 * (core's CustomInserterModal and IconGrid in `@wordpress/block-library`,
 * GPL-2.0-or-later): search and collection tabs in a sidebar, and a grid of
 * icons with their names. It reuses core's `wp-block-icon__inserter-*` classes,
 * so the editor's block-library styles lay it out.
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useAsyncList, useDebounce } from '@wordpress/compose';
import { useMemo, useState } from '@wordpress/element';
import { Button, Modal, SearchControl, Spinner } from '@wordpress/components';
import { Stack, Tabs } from '@wordpress/ui';

type Icon = { name: string; label: string; content: string };
type Collection = { slug: string; label: string };

type Props = {
	value?: string;
	onSelect: ( icon: string ) => void;
	onClose: () => void;
};

type CoreSelectors = {
	getEntityRecords: < T >(
		kind: string,
		name: string,
		query?: Record< string, unknown >
	) => T[] | null;
	hasFinishedResolution: ( selector: string, args: unknown[] ) => boolean;
};

/**
 * Lowercase, accent-free text for searching.
 *
 * @param input Text.
 * @return Normalized text.
 */
const normalize = ( input = '' ): string =>
	input.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).trim().toLowerCase();

/**
 * The icon grid, rendered in batches like core's.
 * @param root0
 * @param root0.icons
 * @param root0.value
 * @param root0.onSelect
 */
function IconGrid( {
	icons,
	value,
	onSelect,
}: {
	icons: Icon[];
	value?: string;
	onSelect: ( icon: string ) => void;
} ) {
	const shownIcons = useAsyncList( icons, { step: 20 } ) as Icon[];

	return (
		<div className="wp-block-icon__inserter-grid">
			{ ! icons.length ? (
				<div className="wp-block-icon__inserter-grid-no-results">
					<p>{ __( 'No results found.', 'axellcore-atelier' ) }</p>
				</div>
			) : (
				<div
					className="wp-block-icon__inserter-grid-icons-list"
					aria-label={ __( 'Icon library', 'axellcore-atelier' ) }
				>
					{ shownIcons.map( ( icon ) => (
						<Button
							key={ icon.name }
							className="wp-block-icon__inserter-grid-icons-list-item"
							onClick={ () => onSelect( icon.name ) }
							variant={
								icon.name === value ? 'primary' : undefined
							}
							__next40pxDefaultSize
						>
							<span
								className="wp-block-icon__inserter-grid-icons-list-item-icon"
								// The registered icon's SVG, from the Icons API.
								dangerouslySetInnerHTML={ {
									__html: icon.content,
								} }
							/>
							<span className="wp-block-icon__inserter-grid-icons-list-item-title">
								{ icon.label }
							</span>
						</Button>
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * The Icon library modal.
 * @param root0
 * @param root0.value
 * @param root0.onSelect
 * @param root0.onClose
 */
export default function IconPicker( { value, onSelect, onClose }: Props ) {
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ currentCollection, setCurrentCollection ] = useState<
		string | null
	>( null );
	const debouncedSetSearchInput = useDebounce( setSearchInput, 300 );

	const collections = useSelect(
		( select ) =>
			(
				select( coreStore ) as unknown as CoreSelectors
			 ).getEntityRecords< Collection >( 'root', 'iconCollection' ),
		[]
	);
	const selectedCollection = value?.split( '/' )[ 0 ];
	const collectionSlug =
		currentCollection ??
		( collections?.some( ( { slug } ) => slug === selectedCollection )
			? selectedCollection
			: collections?.[ 0 ]?.slug ) ??
		null;

	const { icons, hasResolvedIcons } = useSelect(
		( select ) => {
			if ( collectionSlug === null ) {
				return { icons: null, hasResolvedIcons: false };
			}
			const query =
				collectionSlug === '' ? {} : { collection: collectionSlug };
			const { getEntityRecords, hasFinishedResolution } = select(
				coreStore
			) as unknown as CoreSelectors;
			return {
				icons: getEntityRecords< Icon >( 'root', 'icon', query ),
				hasResolvedIcons: hasFinishedResolution( 'getEntityRecords', [
					'root',
					'icon',
					query,
				] ),
			};
		},
		[ collectionSlug ]
	);

	const filteredIcons = useMemo( () => {
		if ( ! icons ) {
			return [];
		}
		if ( ! searchInput ) {
			return icons;
		}
		const input = normalize( searchInput );
		return icons.filter(
			( icon ) =>
				normalize( icon.name ).includes( input ) ||
				normalize( icon.label ).includes( input )
		);
	}, [ searchInput, icons ] );

	return (
		<Modal
			className="wp-block-icon__inserter-modal"
			title={ __( 'Icon library', 'axellcore-atelier' ) }
			onRequestClose={ onClose }
			isFullScreen
		>
			<Tabs.Root
				className="wp-block-icon__inserter"
				orientation="vertical"
				value={ collectionSlug }
				onValueChange={ ( slug ) =>
					setCurrentCollection( slug as string )
				}
			>
				<Stack
					direction="column"
					gap="lg"
					className="wp-block-icon__inserter-sidebar"
				>
					<SearchControl
						__nextHasNoMarginBottom
						value={ searchInput }
						onChange={ debouncedSetSearchInput }
					/>
					<Tabs.List>
						<Tabs.Tab value="">
							{ __( 'All', 'axellcore-atelier' ) }
						</Tabs.Tab>
						{ collections?.map( ( collection ) => (
							<Tabs.Tab
								key={ collection.slug }
								value={ collection.slug }
							>
								{ collection.label }
							</Tabs.Tab>
						) ) }
					</Tabs.List>
				</Stack>
				{ [ { slug: '' }, ...( collections ?? [] ) ].map(
					( collection ) => (
						<Tabs.Panel
							key={ collection.slug }
							tabIndex={ -1 }
							value={ collection.slug }
							className="wp-block-icon__inserter-panel"
						>
							{ ! hasResolvedIcons ? (
								<div
									className="wp-block-icon__inserter-loading"
									role="status"
									aria-label={ __(
										'Loading…',
										'axellcore-atelier'
									) }
								>
									<Spinner />
								</div>
							) : (
								<IconGrid
									icons={ filteredIcons }
									value={ value }
									onSelect={ onSelect }
								/>
							) }
						</Tabs.Panel>
					)
				) }
			</Tabs.Root>
		</Modal>
	);
}
