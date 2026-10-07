import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps } from '@wordpress/blocks';


export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< { content: string } > ) {
	const blockProps = useBlockProps();

	return (
		<RichText
			{ ...blockProps }
			tagName="p"
			value={ attributes.content }
			onChange={ ( value: string ) => setAttributes( { content: value } ) }
			placeholder={ __( 'Help text…', 'axellcore-atelierclub' ) }
			allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
		/>
	);
}
