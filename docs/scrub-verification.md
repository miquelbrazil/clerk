# Phase 0 — History scrub runbook

The public history of this repository contained private information. Phase 0
removes it from the tree (done, committed) and from history (this document).

**The history rewrite and the force-push are human-run.** An agent prepared
everything below and verified the method, but did not execute the rewrite.

> This file is committed to a public repo, so it deliberately contains **no**
> private literals. The literals live in `.scrub/` (gitignored) alongside a
> verifier that greps for them.

## What is being removed

| Item | Where it was | Why |
| --- | --- | --- |
| `phpinfo.txt` | tree + history | Full container environment dump: host home directory, local username, project path, Lando internals |
| `.lando/php/extensions/ioncube/*.so` | tree + history | Two 1.2 MB vendored binaries; also the only reason a personal path existed in `.lando.yml` |
| Hardcoded statement path in `src/Command/ParseStatement.php` | tree + history | Cloud-storage mount path, account label, and a real statement filename |
| ionCube `build_as_root` line in `.lando.yml` | tree + history | Personal filesystem path |

Not being removed: the commit author name and email. That is the maintainer's
public Git identity and is already attached to every other public commit.
Rewriting it would change nothing about the exposure. Say so explicitly rather
than leaving it as an unexplained omission.

## Prerequisites

- `git filter-repo` — `brew install git-filter-repo` (already present on the
  authoring machine).
- A backup exists at `../clerk-backup-pre-phase0` (a full copy of the repo
  including `.git`, taken before any Phase 0 change). Keep it until the
  force-push is confirmed good.

## Step 1 — Review the replacement rules

```bash
cat .scrub/replacements.txt
```

Four rules: two regexes collapsing the cloud-storage statement path and the
personal Lando path, one literal for the account label, one regex for the host
home directory. Confirm each one matches something you actually want gone, and
that the replacement text is inert.

## Step 2 — Dry-run the rewrite on a throwaway clone

Never rehearse on the working repo. `--no-local` forces a real object copy
rather than hardlinks into the source `.git`:

```bash
git clone --no-local . /tmp/clerk-scrubtest
cd /tmp/clerk-scrubtest

git filter-repo --force \
  --invert-paths \
  --path phpinfo.txt \
  --path .lando/php/extensions \
  --replace-text ../../path/to/clerk/.scrub/replacements.txt
```

Use an absolute path to `replacements.txt` — `filter-repo` changes the working
directory's meaning and a relative path will surprise you.

## Step 3 — Verify the throwaway clone

```bash
/path/to/clerk/.scrub/verify-scrub.sh /tmp/clerk-scrubtest
```

Five checks, all of which must pass:

1. **Every blob in every commit** is scanned for each private literal. This is
   stronger than the plan's `git log --all -p | grep` wording: it walks the
   object graph via `git rev-list --all --objects`, so it sees content that
   never renders in a diff (including binaries).
2. **Removed paths touch no commit** — `phpinfo.txt` and both `.so` files.
3. **The plan's literal acceptance grep** over `git log --all -p`.
4. **No blob over 1 MB** survives in history (the ionCube loaders specifically).
5. **The current working tree** is clean of the same literals.

Exit 0 and a green `SCRUB VERIFIED` means safe to proceed. Anything else: stop.

The verifier has been sanity-checked as a *negative* control — run against the
un-scrubbed repo it reports 13 hits and exits non-zero. A verifier that has
never been observed failing is not evidence of anything.

> Implementation note worth preserving: the verifier never pipes into
> `grep -q`. `grep -q` exits on first match, the writer takes SIGPIPE, and
> under `set -o pipefail` the pipeline reports failure — silently converting a
> detection into a pass. Findings are captured into variables or matched with
> here-strings instead.

## Step 4 — Rewrite the real repository

Only after step 3 is green:

```bash
cd /path/to/clerk
git filter-repo --force \
  --invert-paths \
  --path phpinfo.txt \
  --path .lando/php/extensions \
  --replace-text .scrub/replacements.txt

./.scrub/verify-scrub.sh .
```

`filter-repo` removes the `origin` remote by design, to stop a rewrite being
pushed by reflex. Re-add it deliberately:

```bash
git remote add origin https://github.com/miquelbrazil/clerk.git
```

## Step 5 — Confirm what you are about to publish

```bash
git log --stat                       # commits and their file lists
git show --stat HEAD                 # the Phase 0 commit
git ls-files                         # the tree you are publishing
git count-objects -vH                # ~2.5 MB should have disappeared
```

## Step 6 — Force-push

```bash
git push --force-with-lease origin main
```

`--force-with-lease` rather than `--force`: it refuses if the remote moved
since your last fetch. Nothing else pushes to this repo, but the habit costs
nothing and prevents overwriting work you have not seen.

## Step 7 — The part a force-push does NOT do

**A force-push does not delete the old commits from GitHub.** They become
unreferenced, but GitHub keeps serving them by SHA — `github.com/<owner>/<repo>/commit/<old-sha>`
resolves, and so does the API — until GitHub runs garbage collection, which is
not on a schedule you control. The private content stays publicly reachable to
anyone who has, or can guess, an old SHA. Both old SHAs are recorded in
`.scrub/old-shas.txt`.

Two ways to actually finish the job:

1. **Delete and recreate the repository** (recommended here). This repo has
   0 forks, 0 stars, 1 watcher (you), no issues, no PRs, no releases — so
   nothing is lost. `docs/plan.md` already sanctions this: *"recreate repo from
   scrubbed tree + force-push — acceptable given no forks/consumers."* It is
   the only method that gives a hard guarantee.

   ```bash
   gh repo delete miquelbrazil/clerk --yes
   gh repo create miquelbrazil/clerk --public \
     --description "Personal financial data engineering system" \
     --source . --remote origin --push
   ```

2. **Force-push, then ask GitHub Support to purge** the unreachable objects and
   any cached views. This preserves the repo's creation date and URL history
   but depends on a support turnaround, during which the data stays reachable.

Whichever you pick, afterwards verify from the outside:

```bash
git clone https://github.com/miquelbrazil/clerk.git /tmp/clerk-fresh
./.scrub/verify-scrub.sh /tmp/clerk-fresh

# And confirm an old SHA is genuinely gone (expect 404):
gh api repos/miquelbrazil/clerk/commits/$(head -1 .scrub/old-shas.txt)
```

## Rollback

If anything looks wrong before step 6, the rewrite is entirely local:

```bash
rm -rf /path/to/clerk
cp -a /path/to/clerk-backup-pre-phase0 /path/to/clerk
```

After step 6 the backup still holds the original history, but the remote has
already been overwritten — restoring means another force-push.

## After the push

- Enable the pre-commit hook in this clone: `git config core.hooksPath .githooks`
  (and `brew install gitleaks`, which the hook requires).
- Delete `../clerk-backup-pre-phase0` once you are satisfied — it contains the
  private history and is the last copy of it on disk.
- Run `/init` and merge what it learns into `CLAUDE.md`, per the handoff notes.
- Mark Phase 0 complete in `docs/plan.md`.
