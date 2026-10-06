/**
 * Inline icon: a rich text format, like core's Inline image, that inserts any
 * registered icon (Icons API) into text (buttons, paragraphs, headings…).
 * Rich text objects are void elements, so it is saved like core's inline
 * image, as a short placeholder: `<img class="aa-inline-icon"
 * data-icon="collection/name" alt="" src="…">`, whose src is a transparent
 * pixel (see placeholder.ts) so the editor shows no broken image. includes/class-inline-icon.php renders it as
 * the icon's <svg> in the text color on the front end; in the editor a
 * generated mask stylesheet draws it the same way.
 */
import { __ } from '@wordpress/i18n';
import { registerFormatType, insertObject } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import IconPicker from './icon-picker';
import { PLACEHOLDER_SRC } from './placeholder';
import './style.scss';

export const NAME = 'axell/inline-icon';

type EditProps = {
	value: Parameters< typeof insertObject >[ 0 ];
	onChange: ( value: Parameters< typeof insertObject >[ 0 ] ) => void;
	isObjectActive: boolean;
};

/**
 * "Inline icon" in the rich text toolbar's More menu.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.isObjectActive
 */
function Edit( { value, onChange, isObjectActive }: EditProps ) {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<>
			<RichTextToolbarButton
				icon="star-filled"
				title={ __( 'Inline icon', 'axellcore-atelierclub' ) }
				onClick={ () => setIsOpen( true ) }
				isActive={ isObjectActive }
			/>
			{ isOpen && (
				<IconPicker
					onClose={ () => setIsOpen( false ) }
					onSelect={ ( icon: string ) => {
						onChange(
							insertObject( value, {
								type: NAME,
								attributes: {
									icon,
									alt: '',
									url: PLACEHOLDER_SRC,
								},
							} )
						);
						setIsOpen( false );
					} }
				/>
			) }
		</>
	);
}

registerFormatType( NAME, {
	title: __( 'Inline icon', 'axellcore-atelierclub' ),
	tagName: 'img',
	className: 'aa-inline-icon',
	object: true,
	attributes: { icon: 'data-icon', alt: 'alt', url: 'src' },
	edit: Edit,
} as unknown as Parameters< typeof registerFormatType >[ 1 ] );
