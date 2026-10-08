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

		public function get_error_message( $code = '' ) {
			if ( '' !== $code && $code !== $this->code ) {
				return $this->errors[ $code ] ?? '';
			}
			return $this->message;
		}

		/** @var array<string,string> Codes added after the first. */
		public $errors = array();

		public function add( $code, $message ) {
			if ( '' === $this->code ) {
				$this->code    = $code;
				$this->message = $message;
			} else {
				$this->errors[ $code ] = $message;
			}
		}

		public function has_errors() {
			return '' !== $this->code;
		}

		public function get_error_codes() {
			return '' === $this->code ? array() : array_merge( array( $this->code ), array_keys( $this->errors ) );
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

		public function set_role( $role ) {
			$this->roles = array( $role );
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post for tests that build posts by hand.
	 */
	final class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_type = 'page';
		/** @var string */
		public $post_status = 'publish';
		/** @var string */
		public $post_name = '';
		/** @var int */
		public $post_parent = 0;
		/** @var string */
		public $post_title = '';

		/**
		 * @param array<string,mixed> $fields Properties.
		 */
		public function __construct( array $fields = array() ) {
			foreach ( $fields as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}
