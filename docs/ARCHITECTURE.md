# Atelier Axell: Architecture & Current Status

This document exists because a lot changed in one working session without a
commit checkpoint. It's a honest snapshot — what's solid and tested, what's
half-built, and what's a known gap — grouped by subsystem, so you can decide
what to keep, finish, or rewrite before anything else gets layered on top.

**Update:** the two items originally flagged here as the biggest risks — the
content generator living outside the repo, and the i18n pipeline never
actually working — have both been fixed and verified (§6, §7). The chapters
block (§5b) is now also finished. What follows was written before those
fixes and has been updated in place; §8 lists exactly what's committed vs.
still pending.

---

## 1. What this plugin does

Ships the "Atelier Axell" landing page at `/atelier` as a **plugin-owned
FSE template** (`register_block_template()`, WP 6.7+), fully isolated from
the active theme (no theme header/footer/global-styles CSS loads on this
page). Content is authored as **WordPress core blocks** (Group, Columns,
Heading, Paragraph, List, Buttons) styled via `aa-`-prefixed CSS classes —
custom blocks exist only where core blocks can't do the job (the
application form, and now the chapter-numbering wrapper).

Page content is **seeded once on plugin activation**, then fully
panel-editable afterward like any normal WordPress page.

---

## 2. Group: FSE Template + Template Parts

**Status: solid, tested.**

- `includes/class-template-loader.php` registers the template
  (`axellcore-atelier//atelier` — note: this internal registration
  name is unrelated to the page's URL slug, see §4 below) via
  `register_block_template()`.
- `templates/atelier.html` is just:
  ```html
  <!-- wp:template-part {"slug":"axellcore-header","tagName":"header","area":"header"} /-->
  <!-- wp:post-content /-->
  <!-- wp:template-part {"slug":"axellcore-footer","tagName":"footer","area":"footer"} /-->
  ```
- Header/footer are **real `wp_template_part` posts** (not embedded in the
  page content), editable via Site Editor → Patterns → Template Parts. They
  render through `core/template-part`'s own `WP_Query` lookup path — this
  does NOT touch `WP_Block_Templates_Registry` at all, so it's unaffected by
  the registry bug below.
- A real WordPress core bug was found and worked around:
  `get_block_templates()` returns string-keyed results (`plugin//slug` as
  the array key) instead of sequential when only a plugin-registered
  template matches. Core's own `wp_get_post_content_block_attributes()`
  then does `$current_template[0]->content` unconditionally and throws PHP
  warnings. Fixed via a `get_block_templates` filter
  (`Template_Loader::reindex_block_templates()`) that restores sequential
  keys. Covered by regression tests in `tests/unit/TemplateLoaderTest.php`.

**Verification:** confirmed via `parse_blocks()` (no null-attrs blocks),
`wp-content/debug.log` staying clean, and live browser checks (no "invalid
block" warnings, no console errors) at every stage this session.

---

## 3. Group: Assets & CSS isolation

**Status: solid on the frontend. One known gap: editor canvas.**

`includes/class-assets.php`:
- On `template_redirect`, unhooks the theme's own stylesheet, WP's
  `wp_enqueue_global_styles`, and `wp_common_block_scripts_and_styles` — all
  confirmed via direct `curl` of this install's `<head>` output.
- Enqueues `aa-google-fonts`, `aa-tokens`, `aa-sections`,
  `aa-blocks-bridge` (CSS) + `aa-frontend` (JS), gated to
  `is_page_template( Plugin::TEMPLATE_SLUG )`.

**Known gap, not yet fixed:** there is **no `enqueue_block_editor_assets`
hook**. None of the plugin's own CSS loads inside the block editor iframe —
meaning the editor canvas currently shows default WordPress styling (wrong
colors, wrong fonts, wrong spacing), not the actual Axell dark design,
while editing. This was flagged mid-session ("tudo sem as cores certas no
editor") but the fix (mirroring the frontend enqueue, scoped to
`get_page_template_slug( $post ) === Plugin::TEMPLATE_SLUG`) has **not been
implemented yet**.

---

## 4. Group: URL & slugs — two different things, don't conflate them

- **Page URL**: `/atelier` — `Activator::PAGE_SLUG`. Just renamed this
  session from `/atelier`. Updated everywhere: Activator, both
  Playground blueprints, the PR-preview workflow's inline blueprint, docs,
  and the actual local Studio page (`post_name` updated via `wp post
  update`).
- **FSE template registration name**: `axellcore-atelier//atelier`
  — `Plugin::TEMPLATE_NAME` / `Plugin::TEMPLATE_SLUG`. This is an internal
  WordPress template identifier (used for `_wp_page_template` postmeta and
  `is_page_template()` checks), **not a URL**. Deliberately left unchanged —
  renaming it would touch the template file name and registration but has
  zero user-facing effect, so wasn't worth the churn right now.

Playground blueprints (`blueprint.json`, `blueprint-dev.json`, and the
inline one in `.github/workflows/pr-preview-publish.yml`) now use the
native `"landingPage": "/atelier"` field plus a `runPHP` step that sets
`/%postname%/` permalinks (needed for `/atelier` to actually resolve —
Playground defaults to ugly permalinks). This replaced an earlier
`get_page_by_path()` + `wp_safe_redirect()` runPHP hack.

---

## 5. Group: Custom blocks

### 5a. `axellcore/form` + `axellcore/form-input` — **solid, tested, in production content**

The application form. Static blocks (no PHP render), plain browser JS
(`window.wp.*` globals, no build step — same pattern core used before
`@wordpress/scripts`). `form-input` supports text/email/url/tel/textarea/
select/checkbox/hidden, plus a `mask` attribute (`cpf-cnpj`/`cep`/`phone`)
and a `consent` variant for the LGPD checkbox.

**A real, subtle bug was found and fixed here**: `label` was declared
`source:"rich-text"` in `block.json` (meaning WP derives it from the stored
HTML) but the Python content generator was ALSO duplicating it into the
block comment's JSON attrs. For the one field whose label contained an
embedded `<a href="#">` (the consent checkbox), the escaped quotes broke
PHP's block-comment parser, silently returning `attrs = null` for that
whole block. Fixed by never duplicating rich-text-sourced attributes into
the JSON blob. Worth remembering as a general rule for any future block
with a `rich-text`-sourced attribute.

### 5b. `axellcore/chapters` + `axellcore/chapter` — **finished, tested, verified.**

Solves: the 9 (really 10 — see below) numbered "Capítulo NN · Section Name"
section headers had the number **hand-typed as literal text**, so the
numbering was fragile (the original approved mockup itself already has a
gap — it jumps from "Capítulo 09" to "Capítulo 11", skipping 10 — proving
this fragility was real, not hypothetical).

`axellcore/chapters` is a wrapper (`InnerBlocks` restricted to
`axellcore/chapter`) establishing a CSS counter scope. Each
`axellcore/chapter` has a plain-string `label` attribute (e.g. "Manifesto")
and its own `InnerBlocks` for that section's actual content. **Nothing about
the visible "Capítulo NN" text is stored:**
- the **number** is a CSS counter (`.aa-chapters{counter-reset:aa-chapter}`
  / `.aa-chapter{counter-increment:aa-chapter}`) — reordering, adding, or
  removing a chapter in the editor renumbers every chapter automatically.
- the **word** ("Capítulo"/"Chapter") is a real, runtime-translated gettext
  string, injected as a `data-chapter="…"` HTML attribute — server-side via
  `Blocks::render_chapter()` (a `render_callback` using PHP `__()`, since a
  block with a JS `save()` still needs one to reach the frontend without
  freezing the word into `post_content`) and client-side via `wp.i18n.__()`
  in `edit()` (so the editor canvas matches — a block with its own `save()`
  never calls `render_callback` for its own canvas preview). CSS reads it
  back with `content: attr(data-chapter) " " counter(aa-chapter,
  decimal-leading-zero) " · "`.
- All 10 sections (Convite, Manifesto, A Placa, Protagonistas, Proposta, O
  Nome, Como Entrar, Níveis, Benefícios, Editorial) are wrapped by
  `bin/generate-content.py`'s `CHAPTERS` list + `chapters_block()`/
  `chapter_block()` helpers.

**Verified:** `parse_blocks()` shows 0 null-attrs across 313 blocks on the
live page; frontend renders "CAPÍTULO 01 · CONVITE" correctly (confirmed via
screenshot, pt_BR locale); editor canvas's `edit()` independently confirmed
showing `data-chapter="Capítulo"` too (parity with frontend); no console
errors, no invalid-block warnings, in either context.

---

## 6. Group: Content generation pipeline — **fixed, now lives in the repo**

All page/header/footer content is generated by `bin/generate-content.py`
(moved into the repo this session — it used to live in a Claude Code
session scratchpad directory, which is not durable and was the single
biggest structural risk in the project). Run it after changing anything in
it:

```
python3 bin/generate-content.py
```

It builds Gutenberg block markup (helper functions for `core/group`,
`core/columns`, `core/list`, the `axellcore/chapters`/`axellcore/chapter`
pair, etc.) and writes three files: `content/seed-content.html`,
`content/header-part.html`, `content/footer-part.html`, which
`Activator::activate()` reads on plugin activation. `PLUGIN_DIR` is resolved
relative to the script's own location (its grandparent directory), not
hardcoded to any one machine.

**Treat `content/*.html` as build output, not hand-authored source** — same
relationship a `.min.css` would have to its source in a project with a real
CSS build step. Don't hand-edit those three files directly; edit
`bin/generate-content.py` and regenerate.

---

## 7. Group: i18n — UI strings now genuinely translate. Page content still doesn't.

**Fixed this session — two real, independent bugs, both now verified working:**

1. **No compiled `.mo` file.** `languages/` had `.po` and `.pot` but PHP
   gettext (`load_plugin_textdomain()`) requires a compiled `.mo` binary —
   `.po` alone does nothing at runtime. `bin/release.sh`/`bin/release.php`
   ran `wp i18n make-pot` and `wp i18n make-json` but never `wp i18n
   make-mo`. Fixed: both scripts now run `make-pot` → `update-po` (merges
   new strings into every shipped `.po`, preserving existing translations)
   → `make-mo` → `make-json`, in that order, and a `.mo` has been compiled
   locally (`languages/axellcore-atelier-pt_BR.mo`).
2. **No `load_plugin_textdomain()` call anywhere.** Even with a valid `.mo`
   compiled, nothing was loading it — WordPress only auto-loads translations
   for plugins fetched from wordpress.org's own translation API, which this
   plugin isn't. This is the actual reason **every "translated" string had
   been silently inert since `0.1.0`**, independent of bug #1. Fixed: the
   main plugin file now calls `load_plugin_textdomain()` on `plugins_loaded`
   (priority 1, before anything in `includes/` — which registers on `init`
   — can call `__()`), plus a `Domain Path: /languages` header.

All 62 current UI strings (46 as of `0.1.2`, +16 added this session for the
chapters block and template-part titles) are translated in
`languages/axellcore-atelier-pt_BR.po`, merged via `wp i18n update-po`
so none were lost. **Verified live**: `studio wp eval` confirms `__('Chapter',
'axellcore-atelier')` resolves to `"Capítulo"` under the site's `pt_BR`
locale, and the actual page (screenshot-confirmed) renders "CAPÍTULO 01 ·
CONVITE" — both the number and the word are genuinely runtime-generated, not
stored text.

**What still does NOT work — a separate, much larger piece of work, not
started beyond the initial discussion:** the actual page COPY (all the
section headings/body text/labels — hundreds of strings) is **not
translatable at all**. It's static Portuguese HTML baked directly into
`content/*.html` by `bin/generate-content.py` — never passed through `__()`
in any form. Making English the canonical source and generating real
`.po`/`.mo` translations for the page content itself (flagged mid-session:
"conteúdo da pagina e header/footer deve estar todo em EN... com traducao
pt-br") is unrelated to the two bugs just fixed and remains fully open.

---

## 8. Work this session — everything below is done and verified

```
 M .github/workflows/pr-preview-publish.yml   — landingPage + permalink step
 M README.md                                  — /atelier references
 M assets/css/blocks-bridge.css               — apply-terms marker cleanup
 M assets/css/sections.css                    — icon fix follow-on, list→counter
                                                 refactor, chapters CSS
 M assets/js/frontend.js                      — reveal selectors, CPF/CNPJ
                                                 validation + alphanumeric CNPJ
 M axellcore-atelier.php                  — load_plugin_textdomain() (i18n fix)
 M bin/release.php                            — update-po + make-mo steps
 M bin/release.sh                             — update-po + make-mo steps
 M blueprint-dev.json                         — landingPage + permalink step
 M blueprint.json                             — landingPage + permalink step
 M content/seed-content.html                  — regenerated: numerals→lists,
                                                 CNPJ hint, chapters-wrapped
 M includes/class-activator.php               — /atelier slug + template-part
                                                 provisioning
 M includes/class-blocks.php                  — registers chapters/chapter +
                                                 render_callback
 M includes/class-icons.php                   — fill-path icons (was stroke)
 M languages/axellcore-atelier-pt_BR.po   — 16 new strings translated
 M languages/axellcore-atelier.pot        — regenerated
 M readme.txt                                 — /atelier references
 M templates/atelier.html                — template-part references
?? bin/generate-content.py                    — moved into the repo (was scratchpad)
?? content/footer-part.html                   — new (header/footer split out)
?? content/header-part.html                   — new
?? docs/                                      — this file
?? includes/blocks/chapters/                  — new block pair, finished
?? languages/*.json, *.mo                     — compiled translation catalogs
```

Verified this session (browser-tested throughout, no console errors, no
invalid-block warnings, PHPCS/PHPStan/PHPUnit all green): icon fill/path fix
· header/footer as real Template Parts · numeral→real-`<ol>` list
conversions across 7 sections · `/atelier` slug rename (plugin + local site
+ both blueprints + PR-preview workflow) · CPF/CNPJ validation including
the alphanumeric CNPJ format · `axellcore/chapters`/`axellcore/chapter`
fully wired into content with working `data-chapter` i18n · the i18n
pipeline itself (missing `.mo` compilation and missing
`load_plugin_textdomain()`, two independent bugs) · content generator moved
into the repo.

**Still open, not started this session:** editor-canvas CSS loading (§3),
the page-content i18n project (§7, EN source + pt-BR translation for the
actual page copy).

---

## 9. What's still worth doing next

1. **Planned next session** (§10): retire `/atelier-noclass`, apply what it
   proved to the production page, target 0.00% visual regression vs. the
   source mockup via screenshot comparison.
2. The editor-canvas CSS gap (§3) — `enqueue_block_editor_assets`, scoped to
   this template.
3. The page-content i18n project (§7) — a genuinely large piece of work,
   separate from the two i18n bugs already fixed.
4. Nothing else here is a known blocker.

---

## 10. Group: `/atelier-noclass` — an experiment, not a second production page

`/atelier-noclass` (`bin/generate-noclass-content.py`,
`templates/atelier-noclass.html`, `content/noclass-full.html`) proved
that this design is achievable almost entirely via native block style
attributes instead of `aa-` CSS classes — see the
`wp-native-block-styling` global skill (`~/.claude/skills/`) for the full,
reusable writeup of what was learned (which block supports process
server-side vs. purely in the editor, the `position:sticky`/`layout`
theme.json gotchas, the confirmed hard limits).

**Explicit plan for the next session**: this was always meant to be a
scouting exercise, not a permanent second page. The next step is to:
1. Retire the `/atelier-noclass` template/page/generator entirely (they
   don't ship — no reason to maintain two parallel content pipelines).
2. Apply the same native-attribute-first approach directly to the
   *production* `/atelier` page/generator (`bin/generate-content.py`),
   replacing `aa-` classes wherever a native attribute genuinely covers
   it, and keeping `assets/css/sections.css` only for the confirmed hard
   limits (CSS counters/pseudo-elements/backdrop-filter/transitions/
   multi-layer gradients — see the skill for the exact list).
3. Verify with **0.00% visual regression** against the original approved
   mockup (`source/index.html`), via direct screenshot comparison
   (chrome-devtools MCP), not just "looks close" — the noclass experiment
   was judged that way because it was explicitly a proof-of-concept; the
   production page is held to full pixel parity, same as it always has
   been since `0.1.0`.

Until that work happens, `/atelier-noclass` remains live for reference but
should not be extended further (no form section, no fixes beyond what's
already there) — it's disposable scaffolding for the real task.
