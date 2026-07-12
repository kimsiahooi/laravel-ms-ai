#!/usr/bin/env bash
#
# UI / code-standards guard, run by the lefthook pre-commit hook. Enforces the
# mechanical parts of the project's standards so nothing lands that breaks them:
#
#   1. No edits to vendored / generated (read-only) trees — wrap or compose instead
#      of forking shadcn `ui/**` (see docs/CODING-STANDARDS.md).
#   2. No raw colour literals (#hex / rgb() / hsl()) or inline colour styles in TSX —
#      everything goes through the indigo/neutral design tokens (docs/UI-UX-GUIDELINES.md).
#      Escape hatches: a `data:` URI, or a `ui-allow` comment on the line.
#
# The qualitative UI rules a script can't check (branded, every state, light + dark)
# are printed as a reminder when a UI file is touched.
set -euo pipefail

staged=$(git diff --cached --name-only --diff-filter=ACM)
[ -z "$staged" ] && exit 0

fail=0

# 1) Read-only / generated trees.
readonly_re='^(resources/js/components/ui/|resources/js/routes/|resources/js/actions/|resources/js/wayfinder/|bootstrap/ssr/|resources/css/|resources/js/types/generated\.d\.ts)'
blocked=$(printf '%s\n' "$staged" | grep -E "$readonly_re" || true)
if [ -n "$blocked" ]; then
    echo "✗ Edits to vendored/generated (read-only) files are not allowed — wrap/compose instead:" >&2
    printf '   %s\n' "$blocked" >&2
    fail=1
fi

# 2) Design tokens: no raw colour literals in component TSX (tests excluded).
color_re='#[0-9a-fA-F]{3,8}\b|rgb\(|hsl\('
touched_ui=0
for f in $(printf '%s\n' "$staged" | grep -E '\.tsx$' | grep -v '\.test\.tsx$' || true); do
    [ -f "$f" ] || continue
    touched_ui=1
    hits=$(grep -nE "$color_re" "$f" | grep -vE 'data:|ui-allow' || true)
    if [ -n "$hits" ]; then
        echo "✗ $f uses a raw colour literal — use the design tokens (or mark the line 'ui-allow'):" >&2
        printf '%s\n' "$hits" | sed 's/^/     /' >&2
        fail=1
    fi
done

if [ "$touched_ui" -eq 1 ]; then
    echo "• UI checklist (.ai/guidelines/ui-ux.md): branded (not plain); every state (loading/empty/error/success); light + dark." >&2
fi

exit "$fail"
