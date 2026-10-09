import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import type { ChapterAttributes } from './types';

const ALLOWED_BLOCKS = [
	'core/heading',
	'core/paragraph',
	'core/group',
	'core/columns',
	'core/column',
	'core/list',
	'core/list-item',
	'core/buttons',
	'core/button',
	'core/table',
	'core/html',
	'axell/form',
];

/**
 * axellcore/chapter — one numbered chapter within axellcore/chapters.
 *
 * The visible "Capítulo NN" prefix is NEVER stored as text: the number is a
 * CSS counter (.aa-chapters{counter-reset:aa-chapter} /
 * .aa-chapter{counter-increment:aa-chapter} / .aa-chapter-tag::before{
 * content: attr(data-chapter) " " counter(aa-chapter, decimal-leading-zero)
 * " · "} — see assets/css/sections.css), and the word itself ("Chapter" /
 * "Capítulo") is a real, runtime-translated gettext string, never frozen
 * into saved content:
 *  - edit() sets it via __() so the editor canvas matches the site's admin
 *    locale (a block with its own JS save() always renders from edit() in
 *    the canvas — Blocks::render_chapter() is never called there, only on
 *    the actual frontend request).
 *  - save() deliberately does NOT include data-chapter at all — it isn't
 *    something this block should freeze into post_content. The frontend
 *    gets it from Blocks::render_chapter() (includes/class-blocks.php),
 *    which post-processes this same static markup with PHP's __() against
 *    the site's current locale on every request.
 *
 * `label` (the part after "·", e.g. "Manifesto") is stored as a plain
 * string attribute — deliberately NOT rich-text/HTML-sourced; every label
 * here is plain text with no formatting need, so RichText is used purely
 * as an inline-edit widget with allowedFormats:[] and its value stored as
 * an ordinary string.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< ChapterAttributes > ) {
	const blockProps = useBlockProps( { className: 'aa-chapter' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'aa-chapter-body' },
		{ allowedBlocks: ALLOWED_BLOCKS, templateLock: false }
	);

	return (
		<div { ...blockProps }>
			<p
				className="aa-chapter-tag"
				data-chapter={ __( 'Chapter', 'axellcore-atelier' ) }
			>
				<RichText
					tagName="span"
					className="aa-chapter-label"
					value={ attributes.label }
					onChange={ ( label: string ) => setAttributes( { label } ) }
					allowedFormats={ [] }
					placeholder={ __(
						'Chapter name…',
						'axellcore-atelier'
					) }
				/>
			</p>
			<div { ...innerBlocksProps } />
		</div>
	);
}
