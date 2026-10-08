<?php
/**
 * Where hooked callbacks come from, to leave other plugins' output out of
 * the plugin's own pages.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Callback source files.
 */
final class Callbacks {

	/**
	 * File a callback is defined in, or '' when it can't be told.
	 *
	 * @param callable|mixed $callback Callback.
	 * @return string Normalized path.
	 */
	public static function file( $callback ) {
		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new \ReflectionMethod( $callback );
			} elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
			} else {
				return '';
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}
		return wp_normalize_path( (string) $reflection->getFileName() );
	}

	/**
	 * Remove the callbacks of a hook that $drop picks by their file.
	 *
	 * @param string   $hook Hook name.
	 * @param callable $drop Receives a callback's file, true to remove it.
	 */
	public static function remove( $hook, callable $drop ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) ) {
			return;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$file = self::file( $callback['function'] );
				if ( '' !== $file && $drop( $file ) ) {
					remove_action( $hook, $callback['function'], $priority );
				}
			}
		}
	}
}
