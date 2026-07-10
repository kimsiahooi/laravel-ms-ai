# De-hardcode: Wayfinder paths + per-resource labels — Design

**Goal:** Remove hardcoded path strings and repeated resource labels from the frontend
business pages, referencing single-homed sources instead. Backend already uses named routes.

**Scope (agreed):** paths + shared strings. Controller toast messages and single-use UI copy
(placeholders, bespoke empty-state prose) stay inline. PHP↔TS magic strings (`'toast'`, `'assets'`)
are already single-homed — untouched.

## Part A — Paths via Wayfinder

`laravel/wayfinder` is installed, generates `@/routes` + `@/actions` (git-ignored, like the
starter kit already uses), and produces tenant helpers that take the `{tenant}` param, e.g.
`rawMaterials.index.url({ tenant })` → `/demo/raw-materials`.

- **Catalog pages (5):** replace `` const base = `/${tenant.slug}/<resource>` `` with the Wayfinder
  index helper: `const base = index.url({ tenant: tenant.slug })`. The shared abstractions
  (`DataTable`, `useDelete`, `ResourceFormDialog`) keep taking `base` and append `/${id}` (RESTful).
  Breadcrumb dashboard link + tenant `login` link also use their Wayfinder helpers.
- **Admin pages (2):** replace inline `router.delete('/admin/tenants/${slug}/force')` etc. and the
  `<Form action>` / `<Link href>` strings with the matching Wayfinder helpers.

## Part B — Per-resource display descriptor

`resources/js/config/resources.ts` (`.tsx` — holds icon components): one `ResourceMeta`
per catalog resource:

```ts
export type ResourceMeta = { singular: string; plural: string; icon: LucideIcon };
export const rawMaterialMeta: ResourceMeta = { singular: 'raw material', plural: 'Raw materials', icon: Boxes };
```

Each catalog page derives its repeated copy from the descriptor:
- page `<h1>` + breadcrumb + `<Head title>` = `plural`
- toolbar button + `entityLabel` = `singular` (`New ${singular}`)
- empty-state `icon` = `icon`, title = `No ${plural.toLowerCase()} yet`

The **bespoke empty-state description prose stays inline** (single-use, per-resource wording).

## Non-goals
- Not touching controller messages (stay inline).
- Not centralizing single-use placeholders/titles.
- Not changing the shared abstractions' `baseUrl` contract (still a string).

## Verification
`bun run types:check`, `bun run build`, `bun run check:ci`, 122 Pest tests, and a browser smoke
(load a catalog page, create + delete → still works, paths resolve).
