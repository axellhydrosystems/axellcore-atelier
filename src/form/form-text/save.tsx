import { useBlockProps, RichText } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';


export default function save( { attributes }: BlockSaveProps< { content: string } > ) {
	const blockProps = useBlockProps.save();
	return <RichText.Content { ...blockProps } tagName="p" value={ attributes.content } />;
}
