#!/usr/bin/env bash
#
# E2E database lifecycle for the dedicated, isolated e2e environment. `up` builds the
# `..._e2e` central DB + the `e2e_tenant_e2e` tenant DB (seeded with demo data) from
# scratch; `down` drops them. Never touches dev data — everything is `_e2e` suffixed.
#
#   scripts/e2e.sh up     # generate .env.e2e, create+migrate central, provision tenant
#   scripts/e2e.sh down   # drop the tenant + central e2e databases
set -euo pipefail
cd "$(dirname "$0")/.."

action="${1:-up}"

if [ "$action" = "up" ]; then
    bun scripts/e2e-env.mjs
    php artisan e2e:central up                     # create the _e2e central DB (dev conn)
    php artisan migrate --env=e2e --force --quiet  # migrate it
    php artisan e2e:tenant up --env=e2e            # provision the e2e tenant + demo data
elif [ "$action" = "down" ]; then
    php artisan e2e:tenant down --env=e2e || true  # drop the tenant DB (needs central)
    php artisan e2e:central down || true           # drop the _e2e central DB
else
    echo "usage: scripts/e2e.sh up|down" >&2
    exit 1
fi
