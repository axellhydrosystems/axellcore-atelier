<?php
/**
 * PHPUnit bootstrap — loads Brain\Monkey and minimal WP stubs, then requires
 * plugin files directly (not the axellcore-atelierclub.php entry point, so
 * Plugin::instance()->boot() is never auto-run in tests).
 *
 * @package Axellcore_Atelierclub\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ── Activate Patchwork stream wrapper BEFORE defining any stub functions ──────
// Brain\Monkey loads Patchwork lazily (inside setUp()), but our stubs must be
// defined in a file that Patchwork's stream wrapper has already preprocessed —
// otherwise it throws DefinedTooEarly when a test tries to mock them.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// ── WordPress function stubs ──────────────────────────────────────────────────
require_once __DIR__ . '/stubs/functions.php';

// ── WordPress constants ───────────────────────────────────────────────────────

defined( 'ABSPATH' ) || define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );
define( 'AXELLCORE_ATELIERCLUB_VERSION', '0.0.0-test' );
define( 'AXELLCORE_ATELIERCLUB_FILE', dirname( __DIR__ ) . '/axellcore-atelierclub.php' );
define( 'AXELLCORE_ATELIERCLUB_PATH', dirname( __DIR__ ) . '/' );
define( 'AXELLCORE_ATELIERCLUB_URL', 'http://example.com/wp-content/plugins/axellcore-atelierclub/' );

// ── Load plugin includes directly (skip the entry-point file's ->boot() call) ─

require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-plugin.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-template-loader.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-assets.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-classic-template.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-template-parts.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-blocks.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-member.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-locations.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-document.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-members.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-settings.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-admin-rest.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-members-export.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-rest.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-resellers.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-reseller-store.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-resellers-import.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-activator.php';
require_once AXELLCORE_ATELIERCLUB_PATH . 'includes/class-weglot.php';
