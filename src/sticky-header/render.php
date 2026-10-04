<?php
/**
 * Front-end markup for axell/sticky-header.
 *
 * The header stays fixed at the top of the viewport. The Interactivity API
 * store in view.ts sets context.scrolled, which toggles `is-scrolled` on the
 * wrapper (compact padding and background, see style.scss). The class is
 * also set on load, so a page opened already scrolled starts in the right state.
 *
 * @package Axellcore_Atelierclub
 *
 * @var string $content Already-rendered inner blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'data-wp-interactive'        => 'axell/sticky-header',
		'data-wp-context'            => '{"scrolled":false}',
		'data-wp-init'               => 'actions.onScroll',
		'data-wp-class--is-scrolled' => 'context.scrolled',
		'data-wp-on-window--scroll' => 'actions.onScroll',
	)
);

printf( '<header %1$s>%2$s</header>', $wrapper_attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is rendered block HTML.
