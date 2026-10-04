<?php
/**
 * Brazilian state/city reference data (adapted from fervidum/f9brcities —
 * see includes/data/br-states.php and includes/data/br-cities.php) and the
 * `aac_location` hierarchical taxonomy (Country > State > City) it feeds.
 *
 * Terms are created lazily, on first use, by resolve_city_term() — not
 * pre-seeded on activation. Pre-seeding all 27 states + 5,570 cities would
 * bloat wp_terms/wp_term_taxonomy for a taxonomy that, at this phase, only
 * needs the handful of locations members actually submit.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * State/city data + the Country > State > City taxonomy.
 */
final class Locations {

	/**
	 * Taxonomy slug.
	 */
	const TAXONOMY = 'aac_location';

	/**
	 * The one country this taxonomy supports right now — ISO 3166-1 alpha-2,
	 * lowercase, matching the state/city slugs' own style (br-sp-sao-paulo).
	 */
	const COUNTRY_SLUG = 'br';

	/**
	 * Singleton instance.
	 *
	 * @var Locations|null
	 */
	private static $instance = null;

	/**
	 * Lazily-loaded UF => state name map.
	 *
	 * @var array<string,string>|null
	 */
	private $states = null;

	/**
	 * Lazily-loaded UF => [ ibge_code => city name ] map.
	 *
	 * @var array<string,array<int,string>>|null
	 */
	private $cities = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Locations
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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the `aac_location` taxonomy on the `aac_member` post type.
	 *
	 * Deliberately locked down in wp-admin — the same restriction the
	 * frontend form already has (a fixed Estado/Cidade select, never free
	 * text). `show_ui => false` removes both the default checkbox-tree
	 * metabox (which includes an open "add new term" text field) from the
	 * Member edit screen AND the standalone Locations admin screens that
	 * would otherwise let anyone type an arbitrary term name. The `*_terms`
	 * capabilities are additionally locked to `do_not_allow` as defense in
	 * depth, in case any other admin surface tries to expose term
	 * management. None of this affects Members::create_from_params(): wp_insert_term()
	 * and wp_set_object_terms() are plain function calls, not capability-
	 * gated, so the REST-driven Country > State > City resolution still
	 * works exactly as before — only humans in wp-admin are locked out.
	 * `show_admin_column` still shows the resolved (read-only) location on
	 * each member in the list table.
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			Member::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Locations', 'axellcore-atelierclub' ),
					'singular_name' => __( 'Location', 'axellcore-atelierclub' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => false,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'capabilities'      => array(
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * All Brazilian states, UF => name.
	 *
	 * @return array<string,string>
	 */
	public function states(): array {
		if ( null === $this->states ) {
			$this->states = include AXELLCORE_ATELIERCLUB_PATH . 'includes/data/br-states.php';
		}
		return $this->states;
	}

	/**
	 * All cities for one state, ibge_code => name.
	 *
	 * @param string $uf Two-letter state code.
	 * @return array<int,string>
	 */
	public function cities_for_state( string $uf ): array {
		if ( null === $this->cities ) {
			$this->cities = include AXELLCORE_ATELIERCLUB_PATH . 'includes/data/br-cities.php';
		}
		return $this->cities[ strtoupper( $uf ) ] ?? array();
	}

	/**
	 * Resolve (creating as needed) the Brazil > State > City term chain for
	 * one city, returning the leaf (city) term ID. Hierarchical taxonomies
	 * imply ancestor terms automatically once queried, so assigning just the
	 * leaf term to a post is enough — no need to also assign the state/
	 * country terms.
	 *
	 * @param string $uf        Two-letter state code.
	 * @param int    $ibge_code IBGE municipality code.
	 * @return int|null Term ID, or null if $uf/$ibge_code don't resolve to a known place.
	 */
	public function resolve_city_term( string $uf, int $ibge_code ): ?int {
		$uf     = strtoupper( $uf );
		$states = $this->states();

		if ( ! isset( $states[ $uf ] ) ) {
			return null;
		}

		$city_name = $this->cities_for_state( $uf )[ $ibge_code ] ?? null;
		if ( null === $city_name ) {
			return null;
		}

		$state_slug = strtolower( $uf );

		$country_id = $this->get_or_create_term( __( 'Brazil', 'axellcore-atelierclub' ), self::COUNTRY_SLUG, 0 );
		$state_id   = $this->get_or_create_term( $states[ $uf ], self::COUNTRY_SLUG . '-' . $state_slug, $country_id );
		$city_id    = $this->get_or_create_term( $city_name, self::COUNTRY_SLUG . '-' . $state_slug . '-' . sanitize_title( $city_name ), $state_id );

		return $city_id;
	}

	/**
	 * Get a term by slug, creating it (under $parent_id) if it doesn't exist yet.
	 *
	 * @param string $name      Term name.
	 * @param string $slug      Term slug (stable identity — used for the lookup).
	 * @param int    $parent_id Parent term ID, or 0 for a top-level term.
	 * @return int Term ID.
	 */
	private function get_or_create_term( string $name, string $slug, int $parent_id ): int {
		$term = get_term_by( 'slug', $slug, self::TAXONOMY );
		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}

		$result = wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'slug'   => $slug,
				'parent' => $parent_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			// Most likely a term-already-exists race (two concurrent
			// submissions for the same new city) — look it up again rather
			// than failing the whole submission over it.
			$existing = get_term_by( 'slug', $slug, self::TAXONOMY );
			return $existing instanceof \WP_Term ? $existing->term_id : 0;
		}

		return (int) $result['term_id'];
	}
}
