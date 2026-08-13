# Clerk — System Architecture

> Maintenance rule: this diagram is updated in the same PR as any structural
> change. It is diagram-as-code (Mermaid) so it renders on GitHub and diffs
> like source.

## v1 System Diagram

```mermaid
flowchart TB
    subgraph Sources["Data Sources"]
        PDF["PDF Statements<br/>(banks, credit cards)"]
        HV["Harvest exports"]
        QBO["QuickBooks Online exports"]
        XERO["Xero exports"]
        DC["Debit & Credit exports"]
        ADP["ADP earnings statements"]
    end

    subgraph Clerk["Clerk Core (PHP 8.4+, public repo)"]
        direction TB
        subgraph Ingestion["Ingestion Layer"]
            PARSERS["Statement Parsers<br/>(per-vendor, SetaPDF/pdftotext)"]
            ADAPTERS["Source Adapters<br/>(per-system, CSV/OFX/PDF)"]
        end
        CANON["Canonical Transaction Model"]
        subgraph Engine["Processing Engines"]
            DEDUP["Dedup Engine<br/>(exact + fuzzy → candidate queue)"]
            RULES["Categorization Rules Engine<br/>(rules loaded from private storage)"]
        end
        ZCLIENT["Zoho API Client<br/>(OAuth2, throttled,<br/>dry-run → approve → execute)"]
        subgraph Surfaces["Surfaces"]
            CLI["Symfony Console CLI"]
            WEB["Slim Web UI<br/>(Plates + Tailwind + Alpine)<br/>review queues · receipts · viz"]
        end
    end

    subgraph Storage["Storage"]
        PG[("PostgreSQL<br/>staging DB<br/>(local: Lando / hosted: managed)")]
        B2[("Backblaze B2<br/>raw statements · snapshots ·<br/>audit logs · backups")]
        PRIV["Private config storage<br/>(CoA mappings, real rule sets)"]
    end

    subgraph External["External Services"]
        ZOHO["Zoho Books<br/>(system of record)"]
        INF["Infisical<br/>(secret storage)"]
        PROXY["Infisical Agent Proxy<br/>(local: agent sessions /<br/>standalone: hosted jobs)<br/>credentials applied at<br/>network boundary"]
        CFA["Cloudflare Access<br/>(auth for real instance)"]
        MCP["Zoho Books MCP<br/>(conversational inspection<br/>& spot-checks)"]
    end

    PDF --> PARSERS
    HV --> ADAPTERS
    QBO --> ADAPTERS
    XERO --> ADAPTERS
    DC --> ADAPTERS
    ADP --> ADAPTERS

    PARSERS --> CANON
    ADAPTERS --> CANON
    CANON --> PG
    PG <--> DEDUP
    PG <--> RULES
    PRIV --> RULES
    PG --> ZCLIENT
    ZCLIENT -->|"approved writes only"| ZOHO
    ZOHO -->|"read-only recon snapshots"| ZCLIENT
    ZCLIENT --> B2
    PDF -.->|"raw archive"| B2
    PG -.->|"pg_dump backups"| B2

    CLI --> Ingestion
    CLI --> Engine
    CLI --> ZCLIENT
    WEB --> PG
    CFA -->|"gates real instance"| WEB
    INF -.->|"Zone B: env injection<br/>(human-run / deployed app)"| CLI
    INF -.->|"machine identity"| WEB
    INF -.->|"real credentials"| PROXY
    PROXY -.->|"Zone A: brokered requests<br/>(agent-run processes)"| ZCLIENT
    MCP -.->|"human spot-checks"| ZOHO
```

## Component Notes

**Canonical Transaction Model** — the contract everything converges on. Every
ingestion path produces it; every engine consumes it. Money stored as integer
minor units. Every record carries `source_system` + `source_record_id` +
`import_batch_id` for full attributability and reversibility.

**Trust boundary** — the staging Postgres DB is the workspace; Zoho Books is
the system of record. Nothing reaches Zoho except through the write client's
dry-run → human approval → execute pipeline. A mistake in ingestion,
dedup, or categorization can never touch the books directly.

**Public/private boundary** — this repo contains code, synthetic fixtures, and
docs only. Real data lives in gitignored `data/`/`output/`, Postgres, and B2.
Real rule sets and CoA mappings live in private storage and load at runtime.

**Secrets: two-zone model** — Zone A (agent-driven and autonomous execution):
outbound HTTP is brokered by the Infisical Agent Proxy via HTTPS_PROXY; real
credentials are applied at the network boundary and never enter the agent's
environment (prompt-injection exfiltration defense). Zone B (human-run
commands, deployed app runtime): `infisical run` env injection locally,
machine identity in deployment. Flows that cannot be proxy-brokered fall back
to per-process env injection with no-echo rules (see decisions.md D-013).

**Data access** — cakephp/orm standalone (ConnectionManager + TableLocator
bootstrap, Table/Entity classes) over Postgres; Phinx migrations. No raw SQL
in application code. Templates are native PHP via Plates with a mandatory
escaping convention; no Node toolchain (Tailwind standalone binary, vendored
Alpine).

**Deployment shape** — local: everything in Lando. Hosted: Cloudflare
(DNS/CDN/Access) fronts the app; compute on DO App Platform; DO Managed
Postgres adjacent to compute; B2 for objects. Real instance behind Cloudflare
Access; demo instance public with seeded synthetic data. Cloudflare-only
hosting ruled out: no production PHP runtime on Workers.
