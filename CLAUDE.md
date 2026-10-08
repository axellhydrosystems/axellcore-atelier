# axellcore-atelierclub — plugin notes for Claude

This file is plugin-scoped context, separate from `docs/ARCHITECTURE.md` (the
session-status log of what's built/tested/pending). Put durable "why things
are the way they are" notes here; put "what happened this session" in
`docs/ARCHITECTURE.md`.

## `axellcore/form*` — heritage: Gutenberg's removed `core/form` experiment

`axellcore/form` + `axellcore/form-input` (`includes/blocks/form/`) were
heavily inspired by Gutenberg's own experimental form block family —
`core/form`, `core/form-input`, `core/form-submit-button`,
`core/form-submission-notification` — gated behind the `gutenberg-form-blocks`
experiment flag. That experiment existed through the Gutenberg plugin's
**v23.9.1** release and was removed in **24.0.0** by
[PR #82451 "Block Library: Remove the form blocks experiment"](https://github.com/WordPress/gutenberg/pull/82451)
(merged 2026-09-04).

**This is not a dependency and not a fork.** `axellcore/form*` is an
independent, from-scratch implementation (no build step, plain browser JS
against `window.wp.*` globals — see `includes/blocks/form/form/index.js`'s
header comment). The removed experiment's absence from current core has zero
effect on this plugin. The relationship is purely "informed the design."

A trimmed, read-only copy of the reference block family's v23.9.1 source (22
files: `block.json`/`edit.js`/`save.js`/`index.php`/`style.scss`/
`editor.scss`/`variations.js` for each of the four blocks, GPL-2.0-or-later,
same license as this plugin) is vendored at
`docs/reference/gutenberg-core-form-v23.9.1/` — see that folder's own
`README.md` for the full citation. Consult it before changing
`axellcore/form*`'s editor UX, rather than re-cloning Gutenberg.

### Deliberate deviations from the reference (not oversights — don't "fix" these back)

- **Fixed REST submission target.** `axellcore/form` has no
  `submissionMethod`/email/custom-action Inspector like `core/form` does — we
  always POST JSON to `/wp-json/axellcore-atelierclub/v1/members`
  (`includes/class-rest.php`). The reference's email-vs-custom split doesn't
  apply here.
- **Fixed dark visual design via `aa-` CSS classes**, not the reference's
  native `color`/`border` block supports on the input field. The mockup's
  field chrome (`assets/css/sections.css`'s `.aa-field`/`.aa-consent`
  rules) is fixed brand design ported 1:1 from the approved
  `source/index.html` mockup, not meant to be per-field-themeable by an
  editor — adding color/border supports would let someone accidentally break
  pixel-parity with the approved design. See "What's still worth doing next"
  in `docs/ARCHITECTURE.md` for the project's standing pixel-parity
  commitment.
  **Superseded** — see "`axell/form-label` + `axell/form-control` +
  `axell/fieldset`" below: full native color/border/typography/spacing
  supports were added after all, once the field markup was split into
  separate label/control blocks. The pixel-parity risk this bullet warned
  about turned out to be avoidable — the generator's own default content
  still reaches the branded look via the *same* `.aa-field`/`.aa-consent`
  CSS classes as before (see below for how), the native supports are just
  *additionally available* for anyone who wants to override them.
- **Notification visibility via JS class toggling**, not the reference's
  full-page-reload + `?wp-form-result=` GET param + PHP `render_callback`
  filter. `axellcore/form` submits via `fetch()` (`assets/js/frontend.js`) and
  never navigates away, so there's no page reload to carry a GET param across
  — `axellcore/form-submission-notification` instead starts hidden
  (`display:none`) and gets `.is-active` toggled on the matching
  `[data-aa-notice-type="success"|"error"]` element after the fetch
  resolves.

### What was ported from the reference, and what's intentionally skipped

Ported (closing the editor/frontend ergonomics gap, without touching the
fixed visual design):

- Real, editable success/error notification blocks
  (`axellcore/form-submission-notification`) replacing a blocking
  `window.alert()` — the reference's actual pattern, adapted for our
  no-reload REST submission (see "deliberate deviations" above).
- An editor-canvas placeholder for hidden fields (dashed box, not a literal
  invisible `<input type="hidden">` sitting unselectable in the canvas).
- Per-type inserter variations for `axellcore/form-input` (Text/Email/URL/
  Phone/Number/Textarea/Select/Checkbox/Hidden), so picking a field type
  happens at insertion, matching the reference's `variations.js` pattern —
  the Inspector's Type dropdown still exists too, for changing an
  already-inserted field.
- Inspector reorganization: the field's `name` (HTML attribute, an
  implementation detail) moved under `InspectorControls group="advanced"`,
  matching where the reference puts it — not the main Settings panel.
- Label `RichText` accessibility parity (`aria-label`/`data-empty`).
- Auto-derived `name` from the label when left blank, mirroring the
  reference's `getNameFromLabel()` — implemented without the `remove-accents`
  npm dependency (this project has no JS build step), reusing the same
  accent-mapping approach already proven server-side in
  `includes/class-locations.php`.
- **Directly-editable placeholder in the editor canvas.** Found by running
  the real experimental block live (Gutenberg 23.9.1, `gutenberg-form-blocks`
  experiment enabled), not just reading its source: `core/form-input`'s
  `edit()` never disables its preview control — it's a genuine `<input>`/
  `<textarea>` with `value={placeholder}` and `onChange` wired straight to
  `setAttributes`, so typing on the canvas sets the placeholder directly.
  Our earlier version set `disabled: true` on every preview control
  unconditionally, so the *only* way to set a placeholder was the
  Inspector's Placeholder field. Fixed in `form-input/index.js`'s
  `FieldControl()` — text/email/url/number/tel/textarea previews are now
  live and directly editable, matching the reference's exact
  `aria-label`/fallback-placeholder pattern (`"Optional placeholder text"` /
  `"Optional placeholder…"`). `select`/`checkbox` previews were also
  un-disabled (clickable, matching the reference's un-disabled posture for
  every type) even though neither has reference behavior to mirror exactly.
- **`aria-required` on every field**, matching the reference's `save.js`/
  `edit.js` exactly (they set it unconditionally; we only had the native
  `required` attribute before). Mirrored in both `form-input/index.js` and
  `bin/generate-content.py`'s `_field_control_html()` (the Python mirror of
  `FieldControl(attrs, isSave=true)` — the block is static, so the
  generator's output IS the frontend HTML, must match exactly).
- **A real, block-scoped `editorStyle` (`form-input/editor.css`).** The
  single biggest gap, found by literally putting `axellcore/form-input` next
  to the real `core/form-input` on the same page (`/atelier/adesao`, both
  built from the same source copy): our editor canvas had **zero** layout
  CSS for this block. `assets/css/sections.css` only loads on the isolated
  `/atelier` frontend template (see §3/`docs/ARCHITECTURE.md`) — never in
  the editor iframe, and never on any OTHER page this block might appear on
  (e.g. a plain page built for comparison, on the default theme, outside
  our isolated template) — so `<label>` and `<input>` were two bare inline
  siblings inside a `<div>`, cramming onto the same line instead of
  stacking, with the browser's raw UA default `<input>` style showing
  through underneath (2px inset border, ~13px font, 1-2px padding).
  `core/form-input` never has this problem because it ships its own
  `style.scss` — structural rules only (`min-height: 2em`, `line-height: 2`,
  `border-width: 1px`, `border-style: solid`, `padding: 0 0.5em`,
  `font-size: 1em`, no color/font-family/spacing-scale) — which WordPress
  auto-loads in *every* context (frontend and editor, any page) via
  `block.json`'s `style` field.

  Fixed with a new `form-input/style.css`, ported verbatim from the
  reference's own rules, registered the same way (`"style": "file:./
  style.css"`, not `editorStyle` — an earlier, editor-only pass turned out
  to be insufficient once this block was tested on a plain page outside
  `/atelier`, since nothing styled it there either). Every rule is wrapped
  in `:where(...)` — the exact same technique the reference's own
  `style.scss` uses (`:where(.wp-block-form-input__input)`) — which
  contributes **zero specificity**, so `sections.css`'s normal-specificity
  `.aa-field input`/`.aa-field label`/`.aa-consent` rules always win on
  the isolated `/atelier` template regardless of stylesheet load order.
  Verified three ways: (1) `getComputedStyle()` diffed against the live
  reference block in the editor — border/padding/font-size/min-height/
  line-height byte-identical; (2) same diff on the frontend of a plain page
  outside `/atelier` — also byte-identical; (3) `getComputedStyle()` on the
  real `/atelier` production page — confirmed `sections.css`'s own values
  won untouched (`padding: 12px 0`, transparent background, the bronze/
  ivory border-bottom), zero regression.
- **Block icons.** None of this plugin's 5 custom blocks
  (`axellcore/form`, `axellcore/form-input`, `axellcore/chapters`,
  `axellcore/chapter`, and the new `axellcore/form-submission-notification`)
  had an `icon` set in `block.json` at all — every one fell back to
  WordPress's generic default block icon (indistinguishable from each
  other, and from any other unset block in the site). `axellcore/form` and
  `axellcore/form-input` now use the **exact same custom SVG icons** as the
  reference blocks — copied verbatim (path data only) from the scratchpad
  clone of `WordPress/gutenberg` at `v23.9.1`
  (`packages/block-library/src/form{,-input}/icons.js`), registered via the
  `icon` property in each block's `registerBlockType()` JS settings (NOT
  `block.json`'s `icon` field — that only accepts a plain Dashicon slug
  string, not custom SVG markup; a real icon element has to be built with
  `element.createElement('svg', ..., element.createElement('path', {d:...}))`
  in the block's own script instead, mirroring what the reference's
  bundler-based `icons.js` + `import { icon } from './icons'` achieves, just
  without a build step). `axellcore/form-submission-notification` keeps the
  plain Dashicon slug `feedback` in `block.json` — that's what the reference
  itself uses too (`core/form-submission-notification`'s own `block.json`
  has `"icon": "feedback"`, no custom SVG, so nothing to port there).
  `axellcore/chapters`/`axellcore/chapter` aren't part of the `core/form`
  lineage (no reference to match), so they keep sensible plain Dashicon
  slugs: `editor-ol` and `text-page`. Also added `icon: 'visibility'` to the
  "Hidden Input" entry in `form-input/variations.js`, copied verbatim from
  the reference's own `variations.js` (the only variation there with a
  per-variation icon override — also a plain Dashicon slug, not custom SVG).

Explicitly **not** ported (see `docs/reference/gutenberg-core-form-v23.9.1/`
for what these looked like, if reconsidering later):

- `core/form`'s `submissionMethod`/email/custom-action Inspector — doesn't
  apply, fixed REST target (see deviations above).
- Native `color`/`border` block supports on the input field — would risk
  pixel-parity with the fixed mockup design (see deviations above).
- A dedicated `axellcore/form-submit-button` wrapper block — a plain
  `core/buttons > core/button[type=submit]` styled via `.aa-submit-row`/
  `.aa-btn` already gets the same visual result; the reference's wrapper is
  only a thin styling `<div>`, not worth a whole extra block registration
  here.
- Auto-focus-on-insert (`ref.current.focus()`) — minor nicety, not
  implemented.

## Known gap this ties into

`docs/ARCHITECTURE.md` §3 already flags that there's no
`enqueue_block_editor_assets` hook — none of this plugin's CSS loads in the
block editor iframe. The hidden-field placeholder above sidesteps this with
an inline `style` attribute rather than a stylesheet rule, specifically to
avoid needing to solve that broader gap just for one field state. The broader
gap itself remains open.

## `axell/form-label` + `axell/form-control` + `axell/fieldset` — the field split

`axellcore/form-input` (the fused type/label/variant/hint/mask block) was
replaced by three composable blocks, and the whole form family's namespace
was renamed `axellcore/*` → `axell/*` (block names only — the plugin slug,
PHP namespace, textdomain, and `aa-` CSS prefix are all unchanged; scoped
to the form family, not `axellcore/chapters`/`axellcore/chapter`). Explicit
user request, for a concrete reason: **"quero ter os mesmos controles de
espaçamento, tipografia, cores, bordas etc que os demais blocos"** — native
WordPress Style panels (Typography/Color/Border/Dimensions), the same ones
any `core/paragraph` or `core/group` gets. A single fused field block can't
cleanly expose this (color/border targeting the label vs. the input is
ambiguous in one block).

### The three blocks

- **`axell/form-label`** (`includes/blocks/form/form-label/`) — a standalone
  label. Attributes: `text` (rich-text), `for` (id of the paired control),
  `required` (shows the `*`, independent of the control's own `required`
  attribute — the generator keeps them in sync, a live editor could
  theoretically desync them, an accepted tradeoff for real semantic
  correctness), `visuallyHidden` (the new toggle). Full typography/color/
  spacing supports.
- **`axell/form-control`** (`includes/blocks/form/form-control/`) — the
  actual input/select/textarea/checkbox/hidden. Its own root element IS the
  control itself (`useBlockProps()` applied directly, unlike the reference
  `core/form-input`, which needs to manually merge color/border props onto
  an inner element because its root is a separate wrapping div) — so native
  supports apply with zero extra plumbing. `id`/`name`/`required`/
  `placeholder`/`value`/`checked`/`options`/`mask`/`maskSourceName`/
  `citiesSourceName` — same 8 types and same mask/cities-cascade machinery
  as before, `name` falls back to `id` when blank. Full typography/color/
  border/spacing supports (border and color aren't declared for
  `axell/form-input`, the reference — this is genuinely *more* than the
  reference itself offers, since the user asked for parity with "os demais
  blocos" generally, not with the reference's own narrower choice).
- **`axell/fieldset`** (`includes/blocks/form/form-fieldset/`) — a
  real `<fieldset>`/`<legend>` pair (`legend` rich-text, empty → no
  `<legend>` element at all, genuine semantic optionality — not just an
  empty tag). No `layout` support of its own: a plain block container.

### Members are users (roles member_pending / member)

An adesão submission (`Members::create_from_params()`, store target
`member`, `Members::STORE`; the former `aa_member` post type value maps to
it) creates a WordPress user with role `member_pending` ("Membro
Pendente"); the curadoria approves by switching it to `member` (the
status select in the admin detail saves at once). There is no member post
type. Roles: `Member::register_roles()`.

- `user_login`: WooCommerce's e-mail fallback of
  `wc_create_new_customer_username()` (`Members::username_for()`): the part
  before the @ (the domain for sales/hello/mail/contact/info), sanitized,
  lower case; a taken login gets `-NNNN`.
- Unique: the e-mail (`aa_email_exists`, 409) and `br_revenue_id`, stored as
  digits (`aa_document_exists`, 409).
- Fields are user meta named exactly as the form fields (no prefix):
  `company`, `phone`, `professional_registration`, `primary_focus` (slug),
  `profile_type` (`individual`/`legal_entity`), `br_revenue_id`, `country`,
  `address_street`, `address_number`, `address_2`, `neighborhood`,
  `landmark`, `state`, `city`, `postal`, `reseller1`…`reseller5` and
  `reseller{N}_title`. `fullname` is `display_name`, `email` `user_email`,
  `url` `user_url`. `consent` is required, not stored.
- Location (`Members::location()`): the state is a code for BR/US (upper
  case) or a name elsewhere, 2+ characters, any case; the city is sent and
  stored by name, checked against the bundled IBGE list in Brazil.
- Admin: menu Atelier > Members (`admin.php?page=members`, one member
  `&member=<id>`), DataViews/DataForms over `Admin_Rest`
  (`WP_User_Query` on both roles; `PRIMARY_FOCUS_OPTIONS` labels the slugs).
- The CPF/CNPJ control accepts `individual`/`legal_entity` as well as
  `cpf`/`cnpj` (`docTypeOf`); address controls default to the fields
  `country` and `state`.

### Members CSV export (Atelier > Exportar)

`includes/class-members-export.php` (`Members_Export`, page
`admin.php?page=members-export`, plus an "Exportar" button on the members
list) is a port of axellcore's store exporter (`axellcore/includes/
import-export/`: `Axellcore_Exporter`, the export half of `Axellcore_Admin`,
`Axellcore_Fields::csv_line()`/`escape_cell()`; the chip picker and batch
loop of `assets/js/admin/import-export.js` in
`src/admin/members-export/index.ts`). Export only. It never calls axellcore
and every name is its own (AJAX `axellcore_atelierclub_members_export`,
admin-post `axellcore_atelierclub_members_download`, nonce, handle,
`window.aaMembersExport`, `uploads/axellcore-atelierclub-export/`, CSS
`aa-export-*`), so it works with or without axellcore active. Batches of
100 users (`WP_User_Query` on both member roles), UTF-8 BOM, formula
escaping; filters: status, "Cadastrados desde" (a day in the site timezone,
included; `date_query` on `user_registered`, for incremental exports), UF,
cidade, atuação; "Enviado em" is in the site timezone; columns: every member field
with PT headers (labels for status, atuação and tipo de cadastro, masked
CPF/CNPJ); the partner stores are one "Lojas parceiras" cell joined with
", " (a comma inside a store is escaped as `\,`, as axellcore's
`join_values()`). By default ID, Enviado em, Login and País are left out
(`default_columns()`); they can still be picked.

### Styled form (/atelier/adesao/, post 1049)

Styled by attributes on each form block (the controls, `form-check`,
`form-group` and `form-atelier` have color, border, typography and spacing
supports), with block custom CSS only for what has no attribute:
placeholder, focus (`!important`, the attribute's inline border and the
core `has-*-color` classes would win), select options, the legend
`NN — ` prefix (fieldset CSS), the stores' roman index and line
(`counter(slot, roman-period)`, `:focus-within` line in bronze-2) and the
checkbox. Font sizes of 14px and up go in the block CSS, not in
`typography.fontSize`, so the theme's fluid typography does not shrink
them. Fields stack under 782px by `style['@tablet'|'@mobile'].layout.columnSpan: 12`;
the stacked submit button is full width by `@tablet|@mobile dimensions.width`.
Block CSS takes no selector lists (`& a:hover, & a:focus` breaks the rule):
one rule per selector.

The suggestion lists (reseller and city) read `--aa-listbox-bg`, `-color`,
`-border`, `-font`, `-option-padding`, `-active-bg`, `-active-color`
(system colors otherwise); the Atelier form sets them in its block CSS.
The UF id is bound to the active field (`context.selectId` / `textId`,
computed on the server, kept by `callbacks.syncStateIds`), and the
reseller custom-store fields have ids (`{name}-custom-name|uf|city`), so
the form has no duplicate id and no field without id or name.

### Field layout: a core/group grid inside the fieldset

`axell/form-group` is a plain `display: block` field (label, control, help
text) with no width, row, column or grid option of its own. To place fields
side by side, the fieldset holds a `core/group` with
`layout: { type: 'grid', columnCount: 12 }` and `blockGap: 1rem`
(`metadata.name: 'Campos'`), and each form-group sets the core grid span
(`style.layout.columnSpan`, the "Extensão da coluna" control under
Dimensões). Fields that are not laid out (the hidden country) sit in the
fieldset outside the grid. The old `fieldWidth` (Largura slider, inline
`width`) is the form-group `v1` deprecation, which migrates a `%` width to
the span in twelfths. Spacing between fieldsets is the fieldset's own
default `margin-block: 1rem`; under the legend, `0.5rem`.

### `for`/`id` association — the more correct pattern, confirmed live

Label and control are independent sibling blocks, paired via a real HTML
`for`/`id` attribute pair — not the old implicit `<label>{text}{input}
</label>` nesting. Confirmed via browser testing: after this change, the
17 recurring "No label associated with a form field" console warnings on
`/atelier` **disappeared entirely** (they were present through the whole
rest of this session, on the old fused block) — the explicit `for`/`id`
pairing is a genuine, measurable accessibility improvement, not just a
theoretical one.

### The real migration path (and why "soft deprecation" instead of automatic migration)

`axellcore/form-input` stays registered (unchanged `edit`/`save`), with one
addition — `"supports": {"inserter": false}` in its `block.json` — so it
can't be newly inserted but keeps rendering/editing correctly if it's ever
encountered. **This is a deliberate choice, not a shortcut**: researched
whether Gutenberg's `deprecated`/`migrate` API could automatically turn
existing `axellcore/form-input` content into `axell/form-label` +
`axell/form-control` pairs on page load (the user's own ask — "você
consegue aprender isso no repo do gutenberg"). Read `core/gallery`'s real
`deprecated.js` (its own v1→v2 migration, from flat `images`/`ids`
attributes to `core/image` InnerBlocks — the closest real precedent for
"old monolithic block → new block composed of different child block
types"): `runV2Migration()` returns `[newAttributes, imageBlocks]`, but the
**block identity stays `core/gallery`** throughout. This proves the actual
rule: `deprecated`/`migrate` can restructure a block's own attributes/
InnerBlocks (including swapping in different child block types) but
**cannot** make a block silently vanish into sibling blocks of a different
name at the parent level, automatically, on page load. That specific
transformation only exists via the Block Transforms API (`transforms.from`),
which requires an explicit user action (the "Transform to…" toolbar UI) —
it doesn't run automatically against already-stored content.

Since this plugin owns 100% of its own content (no third-party sites using
`axellcore/form-input` to preserve), the real migration path is the same
mechanism already used for every other content change this session: **the
generator is the source of truth**. `bin/generate-content.py` was rewritten
to emit the new block structure directly, `content/seed-content.html` was
regenerated, and `/atelier` (post 30) was re-seeded — verified with the
same rigor as everything else: `parse_blocks()` null-attrs check, 0 invalid
blocks in the editor (356 blocks), PHPCS/PHPStan/PHPUnit green, and a
`getComputedStyle()` diff confirming the frontend is **byte-for-byte
identical** to the pre-split design (padding/border/color/font-size on the
input; color/font-size/letter-spacing/text-transform/weight on the label —
every value matched exactly).

### How the generator reaches the branded look without fighting the native supports

The obvious-seeming plan — bake the brand colors/fonts/borders into each
generated block's `style.*` attributes as literal values, so the *native
supports* render the design — was considered and deliberately **not**
done, because getting WordPress's exact class/style *serialization order*
byte-right by hand (needed for `parse_blocks()` validity) is real,
verifiable risk for no real benefit here. Instead: `bin/generate-content.py`
wraps each label+control pair in a plain `core/group` with `className:
"aa-field"` (or `"aa-consent"` for the checkbox) — reusing
`assets/css/sections.css`'s **existing, unmodified** `.aa-field label`/
`.aa-field input,select,textarea`/`.aa-req`/`.aa-hint`/`.aa-consent`
rules completely as-is. These are plain descendant selectors (`.aa-field
label`, `.aa-field input`) — they don't care which block renders the
`<label>`/`<input>`, only that the DOM shape matches, which it still does.
Similarly, `.aa-apply-form fieldset`/`.aa-apply-form legend` in
`sections.css` (ported from the original mockup's own CSS, unused until
now since the generator never emitted real `<fieldset>` elements before)
apply automatically to `axell/fieldset`'s real `<fieldset>`/`<legend>`
output — **zero CSS changes were needed** for any of this. The row-level
grouping (`core/group.aa-form-row.aa-cols-2/3/addr/city`, wrapping 2-3
field-groups side by side) is also completely unchanged, nested one level
inside the new `axell/fieldset` instead of directly inside the old
flat `axellcore/form`.

Net effect: the native Style panels (Typography/Color/Border/Dimensions)
are **fully present and confirmed working** on all three new blocks — this
was the actual ask — while the generator's own default content keeps
reaching the exact branded look through the same proven CSS classes as
before, with materially lower implementation risk than hand-deriving
native-attribute serialization.

### Consent checkbox and hint text — no longer special cases

The old `variant="consent"` attribute is gone. The consent checkbox is now
just ordinary composition: `core/group.aa-consent` containing
`axell/form-control` (checkbox) *then* `axell/form-label` (its `text`
RichText already supports an embedded `<a>`, same as before) — checkbox-
first, label-after, matching the old visual order purely through block
order, no attribute needed. Hint text (the small helper line under a
field) is now just a plain `core/paragraph` (`aa-hint` className) placed
after the control inside the field group — no dedicated attribute either.

### Vendored reference used for ground-truth verification

Every generated HTML shape in `bin/generate-content.py`'s `_control_html()`
/`form_label()`/`fieldset()` was captured byte-for-byte from the real
blocks via `wp.blocks.getSaveContent()` in the browser (same technique
used throughout this session for `axellcore/form-input`) — not hand-
derived from reading the JS source, specifically to avoid guessing at
WordPress's exact attribute-serialization order (confirmed non-obvious:
e.g. `type="…"` serializes *last* even when logically it reads first in
the JS).

## Content files (what activation reads)

`Activator` creates `/atelier` from `content/atelier-page.html`, its child pages from `content/pages/{slug}.html` only when the `axellcore_atelierclub_create_child_pages` filter returns true (off by default, so a live site gets only the landing; a development site turns it on, e.g. in an mu-plugin), and the header/footer template parts from `content/header-part.html` / `content/footer-part.html`. The database is the source of truth: after editing those pages in the editor, run `bin/export-content.sh` to copy them back into `content/`. `bin/generate-content.py` no longer reproduces the live landing (it writes only `content/seed-content.html`, which nothing reads).

## `axell/form` (generic) and `axell/form-atelier`

`axell/form` is a generic form with optional submission actions, set in its "Ações de envio" panel: **Gravar em** (any post type with `show_ui`, or none; status and the field used as title) and **Enviar e-mail** (Para, Assunto, Mensagem with `{field}` and `{all_fields}` tags). `axell/form-atelier` reuses the same editor (`src/form/form/form-edit.tsx`) and save, stores into `aa_member` (fixed) and inserts the full application template (`src/form/form-atelier/template.ts`, serialized from `/atelier/adesao`).

Every submission goes through `includes/class-form-submission.php`: REST `POST /submit` with JavaScript, `admin-post.php?action=axellcore_form_submit` without it. The request only identifies the form (`post_id`, `form_id`, added on render by `Form_Block`); the settings are read from the saved block (`parse_blocks`), and only fields whose `name` is in the form's rendered markup are kept. A post type can have its own store handler (`axellcore_form_store_handler`); members use `Members::create_from_params`. Content saved with the old `submitsToRest` switch migrates through the block deprecations to `storePostType: aa_member`.

## Form controls: Interactivity directives added on render

The interactive form blocks save plain HTML (elements, ids, names, `hidden`/`disabled`, options); their `data-wp-*` directives are not saved. `includes/class-form-directives.php` (`Form_Directives`) adds them on `render_block_{name}` from the block attributes, with `WP_HTML_Tag_Processor`, for the address controls (state, city, postal, phone, country), CPF/CNPJ (three blocks), the autocomplete (reseller, and `axell/form-control` of type autocomplete) and the submission notification; `Form_Block` adds the form's own (`axell/form`, `axell/form-atelier`). WordPress processes directives after `render_block` (`WP_Block::render`), so they behave as if saved. This keeps the post content and `src/form/form-atelier/template.ts` readable.

- When a control's markup or store changes, change the TS markup (`src/form/form-address/markup.tsx`, `form-control-br-revenue-id/markup.tsx`, `form-control/autocomplete-markup.tsx`) **and** the PHP method that decorates it; they must agree on the elements (matched by tag, `type`, `role`, `data-field`, class).
- Content saved with the directives validates through each block's `deprecated.tsx` (the old markup is in `legacy-*markup.tsx`) and is re-saved without them.
- Single-field controls (CPF/CNPJ, telefone, CEP, país) save the field itself as the block root, with the block class and `wp-block-axell-form-control`; the region directives and the field directives go on that one element. UF and Cidade also save only their field (a `<select>` with the placeholder option; a text `<input>`); `Form_Directives::state_widget()` / `city_widget()` build the `{block class}-wrapper` region on render: the UF select with the states and the free-text input for other countries; the city combobox, the hidden field that is sent, the free-text input and the `{name}-cities` list (or, not searchable, the cities select). Their wrapped save is the `v2` deprecation (`wrappedStateMarkup` / `wrappedCityMarkup`). The earlier wrapped save is a `v2` deprecation (`wrapped-markup.tsx`).
- The autocomplete (reseller, and `axell/form-control` of type autocomplete) saves only its search `<input>`. `Form_Directives::autocomplete_widget()` builds the rest on render: the `{block class}-wrapper` div (the `axell/autocomplete` region), the combobox attributes, the hidden `{name}` and `{name}_titulo` inputs, the custom-store panel (name, back button, UF select from `Form_Directives::UF_CODES`, city select) and the `{name}-list` listbox. Styles and the view key on `[data-wp-interactive='axell/autocomplete']`, not on the block class (now on the input). The wrapped save is the `v2` deprecation (`wrapped-autocomplete-markup.tsx`); content still saved that way (root `DIV`) only gets the directives.
- Check: the rendered `data-wp-*` of `/atelier/pure/adesao/` must match before and after a change (diff of the attributes per element).
- Custom store panel (`autocomplete_widget()`):
  - **Order:** "Voltar à busca" button, then name, UF, city. The button comes first in the Tab order and is placed over the name's end by CSS.
  - **Name:** choosing "não encontrada" moves focus to it.
  - **City:** a combobox over the UF's cities, as the address city (`cityQuery`/`cityTyped`/`cityOpen`/`cityActive` in the context). Its placeholder and open state are context values, not `state` getters, which the server directive processor cannot evaluate.
  - **Keys:** the widget's `onKeydown` only handles the search combobox (no `data-field`), so the arrows in the panel never open the search list.
  - **Legacy markup:** content saved in the wrapped form keeps the city `<select>` (`renderCities`).
- **After a successful submission:** `form.reset()` resets only the DOM, not the Interactivity contexts. The autocomplete (`callbacks.watchReset`, `data-wp-init--reset`) and each address region (`callbacks.initRegion`) listen to the form's `reset` event, wrapped in `withScope`, and go back to their initial state:
  - **Stores:** an empty search, no ID or title, the custom panel closed.
  - **Address:** the city search is empty, and the linked values are read from the fields again on the next tick (the event comes before the defaults are restored).
- **Partner stores on submission:** `Members::submitted_resellers()` drops the empty positions and keeps the order. Stores 2 and 4 are saved as `reseller1` and `reseller2`.
- **Styled slots (/atelier/adesao/):**
  - **Layout:** each field spans the whole slot from its left edge, at full height (the slot's 0.5rem vertical padding moved into the fields: 18px).
  - **Index:** the roman index sits over the field's 46px left padding (`--aa-ac-inset`, which also lines up the UF on phones).
  - **Line:** the slot's line hides while the custom panel is open.

## Footer (/atelier/footer/, post 1078)

One `core/navigation` (aria-label "Rodapé") whose submenus are the columns: the titles are the submenu items (no headings), kept open by the nav's block CSS. The four-column grid is the `core/columns` block style **"Footer"** (`src/block-styles/style.scss`): grid `1.6fr 1fr 1fr 1fr`, with `display: contents` on the nav column, the nav and its container so the submenus become grid items (the a11y tree keeps the nav and lists); two columns under 900px. The theme's `li` margin (`0 0 3px`) and the submenu gap need `!important` zeroes in the nav CSS; hover is color only (`text-decoration: none !important`). Submenu titles and the hours item have `pointer-events: none` in their own block CSS. "Rodapé final" stacks centered by `@tablet|@mobile layout: { orientation: 'vertical', justifyContent: 'center' }`.

## Revendas (post type `revendas`), in place of JetEngine

Production registers the revendas with JetEngine. Without JetEngine (`Resellers::jet_engine_active()`, `class_exists( 'Jet_Engine' )`) this plugin registers the same signature (`includes/class-resellers.php`), so a production database shows everything:
- **Post type:** `revendas`, public, archive, rewrite `revendas`, title only.
- **Taxonomies:** `paises`, `estados` (rewrite `estado`) and `cidades` (rewrite `cidade`), hierarchical but with flat terms.
- **Meta:** the "Assistencia_Revendas" text fields `endereco`, `telefone-1`, `telefone-2`, `site`, `e-mail`, as plain strings.

With JetEngine active nothing of that is registered: no admin and no import. Only the form's custom store stays on.

- **Form store (`Reseller_Store`, always on):** a store text "Nome - UF Cidade" in the adesão form creates a pending revenda.
  - **State and city terms:** reuses the existing ones by an equivalent name. "Rio Grande do Sul" finds production's "Rio Grande do sul", slug `rs`: production slugs mix UF and full names, so never derive a slug.
  - **New terms:** named with the full state name and the IBGE city name.
- **Activation import (`Resellers_Import`):** runs once, when there is no revenda. It first creates `content/revendas-terms.json` (production names and slugs), then the posts of `content/revendas.csv` (no ID: new IDs).
  - **Values:** stored exactly as exported, with no trim, sanitising or kses ("&" stays "&"). Only the formula escape `'@` is undone.
  - **Regenerating both files** from the production copy: `OUT=<plugin>/content studio wp eval-file <plugin>/bin/export-resellers.php`.
  - **Also reads** the axellcore export layout ("Localização", "Telefone").
- **Admin (`Resellers_Admin`, `Resellers_Rest`, `src/admin/resellers/`):** DataViews list and DataForm detail, like members, under the post type menu (`admin.php?page=resellers`, `&reseller=<id|new>`). The core list, new and edit screens redirect there; the trash and the taxonomy screens stay core. REST `admin/resellers` (list, create, read, update, trash) uses the post type caps.
- **Verified against production:** an import on a clean site matches production field by field for 354 of 355 revendas. That covers the title, the 5 meta and the term name, slug and parent. The one difference is a draft that has no meta rows at all in production.

## /atelier landing (post 6) and the header/footer template parts

- **Content:** `/atelier` is the 13 styled section pages (hero to adesão) joined in order. `/atelier/<section>/` stays the source of each one: change a section there, then rebuild post 6 from them.
- **Template parts:** `axellcore-header` and `axellcore-footer` hold the content of `/atelier/header/` and `/atelier/footer/`.
  - **Header:** its root is `axell/sticky-header`, fixed, with the mockup's `is-scrolled` state past 40px. The scrolled padding is `!important`, because the block's padding attribute is inline.
- **`templates/atelier-club.html`:** renders like the section pages, with the theme's global styles and no legacy stylesheets.
  - **Wrappers:** the template parts are `div`, and the semantic `<header>`/`<footer>` is their root block.
  - **Gaps:** a root group with `blockGap: 0` holds header, `main` and footer, and the `post-content` has `blockGap: 0`. Without them the theme's 1.2rem block gap separates the template parts and every section (+269px).
- **Reveal:** set with `revealMode` on each section page, as the source's `.reveal`:
  - `block` on the `.section-head` columns and on the CTA strip;
  - `items` on the numbered lists, the tier cards and the two halves of placa, conceito, editorial and adesão.
- **Page rules (`Design_Tokens`):** smooth scrolling to anchors (off for reduced motion); the first-screen fonts preloaded (Cormorant 300 normal and italic, Inter), whose late swap moved the hero (CLS 0.14).
- **Comparison captures** (`design/base/scripts/shot-file-*.mjs`): they force `.axell-reveal` visible and run with `reducedMotion: 'reduce'`. The reveal's animation leaves composited layers whose text antialiasing differs from the bases (+1.3k px on the selects).

## Releases, updates and translations (SelfDirectory, as axellcore)

- **Updates:** `lib/selfdirectory` (git submodule, `fervidum/selfdirectory`) updates the plugin from the GitHub releases of its `Plugin URI` in the Plugins screen, and installs its language packs. After cloning: `git submodule update --init`.
- **Plugin zip:** `axellcore-atelierclub.X.Y.Z.zip`, built by `release.yml` on a `X.Y.Z` tag. It is `git archive` plus the SelfDirectory class; `.gitattributes` `export-ignore` drops the dev files, `design/`, `src/` and `languages/`.
- **Language packs:** `axellcore-atelierclub.X.Y.Z-pt_BR.zip`, holding the `.po`, the `.mo` and the JS `.json` catalogs. `language.yml` builds them from the orphan branch `language/X.Y.Z` and attaches them to the release.
- **Source strings are English;** Portuguese lives only in `languages/axellcore-atelierclub-pt_BR.po`.
- **Script translations:** every enqueued script with `__()` calls `wp_set_script_translations( $handle, 'axellcore-atelierclub' )` with no path, so the plugin's `languages/` (development) and the installed pack (`wp-content/languages/plugins`) both work. Block scripts get it from `block.json`.
- **Process (run from the Studio site, `studio wp`):**
  1. Write the version's notes under `== Changelog ==` in `readme.txt`.
  2. `studio wp --require=wp-content/plugins/axellcore-atelierclub/bin/release.php axc release X.Y.Z`. It:
     - bumps the header, the constant and the `Stable tag`;
     - regenerates the POT, PO, MO and JSON, and **stops on untranslated strings**;
     - points `blueprint.json` at the `X.Y.Z` assets;
     - commits, tags and pushes; the tag runs `release.yml`.
  3. `studio wp --require=… axc language` pushes `language/X.Y.Z`; `language.yml` attaches `axellcore-atelierclub.X.Y.Z-pt_BR.zip`.
- **Playground (`blueprint.json`):** `setSiteLanguage` pt_BR, then unzip the pt_BR pack into `/wordpress/wp-content/languages/plugins`, then install the release zip. The language comes before the install so the activation runs in pt_BR. `blueprint-dev.json` installs the preview build, which still carries `languages/`.
