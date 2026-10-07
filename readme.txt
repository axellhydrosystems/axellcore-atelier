=== Axellcore — Atelier Club ===
Contributors: axell
Tags: axell, atelier, landing-page, blocks
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-contained landing page (FSE template + core blocks + a custom application-form block) for the Atelier Axell Club invite program.

[Preview the latest release in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelierclub/main/blueprint.json) · [Preview the latest `main` build](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelierclub/main/blueprint-dev.json)

== Description ==

The Atelier Axell Club landing (`/atelier`) built from native WordPress blocks: every section is core blocks styled with block attributes, presets, the plugin's block styles and per-block custom CSS, matched pixel for pixel to the approved mockup. The header and footer are template parts. The application form is a family of `axell/form*` blocks (labels, controls, address with state and city, CPF/CNPJ, partner stores) with Interactivity API behaviour; each submission creates a pending member (a user), reviewed in Atelier > Members. Without JetEngine the plugin also registers the `revendas` post type with production's signature and imports the bundled revendas on activation.

== Changelog ==

= 0.2.0 =
* The /atelier landing rebuilt in native blocks, section by section (pure, then styled at 0.00% against the approved bases), assembled on one page; header and footer as template parts. The fixed header uses the Sticky Header block (scrolled state past 40px).
* The page renders with the theme's global styles: the legacy isolated template, its stylesheets (sections.css, tokens.css, blocks-bridge.css) and Google Fonts are gone. Smooth scrolling to anchors, reveal on scroll per block, and the first-screen fonts preloaded.
* Application form: members are users (pending until approved) with an admin (Atelier > Members) and a CSV export; address controls (country, state, city combobox), CPF/CNPJ, phone and postal masks; partner stores with search over revendas or a new store (name, UF, city), compacted on submission; the widgets reset after a successful submission.
* Revendas: the post type, taxonomies and meta of production's JetEngine setup registered when JetEngine is not active, with a DataViews admin and a one-time import of the bundled CSV on activation. Replaces the axellcore-revendas plugin.
* Blueprints set the São Paulo timezone and Brazilian date and time formats.
* Source strings in English, with the Portuguese in the pt_BR translation; scripts load their translations from the plugin or an installed language pack.
* Updates from GitHub releases in the Plugins screen (SelfDirectory), with pt_BR language packs; the Playground blueprint installs the release in pt_BR.

= 0.1.2 =
* Fix: the consent-checkbox field (`axellcore/form-input`, "Li e concordo…") failed block validation in the editor ("Expected tag name `div`, instead saw `label`"). Root cause: its `label` attribute was redundantly duplicated into the block comment's JSON *and* the stored HTML — for this one field the label contains an embedded `<a href=\"#\">` with escaped quotes, which PHP's block-comment parser can't handle, silently returning `attrs = null` for the whole block (confirmed via `parse_blocks()` against the real stored content). `label` is `source:"rich-text"`, so WordPress already derives it from the HTML — it was never meant to be duplicated into the JSON attrs. No other field's label happened to contain embedded HTML, which is why only this one broke.
* Fix: opening the block editor for the `/atelier-club` page logged `wp_get_post_content_block_attributes()` PHP warnings ("Undefined array key 0", "Attempt to read property content on null") and `parse_blocks(null)` deprecation notices. Root cause: WordPress core's `get_block_templates()` returns results keyed by `plugin//slug` (a string) instead of sequentially when only a plugin-registered template matches (no theme file, no saved override) — a real core edge case, not something fixable by editing core. Worked around with a `get_block_templates` filter that restores sequential array keys for every caller.

= 0.1.1 =
* Replaced almost all Custom HTML blocks in the seeded content with real core-block composition (Group/Columns/Paragraph/Heading/List/Buttons) — nav, hero, "A Placa" visual, tier lock-marks, benefit prize chips, editorial rows/cards, CTA strip, and the footer are now genuinely WYSIWYG-editable, verified live in the block editor (no "invalid block" warnings). Only 4 tiny, structurally-justified Custom HTML blocks remain (3 purely-decorative empty layers, plus the 5 partner-store inputs).
* Registers a small icon set via WordPress 7.1's Icons API (`wp_register_icon_collection()`/`wp_register_icon()`) and renders the lock/clock glyphs with the native `core/icon` block instead of inline SVG.
* The form's submit control is now a real `core/button` with `tagName:"button"`/`type:"submit"` (confirmed via the block editor's Code editor round-trip) — native form-submit semantics instead of an `<a>` styled as a button.
* CTA arrow icons moved from inline SVG to a CSS `mask-image` pseudo-element on `.aa-btn`/`.aa-tier-cta` (see assets/css/blocks-bridge.css), so `core/button` needs only its "Additional CSS class(es)" field, no per-instance markup.

= 0.1.0 =
* FSE blank-canvas template registration, theme/core asset suppression, design tokens + section CSS ported from the approved static mockup, shared frontend behavior (nav scroll state, scroll-reveal, CPF/CNPJ/CEP/phone input masks), and the `axellcore/form` / `axellcore/form-input` blocks.
* Full landing-page content (all 14 sections + the Adesão application form) authored as core-block markup and seeded automatically on activation — pixel-matched against the approved mockup and verified in-browser.
* Fix: bypass KSES for the plugin's own trusted seed content on activation — WP-CLI/no-user contexts were silently stripping `<select>`/`<input>` tags from the form on insert.
* pt_BR translation (46/46 strings) alongside the English source.
