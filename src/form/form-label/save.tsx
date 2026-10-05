import { useBlockProps, RichText } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormLabelAttributes } from './types';

function classesFor( attributes: FormLabelAttributes ): string {
	const classes = [];
	if ( attributes.visuallyHidden ) {
		classes.push( 'is-visually-hidden' );
	}
	if ( attributes.required ) {
		classes.push( 'is-required' );
	}
	return classes.join( ' ' );
}

export default function save( {
	attributes,
}: BlockSaveProps< FormLabelAttributes > ) {
	const blockProps = useBlockProps.save( {
		className: classesFor( attributes ),
	} );
	return (
		<RichText.Content
			{ ...blockProps }
			tagName="label"
			htmlFor={ attributes.for || undefined }
			value={ attributes.text }
		/>
	);
}
