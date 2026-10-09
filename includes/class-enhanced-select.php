<?php
/**
 * Selects of the forms as the profile's (axell/member-select): a button that
 * shows the choice and opens the options, with a filter over them or not.
 * The native select stays in the form, under the button, and is what is
 * sent and validated (without JavaScript it is the field). Its options are
 * read from it (they may change, as the state's by country) and choosing
 * one sets it and fires its change, so the actions it already had run as
 * before (src/select/view.ts).
 *
 * The state (UF) lists "SP · São Paulo" and its button shows "SP".
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searchable selects of the form blocks.
 */
final class Enhanced_Select {

	/**
	 * The view module and its stylesheet.
	 */
	const HANDLE = 'axellcore-atelier-select';

	/**
	 * Store namespace.
	 */
	const STORE = 'axell/select';

	/**
	 * Singleton instance.
	 *
	 * @var Enhanced_Select|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Enhanced_Select
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks: after Form_Directives (10), which sets the state's.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_filter( 'render_block_axell/form-control', array( $this, 'decorate' ), 20 );
		add_filter( 'render_block_axell/form-control-state', array( $this, 'decorate' ), 20 );
	}

	/**
	 * The view module and its stylesheet, enqueued by the first select.
	 */
	public function register_assets() {
		wp_register_script_module(
			self::HANDLE,
			AXELLCORE_ATELIER_URL . 'build/select/view.js',
			array( array( 'id' => '@wordpress/interactivity' ) ),
			AXELLCORE_ATELIER_VERSION
		);
		wp_register_style( self::HANDLE, AXELLCORE_ATELIER_URL . 'build/select/style-frontend.css', array(), AXELLCORE_ATELIER_VERSION );
	}

	/**
	 * The selects that become searchable, by field name: with a filter or
	 * not, and "uf" for a state.
	 *
	 * @return array<string,array{search:bool,kind:string}>
	 */
	public static function fields() {
		/**
		 * Form selects shown as the profile's: field name => { search, kind }.
		 *
		 * @param array<string,array{search:bool,kind:string}> $fields Defaults: main practice, registration type, state.
		 */
		return (array) apply_filters(
			'axellcore_atelier_enhanced_selects',
			array(
				'primary_focus' => array(
					'search' => true,
					'kind'   => '',
				),
				'profile_type'  => array(
					'search' => false,
					'kind'   => '',
				),
				'state'         => array(
					'search' => true,
					'kind'   => 'uf',
				),
			)
		);
	}

	/**
	 * A form block's select, when its name is one of fields().
	 *
	 * @param string $content Block HTML.
	 * @return string
	 */
	public function decorate( $content ) {
		if ( is_admin() || false === stripos( $content, '<select' ) ) {
			return $content;
		}
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! $p->next_tag( array( 'tag_name' => 'SELECT' ) ) ) {
			return $content;
		}
		$name   = (string) $p->get_attribute( 'name' );
		$fields = self::fields();
		if ( ! isset( $fields[ $name ] ) ) {
			return $content;
		}
		// The state's select lives in the address region: its directives keep it.
		$store_ns = 'state' === $name ? 'axell/address' : '';
		return self::wrap_in( $content, (array) $fields[ $name ], $store_ns );
	}

	/**
	 * Wrap the first select of an HTML in the searchable select.
	 *
	 * @param string $html      HTML with a select (and what is around it).
	 * @param array  $args      { search: bool, kind: string ('uf' or '') }.
	 * @param string $store_ns Store of the select's own directives (they
	 *                          keep it inside the new region); '' for none.
	 * @return string
	 */
	public static function wrap_in( $html, array $args, $store_ns = '' ) {
		$start = stripos( $html, '<select' );
		$end   = false === $start ? false : stripos( $html, '</select>', $start );
		if ( false === $end ) {
			return $html;
		}
		$end   += strlen( '</select>' );
		$select = substr( $html, $start, $end - $start );
		return substr( $html, 0, $start ) . self::wrap( $select, $args, $store_ns ) . substr( $html, $end );
	}

	/**
	 * A select as the searchable select: the region, the native select (its
	 * directives with their namespace), the button and the options.
	 *
	 * @param string $select    The select's HTML.
	 * @param array  $args      { search: bool, kind: string }.
	 * @param string $store_ns Store of the select's own directives.
	 * @return string
	 */
	public static function wrap( $select, array $args, $store_ns = '' ) {
		$search = ! empty( $args['search'] );
		$kind   = (string) ( $args['kind'] ?? '' );

		$p = new \WP_HTML_Tag_Processor( $select );
		if ( ! $p->next_tag( array( 'tag_name' => 'SELECT' ) ) ) {
			return $select;
		}
		$id = (string) $p->get_attribute( 'id' );
		if ( '' === $id ) {
			$id = 'aa-select-' . wp_unique_id();
			$p->set_attribute( 'id', $id );
		}
		$class = (string) $p->get_attribute( 'class' );
		$style = (string) $p->get_attribute( 'style' );
		if ( '' !== $store_ns ) {
			foreach ( (array) $p->get_attribute_names_with_prefix( 'data-wp-' ) as $attr ) {
				$value = $p->get_attribute( $attr );
				if ( is_string( $value ) && 'data-wp-interactive' !== $attr ) {
					$p->set_attribute( $attr, self::with_namespace( $value, $store_ns ) );
				}
			}
		}
		$p->set_attribute( 'tabindex', '-1' );

		self::enqueue();

		$context = array(
			'search'      => $search,
			'kind'        => $kind,
			'listId'      => $id . '-options',
			'items'       => array(),
			'value'       => '',
			'placeholder' => '',
			'query'       => '',
			'open'        => false,
			'active'      => -1,
			'ready'       => false,
			'hidden'      => false,
			'disabled'    => false,
		);
		$filter  = $search
			? sprintf(
				'<input type="search" class="aa-select__search" data-field="search" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="%1$s-options" aria-label="%2$s" placeholder="%2$s" autocomplete="off" data-wp-bind--value="context.query" data-wp-bind--aria-activedescendant="state.activeId" data-wp-on--input="actions.onSearch"/>',
				esc_attr( $id ),
				esc_attr__( 'Search', 'axellcore-atelier' )
			)
			: '';

		return sprintf(
			'<div class="aa-select%1$s" data-wp-interactive="%2$s" data-wp-context="%3$s" data-wp-init="callbacks.init" data-wp-class--is-ready="context.ready" data-wp-bind--hidden="context.hidden" data-wp-on--focusout="actions.onFocusOut" data-wp-on--keydown="actions.onKeydown">'
				. '%4$s'
				. '<button type="button" class="aa-select__toggle %5$s"%6$s id="%7$s-toggle" hidden aria-haspopup="listbox" aria-expanded="false" aria-controls="%7$s-options" data-wp-bind--hidden="!context.ready" data-wp-bind--disabled="context.disabled" data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggle">'
				. '<span class="aa-select__value" data-wp-class--is-placeholder="state.isPlaceholder" data-wp-text="state.shown"></span></button>'
				. '<div class="aa-select__popup" hidden data-wp-bind--hidden="!context.open">%8$s'
				. '<ul id="%7$s-options" role="listbox" tabindex="-1"%9$s>'
				. '<template data-wp-each--item="state.filtered" data-wp-each-key="context.item.value"><li role="option" data-wp-bind--id="state.itemId" data-wp-bind--aria-selected="state.isActive" data-wp-class--is-current="state.isCurrent" data-wp-on--mousedown="actions.pick">'
				. '<span class="aa-select__code" data-wp-bind--hidden="!state.isUf" data-wp-text="context.item.value"></span><span class="aa-select__dot" aria-hidden="true" data-wp-bind--hidden="!state.isUf"> · </span><span data-wp-text="state.itemLabel"></span></li></template>'
				. '<li class="aa-select__none" role="presentation" hidden data-wp-bind--hidden="state.hasResults">%10$s</li>'
				. '</ul></div></div>',
			'uf' === $kind ? ' aa-select--uf' : '',
			esc_attr( self::STORE ),
			esc_attr( (string) wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			$p->get_updated_html(),
			esc_attr( $class ),
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
			esc_attr( $id ),
			$filter, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
			$search ? '' : ' data-wp-bind--aria-activedescendant="state.activeId"',
			esc_html__( 'No matches found', 'axellcore-atelier' )
		);
	}

	/**
	 * A directive's value with a store namespace ("!state.x" →
	 * "axell/address::!state.x": the namespace comes first, the negation is
	 * part of the path); one that has a namespace stays.
	 *
	 * @param string $value     Directive value.
	 * @param string $store_ns Store namespace.
	 * @return string
	 */
	public static function with_namespace( $value, $store_ns ) {
		if ( '' === $value || false !== strpos( $value, '::' ) || '{' === $value[0] || '[' === $value[0] ) {
			return $value;
		}
		return $store_ns . '::' . $value;
	}

	/**
	 * The module, its stylesheet and the states' names (for "SP · São Paulo").
	 */
	private static function enqueue() {
		wp_enqueue_script_module( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		wp_interactivity_state(
			self::STORE,
			array(
				'ufNames' => Locations::instance()->states(),
			)
		);
	}
}
