# Tenant soft delete & force delete — design

**Date:** 2026-07-05
**Status:** Approved (pending spec review)
**Area:** Central super-admin (`/admin`) tenant management

## Problem

The central admin dashboard can create tenants but cannot remove them. We want to:

1. **Soft delete** a tenant from the dashboard — the tenant becomes inaccessible,
   but its data (central row **and** its tenant database) is retained.
2. **Force delete** a tenant from a separate **Archived** page — permanently
   removes the central row **and drops the tenant database**.
3. **Restore** a soft-deleted tenant from the Archived page back to active.
4. Guarantee that once a tenant is soft-deleted, **its `/{slug}/…` pages cannot
   be accessed**.

## Critical constraint: soft delete must NOT drop the database

`App\Models\Tenant` already uses the `SoftDeletes` trait (the `tenants` table has
`deleted_at`). But `TenancyServiceProvider::events()` wires:

```
Events\TenantDeleted::class => [ JobPipeline([Jobs\DeleteDatabase::class]) ]
```

and stancl's base `Tenant` model maps Eloquent's **`deleted`** event to
`Events\TenantDeleted`. Because Eloquent fires `deleted` on **soft** deletes too,
the current wiring means **a soft delete would drop the tenant database** — which
defeats the purpose of soft delete and makes restore impossible.

This is the central risk the design must neutralize.

## Approach (chosen: A)

**A — Remap the model's delete events (chosen).** In `App\Models\Tenant`, override
`$dispatchesEvents` so `Events\TenantDeleted` (the DB-teardown trigger) fires on
Eloquent's **`forceDeleted`** event instead of `deleted`. Result:

- `delete()` (soft) → fires `deleting`/`deleted` → **no** `TenantDeleted` → DB kept.
- `forceDelete()` → additionally fires `forceDeleted` → `TenantDeleted` →
  `DeleteDatabase` → DB dropped.

Smallest change, stays inside stancl's event pipeline, no change to
`TenancyServiceProvider`.

**Rejected — B:** gate `DeleteDatabase` on `$tenant->isForceDeleting()` inside the
listener. `TenantDeleted` fires on both delete kinds and reading force-delete state
in the listener is fragile.

**Rejected — C:** unwire `DeleteDatabase` and drop the DB manually in the
controller. Most control but diverges from stancl's model and risks drift.

## Access control (requirement #4) — no new code, one test

`PathTenantResolver::resolveWithoutCache()` resolves via `tenancy()->find($id)`,
which runs through the model's default query and therefore honours the
`SoftDeletes` global scope; `PathTenantResolver::$shouldCache` is `false`, so there
is no stale resolver cache. A soft-deleted tenant returns `null` →
`TenantCouldNotBeIdentifiedByPathException` → HTTP 404. We assert this with a test
rather than adding a guard.

## Backend

### Model — `app/Models/Tenant.php`

Override `$dispatchesEvents`, replicating stancl's base map but moving DB teardown
from `deleted` to `forceDeleted`:

```php
protected $dispatchesEvents = [
    'saving'       => Events\SavingTenant::class,
    'saved'        => Events\TenantSaved::class,
    'creating'     => Events\CreatingTenant::class,
    'created'      => Events\TenantCreated::class,
    'updating'     => Events\UpdatingTenant::class,
    'updated'      => Events\TenantUpdated::class,
    'deleting'     => Events\DeletingTenant::class,
    // NOTE: 'deleted' intentionally NOT mapped to TenantDeleted so a soft delete
    // keeps the tenant database. DB teardown is deferred to force delete:
    'forceDeleted' => Events\TenantDeleted::class,
];
```

### Routes — `routes/web.php` (inside the `admin.` / `auth:central` group)

| Verb + path | Method | Name | Notes |
|---|---|---|---|
| `DELETE /admin/tenants/{tenant}` | `destroy` | `admin.tenants.destroy` | soft delete (active binding) |
| `GET /admin/tenants/trashed` | `trashed` | `admin.tenants.trashed` | archived list |
| `PATCH /admin/tenants/{tenant}/restore` | `restore` | `admin.tenants.restore` | `->withTrashed()` |
| `DELETE /admin/tenants/{tenant}/force` | `forceDestroy` | `admin.tenants.force-destroy` | `->withTrashed()` |

`GET /admin/tenants/trashed` is a static segment and does not collide with the
`{tenant}` routes (different verbs / no `GET /admin/tenants/{tenant}`).

### Controller — `app/Http/Controllers/Central/TenantController.php`

Add to the existing controller (keep `store`):

- `destroy(Tenant $tenant)` → `$tenant->delete()`; `back()->with('success', …)`.
- `trashed(Request $request)` → paginated + searchable list over
  `Tenant::onlyTrashed()`, ordered `deleted_at desc`, mirroring
  `DashboardController`'s `per_page` clamp `[10,25,50,100]` and `search` (id/name)
  handling; `->through()` to `{ slug, name, deleted_at }`; renders
  `admin/tenants/trashed` with `filters`.
- `restore(Tenant $tenant)` (route uses `withTrashed`) → `$tenant->restore()`;
  `back()->with('success', …)`.
- `forceDestroy(Tenant $tenant)` (route uses `withTrashed`) →
  `$tenant->forceDelete()` (fires `forceDeleted` → drops DB);
  `back()->with('success', …)`.

All mutating actions use `back()` so the admin returns to the exact page/search
they were on (matching the existing `Tenant\CategoryController` pattern); the
Inertia redirect reload refreshes `tenants` + `stats` for that URL.

### Validation copy — `StoreTenantRequest`

`Rule::unique('tenants','id')` counts trashed rows, so a slug that is currently in
the archive is rejected on create. Keep that behaviour (the slug's database still
exists); sharpen `slug.unique` copy to hint that the slug may be in the archive and
must be restored or permanently deleted first.

## Frontend

### Dashboard (`resources/js/pages/admin/dashboard.tsx`)

- Add a destructive **Delete** item to each row's dropdown menu.
- Clicking opens an `AlertDialog`: "Delete `{name}`? Its workspace becomes
  inaccessible, but its data is kept — you can restore it from Archived."
- Confirm → `router.delete(route('admin.tenants.destroy', slug), { preserveScroll: true })`;
  the `back()` redirect reloads `tenants`/`stats` for the current URL; success toast.

### New page (`resources/js/pages/admin/tenants/trashed.tsx`)

- Same visual language as the dashboard: card, paginated table, debounced server
  search, per-page `Select`, pagination footer, always-mounted `sr-only`
  `role="status"` region, `aria-busy` container.
- Columns: **Tenant** (name + initials), **Slug**, **Deleted** (relative time of
  `deleted_at`).
- Row actions:
  - **Restore** → `AlertDialog` confirm → `router.patch(route('admin.tenants.restore', slug))`.
  - **Delete permanently** → **type-to-confirm** `AlertDialog`: the confirm button
    is disabled until the admin types the exact slug; body states this drops the
    `tenant_{slug}` database and cannot be undone → `router.delete(route('admin.tenants.force-destroy', slug))`.
- Empty state: "Archive is empty."

### Admin sidebar (`resources/js/components/admin/admin-sidebar.tsx`)

- Add an **Archived** nav item (e.g. lucide `Archive` / `Trash2`) linking to
  `admin.tenants.trashed`.

## Testing (TDD — write failing first)

Backend (Pest, `tests/Feature/Central/`):

1. `destroy` soft-deletes: central row has `deleted_at`; redirect + `success` flash.
2. **DB retained on soft delete** — after `destroy`, the `tenant_<slug>` database
   still exists (the anti-footgun test).
3. **Trashed tenant inaccessible** — after `destroy`, `GET /{slug}/login` → 404.
4. `trashed` lists only soft-deleted tenants, paginated.
5. `restore` clears `deleted_at`; `GET /{slug}/login` reachable again.
6. **`forceDestroy` removes the row AND drops `tenant_<slug>`** (assert the
   database no longer exists).
7. All four routes reject guests / require `auth:central`.
8. (Optional) creating a tenant whose slug is currently trashed is rejected.

Frontend gates: `bun run check:ci` (0 errors), `bun run types:check`, `bun run build`.

## Out of scope

- Bulk delete / bulk restore.
- Auto-expiry / retention purge of archived tenants.
- Per-tenant session invalidation on soft delete (the 404 already blocks access;
  existing tenant sessions become unusable once routes 404).
- Queued DB teardown (`DeleteDatabase` stays synchronous, as today).
