<?php
/**
 * First-run import of the revendas: on activation, when JetEngine is not
 * active and there is no revenda yet, creates the location terms of
 * content/revendas-terms.json (production's names and slugs) and the posts of
 * content/revendas.csv, both written from production by
 * bin/export-resellers.php.
 *
 * Values are stored exactly as they come (as JetEngine stores its text
 * fields): no trimming, no sanitising, no kses; only the formula escape of a
 * cell ("'@…") is undone. The CSV has no ID: posts get new IDs. Columns are
 * matched by header: Nome, Status, País, Estado, Cidade, Endereço,
 * Telefone 1, Telefone 2, Site, E-mail; the axellcore export's combined
 * "Localização" (País > Estado > Cidade) and "Telefone" (comma separated)
 * are read too. No capability checks: activation can run without a user.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports the bundled revendas once.
 */
final class Resellers_Import {

	/**
	 * Bundled CSV, relative to the plugin.
	 */
	const FILE = 'content/revendas.csv';

	/**
	 * Bundled location terms, relative to the plugin.
	 */
	const TERMS_FILE = 'content/revendas-terms.json';

	/**
	 * Option with the last import's summary.
	 */
	const OPTION = 'axellcore_atelierclub_resellers_import';

	/**
	 * Taxonomy per location level.
	 *
	 * @var array<string,string>
	 */
	const LEVELS = array(
		'country' => Resellers::TAX_COUNTRY,
		'state'   => Resellers::TAX_STATE,
		'city'    => Resellers::TAX_CITY,
	);

	/**
	 * Import when JetEngine is not active and there is no revenda.
	 *
	 * @return array{created:int,failed:int}|null Summary, or null when skipped.
	 */
	public static function maybe_run() {
		if ( Resellers::jet_engine_active() ) {
			return null;
		}
		// The activation hook runs before init.
		Resellers::register();
		if ( ! post_type_exists( Resellers::POST_TYPE ) || self::has_resellers() ) {
			return null;
		}
		return self::run( AXELLCORE_ATELIERCLUB_PATH . self::FILE, AXELLCORE_ATELIERCLUB_PATH . self::TERMS_FILE );
	}

	/**
	 * Whether any revenda exists (any status but auto-draft).
	 *
	 * @return bool
	 */
	private static function has_resellers() {
		$found = get_posts(
			array(
				'post_type'        => Resellers::POST_TYPE,
				'post_status'      => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return ! empty( $found );
	}

	/**
	 * Create the terms, then every row of the CSV.
	 *
	 * @param string $file       CSV path.
	 * @param string $terms_file Terms JSON path ('' for none).
	 * @return array{created:int,failed:int}
	 */
	public static function run( $file, $terms_file = '' ) {
		$summary = array(
			'created' => 0,
			'failed'  => 0,
		);

		// Stored as given, like JetEngine (no kses on titles: "&" stays "&").
		$kses = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
		kses_remove_filters();

		if ( '' !== $terms_file && is_readable( $terms_file ) ) {
			self::create_terms( (array) json_decode( (string) file_get_contents( $terms_file ), true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		}

		$rows = is_readable( $file ) ? self::read( (string) file_get_contents( $file ) ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		foreach ( $rows as $index => $row ) {
			$record = self::parse_row( $row );
			$saved  = $record ? self::save( $record ) : 0;
			if ( $saved ) {
				++$summary['created'];
				continue;
			}
			++$summary['failed'];
			error_log( sprintf( 'axellcore-atelierclub: revenda row %d not imported.', $index + 2 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- activation has no UI to report to.
		}

		if ( $kses ) {
			kses_init_filters();
		}
		update_option( self::OPTION, $summary + array( 'time' => time() ), false );
		return $summary;
	}

	/**
	 * Create the bundled terms with their production name and slug (an
	 * existing slug is kept as is).
	 *
	 * @param array<string,array<int,array{name:string,slug:string}>> $terms Terms by taxonomy.
	 */
	private static function create_terms( array $terms ) {
		foreach ( self::LEVELS as $taxonomy ) {
			foreach ( (array) ( $terms[ $taxonomy ] ?? array() ) as $term ) {
				$name = (string) ( $term['name'] ?? '' );
				$slug = (string) ( $term['slug'] ?? '' );
				if ( '' === $name || ( '' !== $slug && get_term_by( 'slug', $slug, $taxonomy ) ) ) {
					continue;
				}
				wp_insert_term( $name, $taxonomy, '' !== $slug ? array( 'slug' => $slug ) : array() );
			}
		}
	}

	/**
	 * Rows of a CSV text keyed by normalised header (UTF-8 BOM stripped,
	 * cells untouched).
	 *
	 * @param string $csv CSV text.
	 * @return array<int,array<string,string>>
	 */
	public static function read( $csv ) {
		$csv    = 0 === strpos( $csv, "\xEF\xBB\xBF" ) ? substr( $csv, 3 ) : $csv;
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
		if ( ! $handle ) {
			return array();
		}
		fwrite( $handle, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream.
		rewind( $handle );

		$headers = array();
		$rows    = array();
		while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- the usual fgetcsv loop.
			if ( array( null ) === $cells ) {
				continue;
			}
			if ( ! $headers ) {
				$headers = array_map( array( self::class, 'normalize_header' ), $cells );
				continue;
			}
			$row = array();
			foreach ( $headers as $i => $header ) {
				$row[ $header ] = (string) ( $cells[ $i ] ?? '' );
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.

		return $rows;
	}

	/**
	 * Header as a plain key: "Localização" → "localizacao", "Telefone 1" →
	 * "telefone1", "E-mail" → "email".
	 *
	 * @param string $header Header text.
	 * @return string
	 */
	public static function normalize_header( $header ) {
		return (string) preg_replace( '/[^a-z0-9]+/', '', strtolower( remove_accents( trim( (string) $header ) ) ) );
	}

	/**
	 * A CSV row as a revenda record, or null without a name.
	 *
	 * @param array<string,string> $row Cells by normalised header.
	 * @return array{title:string,status:string,meta:array<string,string>,location:array<string,string>}|null
	 */
	public static function parse_row( array $row ) {
		$cell  = static function ( $key ) use ( $row ) {
			$value = (string) ( $row[ $key ] ?? '' );
			// Formula-escaped cells ("'=…") lose their quote.
			return in_array( substr( $value, 0, 2 ), array( "'=", "'+", "'-", "'@" ), true ) ? substr( $value, 1 ) : $value;
		};
		$title = $cell( 'nome' );
		if ( '' === trim( $title ) ) {
			return null;
		}

		if ( isset( $row['telefone1'] ) || isset( $row['telefone2'] ) ) {
			$phones = array( $cell( 'telefone1' ), $cell( 'telefone2' ) );
		} else {
			$phones = self::split_values( $cell( 'telefone' ) );
		}

		if ( isset( $row['localizacao'] ) ) {
			$parts    = array_pad( array_map( 'trim', explode( '>', $cell( 'localizacao' ), 3 ) ), 3, '' );
			$location = array_combine( array_keys( self::LEVELS ), $parts );
		} else {
			$location = array(
				'country' => $cell( 'pais' ),
				'state'   => $cell( 'estado' ),
				'city'    => $cell( 'cidade' ),
			);
		}

		$status = $cell( 'status' );
		return array(
			'title'    => $title,
			'status'   => in_array( $status, array( 'publish', 'pending', 'draft', 'private' ), true ) ? $status : 'publish',
			'meta'     => array(
				'endereco'   => $cell( 'endereco' ),
				'telefone-1' => (string) ( $phones[0] ?? '' ),
				'telefone-2' => (string) ( $phones[1] ?? '' ),
				'site'       => $cell( 'site' ),
				'e-mail'     => $cell( 'email' ),
			),
			'location' => $location,
		);
	}

	/**
	 * Values of a comma-separated cell; "\," keeps a comma inside a value.
	 *
	 * @param string $value Cell.
	 * @return string[]
	 */
	public static function split_values( $value ) {
		$marker = "\x1F";
		$parts  = array();
		foreach ( explode( ',', str_replace( '\\,', $marker, (string) $value ) ) as $part ) {
			$part = trim( str_replace( $marker, ',', $part ) );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		return $parts;
	}

	/**
	 * Create the revenda of a record.
	 *
	 * @param array{title:string,status:string,meta:array<string,string>,location:array<string,string>} $record Parsed row.
	 * @return int Post ID, 0 on failure.
	 */
	private static function save( array $record ) {
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Resellers::POST_TYPE,
					'post_status' => $record['status'],
					'post_title'  => $record['title'],
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		foreach ( $record['meta'] as $key => $value ) {
			update_post_meta( (int) $post_id, $key, wp_slash( $value ) );
		}
		foreach ( self::LEVELS as $level => $taxonomy ) {
			$name    = $record['location'][ $level ] ?? '';
			$term_id = '' === $name ? 0 : Reseller_Store::term_id( $taxonomy, $name );
			if ( $term_id ) {
				wp_set_object_terms( (int) $post_id, array( $term_id ), $taxonomy, false );
			}
		}

		return (int) $post_id;
	}
}
