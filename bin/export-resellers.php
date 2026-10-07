<?php
/**
 * Export the revendas of a site (production, with JetEngine) into the files
 * the activation import reads:
 *  - content/revendas.csv: Nome, Status, País, Estado, Cidade, Endereço,
 *    Telefone 1, Telefone 2, Site, E-mail (no ID; values exactly as stored,
 *    cells starting with = + - @ escaped with a quote);
 *  - content/revendas-terms.json: the location terms (name and slug).
 *
 * Usage, from the production copy:
 *   OUT=<plugin>/content studio wp eval-file <plugin>/bin/export-resellers.php
 *
 * @package Axellcore_Atelierclub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$out_dir = rtrim( (string) getenv( 'OUT' ), '/' );
if ( '' === $out_dir || ! is_dir( $out_dir ) ) {
	WP_CLI::error( 'Set OUT to the plugin content directory.' );
}

$taxonomies = array( 'paises', 'estados', 'cidades' );
$escape     = static function ( $value ) {
	$value = (string) $value;
	return in_array( substr( $value, 0, 1 ), array( '=', '+', '-', '@' ), true ) ? "'" . $value : $value;
};
$first_term = static function ( $post_id, $taxonomy ) {
	$names = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
	return is_wp_error( $names ) || ! $names ? '' : $names[0];
};

$posts = get_posts(
	array(
		'post_type'   => 'revendas',
		'post_status' => array( 'publish', 'pending', 'draft', 'private' ),
		'numberposts' => -1,
		'orderby'     => 'ID',
		'order'       => 'ASC',
	)
);

$handle = fopen( $out_dir . '/revendas.csv', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI export.
fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI export.
fputcsv( $handle, array( 'Nome', 'Status', 'País', 'Estado', 'Cidade', 'Endereço', 'Telefone 1', 'Telefone 2', 'Site', 'E-mail' ), ',', '"', '' );
foreach ( $posts as $post ) {
	$row = array( $post->post_title, $post->post_status );
	foreach ( $taxonomies as $taxonomy ) {
		$row[] = $first_term( $post->ID, $taxonomy );
	}
	foreach ( array( 'endereco', 'telefone-1', 'telefone-2', 'site', 'e-mail' ) as $key ) {
		$row[] = (string) get_post_meta( $post->ID, $key, true );
	}
	fputcsv( $handle, array_map( $escape, $row ), ',', '"', '' );
}
fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI export.

$terms = array();
foreach ( $taxonomies as $taxonomy ) {
	foreach ( get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'term_id',
		)
	) as $term ) {
		$terms[ $taxonomy ][] = array(
			'name' => $term->name,
			'slug' => $term->slug,
		);
	}
}
file_put_contents( $out_dir . '/revendas-terms.json', wp_json_encode( $terms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI export.

WP_CLI::success( sprintf( '%d revendas, %d terms.', count( $posts ), array_sum( array_map( 'count', $terms ) ) ) );
