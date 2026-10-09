=== Atelier Axell ===
Contributors: axell
Tags: axell, atelier, landing-page, blocks
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-contained landing page (FSE template + core blocks + a custom application-form block) for the Atelier Axell Club invite program.

[Preview the latest release in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelier/main/blueprint.json) · [Preview the latest `main` build](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/axellhydrosystems/axellcore-atelier/main/blueprint-dev.json)

== Description ==

The Atelier Axell Club landing (`/atelier`) built from native WordPress blocks: every section is core blocks styled with block attributes, presets, the plugin's block styles and per-block custom CSS, matched pixel for pixel to the approved mockup. The header and footer are template parts. The application form is a family of `axell/form*` blocks (labels, controls, address with state and city, CPF/CNPJ, partner stores) with Interactivity API behaviour; each submission creates a pending member (a user), reviewed in Atelier > Members. Without JetEngine the plugin also registers the `revendas` post type with production's signature and imports the bundled revendas on activation.

== Changelog ==

= 0.6.0 =
* User profile: Google's address suggestions on the street field, as on the application form (with Google Maps' dark gray logo on the light screen). Choosing an address fills in street, number, complement, neighborhood, state and city (its exact name, without opening its list) and CEP; nothing is saved until "Update User".
* Address suggestions: the street field keeps what was typed and the street chosen (a value set by the server came back on every render); neighborhood and CEP follow the address chosen, and a complement an earlier address filled in goes with it (one typed by hand stays).

= 0.5.0 =
* Atelier > Settings > Integrations: reCAPTCHA v3 (default) or v2 on the forms, each with its keys and the v3 score threshold, checked with Google before anything is stored; on by default once the keys are filled, and off on local sites (localhost, 127.0.0.1, .local and .test addresses, or the "local" environment type) unless unchecked.
* Atelier > Settings > Integrations: Google Maps Platform (Places API New) with the API key, a note on its billing and costs, and the address cache with "Clear address cache".
* Application form: Google's address suggestions under the street field, through this site (the key never reaches the browser), cached for a day and rate limited per address. Choosing one fills in street, number, complement, neighborhood, state, city and CEP; at most 4 suggestions, "Searching addresses…" and "No address found.", Google Maps' logo as the attribution. Every field can still be filled in by hand.
* Application form: main practice, registration type and the UFs (address and custom store) as selects with a list, as the profile's: the native select is still what is sent and validated. Main practice and the UFs have a filter; a UF is listed as "SP · São Paulo" and shown as "SP".
* Members list: filters only on state, practice and status, all on the server; states by name, and the state filter offers only the states that have members.
* The Atelier name and its description move to the General tab.

= 0.4.0 =
* Atelier > Settings: choose the Atelier page (its slug can change), on tabs (General, Members, E-mails) in the WooCommerce mold.
* Member fields on the user edit and profile screens, in the application form's order, as WooCommerce's customer fields: saved all or nothing (the screen refilled with the error highlighted), masks for phone, CEP and CPF/CNPJ, searchable selects for state, main practice, profile type and country, city autocomplete, partner stores chosen from the resellers.
* Member data in WooCommerce's billing keys: first and last name, phone as +55 and digits, CEP and document without masks, country Brazil only. The profile type is inferred from the document; the main practice is stored by its label; partner stores as reseller_ids, a store typed as text becoming a pending reseller.
* Approval: "Approve" in the users' list (row and bulk) and an "Atelier: Status" group on the profile, whose "Approve member" saves the screen and approves, if there is no error.
* Access: a pending member can never log in or reset the password; a member reaches the dashboard only when "Allow members to log in to the dashboard" is on. The member role has a subscriber's capabilities. No "Switch To" a blocked user.
* Transactional e-mails (new application to the team, application pending, member created, membership approved), on by default, with subject, heading and text editable with placeholders, saved only when they differ from the site language's default. HTML template in the WooCommerce mold and the Atelier's colors, light or dark with the device, with a header logo (none, the site's or a custom one; the bundled Atelier logo by default) and its width, the Atelier name and a description.
* Portfolio address: https:// added when typed without it, refused when not valid, on the form, the profile and the Members screen.
* Application form: a store typed but not chosen stops the submission.
* No other plugins' notices on the users', resellers' and Settings > General screens.
* Design tokens and the Reveal stylesheet only on the Atelier pages; page caches purged when the plugin changes what pages show.

= 0.3.0 =
* Runs on a classic theme with page builders (hello-elementor, Elementor, JetEngine, Weglot, Yoast), as on production: /atelier and its editor render as on the block theme the bases were approved on (Twenty Twenty-Five's theme.json and the saved Site Editor styles, layout support, the template's Post Content, no theme or page builder assets, popups or "Edit with Elementor" switch), at 0.00% against the bases on the page and in the editor.
* Header and footer editable as template parts in a classic theme (Appearance > Template Parts), the page rendering the saved parts.
* Activation creates only /atelier; the pure and per-section pages stay in content/ behind the axellcore_atelier_create_child_pages filter. Fix: the content kept its JSON escapes and the blocks' custom CSS (wp_slash, and WordPress 7's custom CSS filter), and the Hero cover its image id.
* Weglot is kept off /atelier. With Yoast, the page gets the Atelier meta description when it has none. Page styles inlined, no emoji script.
* Revendas: with JetEngine active its screens stay (the check ran before JetEngine loaded).
* Atelier > Settings, with "Create new members as pending".
* Application form: CPF and CNPJ (including the alphanumeric CNPJ) checked for check digits, profile type and uniqueness in the browser and on the server, with one message for each; the server also checks phone, CEP and resellers, and errors point to their field, with JavaScript and without it.
* The Atelier admin screens and the block editor without other plugins' notices.

= 0.2.2 =
* Fix: on a new install (Playground) the header menu was empty and the header and footer logos pointed at the exporting site. The header and footer template parts are now synced with the media and navigation of the install, as the pages already were, and without kses.

= 0.2.1 =
* Fix: logged in, the admin bar covered the Atelier header. The Sticky Header block now sits below it (32px, 46px on small screens), as WordPress sets in --wp-admin--admin-bar--height.

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
* Fix: opening the block editor for the `/atelier` page logged `wp_get_post_content_block_attributes()` PHP warnings ("Undefined array key 0", "Attempt to read property content on null") and `parse_blocks(null)` deprecation notices. Root cause: WordPress core's `get_block_templates()` returns results keyed by `plugin//slug` (a string) instead of sequentially when only a plugin-registered template matches (no theme file, no saved override) — a real core edge case, not something fixable by editing core. Worked around with a `get_block_templates` filter that restores sequential array keys for every caller.

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
