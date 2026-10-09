#!/usr/bin/env bash
# bin/release.sh — bump version, refresh translations, commit/tag/push.
#
# Usage:
#   bin/release.sh patch            # 0.1.0 → 0.1.1  bump + pot + commit + tag + push
#   bin/release.sh minor            # 0.1.0 → 0.2.0
#   bin/release.sh major            # 0.1.0 → 1.0.0
#   bin/release.sh 1.2.3            # explicit version
#
#   bin/release.sh language         # msgmerge the fresh POT into languages/*.po (run after translating)
#
# Options (release subcommand only):
#   --no-commit   Stop after bumping files; do not commit, tag, or push.
#   --no-tag      Commit but do not create a tag or push.
#   --no-push     Commit and tag but do not push to remote.
#
#   Dependency rules (applied automatically):
#     --no-commit  implies --no-tag and --no-push
#     --no-tag     implies --no-push
#
# Mirrors the convention used by the sibling axellcore and essfinance03
# plugins (see their bin/release.sh) — same flags, same version-bump logic.

set -euo pipefail

# ── Paths ────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_FILE="$PLUGIN_DIR/axellcore-atelier.php"
README_TXT="$PLUGIN_DIR/readme.txt"
POT_FILE="$PLUGIN_DIR/languages/axellcore-atelier.pot"

# ── Helpers ──────────────────────────────────────────────────────────────────

die()  { echo "error: $*" >&2; exit 1; }
info() { echo "  → $*"; }

require_cmd() { command -v "$1" &>/dev/null || die "$1 is required"; }

current_version() {
	grep -m1 "Version:" "$PLUGIN_FILE" \
		| sed 's/.*Version:[[:space:]]*//' \
		| tr -d '[:space:]'
}

# ── Dispatch ─────────────────────────────────────────────────────────────────

[[ $# -ge 1 ]] || die "usage: bin/release.sh patch|minor|major|<version>|language [--no-commit] [--no-tag] [--no-push]"

NO_COMMIT=0
NO_TAG=0
NO_PUSH=0

CMD=""
for ARG in "$@"; do
	case "$ARG" in
		--no-commit) NO_COMMIT=1 ;;
		--no-tag)    NO_TAG=1 ;;
		--no-push)   NO_PUSH=1 ;;
		-*)          die "unknown option: $ARG" ;;
		*)           [[ -z "$CMD" ]] && CMD="$ARG" || die "unexpected argument: $ARG" ;;
	esac
done

[[ -n "$CMD" ]] || die "usage: bin/release.sh patch|minor|major|<version>|language [--no-commit] [--no-tag] [--no-push]"

# Dependency rules: --no-commit => --no-tag => --no-push
[[ $NO_COMMIT -eq 1 ]] && NO_TAG=1
[[ $NO_TAG    -eq 1 ]] && NO_PUSH=1

# ─────────────────────────────────────────────────────────────────────────────
# SUBCOMMAND: language
# Merge the current POT into every languages/*.po and report untranslated strings.
# ─────────────────────────────────────────────────────────────────────────────
if [[ "$CMD" == "language" ]]; then
	require_cmd msgmerge

	shopt -s nullglob
	PO_FILES=( "$PLUGIN_DIR"/languages/axellcore-atelier-*.po )
	shopt -u nullglob

	if [[ ${#PO_FILES[@]} -eq 0 ]]; then
		echo "No languages/axellcore-atelier-*.po files yet — nothing to merge."
		echo "Add one (e.g. axellcore-atelier-pt_BR.po) and re-run 'bin/release.sh language'."
		exit 0
	fi

	MISSING_ALL=""
	for PO in "${PO_FILES[@]}"; do
		LOCALE=$(basename "$PO" .po | sed 's/^axellcore-atelier-//')
		info "merging pot into $LOCALE"
		msgmerge --update --backup=none --quiet "$PO" "$POT_FILE"

		MISSING=$(python3 - "$PO" <<'PYEOF'
import sys, re

path = sys.argv[1]
blocks = open(path).read().strip().split("\n\n")
missing = []
for block in blocks:
    lines = block.strip().splitlines()
    if any(l == 'msgid ""' for l in lines):
        continue
    is_fuzzy   = any(l.strip() == '#, fuzzy' for l in lines)
    msgid_val  = ' '.join(re.findall(r'^msgid\s+"(.*)"', '\n'.join(lines), re.M))
    msgstr_val = ' '.join(re.findall(r'^msgstr(?:\[\d+\])?\s+"(.*)"', '\n'.join(lines), re.M))
    if msgid_val and (is_fuzzy or not msgstr_val.strip()):
        missing.append(msgid_val)
for m in missing:
    print(m)
PYEOF
)
		[[ -n "$MISSING" ]] && MISSING_ALL+="\n[$LOCALE]\n$MISSING"
	done

	if [[ -n "$MISSING_ALL" ]]; then
		echo ""
		echo "Untranslated / fuzzy strings:"
		echo -e "$MISSING_ALL"
	else
		echo "All languages fully translated."
	fi
	exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
# SUBCOMMAND: release (patch / minor / major / explicit version)
# ─────────────────────────────────────────────────────────────────────────────

require_cmd git
require_cmd wp

BUMP="$CMD"

CURRENT=$(current_version)
[[ "$CURRENT" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] \
	|| die "could not parse current version from plugin header: $CURRENT"

IFS='.' read -r MAJ MIN PAT <<< "$CURRENT"

case "$BUMP" in
	major)             NEW_VERSION="$((MAJ+1)).0.0" ;;
	minor)             NEW_VERSION="${MAJ}.$((MIN+1)).0" ;;
	patch)             NEW_VERSION="${MAJ}.${MIN}.$((PAT+1))" ;;
	[0-9]*.[0-9]*.[0-9]*)
		NEW_VERSION="$BUMP"
		[[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] \
			|| die "invalid explicit version: $NEW_VERSION"
		;;
	*) die "first argument must be patch, minor, major, explicit semver, or 'language' (got: $BUMP)" ;;
esac

echo ""
echo "axellcore-atelier $CURRENT → $NEW_VERSION"
echo ""

cd "$PLUGIN_DIR"

if git remote get-url origin &>/dev/null; then
	git fetch origin --quiet
	git ls-remote --exit-code origin "refs/tags/${NEW_VERSION}" &>/dev/null \
		&& die "tag ${NEW_VERSION} already exists on remote"
fi

# ── Bump version ─────────────────────────────────────────────────────────────

info "bumping version in axellcore-atelier.php and readme.txt"

sed -i '' "s/ \* Version:.*/ * Version:           ${NEW_VERSION}/" "$PLUGIN_FILE"
sed -i '' "s/define( 'AXELLCORE_ATELIER_VERSION', '[^']*' )/define( 'AXELLCORE_ATELIER_VERSION', '${NEW_VERSION}' )/" "$PLUGIN_FILE"
sed -i '' "s/^Stable tag:.*/Stable tag: ${NEW_VERSION}/" "$README_TXT"

if ! grep -q "^= ${NEW_VERSION} =" "$README_TXT"; then
	TODAY=$(date -u +%Y-%m-%d)
	sed -i '' "s/^== Changelog ==$/== Changelog ==\n\n= ${NEW_VERSION} =\n* Release ${NEW_VERSION} (${TODAY})./" "$README_TXT"
fi

# ── Validate changelog entry ─────────────────────────────────────────────────

CHANGELOG_ENTRY=$(
	awk "/^= ${NEW_VERSION} =/{found=1; next} found && /^= /{exit} found{print}" "$README_TXT" \
		| grep -v '^[[:space:]]*$'
)

[[ -n "$CHANGELOG_ENTRY" ]] \
	|| die "Changelog for ${NEW_VERSION} is empty. Add release notes to readme.txt before releasing."

PLACEHOLDER="* Release ${NEW_VERSION}"
if [[ "$CHANGELOG_ENTRY" == "${PLACEHOLDER}"* && $(echo "$CHANGELOG_ENTRY" | wc -l | tr -d ' ') -eq 1 ]]; then
	die "No changes added in current changelog for ${NEW_VERSION}.\n       Edit readme.txt and replace the placeholder before releasing."
fi

# ── Update POT + PO + compiled MO + JS translation catalog ────────────────────
# The four steps below MUST run in this order: make-pot (extract source
# strings) → update-po (merge into every shipped .po, preserving existing
# translations) → make-mo (compile — PHP gettext via load_plugin_textdomain()
# reads ONLY the compiled .mo, never the .po directly; skipping this step
# means every "translated" string silently stays in English at runtime, which
# is exactly what happened from 0.1.0 through 0.1.2) → make-json (JS/editor
# translation catalogs, generated from the now-current .po).

info "generating axellcore-atelier.pot via wp i18n make-pot"
wp i18n make-pot "$PLUGIN_DIR" "$POT_FILE" \
	--domain=axellcore-atelier \
	--exclude=vendor,node_modules,tests \
	--quiet

for po_file in "$PLUGIN_DIR"/languages/*.po; do
	[ -e "$po_file" ] || continue
	info "merging new strings into $(basename "$po_file") via wp i18n update-po"
	wp i18n update-po "$POT_FILE" "$po_file" --quiet
done

info "compiling .mo files via wp i18n make-mo"
wp i18n make-mo "$PLUGIN_DIR/languages" "$PLUGIN_DIR/languages"

info "generating JS translation catalog via wp i18n make-json"
wp i18n make-json "$PLUGIN_DIR/languages" --no-purge --quiet 2>/dev/null || true

# ── Commit, tag, push ────────────────────────────────────────────────────────

info "staging all changes"
git add -A

if [[ $NO_COMMIT -eq 1 ]]; then
	echo ""
	echo "Files bumped to ${NEW_VERSION} (--no-commit: skipping commit, tag and push)."
	exit 0
fi

info "committing version bump"
git commit --quiet -m "chore: release ${NEW_VERSION}"

if [[ $NO_TAG -eq 1 ]]; then
	echo ""
	echo "Released ${NEW_VERSION} (--no-tag: skipping tag and push)."
	exit 0
fi

info "tagging ${NEW_VERSION}"
git tag "${NEW_VERSION}"

if [[ $NO_PUSH -eq 1 ]] || ! git remote get-url origin &>/dev/null; then
	echo ""
	echo "Released ${NEW_VERSION} locally (no remote configured or --no-push: skipping push)."
	exit 0
fi

info "pushing branch and tag"
BRANCH=$(git rev-parse --abbrev-ref HEAD)
git push origin "$BRANCH" --quiet
git push origin "${NEW_VERSION}" --quiet

echo ""
echo "Released ${NEW_VERSION}."
