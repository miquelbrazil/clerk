# CLAUDE.md — Clerk

Clerk is a personal financial data engineering system. It ingests financial data
from disparate sources (PDF statements, Harvest, QuickBooks Online, Xero,
Debit & Credit, ADP), normalizes it into a canonical schema in a Postgres
staging database, deduplicates and categorizes it, and pushes clean data into
Zoho Books (the system of record). It also serves a local/deployable web UI
(Slim) for review queues, receipt import, and visualizations.

**This is a PUBLIC repository used as a portfolio/public record.** Read the
Privacy Rules below before writing or committing anything.

## Stack

- PHP: minimum 8.4 (Pest 5 floor). Local dev targets 8.5. CI matrix: 8.4 + 8.5.
- CLI: Symfony Console (`bin/console`)
- Web: Slim 4 + Plates (league/plates) native-PHP templates, Tailwind
  (standalone CLI binary — no Node toolchain), Alpine.js (vendored).
  No package.json unless a future need (HMR, JS bundling) forces it —
  that requires a decisions.md entry first.
- Templating rule: ALL dynamic output goes through Plates' escaping helper
  (`$this->e()`). Raw `<?= $var ?>` of any non-literal is a defect. Native PHP
  templates do not auto-escape; this rule is the substitute.
- Tests: Pest 5 (PHPUnit 13). Testing is foundational — see Testing Rules.
- DB: PostgreSQL (local via Lando service; hosted = DO Managed Postgres).
  No SQLite. Data access via cakephp/orm used STANDALONE (no framework):
  ConnectionManager + TableLocator bootstrap in src/, Table/Entity classes,
  no raw SQL in application code (query builder/ORM only; raw SQL permitted
  solely inside migrations when unavoidable).
- Migrations: Phinx (robmorgan/phinx), standalone. All schema changes via
  migrations, no exceptions.
- Object storage: Backblaze B2 via league/flysystem-aws-s3-v3
- Dev environment: Lando
- Secrets: Infisical — two-zone model, see Secrets Rules
- PDF extraction: Setasign SetaPDF-Extractor (LICENSED source package, not the
  ionCube eval build) — compare against smalot/pdfparser and `pdftotext -layout`
  per vendor and use whichever parses that vendor most reliably.

## Commands

- `lando start` — dev environment (PHP + Postgres)
- Zone B (human-run): `infisical run --env=dev -- lando php bin/console <command>`
- Zone A (agent-run): commands are executed inside an Agent Proxy session —
  see Secrets Rules; the agent must NOT wrap commands in `infisical run`
- `lando composer test` — run Pest suite
- `lando composer test -- --tia` — impacted tests only
- `lando composer stan` — PHPStan (level 8)
- `lando composer cs` — coding standards check
- `vendor/bin/phinx migrate` / `rollback` — via console wrapper once built

(If a command above doesn't exist yet, creating it is part of Phase 1.)

## Hard Rules — Privacy (public repo)

NEVER commit, in any file, including tests, fixtures, docs, comments, and
commit messages:

1. Real financial values, transaction descriptions, balances, or dates tied to
   real transactions
2. Account identifiers of any kind: account numbers, card fragments,
   institution + account-label combinations (e.g. "Northgate Bank Visa 4321" —
   note that this example is fabricated; do not substitute a real one)
3. Personal filesystem paths (anything under the user's home, cloud-storage
   mounts, or revealing directory structures)
4. Real vendor/categorization rule sets or CoA mappings (these describe real
   spending patterns) — those live outside this repo
5. Secrets of any kind (see Secrets Rules)
6. Output artifacts: audit logs, run reports with real data, DB dumps,
   Zoho snapshots

All test fixtures MUST be synthetic. Statement fixtures are fabricated
documents that mimic each vendor's *format* with fake data that still
satisfies arithmetic invariants (balances reconcile).

`data/`, `output/`, `logs/`, `tmp/` are gitignored working directories.
Real inputs and outputs only ever live there or in B2.

If you are ever unsure whether something is committable, it is not. Flag it.

## Hard Rules — Secrets (Infisical, two-zone model)

**Zone A — agent-driven & autonomous execution** (coding-agent sessions,
future unattended jobs): credentials are brokered by the **Infisical Agent
Proxy**. Outbound HTTP from processes in this zone routes through the proxy
(HTTPS_PROXY), which applies real credentials at the network boundary. The
agent and its child processes hold placeholders only.
- The agent must NEVER attempt to read, print, or persist credential values,
  even placeholders; never run `env`/`printenv` to inspect secrets; never
  bypass HTTPS_PROXY.
- If a required flow cannot be brokered by the proxy (see docs/decisions.md
  D-013 spike outcome), the fallback is env injection into the SPECIFIC child
  process only, with values never echoed or logged.

**Zone B — trusted deterministic runtime** (commands the human runs directly;
the deployed Slim app): `infisical run -- <command>` locally; Infisical
machine identity (Universal Auth) scoped to this project for CI/deploys.
- Code reads secrets from environment variables only.
- NEVER write secrets to `.env` files, config files, code, or logs.
  `.env.example` documents variable NAMES only.
- Never echo secret values in command output or debug statements.

## Hard Rules — Data Safety

- Zoho Books is the system of record. Scripts NEVER write to Zoho without:
  (a) a dry-run mode that produces a reviewable proposed-changes file,
  (b) explicit human approval, (c) per-record audit logging with record IDs.
- All bulk operations must be idempotent and safely re-runnable.
- Destructive operations (deletes, merges) require an explicit `--confirm` flag
  and log enough to reverse the operation.
- Respect Zoho API rate limits: centralized throttling in the API client,
  never ad-hoc sleeps scattered in scripts.

## Testing Rules

- Tests are written alongside (preferably before) implementation. No parser,
  adapter, or rules-engine change merges without tests.
- Parser invariant: for every fixture statement,
  `opening_balance + sum(transactions) == closing_balance`.
- Dedup invariant: running dedup twice produces zero additional changes
  (idempotency test).
- Rules engine: golden-file tests — identical input produces identical
  categorization output.
- Templates: escaping rule (above) is checked in review; any helper that
  outputs raw HTML must be explicitly named `*Raw` and justified.
- Use the Pest Agent plugin to verify changes against the real suite.

## Conventions

- PSR-4 (`App\` → `src/`), PSR-12 style, strict types in every file.
- Core logic lives in `src/` as a library; `bin/console` commands and the Slim
  app are thin consumers of it. No business logic in commands, routes, or
  templates. Templates receive prepared view data only.
- Small classes, constructor injection, no static state (the CakeORM
  TableLocator is the sanctioned exception, wrapped in our bootstrap).
- Architecture diagram (`docs/architecture.md`, Mermaid) MUST be updated in the
  same PR as any structural change.
- Decisions of consequence get an entry in `docs/decisions.md` (ADR-lite).
- Commits: imperative mood, scoped, small. Never commit directly to main once
  Phase 1 is complete — branch + PR.

## Current Phase

See `docs/plan.md`. Do not skip ahead: Phase 0 (repo hygiene / history scrub)
must complete before any other work is committed.
