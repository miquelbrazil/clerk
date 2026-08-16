# Clerk — Phased Plan

Each phase has acceptance criteria. A phase is complete when its criteria pass
and the work is committed (post-Phase-1: merged via PR). Phases are sequential
unless noted.

## Phase 0 — Repo hygiene & history scrub (BLOCKING)

Existing public history contains private information that must be removed
before any further work.

Tasks:
- Remove from tree AND history: `phpinfo.txt`; ionCube loader binaries under
  `.lando/php/extensions/`; the hardcoded personal path + account label in
  `src/Command/ParseStatement.php`.
- Method: `git filter-repo` (or recreate repo from scrubbed tree + force-push —
  acceptable given no forks/consumers). Human runs the force-push.
- Parameterize `ParseStatement`: statement path becomes a required CLI argument.
- Add gitignored working dirs: `data/`, `output/`, `logs/`, `tmp/` (with .gitkeep).
- Add gitleaks pre-commit hook.
- Rewrite README to describe the project accurately (public-facing).

Acceptance:
- `git log --all -p | grep` finds no trace of the personal path, account label,
  or phpinfo content in any commit.
- Fresh clone + `composer install` + `bin/console list` works.

**Status (2026-08-16): COMPLETE.** Force-push landed; `origin/main` and local
`main` both at the rewritten history, and the scrub greps come back clean.
Acceptance criterion 2 (the one blocked below) was cleared in Phase 1 by
removing the Setasign eval package: `composer install` + `bin/console list`
both succeed from a clean checkout on PHP 8.5.6.

Historical notes from the 2026-08-13 session, kept for the record:
- Scope added beyond the task list above: the ionCube `build_as_root` line in
  `.lando.yml` also carried a personal filesystem path, so it was removed
  (it referenced a binary that Phase 0 deletes anyway). `composer.json`
  name/description and the Lando app name were renamed accounting-tools →
  clerk. `bin/console` now uses Dotenv `safeLoad()` and `ray.php` tolerates an
  absent `RAY_LOCAL_DIR`, both required for the fresh-clone criterion.
- Acceptance criterion 2 is BLOCKED, not failed, by a Phase 1 item:
  `setasign/setapdf-extractor_eval_ioncube_php8.1` pins `php 8.1.*` and its
  license download returns HTTP 401 without interactive setasign credentials,
  so `composer install` cannot complete from a clean checkout. With that one
  package removed, `composer install` and `bin/console list` both succeed
  (verified on PHP 8.5.6). Removing it is Phase 1's first bullet (D-006/D-008);
  it was deliberately NOT done here to avoid pulling Phase 1 work into Phase 0.

## Phase 1 — Foundation modernization

Tasks:
- composer.json: `"php": ">=8.4"`; upgrade Symfony Console to current major;
  remove the Setasign ionCube eval package; add the licensed Setasign source
  package (human provides license credentials via Infisical) OR defer extractor
  choice to Phase 4 and add smalot/pdfparser now.
- Lando: PHP 8.5 appserver + Postgres service; remove ionCube extension config.
- Add Pest 5 + PHPStan (level 8) + coding-standards tooling; composer scripts
  (`test`, `stan`, `cs`); one smoke test proving the suite runs.
- Add cakephp/orm (standalone) + Phinx: ConnectionManager/TableLocator
  bootstrap, phinx config wired to Lando Postgres, one trivial migration +
  Table class + test proving the stack round-trips.
- Infisical integration, Zone B: document the `infisical run` pattern in
  README; `.env.example` with variable names only; verify a trivial command
  reads an env var injected by Infisical.
- Infisical integration, Zone A SPIKE (Agent Proxy): configure a local Agent
  Proxy session; verify a PHP/Guzzle request routes through it (HTTPS_PROXY +
  proxy CA trust) and receives a brokered credential against a simple test
  service. Then determine and record in decisions.md (D-013 outcome):
  (a) can the Zoho OAuth token exchange (client secret + refresh token in
  POST body to accounts.zoho.com) be brokered via proxied-service config?
  (b) can B2's S3 SigV4-signed requests be brokered? For any flow that
  cannot: fallback is per-process env injection with no-echo rules.
- GitHub Actions CI: matrix PHP 8.4/8.5 — install, stan, cs, test.
- Add Slim skeleton (routes stub, Plates templating with escaping convention,
  health endpoint) served via Lando.
- Add `docs/architecture.md` (seeded from handoff) and `docs/decisions.md`.

Acceptance: CI green on both PHP versions; `lando start` serves the Slim
health page; Pest suite runs under both versions.

**Status (2026-08-16):** all tasks complete except the Zone A spike. Verified
locally on PHP 8.5.6:
- `composer install` clean; Symfony Console 8.1 (note: `Application::add()` was
  removed in favour of `addCommand()`); ionCube eval package gone,
  `smalot/pdfparser` in its place per D-018.
- Lando runs PHP 8.5 + nginx + Postgres 16. `/health` returns HTTP 200 with a
  live database check; `/` renders through Plates.
- Pest: 8 passing, including a real Postgres round-trip through Phinx →
  CakeORM → Entity. PHPStan level 8 clean with no baseline and no ignores.
  PHPCS clean.
- `phinx migrate` / `rollback -t 0` / re-migrate all verified.
- Zone B verified: `env:check` reports presence only and correctly detects an
  injected variable. The `infisical` CLI is NOT installed on this machine, so
  the real `infisical run` half is unrun — install it, then compare
  `lando console env:check` against
  `infisical run --env=dev -- lando console env:check`.
- CI workflow written and its YAML validated; the suite was additionally run
  against Postgres over TCP with CI's exact env vars. **The PHP 8.4 leg is
  unproven locally** — the `php@8.4` formula is installed but its binary is not
  linked, so 8.4 gets its first real run on CI.

**Outstanding — Zone A Agent Proxy spike (D-013).** Deliberately not attempted:
it requires real Zoho and B2 credentials against live endpoints, which the
agent must never handle (CLAUDE.md Zone A rules), and the `infisical` CLI is
absent. This is a human-run task. Its outcome is recorded under D-013, and
Phase 2 depends on it, since the Zoho OAuth client is the first flow that must
choose between proxy brokering and per-process env injection.

## Phase 2 — Zoho recon (read-only)

Tasks:
- Zoho OAuth client (Guzzle): token refresh, centralized rate limiting,
  org-ID handling. Credentials via Infisical.
- Snapshot commands (read-only): full Chart of Accounts; transaction counts by
  account/year; uncategorized transaction volume; bank/card accounts list.
- Snapshots written to `output/recon/<date>/` as JSON + a Markdown summary.
  Raw snapshots uploaded to B2; summary reviewed by human. NOTHING committed.
- Full Zoho Books organization export/backup procedure documented and executed
  (human-triggered), archived to B2.

Acceptance: recon summary reviewed; backup verified in B2; zero write calls
made (assert via client design: write methods don't exist yet).

## Phase 3 — Staging database & canonical model

Tasks:
- Postgres schema via Phinx migrations + CakeORM Table/Entity classes:
  `transactions` (canonical: date, posted_date,
  amount_minor, currency, description_raw, description_normalized, source_system,
  source_record_id, source_file, account_ref, category_ref nullable,
  import_batch_id, hash), `accounts`, `import_batches`, `match_candidates`,
  `merge_decisions`, `categorization_rules`, `audit_log`.
- Money as integer minor units. Never floats.
- Import batch concept: every ingestion run is a batch, fully attributable
  and reversible.
- Fixture/demo data generator (doubles as test factory).

Acceptance: migrations up/down cleanly; factories generate coherent demo data;
schema documented in architecture doc.

## Phase 4 — Statement parsers (repeat per vendor)

Human drops 2–3 real sample statements per vendor into `data/samples/<vendor>/`
(gitignored). For each vendor:
- Inspect extracted text structure (SetaPDF licensed / smalot / pdftotext —
  pick per vendor, record choice in decisions.md).
- Write parser producing canonical transactions.
- Create SYNTHETIC fixture(s) mimicking the format; commit fixtures + tests.
- Invariant test: opening + Σ transactions == closing, per page and per statement.
- Edge cases: multi-line descriptions, page breaks mid-table, year boundaries,
  credits vs debits sign conventions.

Acceptance per vendor: real samples parse with balances reconciling; synthetic
fixture tests green; parser registered in an ingestion command
(`clerk:ingest statement <vendor> <path>` → staging DB, batched).

## Phase 5 — Source adapters (parallelizable with Phase 4)

One adapter per system: Harvest, QuickBooks Online, Xero, Debit & Credit,
ADP earnings statements. Prefer each system's export files (CSV/QBO/OFX/PDF)
over live APIs unless an API is clearly easier for that system.

Each adapter: maps source format → canonical transaction schema; preserves
source_system + source_record_id; synthetic fixtures + tests; registered
ingestion command.

Acceptance: each adapter ingests a real export into staging (human-verified
row counts), synthetic tests green.

## Phase 6 — Deduplication engine

- Exact-match pass: hash on (amount, date, normalized description, account).
- Fuzzy pass: amount match + date window (±3 days, transaction vs posting) +
  description similarity → writes `match_candidates`, never auto-merges
  across sources unless exact.
- Precedence rules (source of truth per data type) encoded in config:
  bank/card statements authoritative for cash transactions; Harvest for
  invoicing history; ADP for payroll. Document in decisions.md.
- Review queue UI (first real Slim feature): human adjudicates candidates;
  every decision recorded in `merge_decisions` (reversible).
- Idempotency test: second run produces zero new candidates/changes.

Acceptance: dedup across ≥2 overlapping real sources produces a candidate
queue with no silent merges; idempotency test green.

## Phase 7 — CoA normalization, categorization, Zoho push

- CoA proposal: from recon snapshot + staging data, propose normalized Chart
  of Accounts + mapping (old → new). Human reviews/edits. Mapping lives
  OUTSIDE the repo (private storage), loaded at runtime.
- Categorization rules engine: vendor/description pattern → account + reporting
  tags. Rules private. Golden-file tests use synthetic rules + fixtures.
  Ambiguous transactions → exceptions queue in UI, never guessed.
- Zoho write client: every write command has `--dry-run` (default) producing a
  proposed-changes file; `--execute` requires the human to have approved;
  per-record audit log to B2.
- Execution order: CoA changes → historical imports → categorization →
  reporting tags. Spot-check via Zoho Books MCP conversationally after each
  bulk run.

Acceptance: 5 years of transactions categorized in Zoho with a complete audit
trail; exceptions queue empty or consciously deferred; balances reconcile
against statement totals per account per year.

## Phase 8 — Web UI & deployment

- Flesh out Slim app: dashboards/visualizations (Money-style), receipt import,
  review queues (already built in 6/7).
- Demo mode: seeded synthetic database; deployable public instance.
- Topology (D-016): Cloudflare = DNS + CDN + Access (auth; no app-level auth
  in v1) → DO App Platform = app compute → DO Managed Postgres = database
  (kept adjacent to compute; not cross-cloud) → B2 = object storage.
  Cloudflare-only hosting is ruled out (no production PHP runtime on Workers).
  Google Cloud Run remains the recorded alternative compute target.
- Secrets in deployment: Infisical machine identity (Zone B). If unattended
  autonomous jobs are ever hosted, they run behind a STANDALONE Agent Proxy
  (Zone A) — new decisions.md entry required at that time.

Acceptance: public demo reachable; real instance reachable only through
Cloudflare Access; deploys are reproducible from the repo.

## Standing rules (all phases)

- Human runs force-pushes, approves all Zoho writes, and reviews all
  dry-run diffs.
- Agent sessions that need external API access run inside an Agent Proxy
  session (Zone A); the agent never handles real credential values.
- Update `docs/architecture.md` in the same PR as structural changes.
- New consequential choices → `docs/decisions.md`.
