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
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

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
		return array( 'countryField' => '' !== $field ? $field : 'pais' );
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
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! self::address_region( $p, self::country_link( $block['attrs'] ?? array() ) ) ) {
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
			array( 'stateField' => '' !== (string) ( $attrs['stateField'] ?? '' ) ? (string) $attrs['stateField'] : 'uf' )
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

		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! $p->next_tag( array( 'tag_name' => 'DIV' ) ) ) {
			return $content;
		}
		self::set(
			$p,
			array(
				'data-wp-interactive'  => 'axell/autocomplete',
				'data-wp-context'      => self::json(
					array(
						'postType'      => $post_type,
						'template'      => $template,
						'allowNotFound' => $allow_notfound,
						'query'         => '',
						'text'          => '',
						'selectedId'    => '',
						'titulo'        => '',
						'open'          => false,
						'notFound'      => false,
						'loading'       => false,
						'custom'        => false,
						'customName'    => '',
						'customUf'      => '',
						'customCity'    => '',
						'cityOptions'   => array(),
						'activeIndex'   => -1,
						'options'       => array(),
					)
				),
				'data-wp-on--keydown'  => 'actions.onKeydown',
				'data-wp-on--focusout' => 'actions.onFocusOut',
			)
		);

		$hidden = 0;
		while ( $p->next_tag() ) {
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
				$p->set_attribute( 'data-wp-bind--value', 0 === $hidden++ ? 'context.selectedId' : 'context.titulo' );
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
