<?php
/**
 * Native WP-CLI release command for axellcore-atelierclub.
 *
 * A standalone implementation (does not shell out to bin/release.sh — the
 * two are independent, same as the sibling axellcore/essfinance03 plugins):
 * bumps the plugin version, validates the readme.txt changelog entry,
 * regenerates the POT/JS translation catalogs, and commits/tags/pushes.
 *
 * Usage:
 *   wp --require=bin/release.php axc release patch
 *   wp --require=bin/release.php axc release minor
 *   wp --require=bin/release.php axc release major
 *   wp --require=bin/release.php axc release 1.2.3
 *   wp --require=bin/release.php axc release patch --no-commit
 *   wp --require=bin/release.php axc release patch --no-tag
 *   wp --require=bin/release.php axc release patch --no-push
 *
 *   wp --require=bin/release.php axc language
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

// ── Process helpers ─────────────────────────────────────────────────────────

/**
 * Run a shell command via proc_open. Prints output and dies on failure.
 *
 * @param string      $cmd    Shell command.
 * @param string|null $cwd    Working directory; null inherits current.
 * @param bool        $silent Suppress stdout printing.
 * @return string Captured stdout.
 */
function axellcore_atelierclub_run( string $cmd, ?string $cwd = null, bool $silent = false ): string {
	$descriptors = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	$process = proc_open( $cmd, $descriptors, $pipes, $cwd );
	if ( ! is_resource( $process ) ) {
		WP_CLI::error( "Failed to start: $cmd" );
	}

	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exit = proc_close( $process );

	if ( ! $silent && '' !== $stdout ) {
		WP_CLI::line( rtrim( $stdout ) );
	}

	if ( 0 !== $exit ) {
		WP_CLI::error( '' !== $stderr ? rtrim( $stderr ) : "Command failed (exit $exit): $cmd" );
	}

	return $stdout;
}

/**
 * Like axellcore_atelierclub_run() but returns [ exit_code, stdout ] without dying.
 *
 * @param string      $cmd Shell command.
 * @param string|null $cwd Working directory.
 * @return array{ 0: int, 1: string }
 */
function axellcore_atelierclub_try_run( string $cmd, ?string $cwd = null ): array {
	$descriptors = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	$process = proc_open( $cmd, $descriptors, $pipes, $cwd );
	if ( ! is_resource( $process ) ) {
		return array( 1, '' );
	}

	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return array( proc_close( $process ), $stdout );
}

/**
 * Assert that an external command exists on PATH.
 *
 * @param string $cmd Command name.
 */
function axellcore_atelierclub_require_cmd( string $cmd ): void {
	list( $exit ) = axellcore_atelierclub_try_run( "command -v $cmd" );
	if ( 0 !== $exit ) {
		WP_CLI::error( "$cmd is required but not found on PATH." );
	}
}

/**
 * Resolve the plugin root directory (bin/../).
 *
 * @return string Absolute path, no trailing slash.
 */
function axellcore_atelierclub_plugin_dir(): string {
	return dirname( __FILE__, 2 );
}

/**
 * Read the current version from the plugin header.
 *
 * @param string $plugin_file Absolute path to axellcore-atelierclub.php.
 * @return string Version string e.g. "0.1.0".
 */
function axellcore_atelierclub_current_version( string $plugin_file ): string {
	$contents = file_get_contents( $plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents ) {
		WP_CLI::error( "Cannot read $plugin_file" );
	}
	if ( ! preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $contents, $m ) ) {
		WP_CLI::error( "Cannot parse version from $plugin_file" );
	}
	return trim( $m[1] );
}

/**
 * Bump a semver string.
 *
 * @param string $current Current version.
 * @param string $bump    "patch", "minor", "major", or an explicit semver.
 * @return string New version.
 */
function axellcore_atelierclub_bump_version( string $current, string $bump ): string {
	if ( ! preg_match( '/^(\d+)\.(\d+)\.(\d+)$/', $current, $m ) ) {
		WP_CLI::error( "Cannot parse current version: $current" );
	}

	$maj = (int) $m[1];
	$min = (int) $m[2];
	$pat = (int) $m[3];

	switch ( $bump ) {
		case 'major':
			return ( $maj + 1 ) . '.0.0';
		case 'minor':
			return "$maj." . ( $min + 1 ) . '.0';
		case 'patch':
			return "$maj.$min." . ( $pat + 1 );
		default:
			if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $bump ) ) {
				WP_CLI::error( "Invalid version: $bump" );
			}
			return $bump;
	}
}

/**
 * Extract the changelog bullet lines for one version from readme.txt.
 *
 * @param string $readme  Full readme.txt contents.
 * @param string $version Version heading to look for (e.g. "0.1.1").
 * @return string Non-empty lines between "= $version =" and the next heading.
 */
function axellcore_atelierclub_changelog_entry( string $readme, string $version ): string {
	if ( ! preg_match( '/^= ' . preg_quote( $version, '/' ) . ' =\R(.*?)(?=^= |\z)/ms', $readme, $m ) ) {
		return '';
	}
	$lines = array_filter(
		array_map( 'rtrim', explode( "\n", $m[1] ) ),
		static fn( string $l ): bool => '' !== trim( $l )
	);
	return implode( "\n", $lines );
}

/**
 * Parse a .po file and return msgids that are untranslated or fuzzy.
 *
 * @param string $po_file Absolute path to .po file.
 * @return list<string> Untranslated msgid values.
 */
function axellcore_atelierclub_po_missing( string $po_file ): array {
	$contents = file_get_contents( $po_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $contents ) {
		return array();
	}

	$blocks  = preg_split( '/\n{2,}/', trim( $contents ) ) ?: array();
	$missing = array();

	foreach ( $blocks as $block ) {
		$lines = explode( "\n", trim( $block ) );

		if ( in_array( 'msgid ""', $lines, true ) ) {
			continue;
		}

		$is_fuzzy = in_array( '#, fuzzy', $lines, true );

		// A value may continue on the following "…" lines (long strings wrap).
		$msgid_val  = '';
		$msgstr_val = '';
		$current    = null;
		foreach ( $lines as $line ) {
			if ( preg_match( '/^msgid\s+"(.*)"$/', $line, $m ) ) {
				$current    = 'id';
				$msgid_val .= $m[1];
			} elseif ( preg_match( '/^msgstr(?:\[\d+\])?\s+"(.*)"$/', $line, $m ) ) {
				$current     = 'str';
				$msgstr_val .= $m[1];
			} elseif ( preg_match( '/^"(.*)"$/', $line, $m ) ) {
				if ( 'id' === $current ) {
					$msgid_val .= $m[1];
				} elseif ( 'str' === $current ) {
					$msgstr_val .= $m[1];
				}
			} else {
				$current = null;
			}
		}

		if ( '' !== $msgid_val && ( $is_fuzzy || '' === trim( $msgstr_val ) ) ) {
			$missing[] = $msgid_val;
		}
	}

	return $missing;
}

/**
 * Point blueprint.json at a release: the plugin zip and the pt_BR language pack
 * are versioned assets (axellcore-atelierclub.X.Y.Z.zip,
 * axellcore-atelierclub.X.Y.Z-pt_BR.zip), so no "latest" URL can name them.
 *
 * @param string $file    Blueprint path.
 * @param string $version Release version.
 */
function axellcore_atelierclub_point_blueprint( string $file, string $version ): void {
	if ( ! is_readable( $file ) ) {
		return;
	}
	$base     = 'https://github.com/axellhydrosystems/axellcore-atelierclub/releases/download/' . $version . '/';
	$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$contents = preg_replace(
		'#https://github\.com/axellhydrosystems/axellcore-atelierclub/releases/[^"]*?/axellcore-atelierclub(?:\.[0-9.]+)?-([a-z]{2,3}_[A-Z]{2,4})\.zip#',
		$base . 'axellcore-atelierclub.' . $version . '-$1.zip',
		$contents
	);
	$contents = preg_replace(
		'#https://github\.com/axellhydrosystems/axellcore-atelierclub/releases/[^"]*?/axellcore-atelierclub(?:\.[0-9.]+)?\.zip#',
		$base . 'axellcore-atelierclub.' . $version . '.zip',
		$contents
	);
	file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

// ── WP-CLI command class ────────────────────────────────────────────────────

/**
 * Manages axellcore-atelierclub plugin releases and language packs.
 */
class Axellcore_Atelierclub_CLI_Command extends WP_CLI_Command {

	/**
	 * Bump the plugin version, update the POT/JSON translation catalogs,
	 * commit, tag and push.
	 *
	 * ## OPTIONS
	 *
	 * <bump>
	 * : Version bump: patch, minor, major, or explicit semver (e.g. 1.2.3).
	 *
	 * [--commit]
	 * : Commit the version bump. Default true — pass --no-commit to stop
	 * after bumping files (implies --no-tag and --no-push).
	 *
	 * [--tag]
	 * : Tag the release commit. Default true — pass --no-tag to commit
	 * without tagging (implies --no-push).
	 *
	 * [--push]
	 * : Push the branch and tag. Default true — pass --no-push to commit
	 * and tag locally only.
	 *
	 * ## EXAMPLES
	 *
	 *   wp --require=bin/release.php axc release patch
	 *   wp --require=bin/release.php axc release 1.3.0 --no-push
	 *
	 * @subcommand release
	 * @when before_wp_load
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function release( array $args, array $assoc_args ): void {
		axellcore_atelierclub_require_cmd( 'git' );
		axellcore_atelierclub_require_cmd( 'wp' );

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'usage: wp --require=bin/release.php axc release <patch|minor|major|X.Y.Z> [--no-commit] [--no-tag] [--no-push]' );
		}
		$bump = $args[0];

		// WP-CLI convention: declare the positive boolean ("commit", default
		// true) and let WP-CLI's own --no-<flag> negation set it to false —
		// declaring "no-commit" itself as the flag name doesn't work, WP-CLI's
		// synopsis validator still tries to resolve --no-commit against a
		// "commit" flag first and rejects it as unknown.
		// Dependency rules: --no-commit => --no-tag => --no-push.
		$no_commit = false === ( $assoc_args['commit'] ?? true );
		$no_tag    = $no_commit || false === ( $assoc_args['tag'] ?? true );
		$no_push   = $no_tag || false === ( $assoc_args['push'] ?? true );

		$plugin_dir  = axellcore_atelierclub_plugin_dir();
		$plugin_file = $plugin_dir . '/axellcore-atelierclub.php';
		$readme_file = $plugin_dir . '/readme.txt';
		$pot_file    = $plugin_dir . '/languages/axellcore-atelierclub.pot';

		$current = axellcore_atelierclub_current_version( $plugin_file );
		$version = axellcore_atelierclub_bump_version( $current, $bump );

		WP_CLI::log( '' );
		WP_CLI::log( "axellcore-atelierclub $current → $version" );
		WP_CLI::log( '' );

		list( $has_remote ) = axellcore_atelierclub_try_run( 'git remote get-url origin', $plugin_dir );
		if ( 0 === $has_remote ) {
			axellcore_atelierclub_run( 'git fetch origin --quiet', $plugin_dir, true );
			list( $tag_exists ) = axellcore_atelierclub_try_run( "git ls-remote --exit-code origin refs/tags/{$version}", $plugin_dir );
			if ( 0 === $tag_exists ) {
				WP_CLI::error( "tag {$version} already exists on remote" );
			}
		}

		// ── Bump version in the plugin header + constant, and readme.txt Stable tag ──

		WP_CLI::log( '  → bumping version in axellcore-atelierclub.php and readme.txt' );

		$plugin_contents = file_get_contents( $plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$plugin_contents = preg_replace( '/ \* Version:.*/', " * Version:           {$version}", $plugin_contents, 1 );
		$plugin_contents = preg_replace( "/define\\( 'AXELLCORE_ATELIERCLUB_VERSION', '[^']*' \\)/", "define( 'AXELLCORE_ATELIERCLUB_VERSION', '{$version}' )", $plugin_contents, 1 );
		file_put_contents( $plugin_file, $plugin_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$readme = file_get_contents( $readme_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$readme = preg_replace( '/^Stable tag:.*/m', "Stable tag: {$version}", $readme, 1 );

		if ( ! preg_match( '/^= ' . preg_quote( $version, '/' ) . ' =/m', $readme ) ) {
			$today  = gmdate( 'Y-m-d' );
			$readme = preg_replace(
				'/^== Changelog ==$/m',
				"== Changelog ==\n\n= {$version} =\n* Release {$version} ({$today}).",
				$readme,
				1
			);
		}
		file_put_contents( $readme_file, $readme ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// ── Validate the changelog entry isn't an empty placeholder ──────────────────

		$entry = axellcore_atelierclub_changelog_entry( $readme, $version );
		if ( '' === $entry ) {
			WP_CLI::error( "Changelog for {$version} is empty. Add release notes to readme.txt before releasing." );
		}
		$entry_lines  = explode( "\n", $entry );
		$is_stub_only = 1 === count( $entry_lines ) && str_starts_with( $entry_lines[0], "* Release {$version}" );
		if ( $is_stub_only ) {
			WP_CLI::error( "No changes added in current changelog for {$version}.\n       Edit readme.txt and replace the placeholder before releasing." );
		}

		// ── Regenerate POT + PO + compiled MO + JS translation catalogs ──────────────
		// Order matters: make-pot (extract source strings) → update-po (merge into
		// every shipped .po, preserving existing translations) → make-mo (compile —
		// PHP gettext via load_plugin_textdomain() reads ONLY the compiled .mo, never
		// the .po directly; skipping this step means every "translated" string
		// silently stays in English at runtime, which is exactly what happened from
		// 0.1.0 through 0.1.2) → make-json (JS/editor catalogs, from the current .po).

		WP_CLI::log( '  → generating axellcore-atelierclub.pot via wp i18n make-pot' );
		axellcore_atelierclub_run(
			'wp i18n make-pot ' . escapeshellarg( $plugin_dir ) . ' ' . escapeshellarg( $pot_file )
				. ' --domain=axellcore-atelierclub --exclude=vendor,node_modules,tests --quiet',
			$plugin_dir,
			true
		);

		foreach ( glob( $plugin_dir . '/languages/*.po' ) as $po_file ) {
			WP_CLI::log( "  → merging new strings into " . basename( $po_file ) . ' via wp i18n update-po' );
			axellcore_atelierclub_run(
				'wp i18n update-po ' . escapeshellarg( $pot_file ) . ' ' . escapeshellarg( $po_file ) . ' --quiet',
				$plugin_dir,
				true
			);
		}

		WP_CLI::log( '  → compiling .mo files via wp i18n make-mo' );
		axellcore_atelierclub_run(
			'wp i18n make-mo ' . escapeshellarg( $plugin_dir . '/languages' ) . ' ' . escapeshellarg( $plugin_dir . '/languages' ),
			$plugin_dir,
			true
		);

		WP_CLI::log( '  → generating JS translation catalog via wp i18n make-json' );
		axellcore_atelierclub_try_run(
			'wp i18n make-json ' . escapeshellarg( $plugin_dir . '/languages' ) . ' --no-purge --quiet',
			$plugin_dir
		);

		// ── Every shipped locale fully translated (language packs are published from them) ──

		foreach ( glob( $plugin_dir . '/languages/axellcore-atelierclub-*.po' ) ?: array() as $po_file ) {
			$missing = axellcore_atelierclub_po_missing( $po_file );
			if ( array() !== $missing ) {
				WP_CLI::error( basename( $po_file ) . ' has ' . count( $missing ) . " untranslated strings:\n  " . implode( "\n  ", array_slice( $missing, 0, 20 ) ) );
			}
		}

		// ── Blueprints point at this release's assets ────────────────────────────────

		WP_CLI::log( '  → pointing blueprint.json at the ' . $version . ' release assets' );
		axellcore_atelierclub_point_blueprint( $plugin_dir . '/blueprint.json', $version );

		// ── Commit, tag, push ─────────────────────────────────────────────────────────

		WP_CLI::log( '  → staging all changes' );
		axellcore_atelierclub_run( 'git add -A', $plugin_dir, true );

		if ( $no_commit ) {
			WP_CLI::log( '' );
			WP_CLI::success( "Files bumped to {$version} (--no-commit: skipping commit, tag and push)." );
			return;
		}

		WP_CLI::log( '  → committing version bump' );
		axellcore_atelierclub_run( 'git commit --quiet -m ' . escapeshellarg( "chore: release {$version}" ), $plugin_dir, true );

		if ( $no_tag ) {
			WP_CLI::success( "Released {$version} (--no-tag: skipping tag and push)." );
			return;
		}

		WP_CLI::log( "  → tagging {$version}" );
		axellcore_atelierclub_run( 'git tag ' . escapeshellarg( $version ), $plugin_dir, true );

		if ( $no_push || 0 !== $has_remote ) {
			WP_CLI::success( "Released {$version} locally (no remote configured or --no-push: skipping push)." );
			return;
		}

		WP_CLI::log( '  → pushing branch and tag' );
		$branch = trim( axellcore_atelierclub_run( 'git rev-parse --abbrev-ref HEAD', $plugin_dir, true ) );
		axellcore_atelierclub_run( 'git push origin ' . escapeshellarg( $branch ) . ' --quiet', $plugin_dir, true );
		axellcore_atelierclub_run( 'git push origin ' . escapeshellarg( $version ) . ' --quiet', $plugin_dir, true );

		WP_CLI::success( "Released {$version}." );
	}

	/**
	 * Merge the current POT into every languages/*.po, report
	 * untranslated/fuzzy strings, and (when all are translated) push the
	 * language/<version> branch the Language workflow packs.
	 *
	 * ## OPTIONS
	 *
	 * [--branch]
	 * : Push the language/<version> branch. Default true — pass --no-branch
	 * to only merge and report.
	 *
	 * ## EXAMPLES
	 *
	 *   wp --require=bin/release.php axc language
	 *   wp --require=bin/release.php axc language --no-branch
	 *
	 * @subcommand language
	 * @when before_wp_load
	 */
	public function language( array $args = array(), array $assoc_args = array() ): void {
		axellcore_atelierclub_require_cmd( 'wp' );

		$plugin_dir = axellcore_atelierclub_plugin_dir();
		$pot_file   = $plugin_dir . '/languages/axellcore-atelierclub.pot';
		$po_files   = glob( $plugin_dir . '/languages/axellcore-atelierclub-*.po' ) ?: array();

		if ( array() === $po_files ) {
			WP_CLI::log( 'No languages/axellcore-atelierclub-*.po files yet — nothing to merge.' );
			WP_CLI::log( "Add one (e.g. axellcore-atelierclub-pt_BR.po) and re-run 'wp axc language'." );
			return;
		}

		$missing_all = array();

		foreach ( $po_files as $po_file ) {
			$locale = preg_replace( '/^axellcore-atelierclub-/', '', basename( $po_file, '.po' ) );
			WP_CLI::log( "  → merging pot into {$locale}" );
			// Same merge as the release (wp i18n), so the .po keeps its formatting.
			axellcore_atelierclub_run(
				'wp i18n update-po ' . escapeshellarg( $pot_file ) . ' ' . escapeshellarg( $po_file ) . ' --quiet',
				$plugin_dir,
				true
			);

			$missing = axellcore_atelierclub_po_missing( $po_file );
			if ( array() !== $missing ) {
				$missing_all[ $locale ] = $missing;
			}
		}

		if ( array() !== $missing_all ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Untranslated / fuzzy strings:' );
			foreach ( $missing_all as $locale => $strings ) {
				WP_CLI::log( "[{$locale}]" );
				foreach ( $strings as $string ) {
					WP_CLI::log( "  {$string}" );
				}
			}
			return;
		}

		WP_CLI::success( 'All languages fully translated.' );

		// ── language/<version> orphan branch: .po + JS catalogs (.json) ──────────────
		// Pushing it runs the Language workflow, which compiles the .mo and
		// attaches axellcore-atelierclub.<version>-<locale>.zip to the release.

		if ( false === ( $assoc_args['branch'] ?? true ) ) {
			return;
		}

		$current     = axellcore_atelierclub_current_version( $plugin_dir . '/axellcore-atelierclub.php' );
		$lang_branch = "language/{$current}";
		$lang_repo   = sys_get_temp_dir() . '/axellcore-atelierclub-lang-' . uniqid();
		mkdir( $lang_repo, 0755, true );

		$remote_url = trim( axellcore_atelierclub_run( 'git remote get-url origin', $plugin_dir, true ) );
		$git_name   = trim( axellcore_atelierclub_run( 'git config user.name', $plugin_dir, true ) );
		$git_email  = trim( axellcore_atelierclub_run( 'git config user.email', $plugin_dir, true ) );
		$today_iso  = gmdate( 'Y-m-d\TH:i:s+00:00' );

		foreach ( $po_files as $po_file ) {
			$dest = $lang_repo . '/' . basename( $po_file );
			$po   = (string) file_get_contents( $po_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// SelfDirectory compares the installed pack's Project-Id-Version with the release.
			$po = preg_replace( '/^"Project-Id-Version:.*$/m', '"Project-Id-Version: axellcore-atelierclub ' . $current . '\\n"', $po );
			$po = preg_replace( '/^"PO-Revision-Date:.*$/m', '"PO-Revision-Date: ' . $today_iso . '\\n"', $po );
			file_put_contents( $dest, $po ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		foreach ( glob( $plugin_dir . '/languages/axellcore-atelierclub-*.json' ) ?: array() as $json ) {
			copy( $json, $lang_repo . '/' . basename( $json ) );
		}

		axellcore_atelierclub_run( 'git init --quiet', $lang_repo, true );
		axellcore_atelierclub_run( 'git remote add origin ' . escapeshellarg( $remote_url ), $lang_repo, true );
		axellcore_atelierclub_run( 'git checkout --orphan ' . escapeshellarg( $lang_branch ) . ' --quiet', $lang_repo, true );
		axellcore_atelierclub_run( 'git add .', $lang_repo, true );
		axellcore_atelierclub_run(
			'git -c user.name=' . escapeshellarg( $git_name ) . ' -c user.email=' . escapeshellarg( $git_email )
				. ' commit --quiet -m ' . escapeshellarg( "i18n: language packs for {$current}" ),
			$lang_repo,
			true
		);
		WP_CLI::log( "  → pushing {$lang_branch}" );
		axellcore_atelierclub_run( 'git push origin ' . escapeshellarg( $lang_branch ) . ' --force --quiet', $lang_repo, true );
		axellcore_atelierclub_run( 'rm -rf ' . escapeshellarg( $lang_repo ) );

		WP_CLI::success( "Language branch {$lang_branch} pushed." );

		// A push to an orphan branch runs no workflow (the branch has no
		// .github/), so the Language workflow is dispatched from the default branch.
		list( $gh_exit ) = axellcore_atelierclub_try_run( 'command -v gh' );
		if ( 0 !== $gh_exit ) {
			WP_CLI::warning( "gh CLI not found — run the Language workflow by hand with version={$current}." );
			return;
		}
		$repo = preg_replace( array( '#.*github\.com[:/]#', '#\.git$#' ), '', $remote_url );
		axellcore_atelierclub_run( 'gh workflow run language.yml --repo ' . escapeshellarg( $repo ) . ' --field version=' . escapeshellarg( $current ), $plugin_dir, true );
		WP_CLI::success( "Language workflow dispatched: it attaches axellcore-atelierclub.{$current}-<locale>.zip to the {$current} release." );
	}
}

WP_CLI::add_command( 'axc', 'Axellcore_Atelierclub_CLI_Command' );
