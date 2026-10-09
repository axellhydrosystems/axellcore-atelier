<?php
/**
 * No build step (plain browser JS) — see form/index.asset.php's own header
 * comment for why this sidecar is hand-written.
 */
return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	),
	'version'      => AXELLCORE_ATELIER_VERSION,
);
