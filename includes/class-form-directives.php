<?php
/**
 * Interactivity directives of the form controls, added on render.
 *
 * The blocks save plain HTML (elements, ids, names, hidden/disabled, options);
 * the data-wp-* directives their view scripts need are set here, from the
 * block attributes, so the post content and the form template stay readable.
 * WordPress processes the directives after render_block (WP_Block::render),
 * so they work as if they had been saved. The application form's own
 * directives are added by Form_Block.
 *
 * Each method walks the block's HTML in document order and sets, on each
 * element, the directives the store expects (src/form/form-address/view.ts,
 * src/form/form-control-br-revenue-id/view.ts, src/form/form-control/view.ts,
 * src/form/form/view.ts).
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the Interactivity directives to the form controls on render.
 */
final class Form_Directives {

	/** Reseller search: post type (src/form/form-control-reseller/constants.ts). */
	const RESELLER_POST_TYPE = 'revendas';

	/** Reseller search: label of each result. */
	const RESELLER_TEMPLATE = '[post_title] · [tax:estado:slug:uppercase] [tax:cidade]';

	/** UF codes of the custom-store panel (the reseller's own UF and city). */
	/**
	 * Autocomplete for the city comboboxes. A token Chrome does not know
	 * ("aa-city-search") kept its address autofill away, but it is not a valid
	 * value: Lighthouse fails autocomplete-valid and the agent accessibility
	 * tree. So the valid "off".
	 */
	const NO_AUTOFILL = 'off';

	const UF_CODES = array( 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO' );

	/** CPF/CNPJ placeholders by fixed type (src/form/form-control-br-revenue-id/document.ts). */
	const DOCUMENT_PLACEHOLDERS = array(
		'cpf'  => '000.000.000-00',
		'cnpj' => '00.000.000/0000-00',
		''     => '000.000.000-00 / 00.000.000/0000-00',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Form_Directives|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Form_Directives
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
		$map = array(
			'axell/form-control-state'                => 'state',
			'axell/form-control-city'                 => 'city',
			'axell/form-control-postal'               => 'postal',
			'axell/form-control-phone'                => 'phone',
			'axell/form-control-country'              => 'country',
			'axell/form-control-br-revenue-id'        => 'document',
			'axell/form-control-br-revenue-id-legal'  => 'document',
			'axell/form-control-br-revenue-id-person' => 'document',
			'axell/form-control-reseller'             => 'autocomplete',
			'axell/form-control'                      => 'autocomplete',
			'axell/form-submission-notification'      => 'notification',
		);
		foreach ( $map as $block_name => $method ) {
			add_filter( 'render_block_' . $block_name, array( $this, $method ), 10, 2 );
		}
	}

	/**
	 * JSON for a data-wp-context, as JSON.stringify writes it.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return string
	 */
	private static function json( $context ) {
		return (string) wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Set several attributes on the current tag.
	 *
	 * @param \WP_HTML_Tag_Processor $p     Processor on the tag.
	 * @param array<string,string>   $attrs Attributes.
	 */
	private static function set( $p, $attrs ) {
		foreach ( $attrs as $name => $value ) {
			$p->set_attribute( $name, $value );
		}
	}

	/**
	 * How an address control finds its country: the chosen one (upper case,
	 * Brazil when the block stores none) or the linked field.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return array<string,string>
	 */
	private static function country_link( $attrs ) {
		if ( 'select' === ( $attrs['countrySource'] ?? 'field' ) ) {
			return array( 'fixedCountry' => strtoupper( (string) ( $attrs['country'] ?? 'BR' ) ) );
		}
		$field = (string) ( $attrs['countryField'] ?? '' );
		return array( 'countryField' => '' !== $field ? $field : 'country' );
	}

	/**
	 * Make the block's root (wrapper or field) a region of the axell/address store.
	 *
	 * @param \WP_HTML_Tag_Processor $p       Processor, before the root.
	 * @param array<string,mixed>    $context Context besides the form id.
	 * @return bool Whether the wrapper was found.
	 */
	private static function address_region( $p, $context ) {
		// The block's root: a wrapper div, or the field itself (single-field
		// controls save no wrapper).
		if ( ! $p->next_tag() ) {
			return false;
		}
		self::set(
			$p,
			array(
				'data-wp-interactive'  => 'axell/address',
				'data-wp-context'      => self::json( array_merge( array( 'form' => '' ), $context ) ),
				'data-wp-init--region' => 'callbacks.initRegion',
			)
		);
		return true;
	}

	/**
	 * Move to the control's field: the root itself when the block saves no
	 * wrapper, else the first such tag inside the wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor $p   Processor on the block's root.
	 * @param string                 $tag Field tag (INPUT or SELECT).
	 * @return bool Whether the field was found.
	 */
	private static function to_field( $p, $tag ) {
		if ( 'DIV' !== $p->get_tag() ) {
			return true;
		}
		return $p->next_tag( array( 'tag_name' => $tag ) );
	}

	/**
	 * UF: a select for countries with a state list, a text field otherwise.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function state( $content, $block ) {
		$attrs   = $block['attrs'] ?? array();
		$context = self::country_link( $attrs );

		// Saved as the field only: build the widget around it.
		$field = new \WP_HTML_Tag_Processor( $content );
		if ( $field->next_tag() && 'SELECT' === $field->get_tag() ) {
			return self::state_widget( $field, $attrs, $context );
		}

		// Content saved with the whole widget: only the directives are added.
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, $context ) ) {
			return $content;
		}
		while ( $p->next_tag() ) {
			if ( 'SELECT' === $p->get_tag() ) {
				self::set(
					$p,
					array(
						'data-wp-bind--hidden'   => '!state.hasStateList',
						'data-wp-bind--disabled' => '!state.hasStateList',
						'data-wp-on--change'     => 'actions.onState',
						'data-wp-watch'          => 'callbacks.renderStates',
					)
				);
			} elseif ( 'INPUT' === $p->get_tag() ) {
				self::set(
					$p,
					array(
						'data-wp-bind--hidden'   => 'state.hasStateList',
						'data-wp-bind--disabled' => 'state.hasStateList',
						'data-wp-on--input'      => 'actions.onField',
					)
				);
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * City: a search over the cities of the state, or a list; free text when
	 * the country has no cities list.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function city( $content, $block ) {
		$attrs      = $block['attrs'] ?? array();
		$searchable = ! empty( $attrs['searchable'] );
		$context    = array_merge(
			self::country_link( $attrs ),
			array( 'stateField' => '' !== (string) ( $attrs['stateField'] ?? '' ) ? (string) $attrs['stateField'] : 'state' )
		);
		if ( $searchable ) {
			$context = array_merge(
				$context,
				array(
					'query'  => '',
					'typed'  => '',
					'code'   => '',
					'open'   => false,
					'active' => -1,
				)
			);
		}

		// Saved as the field only: build the search (or the list) around it.
		$field = new \WP_HTML_Tag_Processor( $content );
		if ( $field->next_tag() && 'INPUT' === $field->get_tag() ) {
			return self::city_widget( $field, $attrs, $context, $searchable );
		}

		// Content saved with the whole widget: only the directives are added.
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, $context ) ) {
			return $content;
		}
		if ( $searchable ) {
			$p->set_attribute( 'data-wp-on--focusout', 'actions.onCityFocusOut' );
		}

		$free_text = array(
			'data-wp-bind--hidden'   => 'state.hasCityList',
			'data-wp-bind--disabled' => 'state.hasCityList',
			'data-wp-on--input'      => 'actions.onField',
		);
		while ( $p->next_tag() ) {
			$tag = $p->get_tag();
			if ( 'INPUT' === $tag && 'combobox' === $p->get_attribute( 'role' ) ) {
				self::set(
					$p,
					array(
						'data-wp-bind--hidden'        => '!state.hasCityList',
						'data-wp-bind--disabled'      => '!state.hasCityList',
						'data-wp-bind--value'         => 'context.query',
						'data-wp-bind--aria-expanded' => 'context.open',
						'data-wp-on--input'           => 'actions.onCitySearch',
						'data-wp-on--keydown'         => 'actions.onCityKeydown',
					)
				);
			} elseif ( 'INPUT' === $tag && 'hidden' === $p->get_attribute( 'type' ) ) {
				self::set(
					$p,
					array(
						'data-wp-bind--disabled' => '!state.hasCityList',
						'data-wp-bind--value'    => 'context.code',
					)
				);
			} elseif ( 'INPUT' === $tag ) {
				self::set( $p, $free_text );
			} elseif ( 'SELECT' === $tag ) {
				self::set(
					$p,
					array(
						'data-wp-bind--hidden'   => '!state.hasCityList',
						'data-wp-bind--disabled' => '!state.hasCityList',
						'data-wp-on--change'     => 'actions.onField',
						'data-wp-watch'          => 'callbacks.renderCities',
					)
				);
			} elseif ( 'UL' === $tag ) {
				self::set(
					$p,
					array(
						// Never a Tab stop (it scrolls, so Chrome would make it one).
						'tabindex'              => '-1',
						'data-wp-bind--hidden'  => '!state.cityListOpen',
						'data-wp-on--click'     => 'actions.pickCity',
						'data-wp-on--mousedown' => 'actions.keepFocus',
						'data-wp-watch'         => 'callbacks.renderCitySearch',
					)
				);
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * Postal code: masked by country.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function postal( $content, $block ) {
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, self::country_link( $block['attrs'] ?? array() ) ) ) {
			return $content;
		}
		if ( self::to_field( $p, 'INPUT' ) ) {
			$p->set_attribute( 'data-wp-on--input', 'actions.onPostalInput' );
			// Browser autofill of the address (the block has no setting for it).
			if ( null === $p->get_attribute( 'autocomplete' ) ) {
				$p->set_attribute( 'autocomplete', 'postal-code' );
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * Phone: masked by country; in Brazil, by line type too.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function phone( $content, $block ) {
		$attrs   = $block['attrs'] ?? array();
		$context = self::country_link( $attrs );
		$line    = (string) ( $attrs['lineType'] ?? '' );
		if ( '' !== $line && 'both' !== $line ) {
			$context['lineType'] = $line;
		}

		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, $context ) ) {
			return $content;
		}
		if ( self::to_field( $p, 'INPUT' ) ) {
			$p->set_attribute( 'data-wp-on--input', 'actions.onPhoneInput' );
		}
		return $p->get_updated_html();
	}

	/**
	 * Country: a select, or a hidden field with a fixed country.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function country( $content, $block ) {
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, array() ) ) {
			return $content;
		}
		// The field is the root (no wrapper), or inside an older wrapper.
		$is_root = 'DIV' !== $p->get_tag();
		while ( $is_root || $p->next_tag() ) {
			$is_root = false;
			if ( 'SELECT' === $p->get_tag() ) {
				self::set(
					$p,
					array(
						'data-wp-init'       => 'callbacks.initCountry',
						'data-wp-on--change' => 'actions.onField',
					)
				);
			} elseif ( 'INPUT' === $p->get_tag() ) {
				$p->set_attribute( 'data-wp-init', 'callbacks.initCountry' );
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * Attributes of an address region wrapper (the axell/address store).
	 *
	 * @param string              $classes Wrapper classes.
	 * @param array<string,mixed> $context Context besides the form id.
	 * @param string              $extra   More attributes (already escaped).
	 * @return string Opening tag.
	 */
	private static function region_open( $classes, $context, $extra = '' ) {
		return sprintf(
			'<div class="%1$s" data-wp-interactive="axell/address" data-wp-context="%2$s" data-wp-init--region="callbacks.initRegion"%3$s>',
			esc_attr( $classes ),
			esc_attr( self::json( array_merge( array( 'form' => '' ), $context ) ) ),
			$extra
		);
	}

	/**
	 * Wrapper class of a field-only save: the field's block class + -wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor $p Processor on the saved field.
	 * @return string
	 */
	private static function wrapper_class( $p ) {
		$classes = preg_split( '/\s+/', trim( (string) $p->get_attribute( 'class' ) ) );
		return ( $classes[0] ?? 'wp-block-axell-form-control' ) . '-wrapper';
	}

	/**
	 * The saved field's look (block class and inline style, from the block
	 * supports) as attributes for the fields the render builds next to it.
	 *
	 * @param \WP_HTML_Tag_Processor $p Processor on the saved field.
	 * @return string Escaped class (and style) attributes.
	 */
	private static function look( $p ) {
		$style = (string) $p->get_attribute( 'style' );
		return sprintf( ' class="%s"', esc_attr( (string) $p->get_attribute( 'class' ) ) )
			. ( '' !== $style ? sprintf( ' style="%s"', esc_attr( $style ) ) : '' );
	}

	/**
	 * Field name of a control: its name attribute, else its id.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return string
	 */
	private static function field_name( $attrs ) {
		$name = (string) ( $attrs['name'] ?? '' );
		return '' !== $name ? $name : (string) ( $attrs['id'] ?? '' );
	}

	/**
	 * UF widget around the saved select: the select (states of the country,
	 * shown when it has a list) and a free-text input for other countries.
	 *
	 * @param \WP_HTML_Tag_Processor $p       Processor on the saved <select>.
	 * @param array<string,mixed>    $attrs   Block attributes.
	 * @param array<string,mixed>    $context Region context.
	 * @return string
	 */
	private static function state_widget( $p, $attrs, $context ) {
		$wrapper     = self::wrapper_class( $p );
		$id          = (string) $p->get_attribute( 'id' );
		$name        = self::field_name( $attrs );
		$look        = self::look( $p );
		$required    = null !== $p->get_attribute( 'required' ) ? ' required' : '';
		$placeholder = (string) ( $attrs['placeholder'] ?? '' );

		$options = sprintf( '<option value="">%s</option>', esc_html( '' !== $placeholder ? $placeholder : '—' ) );
		foreach ( self::UF_CODES as $uf ) {
			$options .= sprintf( '<option value="%1$s">%1$s</option>', esc_attr( $uf ) );
		}

		// The id goes to the active field only (the select, or the text field
		// for a country without a state list): no duplicate id in the form.
		// The server knows the list from the chosen country (a linked country
		// field starts as Brazil); callbacks.syncStateIds keeps it on change.
		$has_list            = ! isset( $context['fixedCountry'] ) || in_array( $context['fixedCountry'], array( 'BR', 'US' ), true );
		$context['fieldId']  = $id;
		$context['selectId'] = $has_list ? $id : null;
		$context['textId']   = $has_list ? null : $id;

		return self::region_open( $wrapper, $context, ' data-wp-watch="callbacks.syncStateIds"' )
			. sprintf(
				'<select id="%1$s" name="%2$s"%3$s autocomplete="address-level1" hidden disabled%4$s data-wp-bind--id="context.selectId" data-wp-bind--hidden="!state.hasStateList" data-wp-bind--disabled="!state.hasStateList" data-wp-on--change="actions.onState" data-wp-watch="callbacks.renderStates">%5$s</select>',
				esc_attr( $id ),
				esc_attr( $name ),
				$look,
				$required,
				$options
			)
			. sprintf(
				'<input type="text" name="%2$s"%5$s autocomplete="address-level1"%3$s%4$s data-wp-bind--id="context.textId" data-wp-bind--hidden="state.hasStateList" data-wp-bind--disabled="state.hasStateList" data-wp-on--input="actions.onField"/>',
				esc_attr( $id ),
				esc_attr( $name ),
				'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
				$required,
				$look
			)
			. '</div>';
	}

	/**
	 * Cidade widget around the saved input. Searchable: the input becomes the
	 * combobox over the cities of the state, with the hidden field that is
	 * sent, a free-text input for countries without cities and the list.
	 * Otherwise: a select of the cities, and the input as the free text.
	 *
	 * @param \WP_HTML_Tag_Processor $p          Processor on the saved <input>.
	 * @param array<string,mixed>    $attrs      Block attributes.
	 * @param array<string,mixed>    $context    Region context.
	 * @param bool                   $searchable Search instead of a list.
	 * @return string
	 */
	private static function city_widget( $p, $attrs, $context, $searchable ) {
		$wrapper     = self::wrapper_class( $p );
		$name        = self::field_name( $attrs );
		$required    = null !== $p->get_attribute( 'required' ) ? ' required' : '';
		$placeholder = (string) $p->get_attribute( 'placeholder' );
		$look        = self::look( $p );
		$free_text   = array(
			'data-wp-bind--hidden'   => 'state.hasCityList',
			'data-wp-bind--disabled' => 'state.hasCityList',
			'data-wp-on--input'      => 'actions.onField',
		);

		if ( ! $searchable ) {
			self::set(
				$p,
				array_merge(
					array(
						'name'         => $name,
						'autocomplete' => 'address-level2',
					),
					$free_text
				)
			);
			return self::region_open( $wrapper, $context )
				. sprintf(
					'<select name="%1$s"%3$s autocomplete="address-level2" hidden disabled%2$s data-wp-bind--hidden="!state.hasCityList" data-wp-bind--disabled="!state.hasCityList" data-wp-on--change="actions.onField" data-wp-watch="callbacks.renderCities"><option value="">—</option></select>',
					esc_attr( $name ),
					$required,
					$look
				)
				. trim( $p->get_updated_html() )
				. '</div>';
		}

		$list_id = $name . '-cities';
		self::set(
			$p,
			array(
				'role'                        => 'combobox',
				// A combobox: no browser autofill (see NO_AUTOFILL).
				'autocomplete'                => self::NO_AUTOFILL,
				'aria-autocomplete'           => 'list',
				'aria-controls'               => $list_id,
				'hidden'                      => true,
				'disabled'                    => true,
				'data-wp-bind--hidden'        => '!state.hasCityList',
				'data-wp-bind--disabled'      => '!state.hasCityList',
				'data-wp-bind--value'         => 'context.query',
				'data-wp-bind--aria-expanded' => 'context.open',
				'data-wp-on--input'           => 'actions.onCitySearch',
				'data-wp-on--keydown'         => 'actions.onCityKeydown',
			)
		);

		return self::region_open( $wrapper . ' aa-city-search', $context, ' data-wp-on--focusout="actions.onCityFocusOut"' )
			. trim( $p->get_updated_html() )
			. sprintf( '<input type="hidden" name="%s" disabled data-wp-bind--disabled="!state.hasCityList" data-wp-bind--value="context.code"/>', esc_attr( $name ) )
			. sprintf(
				'<input type="text" name="%1$s"%4$s autocomplete="address-level2"%2$s%3$s data-wp-bind--hidden="state.hasCityList" data-wp-bind--disabled="state.hasCityList" data-wp-on--input="actions.onField"/>',
				esc_attr( $name ),
				'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
				$required,
				$look
			)
			. sprintf(
				'<ul id="%s" role="listbox" hidden tabindex="-1" data-wp-bind--hidden="!state.cityListOpen" data-wp-on--click="actions.pickCity" data-wp-on--mousedown="actions.keepFocus" data-wp-watch="callbacks.renderCitySearch"></ul>',
				esc_attr( $list_id )
			)
			. '</div>';
	}

	/**
	 * CPF/CNPJ: masked and validated; the PF/PJ type comes from a field or is
	 * fixed by the block (person = CPF, legal = CNPJ).
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function document( $content, $block ) {
		$attrs = $block['attrs'] ?? array();
		$fixed = '';
		if ( 'axell/form-control-br-revenue-id-person' === ( $block['blockName'] ?? '' ) ) {
			$fixed = 'cpf';
		} elseif ( 'axell/form-control-br-revenue-id-legal' === ( $block['blockName'] ?? '' ) ) {
			$fixed = 'cnpj';
		}
		$placeholder = (string) ( $attrs['placeholder'] ?? '' );

		$p = new \WP_HTML_Tag_Processor( $content );
		// The block's root: the input itself, or an older wrapper div.
		if ( ! $p->next_tag() ) {
			return $content;
		}
		self::set(
			$p,
			array(
				'data-wp-interactive' => 'axell/document',
				'data-wp-context'     => self::json(
					array(
						'type'        => $fixed,
						'fixed'       => '' !== $fixed,
						'placeholder' => '' !== $placeholder ? $placeholder : self::DOCUMENT_PLACEHOLDERS[ $fixed ],
						'typeField'   => '' !== $fixed ? '' : (string) ( $attrs['typeField'] ?? '' ),
					)
				),
				'data-wp-init'        => 'callbacks.linkType',
			)
		);
		if ( self::to_field( $p, 'INPUT' ) ) {
			self::set(
				$p,
				array(
					'data-wp-bind--placeholder' => 'state.placeholder',
					'data-wp-bind--maxlength'   => 'state.maxLength',
					'data-wp-on--input'         => 'actions.onInput',
					'data-wp-on--blur'          => 'actions.validate',
				)
			);
		}
		return $p->get_updated_html();
	}

	/**
	 * Autocomplete over a post type: the reseller block, and axell/form-control
	 * of type autocomplete.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function autocomplete( $content, $block ) {
		$attrs = $block['attrs'] ?? array();
		if ( 'axell/form-control-reseller' === ( $block['blockName'] ?? '' ) ) {
			$post_type      = self::RESELLER_POST_TYPE;
			$template       = self::RESELLER_TEMPLATE;
			$allow_notfound = (bool) ( $attrs['allowNotFound'] ?? true );
		} elseif ( 'autocomplete' === ( $attrs['type'] ?? '' ) ) {
			$post_type      = (string) ( $attrs['sourcePostType'] ?? '' );
			$template       = (string) ( $attrs['labelTemplate'] ?? '' );
			$template       = '' !== $template ? $template : '[post_title]';
			$allow_notfound = ! empty( $attrs['allowNotFound'] );
		} else {
			return $content;
		}

		$context = array(
			'postType'      => $post_type,
			'template'      => $template,
			'allowNotFound' => $allow_notfound,
			'query'         => '',
			'text'          => '',
			'selectedId'    => '',
			'title'         => '',
			'open'          => false,
			'notFound'      => false,
			'loading'       => false,
			'custom'        => false,
			'customName'    => '',
			'customUf'      => '',
			'customCity'    => '',
			'cityOptions'   => array(),
			'cityQuery'     => '',
			'cityTyped'     => '',
			'cityOpen'      => false,
			'cityActive'    => -1,
			'cityHint'      => __( 'Select state', 'axellcore-atelier' ),
			'activeIndex'   => -1,
			'options'       => array(),
		);

		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! $p->next_tag() ) {
			return $content;
		}
		if ( 'INPUT' === $p->get_tag() ) {
			$name = (string) ( $attrs['name'] ?? '' );
			$name = '' !== $name ? $name : (string) ( $attrs['id'] ?? '' );
			return self::autocomplete_widget( $p, $name, $context );
		}

		// Content saved before the field-only save: the widget is in the
		// markup, only the directives are added.
		self::set(
			$p,
			array(
				'data-wp-interactive'  => 'axell/autocomplete',
				'data-wp-context'      => self::json( $context ),
				'data-wp-on--keydown'  => 'actions.onKeydown',
				'data-wp-on--focusout' => 'actions.onFocusOut',
				'data-wp-init--reset'  => 'callbacks.watchReset',
			)
		);

		$hidden = 0;
		while ( $p->next_tag( array( 'tag_closers' => 'skip' ) ) ) {
			$tag = $p->get_tag();
			if ( 'INPUT' === $tag && 'combobox' === $p->get_attribute( 'role' ) ) {
				self::set(
					$p,
					array(
						'data-wp-bind--hidden'        => 'context.custom',
						'data-wp-bind--value'         => 'context.text',
						'data-wp-bind--aria-expanded' => 'context.open',
						'data-wp-on--input'           => 'actions.onInput',
					)
				);
			} elseif ( 'INPUT' === $tag && 'hidden' === $p->get_attribute( 'type' ) ) {
				// The chosen post's ID, then its title.
				$p->set_attribute( 'data-wp-bind--value', 0 === $hidden++ ? 'context.selectedId' : 'context.title' );
			} elseif ( 'DIV' === $tag && $p->has_class( 'aa-ac-custom' ) ) {
				$p->set_attribute( 'data-wp-bind--hidden', '!context.custom' );
			} elseif ( 'INPUT' === $tag && 'name' === $p->get_attribute( 'data-field' ) ) {
				self::set(
					$p,
					array(
						'data-wp-bind--value' => 'context.customName',
						'data-wp-on--input'   => 'actions.onCustomInput',
					)
				);
			} elseif ( 'BUTTON' === $tag && $p->has_class( 'aa-ac-back' ) ) {
				$p->set_attribute( 'data-wp-on--click', 'actions.backToSearch' );
			} elseif ( 'SELECT' === $tag && 'city' === $p->get_attribute( 'data-field' ) ) {
				self::set(
					$p,
					array(
						'data-wp-bind--disabled' => '!context.customUf',
						'data-wp-on--change'     => 'actions.onCustomCity',
						'data-wp-watch'          => 'callbacks.renderCities',
					)
				);
			} elseif ( 'SELECT' === $tag ) {
				self::set(
					$p,
					array(
						'data-wp-bind--value' => 'context.customUf',
						'data-wp-on--change'  => 'actions.onCustomUf',
					)
				);
			} elseif ( 'UL' === $tag ) {
				self::set(
					$p,
					array(
						// Never a Tab stop (it scrolls, so Chrome would make it one).
						'tabindex'              => '-1',
						'data-wp-bind--hidden'  => '!context.open',
						'data-wp-on--click'     => 'actions.pick',
						'data-wp-on--mousedown' => 'actions.keepFocus',
						'data-wp-watch'         => 'callbacks.renderList',
					)
				);
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * The autocomplete widget around the saved search field: the region
	 * wrapper, the field as a combobox, the hidden ID and title fields, the
	 * custom-store panel (name, UF, city) and the suggestion list, with the
	 * axell/autocomplete directives (src/form/form-control/view.ts).
	 *
	 * @param \WP_HTML_Tag_Processor $p       Processor on the saved <input>.
	 * @param string                 $name    Field name (the hidden ID's name).
	 * @param array<string,mixed>    $context Initial context.
	 * @return string
	 */
	private static function autocomplete_widget( $p, $name, $context ) {
		$list_id = $name . '-list';
		$classes = preg_split( '/\s+/', trim( (string) $p->get_attribute( 'class' ) ) );
		$wrapper = ( $classes[0] ?? 'wp-block-axell-form-control' ) . '-wrapper';

		self::set(
			$p,
			array(
				'role'                        => 'combobox',
				'aria-autocomplete'           => 'list',
				'aria-controls'               => $list_id,
				'data-wp-bind--hidden'        => 'context.custom',
				'data-wp-bind--value'         => 'context.text',
				'data-wp-bind--aria-expanded' => 'context.open',
				'data-wp-on--input'           => 'actions.onInput',
			)
		);
		$field = trim( $p->get_updated_html() );

		$uf_options = '<option value="">' . esc_html__( 'State code', 'axellcore-atelier' ) . '</option>';
		foreach ( self::UF_CODES as $uf ) {
			$uf_options .= sprintf( '<option value="%1$s">%1$s</option>', esc_attr( $uf ) );
		}
		$search_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>';

		return sprintf(
			'<div class="%1$s" data-wp-interactive="axell/autocomplete" data-wp-context="%2$s" data-wp-on--keydown="actions.onKeydown" data-wp-on--focusout="actions.onFocusOut" data-wp-init--reset="callbacks.watchReset">'
				. '%3$s'
				. '<input type="hidden" name="%4$s" data-wp-bind--value="context.selectedId"/>'
				. '<input type="hidden" name="%4$s_title" data-wp-bind--value="context.title"/>'
				. '<div class="aa-ac-custom" hidden data-wp-bind--hidden="!context.custom">'
				// The back button comes before the name in the Tab order (it is
				// placed over the name's end by CSS): Tab from the name goes to the UF.
				. '<div class="aa-ac-name"><button type="button" class="aa-ac-back" aria-label="%6$s" data-wp-on--click="actions.backToSearch">%7$s</button>'
				. '<input type="text" id="%4$s-custom-store" data-field="name" aria-label="%5$s" placeholder="%5$s" autocomplete="off" data-wp-bind--value="context.customName" data-wp-on--input="actions.onCustomInput"/></div>'
				. '%14$s'
				// The city: a combobox over the UF's cities, as the address city.
				. '<div class="aa-ac-city" data-wp-on--focusout="actions.onCustomCityFocusOut">'
				. '<input type="text" id="%4$s-custom-city" data-field="city" role="combobox" aria-label="%10$s" placeholder="%11$s" aria-autocomplete="list" aria-controls="%4$s-custom-cities" aria-expanded="false" autocomplete="%13$s" disabled data-wp-bind--disabled="!context.customUf" data-wp-bind--placeholder="context.cityHint" data-wp-bind--value="context.cityQuery" data-wp-bind--aria-expanded="context.cityOpen" data-wp-on--input="actions.onCustomCitySearch" data-wp-on--keydown="actions.onCustomCityKeydown"/>'
				. '<ul id="%4$s-custom-cities" role="listbox" hidden tabindex="-1" data-wp-bind--hidden="!context.cityOpen" data-wp-on--click="actions.pickCustomCity" data-wp-on--mousedown="actions.keepFocus" data-wp-watch="callbacks.renderCustomCities"></ul>'
				. '</div>'
				. '</div>'
				. '<ul id="%12$s" role="listbox" hidden tabindex="-1" data-wp-bind--hidden="!context.open" data-wp-on--click="actions.pick" data-wp-on--mousedown="actions.keepFocus" data-wp-watch="callbacks.renderList"></ul>'
				. '</div>',
			esc_attr( $wrapper ),
			esc_attr( self::json( $context ) ),
			$field,
			esc_attr( $name ),
			// "Nome da loja", and an id without "name": with a plain "Nome",
			// Chrome takes it for the person's name and autofills it despite
			// autocomplete="off".
			esc_attr__( 'Store name', 'axellcore-atelier' ),
			esc_attr__( 'Back to search', 'axellcore-atelier' ),
			$search_icon,
			esc_attr__( 'State code', 'axellcore-atelier' ),
			$uf_options,
			esc_attr__( 'City', 'axellcore-atelier' ),
			esc_html__( 'Select state', 'axellcore-atelier' ),
			esc_attr( $list_id ),
			esc_attr( self::NO_AUTOFILL ),
			// The UF: a list with a filter, "SP · São Paulo" (Enhanced_Select).
			Enhanced_Select::wrap(
				sprintf(
					'<select id="%1$s-custom-uf" aria-label="%2$s" data-wp-bind--value="context.customUf" data-wp-on--change="actions.onCustomUf">%3$s</select>',
					esc_attr( $name ),
					esc_attr__( 'State code', 'axellcore-atelier' ),
					$uf_options
				),
				array(
					'search' => true,
					'kind'   => 'uf',
				),
				'axell/autocomplete'
			)
		);
	}

	/**
	 * Submission notice: shown by the form store for its result.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function notification( $content, $block ) {
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! $p->next_tag( array( 'tag_name' => 'DIV' ) ) ) {
			return $content;
		}
		$type = 'error' === ( $block['attrs']['type'] ?? 'success' ) ? 'error' : 'success';
		$p->set_attribute( 'data-wp-bind--hidden', 'error' === $type ? '!state.isError' : '!state.isSuccess' );
		return $p->get_updated_html();
	}
}
