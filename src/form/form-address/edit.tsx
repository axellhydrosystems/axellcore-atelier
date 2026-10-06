import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

// Each address field is a form group (its label and its control, linked by id/for).
// The region only holds the groups, so the store reads every control from one place.
const ALLOWED_BLOCKS = [ 'axell/form-group' ];

const TEMPLATE: Array< [ string, Record< string, unknown >, Array< [ string, Record< string, unknown > ] > ] > = [
	[ 'axell/form-group', {}, [
		[ 'axell/form-label', { text: 'País', for: 'pais', required: true } ],
		[ 'axell/form-control-country', { id: 'pais', name: 'pais', required: true } ],
	] ],
	[ 'axell/form-group', {}, [
		[ 'axell/form-label', { text: 'UF', for: 'uf', required: true } ],
		[ 'axell/form-control-state', { id: 'uf', name: 'uf', required: true } ],
	] ],
	[ 'axell/form-group', {}, [
		[ 'axell/form-label', { text: 'Cidade', for: 'cidade', required: true } ],
		[ 'axell/form-control-city', { id: 'cidade', name: 'cidade', required: true } ],
	] ],
	[ 'axell/form-group', {}, [
		[ 'axell/form-label', { text: 'CEP', for: 'cep', required: true } ],
		[ 'axell/form-control-postal', { id: 'cep', name: 'cep', required: true, placeholder: '00000-000' } ],
	] ],
];

export default function Edit() {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE as never,
		templateInsertUpdatesSelection: false,
	} );
	return <div { ...innerBlocksProps } />;
}
