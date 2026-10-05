import { registerBlockType } from '@wordpress/blocks';
import type { BlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { group as groupIcon, grid as gridIcon, row as rowIcon, stack as stackIcon } from '@wordpress/icons';
import './style.scss';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import type { FormFieldsetAttributes } from './types';

/**
 * Layout variations, as core/group offers them: Simples (default group),
 * Linha (row), Coluna (column) and Grade (grid). Each sets the block's layout
 * attribute. Scope `block` makes the picker show on insert, like core/group.
 */
const variations = [
	{
		name: 'fieldset-simple',
		title: __( 'Simples', 'axellcore-atelierclub' ),
		description: __( 'Campos em um bloco simples, sem layout definido.', 'axellcore-atelierclub' ),
		icon: groupIcon,
		attributes: { layout: { type: 'default' } },
		isDefault: true,
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: ( attributes: { layout?: { type?: string } } ) =>
			! attributes?.layout || attributes.layout.type === 'default',
	},
	{
		name: 'fieldset-row',
		title: __( 'Linha', 'axellcore-atelierclub' ),
		description: __( 'Campos lado a lado, em uma linha.', 'axellcore-atelierclub' ),
		icon: rowIcon,
		attributes: { layout: { type: 'flex', flexWrap: 'nowrap' } },
		isDefault: false,
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: ( attributes: { layout?: { type?: string; orientation?: string; flexWrap?: string } } ) =>
			attributes?.layout?.type === 'flex' &&
			attributes.layout.orientation !== 'vertical' &&
			attributes.layout.flexWrap === 'nowrap',
	},
	{
		name: 'fieldset-column',
		title: __( 'Coluna', 'axellcore-atelierclub' ),
		description: __( 'Campos empilhados, em uma coluna.', 'axellcore-atelierclub' ),
		icon: stackIcon,
		attributes: { layout: { type: 'flex', orientation: 'vertical' } },
		isDefault: false,
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: ( attributes: { layout?: { type?: string; orientation?: string } } ) =>
			attributes?.layout?.type === 'flex' && attributes.layout.orientation === 'vertical',
	},
	{
		name: 'fieldset-grid',
		title: __( 'Grade', 'axellcore-atelierclub' ),
		description: __( 'Campos em uma grade.', 'axellcore-atelierclub' ),
		icon: gridIcon,
		attributes: { layout: { type: 'grid' } },
		isDefault: false,
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: ( attributes: { layout?: { type?: string } } ) => attributes?.layout?.type === 'grid',
	},
] as unknown as BlockVariation< FormFieldsetAttributes >[];

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon: groupIcon,
	variations,
	edit: Edit,
	save,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
