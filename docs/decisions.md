# Clerk — Decision Log (ADR-lite)

Format: one entry per consequential decision. Status: Accepted unless noted.
New decisions are appended, never rewritten; superseded decisions are marked.

## 2026-08 · D-001 — Bulk work via REST API scripts, not per-transaction MCP calls
Bulk operations (import, dedup, mass categorization) are executed by
deterministic PHP scripts against the Zoho Books REST API. AI judgment is
encoded once into reviewable rule sets, then applied consistently by code.
Zoho Books MCP is reserved for conversational inspection and spot-checks.
**Why:** per-transaction LLM tool calls are slow, costly, and non-deterministic;
tax-grade bookkeeping requires consistency and auditability.

## 2026-08 · D-002 — PHP as project language
**Why:** primary language of the maintainer; reference value of readable
code outweighs marginally larger Python ecosystem for this domain. Zoho API
is plain REST+JSON; no capability gap.

## 2026-08 · D-003 — Public repo with strict public/private split
Code, architecture, synthetic fixtures: public (portfolio value).
Real data, rule sets, CoA mappings, outputs: private (gitignored dirs, B2,
private config storage). Enforced by CLAUDE.md hard rules + gitleaks hook.
Prior private info in history removed via history scrub (Phase 0).

## 2026-08 · D-004 — Staging-database architecture
All sources ingest into a staging DB first; dedup/categorization happen there;
only clean, approved data flows to Zoho. Zoho is system of record, staging is
workspace.

## 2026-08 · D-005 — Secrets via Infisical, runtime injection only
`infisical run -- <cmd>` for interactive use; machine identity (Universal
Auth) for CI/deploys. No secrets on disk; `.env.example` documents names only.
**Supersedes:** earlier plan to use 1Password CLI. **Why:** maintainer already
pays for Infisical and wants to establish the correct usage pattern.

## 2026-08 · D-006 — PHP 8.4 minimum, 8.5 local, CI matrix both
**Why:** Pest 5 requires >= 8.4; 8.5 ecosystem friction is largely resolved;
matrix CI catches stragglers. Blocker removed by dropping the ionCube eval
Setasign build in favor of the licensed source package (D-008).

## 2026-08 · D-007 — Slim 4 for web UI; Twig + Tailwind (standalone CLI) + Alpine
**Why:** maintainer preference; light stack; no Node toolchain (Tailwind
standalone binary, Alpine vendored). Symfony Console retained for CLI.

## 2026-08 · D-008 — PDF extraction: licensed SetaPDF source package, evaluated per-vendor against smalot/pdfparser and pdftotext
**Why:** eval ionCube build pins PHP 8.1 and blocks D-006. Extractor choice is
per-vendor pragmatic; record the pick per vendor here as parsers land.

## 2026-08 · D-009 — Pest 5 as test framework; testing as project foundation
Invariant-driven: balance reconciliation for parsers, idempotency for dedup,
golden files for the rules engine. Pest Agent plugin used by the coding agent
to verify changes.

## 2026-08 · D-010 — PostgreSQL from day one; no SQLite
**Why:** hosted destination (App Platform + managed Postgres) makes Postgres
inevitable; dual-dialect SQLite/Postgres split is a tax with no benefit given
Lando makes local Postgres trivial; App Platform ephemeral filesystem punishes
SQLite. Demo vs real = two databases, same schema.

## 2026-08 · D-011 — Auth via Cloudflare Access for real instance; demo instance public
**Why:** zero auth code in v1; maintainer already has Cloudflare. App-level
auth deferred; revisit if it becomes desirable portfolio material.

## 2026-08 · D-012 — Money as integer minor units
Floats never touch monetary values. Non-negotiable for reconciliation
invariants.

## 2026-08 · D-013 — Two-zone secrets model: Agent Proxy for agent-driven execution
Zone A (coding-agent sessions, future unattended jobs): outbound HTTP brokered
by Infisical Agent Proxy (HTTPS_PROXY); real credentials applied at the
network boundary, never present in the agent's environment. Zone B (human-run
commands, deployed app runtime): `infisical run` env injection / machine
identity. **Extends D-005.** **Why:** env injection removes secrets at rest
but leaves them readable in the process environment of agent-spawned
processes — an exfiltration surface under prompt injection. Proxy brokering
removes credentials from the agent's reach entirely.
**Open (Phase 1 spike):** whether Zoho's OAuth token exchange (secret in POST
body) and B2's SigV4-signed requests are proxy-brokerable; fallback for
non-brokerable flows is per-process env injection with no-echo rules.
Record spike outcome here.

## 2026-08 · D-014 — Native PHP templates via Plates; no Twig
**Amends D-007 (templating portion).** league/plates over Twig and PHP-View.
**Why:** maintainer philosophy — PHP is the templating language; Plates
provides the CakePHP-style View abstraction (layouts, sections, folders)
while staying native and League-ecosystem-aligned. **Cost accepted:** no
auto-escaping; mitigated by a hard convention — all dynamic output through
`$this->e()`, raw-output helpers must be named `*Raw` and justified.

## 2026-08 · D-015 — Data access via cakephp/orm standalone + Phinx migrations
**Why:** maintainer fluency and preference for the CakeORM API; avoids
framework heft of Doctrine/Symfony or Eloquent/Laravel; existing composer.json
already used Cake components (core, chronos, collection). Standalone use =
manual ConnectionManager/TableLocator bootstrap, accepted. Phinx used
directly for migrations (standalone, Postgres-native). No raw SQL in
application code.

## 2026-08 · D-016 — Deployment topology: Cloudflare edge, DO compute+DB, B2 objects
Cloudflare = DNS + CDN + Access (auth). DO App Platform = app compute.
DO Managed Postgres = database, deliberately adjacent to compute (cross-cloud
DB latency buys nothing). B2 = object storage. **Cloudflare-only rejected:**
Workers has no production PHP runtime. Google Cloud Run recorded as alternate
compute target if App Platform cost/constraints chafe. Multi-provider spread
is also deliberate portfolio surface.

## 2026-08 · D-017 — No Node toolchain
Tailwind standalone CLI binary + vendored Alpine.js; no package.json, no Vite.
**Why:** server-rendered Plates + Alpine sprinkles need no HMR/bundling.
**Revisit trigger:** a charting/visualization library requiring a real build
step; adding one requires a new entry here first.

## 2026-08 · D-018 — PDF extractor deferred to Phase 4; smalot/pdfparser in the interim
**Amends D-008 (timing, not substance).** Phase 1 removes the ionCube eval
package and adds `smalot/pdfparser`; the licensed SetaPDF source package is
NOT added yet. **Why:** the extractor choice is per-vendor and evidence-based
(D-008), and that evidence only exists in Phase 4 when real statements are on
hand. Adding a licensed dependency in Phase 1 would gate `composer install` on
Setasign credentials for no Phase 1 benefit — none of Phase 1's acceptance
criteria touch PDF parsing. `ParseStatement` is now a text-dump inspection aid
rather than a parser, which is what Phase 4 actually needs to start.
**Revisit:** Phase 4, per vendor; record each pick under D-008.

## 2026-08 · D-019 — cakephp/i18n is a required dependency, not optional
CakeORM's `Timestamp` behavior instantiates `Cake\I18n\DateTime` directly and
fatals when the package is absent; `DateTimeType` likewise prefers it and only
falls back to `DateTimeImmutable` when it cannot be found. Standalone ORM use
(D-015) therefore requires `cakephp/i18n` explicitly — it is not pulled in by
`cakephp/orm`. Entity datetime properties are typed `\Cake\I18n\DateTime`.

## 2026-08 · D-020 — Local Postgres uses Lando's default superuser
Lando's `postgres` service provisions the superuser as `postgres` with an empty
password and ignores a custom `creds` user/password block; only `database` is
honoured. Local defaults in `ConnectionFactory` match that reality rather than
fighting it. These are throwaway container credentials with no bearing on
deployment, where all `DB_*` values are injected (D-005). Consequence: the env
reader treats an *unset* variable as "use default" but an explicitly empty one
as a real value, since an empty password is legitimate locally.

## 2026-08 · D-021 — Database-dependent tests are grouped, not mocked
Integration tests carry the `database` group and skip when Postgres is
unreachable, so `--exclude-group=database` gives a green suite without a
database while CI and Lando run them for real. **Why:** D-010 rules out a
SQLite fallback, and mocking the ORM would test the mock rather than the
Postgres round-trip that the tests exist to prove.

## 2026-08 · D-022 — Template escaping is enforced by a test, not only review
`tests/Unit/TemplateEscapingTest.php` scans every Plates template and fails on
any short-echo that is not `$this->e()`, a sanctioned structural helper, or an
explicitly named `*Raw` helper. **Why:** D-014 accepted the loss of
auto-escaping on the strength of a convention; a convention guarded only by
human review is one distracted PR away from an XSS defect. The test was
verified to fail on an introduced violation, not merely to pass.

## 2026-08 · D-023 — PHPStan runs single-process ~~(SUPERSEDED by D-024)~~
`spatie/ray` registers a shutdown function that instantiates Ray and a UUID
factory at process exit. Inside PHPStan's parallel workers this was observed
aborting a cold run non-deterministically — a flaky failure unrelated to the
code under analysis, and one that would land on CI (cold cache, shared
runners) rather than locally. Analysis is pinned to one process.
**Cost accepted:** slower analysis as the codebase grows. **Revisit trigger:**
analysis time becoming painful, or dropping spatie/ray — it is currently
called from no application code and is retained only as a debugging aid.

## 2026-08 · D-024 — spatie/ray removed; PHPStan parallelism restored
**Supersedes D-023.** `spatie/ray` is dropped from `require-dev` and `ray.php`
deleted; PHPStan's `maximumNumberOfProcesses: 1` pin is removed with it.
**Why:** D-023's own revisit trigger. Ray was called from no application code,
so the only thing it contributed was a shutdown hook that made cold PHPStan
runs flaky. Removing the cause is better than working around it, and it also
drops five transitive dependencies (spatie/macroable, spatie/backtrace,
ramsey/uuid, ramsey/collection, brick/math) from the dev tree.
**Revisit trigger:** wanting Ray for debugging — reinstall it ad hoc
(`composer require --dev spatie/ray`) rather than carrying it permanently, and
re-pin PHPStan to one process for as long as it is installed.

## 2026-08 · D-025 — Deployment secrets mechanism deferred to Phase 8 (OPEN)
How the deployed app obtains secrets is **not decided**. Options, with the
trade-off that matters for each:
- **Infisical → platform env-var sync** (integration pushes secrets into DO App
  Platform's own store; app reads `getenv()` and knows nothing about
  Infisical). No runtime dependency on Infisical availability; keeps the app
  ignorant, which is what D-005's "environment variables only" rule assumes.
- **Infisical CLI in the container entrypoint** (CLI wraps the app process at
  boot). Secrets never persist in the platform's store, but the app cannot
  start if Infisical is unreachable.
- **Infisical PHP SDK in the app** (app fetches secrets at runtime). Requires
  the app to hold an Infisical credential — this moves the bootstrapping
  problem rather than solving it, and widens what a compromised app can read.
**Why deferred:** the choice is only testable against a real deployment
target, which Phase 8 builds. Recording it now so the question is not
rediscovered later. **Does not affect Zone A/B locally** (D-005/D-013):
`infisical run` on the host, wrapping `lando`, is unchanged.

## 2026-08 · D-026 — Host prerequisites bootstrapped by `bin/setup`, not Composer
`.githooks/pre-commit` requires `core.hooksPath=.githooks` per clone, and
gitleaks requires a host install. Both are enrolled by a single host-side
script, `bin/setup`, invoked once after cloning. **Why:** `lando composer
install` runs Composer inside the app container, so a Composer
`post-install-cmd` would have to reach into the host `.git/` and cope with the
container's PATH not carrying git. Gitleaks is a host binary regardless —
commits happen on the host — so the install and enrollment steps belong
together. `bin/setup` is also the natural home for the docker/lando/gh prereq
checks tracked in #5; its OS gate points at #8 for the deferred Linux/Windows
story. **Rejected:** auto-installing Homebrew (installer is opinionated about
shell rc files and `/opt/homebrew`; the script bails with `https://brew.sh`
instead); and belt-and-suspenders enrollment via both `bin/setup` and a
Composer script (anyone skipping one will skip the other, so two paths just
double the surface area).

## 2026-08 · D-027 — FrankenPHP replaces nginx as the web server and PHP runtime
The Slim front controller is served by FrankenPHP (`dunglas/frankenphp`,
Caddy with PHP embedded), replacing php-fpm plus a 27-line nginx vhost
template forked from Lando's default. **Why:** the fork existed to add one
`try_files` block, without which any routed non-file (`/health`) 404s before
Slim sees the request; Caddy's `php_server` directive applies that fallback
natively, and FrankenPHP's image bakes in a Caddyfile already rooted at
`public/`, so the repo carries zero web-server config. One container replaces
two — Lando 3.26 has no Caddy service type, so any external-Caddy shape means
hand-driving php-fpm through undocumented builder options (`via: cli` +
`phpServer` + `command`) plus a sidecar. FrankenPHP instead drops in as an
`image:` override on Lando's stock php service: it keeps the official PHP
image layout (`docker-php-entrypoint`, `conf.d`, `install-php-extensions`),
so composer, tooling and the php.ini mount survive. It is also a deliberate
production evaluation: D-016's Phase 8 target (DO App Platform) favours a
single-container runtime, and running FrankenPHP daily locally is the cheapest
way to learn whether it belongs there — and in other projects. **Rejected:**
a `caddy:2-alpine` sidecar dialling php-fpm — built and verified working, but
it keeps two containers, a Caddyfile in the repo, and the fragile `phpServer`
knob, while evaluating nothing new. **Cost accepted:** FrankenPHP is a ZTS
PHP build shipping only PHP's default extensions, so `pdo_pgsql`, `intl`
(D-019) and `zip` are compiled by an `install-php-extensions` build step, and
local no longer runs the same PHP build as CI's stock NTS `setup-php` — a
thread-safety or extension discrepancy would surface locally first, not in
CI. The image tag (`1-php8.5`) is pinned independently of Lando's php version
and must be bumped alongside it. **Revisit trigger:** Phase 8 deployment
makes the production call; if FrankenPHP disappoints before then, the
verified fallback is the rejected shape above — stock php service forced to
fpm (`via: cli`, `phpServer: fpm`, `command: php-fpm`) behind a
`caddy:2-alpine` sidecar whose Caddyfile is six lines ending in
`php_fastcgi app:9000` (the `fpm` alias exists only under `via: nginx`).

