# Coding Standards

This project enforces two formatter/linters, split by language. Run them before
finalizing any change.

| Language | Tool | Config | Primary command |
|---|---|---|---|
| JS / TS / JSX / TSX / JSON | **Biome** 2.x | `biome.json` | `bun run check` |
| PHP | **Laravel Pint** | `pint.json` | `vendor/bin/pint --dirty` |

**Package manager / JS runtime: [Bun](https://bun.sh).** Use `bun install` and
`bun run …`; the lockfile is `bun.lock`. Do not use npm / pnpm / yarn.

---

## Biome — frontend (JS / TS / JSON)

Biome **replaces ESLint + Prettier** (both were removed when Biome was adopted).
A single, fast tool handles formatting, linting, and import organization.

### Commands

- `bun run check` → `biome check --write .` — format + organize imports + safe
  lint fixes. **Run this before committing any frontend change.**
- `bun run check:ci` → `biome check .` — verify only, no writes (CI / pre-commit).
  A passing run has **0 errors**.
- `bun run format` / `bun run lint` — formatting-only / lint-only variants.
- `bun run types:check` → `tsc --noEmit` — TypeScript type checking (Biome does
  not type-check).

### Style (matches the Laravel starter kit it replaced)

- **Indent:** 4 spaces
- **Quotes:** single (JS), double (JSX attributes)
- **Semicolons:** always
- **Line width:** 80
- **Imports:** auto-organized/sorted on `check`
- **Type imports:** `import type { … }` enforced
- **Tailwind classes:** sorted in `class`/`className` and in `clsx()`, `cn()`,
  `cva()` — see rule policy below

### Rule policy

- Base: Biome **recommended** ruleset.
- `suspicious/noExplicitAny` is **off** (matches the prior ESLint config — `any`
  is allowed where pragmatic).
- These are **`warn`, not error**, because they only fire on Laravel's
  **starter-kit UI** (`welcome.tsx`, `pages/auth/*`, the two-factor components) —
  code this project replaces during the UI/UX phase (spec §8 / Phase 6). Treat
  them as TODOs when you rewrite or touch those files; **new code should
  introduce zero of them:**
  - a11y: `noSvgWithoutTitle`, `noPositiveTabindex`, `useButtonType`,
    `useSemanticElements`, `useAriaPropsSupportedByRole`
  - `suspicious/noArrayIndexKey`, `suspicious/useIterableCallbackReturn`
  - `security/noDangerouslySetInnerHtml` — one intentional use in the starter
    theme-flash script. **Re-enable as `error`** once the starter `welcome.tsx`
    is removed.
  - `nursery/useSortedClasses` (Tailwind sorting). It is not applied by the safe
    `check` fixer; run `bunx biome check --write --unsafe` to auto-sort classes.
- Baseline today: `bun run check:ci` = **0 errors, ~88 warnings**, all
  starter-kit inherited. **Do not add new errors.**

### Excluded from Biome

Configured in `biome.json` (`files.includes`) and via `.gitignore`
(`vcs.useIgnoreFile`):

- Generated: `resources/js/{actions,routes,wayfinder}`, `bootstrap/ssr`
  (Wayfinder + SSR output)
- Vendored UI: `resources/js/components/ui` (shadcn)
- `resources/css/**` — Tailwind v4 CSS; Biome's CSS parser does not fully support
  v4 at-rules (`@theme`, `@utility`, …), so CSS is left untouched
- `composer.json`, plus the always-ignored `vendor`, `node_modules`,
  `public/build`

### Editor setup

Install the **Biome** VS Code / JetBrains extension, set it as the default
formatter for JS/TS/JSON, and enable format-on-save. Disable ESLint/Prettier
editor extensions for this project so they don't fight Biome.

---

## PHP — Laravel Pint

- Run `vendor/bin/pint --dirty` before finalizing PHP changes (config `pint.json`).
- Biome never touches PHP or Blade files.

---

## Pre-finalize checklist

1. `bun run check` — frontend, **0 errors**
2. `bun run types:check` — passes
3. `vendor/bin/pint --dirty` — if any PHP changed
4. `php artisan test` — passes
