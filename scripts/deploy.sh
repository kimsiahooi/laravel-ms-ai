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
set -euo pipefail

main() {
    # Always operate from the project root, wherever we were invoked from.
    cd "$(dirname "$0")/.."

    echo "▶ [1/9] Pulling latest code…"
    git pull --ff-only

    echo "▶ [1b/9] Guarding against stray ds() debug calls…"
    # LaraDumps is a dev-only tool, and `--no-dev` below strips it from the prod
    # vendor tree — so a committed ds()/dsN()/dsq() would fatal at runtime here.
    # Fail the deploy (before any build/restart) if any remain in app source.
    if grep -rEn --include='*.php' '(^|[^[:alnum:]_>$])ds([0-9]|q)?\(' app routes database config; then
        echo "✗ Found LaraDumps debug calls above — remove them before deploying." >&2
        exit 1
    fi

    echo "▶ [2/9] Installing PHP dependencies (production only, no dev tools)…"
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

    echo "▶ [3/9] Installing JS dependencies…"
    bun install --frozen-lockfile

    echo "▶ [4/9] Clearing stale caches (so the new config/routes take effect)…"
    php artisan optimize:clear

    echo "▶ [5/9] Migrating the central database…"
    php artisan migrate --force

    echo "▶ [6/9] Migrating every tenant database…"
    php artisan tenants:migrate --force

    echo "▶ [7/9] Ensuring the public storage symlink exists…"
    php artisan storage:link || true

    echo "▶ [8/9] Building client + SSR bundles…"
    bun run build:ssr

    echo "▶ [8b/9] Verifying the build (Biome + TypeScript)…"
    # `set -euo pipefail` (top of file) means either check exiting non-zero
    # aborts the deploy HERE — before the pm2 restart below — so a failed check
    # leaves the currently-running SSR process untouched and serving.
    # NOTE: the Pest suite is intentionally NOT run here. It runs under the
    # `testing` env against dedicated `*_test` databases (not prod), so it belongs
    # in CI / local pre-deploy, not on the server.
    # `check:deploy` gates on errors only, so advisory warnings don't clutter the
    # deploy log (they still surface in `bun run check` locally and in CI). Errors
    # still abort the deploy here — before the pm2 restart below.
    bun run check:deploy
    bun run types:check

    echo "▶ [8c/9] Re-caching config / routes / views for production…"
    php artisan optimize

    echo "▶ [9/9] (Re)starting the Inertia SSR process under pm2…"
    # Restart only OUR process (never `pm2 ... all`, which would touch every
    # app on the box). Restart re-runs the artisan command, which reloads the
    # freshly built SSR bundle. Falls back to a first-time start.
    if pm2 restart inertia-ssr --update-env >/dev/null 2>&1; then
        echo "   restarted existing inertia-ssr"
    else
        pm2 start "php artisan inertia:start-ssr" --name inertia-ssr
    fi
    pm2 save

    echo "✅ Deploy complete."
}

main "$@"
