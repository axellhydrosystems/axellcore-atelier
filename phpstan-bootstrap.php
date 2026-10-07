<?php
/**
 * PHPStan bootstrap — defines plugin constants for static analysis.
 * Not loaded at runtime; only used by PHPStan.
 */
define( 'AXELLCORE_ATELIERCLUB_VERSION', '0.0.0' );
define( 'AXELLCORE_ATELIERCLUB_FILE', __DIR__ . '/axellcore-atelierclub.php' );
define( 'AXELLCORE_ATELIERCLUB_PATH', __DIR__ . '/' );
define( 'AXELLCORE_ATELIERCLUB_URL', 'http://example.com/wp-content/plugins/axellcore-atelierclub/' );

// WP 7.1's Icons API (wp-includes/icons.php) isn't in php-stubs/wordpress-stubs
// yet — stub the signatures so PHPStan can see them. Guarded so this file
// stays safe if ever accidentally included somewhere real (it isn't).
if ( ! function_exists( 'wp_register_icon_collection' ) ) {
	function wp_register_icon_collection( string $slug, array $args ): bool {
		return true;
	}
}
if ( ! function_exists( 'wp_register_icon' ) ) {
	function wp_register_icon( string $icon_name, array $args ): bool {
		return true;
	}
}
if ( ! function_exists( 'wp_unregister_icon_collection' ) ) {
	function wp_unregister_icon_collection( string $slug ): bool {
		return true;
	}
}
if ( ! function_exists( 'wp_unregister_icon' ) ) {
	function wp_unregister_icon( string $icon_name ): bool {
		return true;
	}
}
if ( ! function_exists( 'wp_get_icon' ) ) {
	function wp_get_icon( string $name, array $args = array() ): string {
		return '';
	}
}

// SelfDirectory (lib/selfdirectory, a git submodule absent from CI checkouts).
if ( ! function_exists( 'selfd' ) ) {
	function selfd( string $file ): void {} // phpcs:ignore
}
