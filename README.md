# Axellcore — Atelier Club

**Contributors:** axell
**Tags:** axell, atelier, landing-page, blocks
**Requires at least:** 6.7
**Tested up to:** 7.1
**Requires PHP:** 7.4
**Stable tag:** 0.1.0
**License:** GPL-2.0-or-later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Self-contained landing page (FSE template + core blocks + a custom application-form block) for the Atelier Axell Club invite program.

[Preview the latest release in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelierclub/main/blueprint.json) · [Preview the latest `main` build](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelierclub/main/blueprint-dev.json)

## Description

Ships the Atelier Axell Club landing page as a plugin-owned FSE template (registered via `register_block_template()`, editable in the Site Editor, no theme header/footer) plus two small custom blocks (`axellcore/form`, `axellcore/form-input`) for the application form — every other section is composed from core WordPress blocks styled with the plugin's own `aa-`-prefixed stylesheet. On activation, the plugin provisions the `/atelier` page automatically if it doesn't already exist.

## Changelog

### 0.1.0
* FSE blank-canvas template registration, theme/core asset suppression, design tokens + section CSS ported from the approved static mockup, shared frontend behavior (nav scroll state, scroll-reveal, CPF/CNPJ/CEP/phone input masks), and the `axellcore/form` / `axellcore/form-input` blocks.
* Full landing-page content (all 14 sections + the Adesão application form) authored as core-block markup and seeded automatically on activation — pixel-matched against the approved mockup and verified in-browser.
* Fix: bypass KSES for the plugin's own trusted seed content on activation — WP-CLI/no-user contexts were silently stripping `<select>`/`<input>` tags from the form on insert.
* pt_BR translation (46/46 strings) alongside the English source.

---

## Development

### Requirements

| Tool | Version |
|---|---|
| PHP | ≥ 7.4 (tooling: 8.1+) |
| Composer | ≥ 2 |
| WP-CLI | latest |

### Scripts

```bash
composer install       # install dev dependencies (PHPCS/WPCS, PHPStan, PHPUnit, Brain\Monkey)
composer test           # PHPUnit unit tests (tests/unit)
composer analyse         # PHPStan (level 5, WordPress stubs)
composer lint             # PHPCS against WPCS + PHPCompatibility
composer lint:fix          # PHPCBF auto-fix
```

### Architecture

```
axellcore-atelierclub.php     # bootstrap: header, constants, requires, activation hook
includes/
├── class-plugin.php          # loader — wires the other classes' hooks on boot()
├── class-template-loader.php # registers the "Atelier — Blank Canvas" FSE template
├── class-assets.php          # enqueues aa-tokens/aa-sections/aa-frontend; strips theme/core CSS
├── class-blocks.php          # registers axellcore/form + axellcore/form-input
├── class-activator.php       # creates the /atelier page + header/footer template parts on activation (idempotent)
└── blocks/form/
    ├── form/                 # axellcore/form — <form> wrapper (static block, InnerBlocks)
    └── form-input/           # axellcore/form-input — text/email/tel/select/checkbox/hidden field
templates/
└── atelier-club.html         # FSE template content: just <!-- wp:post-content /-->
assets/
├── css/{tokens,sections}.css # design tokens + component CSS, ported from the approved mockup, aa-* prefixed
└── js/frontend.js            # nav scroll state, scroll-reveal, CPF/CNPJ/CEP/phone input masks
```

### Release tooling

Two independent implementations of the same release flow (bump version, validate the `readme.txt` changelog entry, regenerate translation catalogs, commit/tag/push) — pick whichever fits your workflow:

```bash
bin/release.sh patch                                    # bash
wp --require=bin/release.php axc release patch           # WP-CLI
```

Both support `--no-commit` / `--no-tag` / `--no-push` (dependency rules apply: `--no-commit` implies the other two).

```bash
bin/release.sh language                                  # merge languages/*.pot into *.po, list untranslated strings
wp --require=bin/release.php axc language
```

### WordPress Playground

`blueprint.json` installs the latest tagged GitHub Release; `blueprint-dev.json` installs the latest `main` build via the `PR Preview - Build` workflow's `plugin-zip` artifact. Every pull request also gets its own live preview link posted by `PR Preview - Publish`. Both install [WP KSES SVG](https://github.com/camaleaun/wp-kses-svg) first (SVG uploads, sanitized), since the Atelier logo is an SVG.
