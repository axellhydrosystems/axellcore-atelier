#!/usr/bin/env bash
# Verifies the Bootstrap prototype: HTML validation, minimal CSS size, and visual regression
# against the current bases in base/ (0.00% = identical pixels).
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
SKILL="$HOME/.claude/skills/figma-auto-html-merge/scripts"
SHOT="$HOME/Studio/axell-clube/.claude/skills/axell-atelier-html-to-wordpress/scripts/shot-file-forced.mjs"
OUT="${TMPDIR:-/tmp}/bootstrap-verify"; mkdir -p "$OUT"

echo "== HTML (html-validate, recommended)"
npx --yes html-validate "$HERE/index.html" && echo "   no messages"

echo "== CSS (minimal stylesheet)"
wc -c "$HERE/style.min.css" | awk '{print "   " $1 " bytes"}'

echo "== Visual regression vs base/ bases"
cd "$HERE/.." && for w in 1440 390; do
  node "$SHOT" "$HERE/index.html" "$w" "$OUT/proto-$w.png" >/dev/null
done
python3 - "$HERE" "$OUT" <<'PY'
import sys
from PIL import Image, ImageChops
here, out = sys.argv[1], sys.argv[2]
for name, base, proto in [('desktop 1440', '../base/desktop.png', 'proto-1440.png'), ('mobile 390', '../base/mobile.png', 'proto-390.png')]:
    b = Image.open(f'{here}/{base}').convert('RGB'); p = Image.open(f'{out}/{proto}').convert('RGB')
    diff = ImageChops.difference(b, p).getbbox() if b.size == p.size else 'size differs'
    print(f'   {name}: {"0.00% (identical)" if diff is None else diff}')
PY
