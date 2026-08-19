# Clerk

Clerk is a personal financial data engineering system. It ingests financial
data from disparate sources, normalizes it into a canonical schema in a
Postgres staging database, deduplicates and categorizes it, and pushes clean
data into [Zoho Books](https://www.zoho.com/books/) — the system of record. A
local (and deployable) web UI serves review queues, receipt import, and
visualizations.

This repository is public as a portfolio and public engineering record. It
contains **code, synthetic fixtures, and documentation only**. No real
financial data, account identifiers, rule sets, or credentials are committed
here — see [Privacy](#privacy) below.

## Why

Five years of bookkeeping had accumulated across PDF statements, Harvest,
QuickBooks Online, Xero, Debit & Credit, and ADP. Reconciling that by hand is
slow and inconsistent. Clerk encodes the judgment once — as reviewable rules
and deterministic code — and applies it consistently, with an audit trail.

## Design

- **Staging database first.** Every source ingests into Postgres. Dedup and
  categorization happen there. Only clean, human-approved data reaches Zoho.
- **Zoho is never written to blindly.** Every write command is dry-run by
  default, produces a reviewable proposed-changes file, and logs per-record
  audit entries on execution.
- **Money is integer minor units.** Floats never touch monetary values.
- **Everything is attributable and reversible.** Each record carries its
  source system, source record ID, and import batch.
- **Invariant-driven tests.** Parsers must reconcile
  (`opening + Σ transactions == closing`); dedup must be idempotent; the rules
  engine is covered by golden files.

See [docs/architecture.md](docs/architecture.md) for the system diagram,
[docs/plan.md](docs/plan.md) for the phased build plan, and
[docs/decisions.md](docs/decisions.md) for the decision log.

## Stack

| Concern | Choice |
| --- | --- |
| Language | PHP 8.4 minimum (8.5 local; CI matrix 8.4 + 8.5) |
| CLI | Symfony Console (`bin/console`) |
| Web | Slim 4 + Plates, Tailwind (standalone CLI), Alpine.js — no Node toolchain |
| Database | PostgreSQL via cakephp/orm (standalone) + Phinx migrations |
| Tests | Pest 5, PHPStan level 8 |
| Object storage | Backblaze B2 (Flysystem S3) |
| Secrets | Infisical (two-zone model) |
| Dev environment | Lando |

## Getting started

> Clerk is early — the build is at Phase 1 of [docs/plan.md](docs/plan.md),
> so parts of the stack above are still landing.

```bash
git clone https://github.com/miquelbrazil/clerk.git
cd clerk

# One-time host setup: install gitleaks, enroll the privacy pre-commit hook.
./bin/setup

lando start           # PHP 8.5 + nginx + PostgreSQL 16
lando composer install
lando phinx migrate   # create the staging schema
lando console list
```

`lando start` prints the app URL; `/health` reports app and database status as
JSON.

### Everyday commands

| Command | Does |
| --- | --- |
| `lando test` | Run the Pest suite |
| `lando stan` | PHPStan level 8 |
| `lando cs` | PSR-12 check (`lando composer cs:fix` to autofix) |
| `lando phinx migrate` / `rollback` | Apply / revert migrations |
| `lando psql` | psql shell on the staging database |
| `lando console env:check` | Report which env vars are set (names only) |

Tests that need Postgres are grouped: `vendor/bin/pest --exclude-group=database`
runs the suite without a database.

Copy `.env.example` to `.env` for local editor/debug paths. It documents
variable **names** only; real values are never stored on disk.

## Secrets

Secrets are managed by Infisical and injected at runtime. There are two zones:

- **Zone B — human-run commands and the deployed app.** Values are injected
  into the environment: `infisical run --env=dev -- lando console <command>`.
  Code reads them from environment variables and nowhere else. Verify injection
  is working with:

  ```bash
  lando console env:check                              # secrets unset
  infisical run --env=dev -- lando console env:check   # secrets set
  ```

  `env:check` prints **presence only** — never a value, length, or prefix — so
  it is safe to run anywhere.
- **Zone A — coding-agent sessions and unattended jobs.** Outbound HTTP is
  routed through the Infisical Agent Proxy, which applies real credentials at
  the network boundary. The process holds placeholders only, so a
  prompt-injected agent has nothing to exfiltrate.

No secret is ever written to a file, a config, or a log.

## Privacy

Real financial values, transaction descriptions, account identifiers, personal
filesystem paths, categorization rule sets, and Chart of Accounts mappings are
**never** committed. Real inputs and outputs live only in the gitignored
`data/`, `output/`, `logs/`, and `tmp/` directories, in Postgres, or in B2.

All committed fixtures are synthetic: fabricated documents that mimic a
vendor's *format* with fake data that still satisfies the arithmetic
invariants. This is enforced by the pre-commit hook (gitleaks plus the
repo-specific rules in [.gitleaks.toml](.gitleaks.toml)) and by the hard rules
in [CLAUDE.md](CLAUDE.md).

## License

Proprietary. Published for reference; not licensed for reuse.
