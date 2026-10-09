<?php
/**
 * Allows the form markup of the application page through wp_kses, so the
 * page content keeps its fields when saved without an unfiltered_html user
 * (e.g. from WP-CLI or a REST write by an editor).
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the form elements and attributes used by the application form.
 */
final class Kses {

	/**
	 * Singleton instance.
	 *
	 * @var Kses|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Kses
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_form_markup' ), 10, 2 );
	}

	/**
	 * Add the form tags and attributes to the post context.
	 *
	 * @param array  $tags    Allowed tags.
	 * @param string $context Kses context.
	 * @return array
	 */
	public function allow_form_markup( $tags, $context ) {
		if ( 'post' !== $context ) {
			return $tags;
		}

		$common = array(
			'id'     => true,
			'class'  => true,
			'style'  => true,
			'hidden' => true,
			'role'   => true,
			'data-*' => true,
			'aria-*' => true,
		);

		$tags['form']     = array_merge(
			$common,
			array(
				'novalidate' => true,
				'method'     => true,
				'action'     => true,
			)
		);
		$tags['input']    = array_merge(
			$common,
			array(
				'type'         => true,
				'name'         => true,
				'value'        => true,
				'placeholder'  => true,
				'required'     => true,
				'checked'      => true,
				'disabled'     => true,
				'autocomplete' => true,
				'maxlength'    => true,
			)
		);
		$tags['select']   = array_merge(
			$common,
			array(
				'name'         => true,
				'required'     => true,
				'disabled'     => true,
				'autocomplete' => true,
			)
		);
		$tags['option']   = array_merge(
			$common,
			array(
				'value'    => true,
				'selected' => true,
			)
		);
		$tags['textarea'] = array_merge(
			$common,
			array(
				'name'        => true,
				'placeholder' => true,
				'required'    => true,
				'rows'        => true,
			)
		);
		$tags['label']    = array_merge( $common, array( 'for' => true ) );
		$tags['fieldset'] = $common;
		$tags['legend']   = $common;
		$tags['ul']       = $common;
		$tags['ol']       = array_merge( $common, array( 'type' => true ) );
		$tags['li']       = $common;
		$tags['button']   = array_merge(
			$common,
			array(
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'disabled' => true,
			)
		);

		return $tags;
	}
}
