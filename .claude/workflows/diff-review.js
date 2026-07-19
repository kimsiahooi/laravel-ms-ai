// Cheap, diff-scoped, single-pass code review.
//
// The deterministic gates (biome, pint, tsc, phpstan, pest, the ui-guard) catch
// the mechanical issues for free. This workflow is the *judgment* layer — it
// reviews only the changed source, one reviewer per file, single pass (no
// adversarial verify swarm), and skips trivially small diffs. Roughly ~1/5 the
// tokens of a full multi-lens+verify review.
//
// Run it after a substantial change:  Workflow({ name: 'diff-review' })
// For the deeper, billed pass on a risky change, use /code-review instead.

export const meta = {
  name: 'diff-review',
  description: 'Cheap diff-scoped single-pass review of the current changes',
  phases: [
    { title: 'Scout', detail: 'list changed source files (skip if the diff is small)' },
    { title: 'Review', detail: 'one reviewer per changed file, single pass' },
  ],
}

const MIN_ADDED_LINES = 40 // below this the diff isn't worth an agent pass
const MAX_FILES = 12 // cap the reviewer fan-out so cost stays bounded

const SCOUT_SCHEMA = {
  type: 'object',
  additionalProperties: false,
  properties: {
    files: {
      type: 'array',
      items: {
        type: 'object',
        additionalProperties: false,
        properties: {
          path: { type: 'string' },
          added: { type: 'number' },
          removed: { type: 'number' },
        },
        required: ['path', 'added'],
      },
    },
    totalAdded: { type: 'number' },
  },
  required: ['files', 'totalAdded'],
}

const FINDINGS_SCHEMA = {
  type: 'object',
  additionalProperties: false,
  properties: {
    findings: {
      type: 'array',
      items: {
        type: 'object',
        additionalProperties: false,
        properties: {
          file: { type: 'string' },
          line: { type: 'number' },
          severity: { type: 'string', enum: ['high', 'medium', 'low'] },
          claim: { type: 'string' },
          failure: { type: 'string' },
        },
        required: ['file', 'severity', 'claim'],
      },
    },
  },
  required: ['findings'],
}

phase('Scout')
const scout = await agent(
  `List the source-code changes currently under review in this git repo.

Compute the change set as the UNION of:
  A) commits not yet pushed:  git diff --numstat @{upstream}...HEAD
     (if that errors or is empty, try:  git diff --numstat origin/main...HEAD)
  B) uncommitted working changes:  git diff --numstat HEAD

Keep only real source files. EXCLUDE: lockfiles (*.lock, bun.lock, composer.lock),
generated/vendored trees (resources/js/routes, resources/js/actions,
resources/js/wayfinder, resources/js/components/ui, bootstrap/ssr,
resources/js/types/generated.d.ts), build output (public/build), and
phpstan-baseline.neon.

Return JSON: { files: [{path, added, removed}], totalAdded } where totalAdded is
the sum of added lines across the kept files.`,
  { label: 'scout', phase: 'Scout', schema: SCOUT_SCHEMA, agentType: 'general-purpose' },
)

const files = (scout?.files ?? []).filter((f) => f && typeof f.path === 'string')
const totalAdded = scout?.totalAdded ?? 0

if (files.length === 0 || totalAdded < MIN_ADDED_LINES) {
  log(
    `Skipping review: ${totalAdded} added line(s) across ${files.length} file(s) — under the ${MIN_ADDED_LINES}-line threshold.`,
  )
  return { skipped: true, totalAdded, changedFiles: files.length, findings: [] }
}

const targets = files.slice(0, MAX_FILES)
if (files.length > MAX_FILES) {
  log(`Reviewing the first ${MAX_FILES} of ${files.length} changed files (cap).`)
}

phase('Review')
const reviewed = await parallel(
  targets.map((f) => () =>
    agent(
      `Review ONLY the changes to \`${f.path}\`. Read the file and its diff
(git diff HEAD -- "${f.path}", and git diff @{upstream}...HEAD -- "${f.path}").

Report only REAL problems a compiler/linter/test would miss: logic errors, wrong
routes/params/props, SSR or hydration hazards, security holes, broken edge cases,
and semantic/UX mistakes (e.g. copy that misrepresents state). Do NOT report
formatting, style, import order, or anything biome/pint/tsc/phpstan/pest already
enforce. Be precise; prefer a concrete input -> wrong-output trace.

Return JSON: { findings: [{file, line, severity, claim, failure}] }. Empty
findings array if the change looks correct.`,
      {
        label: `review:${f.path}`,
        phase: 'Review',
        schema: FINDINGS_SCHEMA,
        agentType: 'general-purpose',
      },
    ).then((r) => (r?.findings ?? []).map((x) => ({ ...x, file: x.file || f.path }))),
  ),
)

const findings = reviewed
  .filter(Boolean)
  .flat()
  .sort((a, b) => {
    const order = { high: 0, medium: 1, low: 2 }
    return (order[a.severity] ?? 3) - (order[b.severity] ?? 3)
  })

log(`Review complete: ${findings.length} finding(s) across ${targets.length} file(s).`)
return { skipped: false, reviewedFiles: targets.length, findings }
