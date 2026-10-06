import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { ChapterAttributes } from './types';

export default function save( {
	attributes,
}: BlockSaveProps< ChapterAttributes > ) {
	const blockProps = useBlockProps.save( { className: 'aa-chapter' } );
	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'aa-chapter-body',
	} );

	return (
		<div { ...blockProps }>
			<p className="aa-chapter-tag">
				<RichText.Content
					tagName="span"
					className="aa-chapter-label"
					value={ attributes.label }
				/>
			</p>
			<div { ...innerBlocksProps } />
		</div>
	);
}
