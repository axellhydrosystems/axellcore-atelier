# Original (approved source)

- `index.html`: copy of `source/index.html` (approved mockup, sha256 35eb9c16…). Do not edit.
- `base-desktop.png` (1440 × 15951) and `base-mobile.png` (390 × 24849): approved bases, rendered from the original with reveals forced visible and transitions off.

The prototype (`../index.html`, `../style.min.css`) is the source going forward. It must stay at 0.00% against these bases, except for the exceptions below.

## Exceptions (changes allowed to move the pixels)

| Change | Where | Why | Effect |
|---|---|---|---|
| Chapter label on ivory: `#B4996A` → `#7D6138` | `.aa-on-ivory .aa-chapter-tag`, `.aa-chapter.aa-ivory .aa-chapter-tag` (WordPress) | Lighthouse contrast: 2.3:1 → 4.9:1 (AA) | Visible colour change on the chapter labels |
| Footer copyright: alpha 0.4 → 0.55 | `.aa-footer-bottom` (WordPress) | Lighthouse contrast: 3.4:1 → 5.4:1 (AA) | Visible colour change on the footer line |

None of these is applied to the prototype yet. When one is applied there, the base must be re-recorded from the prototype with that change, and the row above must say so.
