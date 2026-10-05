<?php
/**
 * Plugin Name:       Axellcore — Atelier Club
 * Plugin URI:        https://axell.com.br/
 * Description:       Self-contained landing page (FSE template + core blocks + a custom application-form block) for the Atelier Axell Club invite program.
 * Version:           0.1.2
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Axell
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       axellcore-atelierclub
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AXELLCORE_ATELIERCLUB_VERSION', '0.1.2' );
define( 'AXELLCORE_ATELIERCLUB_FILE', __FILE__ );
define( 'AXELLCORE_ATELIERCLUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'AXELLCORE_ATELIERCLUB_URL', plugin_dir_url( __FILE__ ) );

// WordPress only auto-loads translations for plugins fetched from
// wordpress.org's own translation API — a plugin distributed any other way
// (like this one) must load its own .mo explicitly, or every __()/_e() call
// silently stays in the source (English) string regardless of site locale
// or how complete the .po/.mo actually is. Hooked directly here (not on
// plugins_loaded/init) so it's guaranteed to run before anything in
// includes/ — which registers on `init` — ever calls __().
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'axellcore-atelierclub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-plugin.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-label-template.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-options-rest.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-kses.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-template-loader.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-assets.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-blocks.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-icons.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-member.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-locations.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-members.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-form-block.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-seo.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-rest.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-admin-rest.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-template-parts.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-classic-template.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-reveal.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-activator.php';

register_activation_hook( AXELLCORE_ATELIERCLUB_FILE, array( 'Axellcore_Atelierclub\\Activator', 'activate' ) );

Axellcore_Atelierclub\Plugin::instance()->boot();
