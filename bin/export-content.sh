#!/usr/bin/env bash
# Exports the live site's pages and template parts into content/, the files
# Activator reads on activation: atelier-page.html (landing), pages/{slug}.html
# (its child pages) and header-part.html / footer-part.html. Run from anywhere
# while the Studio site is running. The database is the source of truth; this
# only copies it into the repository.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SITE_DIR="$(cd "$PLUGIN_DIR/../../.." && pwd)"

PHP="$(cat <<'PHP'
$dir = AXELLCORE_ATELIERCLUB_PATH . 'content/';
$landing = get_page_by_path( 'atelier', OBJECT, 'page' );
if ( ! $landing ) { echo "landing not found\n"; return; }
file_put_contents( $dir . 'atelier-page.html', $landing->post_content );
echo "atelier-page.html\n";
foreach ( get_posts( array( 'post_type' => 'page', 'post_parent' => $landing->ID, 'post_status' => 'any', 'numberposts' => -1 ) ) as $child ) {
	file_put_contents( $dir . 'pages/' . $child->post_name . '.html', $child->post_content );
	echo 'pages/' . $child->post_name . ".html\n";
}
$parts = array( 'axellcore-header' => 'header-part.html', 'axellcore-footer' => 'footer-part.html' );
foreach ( $parts as $slug => $file ) {
	$part = get_posts( array( 'post_type' => 'wp_template_part', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) );
	if ( $part ) {
		file_put_contents( $dir . $file, $part[0]->post_content );
		echo $file . "\n";
	}
}
PHP
)"

cd "$SITE_DIR"
studio wp eval "$PHP"
