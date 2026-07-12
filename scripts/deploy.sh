#!/usr/bin/env bash
#
# Production deploy for the multi-tenant ERP.
# Run from the project root as the SITE user (not root):
#
#     bun run deploy            # or: bash scripts/deploy.sh
#
# The whole body lives in main() and is only invoked on the last line, so a
# `git pull` that rewrites this very file mid-run can't corrupt the shell:
# bash has already parsed the function into memory before it executes.
#
#   -e            abort the instant any command fails (non-zero exit)
#   -u            treat use of an unset variable as an error (catches typos)
#   -o pipefail   a pipeline fails if ANY stage fails, not just the last one
set -euo pipefail

main() {
    # Always operate from the project root, wherever we were invoked from.
    cd "$(dirname "$0")/.."

    echo "▶ [1/10] Pulling latest code…"
    # --ff-only: only fast-forward; abort (rather than make a merge commit) if the
    # server branch has diverged from origin, keeping the box an exact mirror.
    git pull --ff-only

    echo "▶ [1b/10] Guarding against stray ds() debug calls…"
    # LaraDumps is a dev-only tool, and `--no-dev` below strips it from the prod
    # vendor tree — so a committed ds()/dsN()/dsq() would fatal at runtime here.
    # Fail the deploy (before any build/restart) if any remain in app source.
    #   -r recurse · -E extended regex · -n show line numbers · --include only *.php
    if grep -rEn --include='*.php' '(^|[^[:alnum:]_>$])ds([0-9]|q)?\(' app routes database config; then
        echo "✗ Found LaraDumps debug calls above — remove them before deploying." >&2
        exit 1
    fi

    echo "▶ [2/10] Removing stale vendor/ + node_modules/ for a clean install…"
    # Wipe both dependency trees so the installs below resolve from a clean slate
    # (no orphaned packages left by a removed require). This is safe for the live
    # pm2 SSR process: `inertia:start-ssr` loaded its Node modules into memory at
    # startup and keeps serving from Node's require-cache — deleting the files on
    # disk doesn't stop it. `bun install` restores node_modules two steps down,
    # long before the pm2 restart at the end re-reads the freshly built bundle.
    # (While `composer install` runs, vendor/ is briefly absent — a short php-fpm
    # window; add `php artisan down`/`up` around the installs if that's not ok.)
    rm -rf vendor node_modules

    echo "▶ [3/10] Installing PHP dependencies (production only, no dev tools)…"
    #   --no-interaction      never prompt; use defaults (required for an unattended run)
    #   --prefer-dist         fetch zipped release archives instead of cloning git sources
    #   --optimize-autoloader build a static class-map so class loading needs no fs lookup
    #   --no-dev              skip require-dev (Pest/Pail/Boost/LaraDumps) — keep prod lean
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

    echo "▶ [4/10] Installing JS dependencies…"
    # --frozen-lockfile: install exactly what bun.lock pins; fail if it's stale, so the
    # server never silently resolves different versions (bun's equivalent of `npm ci`).
    bun install --frozen-lockfile

    echo "▶ [5/10] Clearing stale caches (so the new config/routes take effect)…"
    php artisan optimize:clear

    echo "▶ [6/10] Migrating the central database…"
    # --force: run without the interactive "you're in production, sure?" prompt
    # (Laravel blocks prod migrations otherwise).
    php artisan migrate --force

    echo "▶ [7/10] Migrating every tenant database…"
    # --force: same production-confirmation bypass, for the per-tenant migrations.
    php artisan tenants:migrate --force

    echo "▶ [8/10] Ensuring the public storage symlink exists…"
    # `|| true`: storage:link exits non-zero when the symlink already exists, so this
    # swallows that failure and keeps the step idempotent under `set -e`.
    php artisan storage:link || true

    echo "▶ [9/10] Building client + SSR bundles…"
    bun run build:ssr

    echo "▶ [9b/10] Verifying the build (Biome + TypeScript)…"
    # `set -euo pipefail` (top of file) means either check exiting non-zero
    # aborts the deploy HERE — before the pm2 restart below — so a failed check
    # leaves the currently-running SSR process untouched and serving.
    # NOTE: the Pest suite is intentionally NOT run here. It runs under the
    # `testing` env against dedicated `*_test` databases (not prod), so it belongs
    # in CI / local pre-deploy, not on the server.
    # `check:deploy` gates on errors only, so advisory warnings don't clutter the
    # deploy log (they still surface in `bun run check` locally and in CI). Errors
    # still abort the deploy here — before the pm2 restart below.
    #   check:deploy → `biome check --diagnostic-level=error .` (show/fail on errors only)
    #   types:check  → `tsc --noEmit` (type-check only; emit no JS)
    bun run check:deploy
    bun run types:check

    echo "▶ [9c/10] Re-caching config / routes / views for production…"
    php artisan optimize

    echo "▶ [10/10] (Re)starting the Inertia SSR process under pm2…"
    # Restart only OUR process (never `pm2 ... all`, which would touch every
    # app on the box). Restart re-runs the artisan command, which reloads the
    # freshly built SSR bundle. Falls back to a first-time start.
    #   --update-env     re-read the current env so new .env values take effect
    #   >/dev/null 2>&1  silence stdout AND stderr (only the exit code is used below)
    if pm2 restart inertia-ssr --update-env >/dev/null 2>&1; then
        echo "   restarted existing inertia-ssr"
    else
        # --name: give the process a stable handle so future restarts can target it
        pm2 start "php artisan inertia:start-ssr" --name inertia-ssr
    fi
    # pm2 save: persist the process list so pm2 resurrects it on server reboot
    pm2 save

    echo "✅ Deploy complete."
}

main "$@"
