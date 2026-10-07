<?php
/**
 * Revenda locations and the member form's custom store: a store text
 * "Nome - UF Cidade" that matches the format creates a pending revenda
 * (curadoria publishes it). Runs with or without JetEngine, since the post
 * type, taxonomies and meta keys are the same.
 *
 * Location terms are flat and named like production's: country "Brasil",
 * the state by its full name ("Rio Grande do Sul", slug from the name), the
 * city by its name.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Location terms of revendas and pending revendas from the form.
 */
final class Reseller_Store {

	/**
	 * Name of the country term for Brazil.
	 */
	const BRAZIL = 'Brasil';

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		add_filter( 'axellcore_atelierclub_reseller_text', array( self::class, 'reseller_from_text' ), 10, 2 );
	}

	/**
	 * Filter callback: the revenda for a custom store text, created as
	 * pending when the text matches "Nome - UF Cidade". Anything else is left
	 * alone (the form keeps the plain text).
	 *
	 * @param int    $post_id Revenda found so far.
	 * @param string $text    Store text.
	 * @return int
	 */
	public static function reseller_from_text( $post_id, $text ) {
		if ( $post_id || ! post_type_exists( Resellers::POST_TYPE ) ) {
			return (int) $post_id;
		}
		$parts = self::parse_store_text( (string) $text );
		return $parts ? self::create_pending( $parts ) : 0;
	}

	/**
	 * Parse "Nome - UF Cidade" (e.g. "Adair Beal - RS Passo Fundo"): the name
	 * is what comes before the first hyphen, the UF the two letters that start
	 * the rest (any case), the city everything after them.
	 *
	 * @param string $text Text sent by the member.
	 * @return array{nome:string,uf:string,cidade:string}|null Null when the text does not match.
	 */
	public static function parse_store_text( $text ) {
		if ( ! preg_match( '/^\s*(.+?)\s*-\s*([A-Za-z]{2})\s+(.+?)\s*$/u', (string) $text, $m ) ) {
			return null;
		}
		$uf = strtoupper( $m[2] );
		if ( ! isset( Locations::instance()->states()[ $uf ] ) ) {
			return null;
		}
		return array(
			'nome'   => sanitize_text_field( $m[1] ),
			'uf'     => $uf,
			'cidade' => sanitize_text_field( $m[3] ),
		);
	}

	/**
	 * Full name of a Brazilian state given as UF or name in any case or
	 * accents ("RS", "Rio Grande do sul" → "Rio Grande do Sul"); the value
	 * itself when it is not one.
	 *
	 * @param string $state UF or name.
	 * @return string
	 */
	public static function state_name( $state ) {
		$state  = trim( (string) $state );
		$states = Locations::instance()->states();
		if ( isset( $states[ strtoupper( $state ) ] ) ) {
			return $states[ strtoupper( $state ) ];
		}
		$slug = sanitize_title( $state );
		foreach ( $states as $name ) {
			if ( sanitize_title( $name ) === $slug ) {
				return $name;
			}
		}
		return $state;
	}

	/**
	 * UF of a Brazilian state name or UF, '' when it is not one.
	 *
	 * @param string $state UF or name.
	 * @return string
	 */
	public static function state_code( $state ) {
		$name = self::state_name( $state );
		$uf   = array_search( $name, Locations::instance()->states(), true );
		return false === $uf ? '' : (string) $uf;
	}

	/**
	 * The official name of a city of the UF, matched by slug ("Florianopolis"
	 * finds "Florianópolis"), or '' when it is not in the IBGE list.
	 *
	 * @param string $uf   UF code.
	 * @param string $city City as typed.
	 * @return string
	 */
	public static function known_city( $uf, $city ) {
		$slug = sanitize_title( (string) $city );
		foreach ( Locations::instance()->cities_for_state( (string) $uf ) as $known ) {
			if ( sanitize_title( $known ) === $slug ) {
				return $known;
			}
		}
		return '';
	}

	/**
	 * Location names normalised for Brazil: the state's full name and the
	 * city's official name when known.
	 *
	 * @param string $country Country name.
	 * @param string $state   State UF or name.
	 * @param string $city    City name.
	 * @return array{country:string,state:string,city:string}
	 */
	public static function normalize_location( $country, $state, $city ) {
		$country = trim( (string) $country );
		$state   = trim( (string) $state );
		$city    = trim( (string) $city );
		if ( 'brasil' === sanitize_title( $country ) || 'brazil' === sanitize_title( $country ) ) {
			$country = self::BRAZIL;
			$state   = self::state_name( $state );
			$uf      = self::state_code( $state );
			$known   = $uf ? self::known_city( $uf, $city ) : '';
			$city    = '' !== $known ? $known : $city;
		}
		return array(
			'country' => $country,
			'state'   => $state,
			'city'    => $city,
		);
	}

	/**
	 * Set a revenda's country, state and city terms ('' clears that level).
	 *
	 * @param int                                             $post_id  Revenda ID.
	 * @param array{country:string,state:string,city:string} $location Normalised names.
	 */
	public static function assign_location( $post_id, array $location ) {
		$levels = array(
			'country' => Resellers::TAX_COUNTRY,
			'state'   => Resellers::TAX_STATE,
			'city'    => Resellers::TAX_CITY,
		);
		foreach ( $levels as $level => $taxonomy ) {
			$name    = $location[ $level ] ?? '';
			$term_id = '' === $name ? 0 : self::term_id( $taxonomy, $name );
			wp_set_object_terms( (int) $post_id, $term_id ? array( $term_id ) : array(), $taxonomy, false );
		}
	}

	/**
	 * Name of a revenda's term in a taxonomy, '' when none.
	 *
	 * @param int    $post_id  Revenda ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function term_name( $post_id, $taxonomy ) {
		$names = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $names ) || ! $names ? '' : (string) $names[0];
	}

	/**
	 * The term named $name, else an equivalent one (same name ignoring case
	 * and accents, or that slug: "Rio Grande do Sul" finds production's
	 * "Rio Grande do sul", slug rs), else a new term named $name.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Term name.
	 * @return int Term ID, 0 on failure.
	 */
	public static function term_id( $taxonomy, $name ) {
		$found = get_term_by( 'name', $name, $taxonomy );
		if ( $found && $found->name === $name ) {
			return (int) $found->term_id;
		}
		$key   = sanitize_title( $name );
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			if ( sanitize_title( $term->name ) === $key || $term->slug === $key ) {
				return (int) $term->term_id;
			}
		}
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			$existing = $created->get_error_data( 'term_exists' );
			return $existing ? (int) $existing : 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * Create a revenda as pending (not published, so it stays out of the
	 * member search until curadoria approves it). The same name in the same
	 * state (and city, when known) is reused.
	 *
	 * @param array{nome:string,uf:string,cidade:string} $parts Output of parse_store_text().
	 * @return int Post ID, or 0 on failure.
	 */
	public static function create_pending( array $parts ) {
		$location         = self::normalize_location( self::BRAZIL, $parts['uf'], $parts['cidade'] );
		$location['city'] = self::known_city( $parts['uf'], $parts['cidade'] );

		$existing = self::find_duplicate( $parts['nome'], $location );
		if ( $existing ) {
			return $existing;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Resellers::POST_TYPE,
				'post_title'  => $parts['nome'],
				'post_status' => 'pending',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		self::assign_location( (int) $post_id, $location );

		return (int) $post_id;
	}

	/**
	 * A revenda with the same name in the same state and, when the city is
	 * known, the same city.
	 *
	 * @param string                                          $name     Revenda name.
	 * @param array{country:string,state:string,city:string} $location Normalised names.
	 * @return int Post ID, or 0 when there is none.
	 */
	private static function find_duplicate( $name, array $location ) {
		$ids = get_posts(
			array(
				'post_type'      => Resellers::POST_TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'title'          => $name,
				'fields'         => 'ids',
				'posts_per_page' => 20,
			)
		);
		foreach ( $ids as $id ) {
			if ( 0 !== strcasecmp( self::term_name( (int) $id, Resellers::TAX_STATE ), $location['state'] ) ) {
				continue;
			}
			if ( '' === $location['city'] || 0 === strcasecmp( self::term_name( (int) $id, Resellers::TAX_CITY ), $location['city'] ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
}
