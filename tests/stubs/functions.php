<?php
/**
 * Minimal WordPress function stubs for PHPUnit.
 *
 * This file is required (not eval'd) so Patchwork can intercept these
 * functions and Brain\Monkey can mock them per test.
 *
 * add_action / add_filter / remove_action / remove_filter are intentionally
 * absent — Brain\Monkey\setUp() loads its own wp-hook-functions.php, which
 * provides them with full hook-tracking support. Defining them here would
 * silently block that file (it uses function_exists() guards).
 *
 * @package Axellcore_Atelierclub\Tests
 */

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ) {
		return false;
	}
}

if ( ! function_exists( 'register_block_template' ) ) {
	function register_block_template( $template_name, $args = array() ) {
		return null;
	}
}

if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
	function register_block_type_from_metadata( $path, $args = array() ) {
		return null;
	}
}

if ( ! function_exists( 'register_block_style' ) ) {
	function register_block_style( $block_name, $style_properties ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {}
}

if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		return false;
	}
}

if ( ! function_exists( 'wp_is_block_theme' ) ) {
	function wp_is_block_theme() {
		return true;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data() {
			return $this->data;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public $ID              = 0;
		public $roles           = array();
		public $user_login      = '';
		public $user_email      = '';
		public $user_url        = '';
		public $user_registered = '';
		public $display_name    = '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}
