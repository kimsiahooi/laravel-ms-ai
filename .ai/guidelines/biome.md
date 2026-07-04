# Biome (JS/TS/JSON Formatter & Linter)

This project uses **Biome** as the single formatter + linter for JavaScript,
TypeScript, JSX/TSX, and JSON. It **replaces ESLint and Prettier** (both removed).
Config: `biome.json`. Full policy: `docs/CODING-STANDARDS.md`.

- If you modified any JS/TS/TSX/JSON files, you must run `bun run check`
  (`biome check --write .`) before finalizing — it formats, organizes imports,
  and applies safe lint fixes.
- Verify with `bun run check:ci` (`biome check .`): a clean run is **0 errors**.
  Pre-existing warnings come from Laravel's starter-kit UI and are non-blocking —
  do not add new ones.
- Do NOT hand-format JS/TS or reintroduce ESLint/Prettier. PHP formatting stays
  with Pint; Biome never touches PHP/Blade.
- Package manager / JS runtime is **Bun** (`bun install`, `bun run …`), not
  npm / pnpm / yarn.
