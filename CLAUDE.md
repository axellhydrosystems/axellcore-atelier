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
- **Fixed dark visual design via `aac-` CSS classes**, not the reference's
  native `color`/`border` block supports on the input field. The mockup's
  field chrome (`assets/css/sections.css`'s `.aac-field`/`.aac-consent`
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
  still reaches the branded look via the *same* `.aac-field`/`.aac-consent`
  CSS classes as before (see below for how), the native supports are just
  *additionally available* for anyone who wants to override them.
- **Notification visibility via JS class toggling**, not the reference's
  full-page-reload + `?wp-form-result=` GET param + PHP `render_callback`
  filter. `axellcore/form` submits via `fetch()` (`assets/js/frontend.js`) and
  never navigates away, so there's no page reload to carry a GET param across
  — `axellcore/form-submission-notification` instead starts hidden
  (`display:none`) and gets `.is-active` toggled on the matching
  `[data-aac-notice-type="success"|"error"]` element after the fetch
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
  `.aac-field input`/`.aac-field label`/`.aac-consent` rules always win on
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
  `core/buttons > core/button[type=submit]` styled via `.aac-submit-row`/
  `.aac-btn` already gets the same visual result; the reference's wrapper is
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
PHP namespace, textdomain, and `aac-` CSS prefix are all unchanged; scoped
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
  empty tag). Own native `layout` support too, though the generator doesn't
  end up needing it (see below).

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
"aac-field"` (or `"aac-consent"` for the checkbox) — reusing
`assets/css/sections.css`'s **existing, unmodified** `.aac-field label`/
`.aac-field input,select,textarea`/`.aac-req`/`.aac-hint`/`.aac-consent`
rules completely as-is. These are plain descendant selectors (`.aac-field
label`, `.aac-field input`) — they don't care which block renders the
`<label>`/`<input>`, only that the DOM shape matches, which it still does.
Similarly, `.aac-apply-form fieldset`/`.aac-apply-form legend` in
`sections.css` (ported from the original mockup's own CSS, unused until
now since the generator never emitted real `<fieldset>` elements before)
apply automatically to `axell/fieldset`'s real `<fieldset>`/`<legend>`
output — **zero CSS changes were needed** for any of this. The row-level
grouping (`core/group.aac-form-row.aac-cols-2/3/addr/city`, wrapping 2-3
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
just ordinary composition: `core/group.aac-consent` containing
`axell/form-control` (checkbox) *then* `axell/form-label` (its `text`
RichText already supports an embedded `<a>`, same as before) — checkbox-
first, label-after, matching the old visual order purely through block
order, no attribute needed. Hint text (the small helper line under a
field) is now just a plain `core/paragraph` (`aac-hint` className) placed
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

`Activator` creates `/atelier` from `content/atelier-page.html`, its child pages from `content/pages/{slug}.html`, and the header/footer template parts from `content/header-part.html` / `content/footer-part.html`. The database is the source of truth: after editing those pages in the editor, run `bin/export-content.sh` to copy them back into `content/`. `bin/generate-content.py` no longer reproduces the live landing (it writes only `content/seed-content.html`, which nothing reads).
