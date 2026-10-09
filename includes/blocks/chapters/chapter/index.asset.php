<?php
/**
 * No build step (plain browser JS, no @wordpress/scripts webpack pass), so
 * this hand-written .asset.php stands in for the usual generated sidecar —
 * WP's register_block_script_handle() only auto-wires script dependencies
 * when one is present (see wp-includes/blocks.php), and only sets up
 * translations (wp_set_script_translations) when 'wp-i18n' is listed here.
 */
return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
	'version'      => AXELLCORE_ATELIER_VERSION,
);
