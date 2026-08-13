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

Five rules, applied to blob contents only (commit messages are a separate
`--replace-message` option, and are verified clean by check 4b):

1. Deletes the ionCube `build_as_root` key and its list item together, so no
   orphaned YAML key is left behind.
2. Collapses the cloud-storage statement path.
3. Collapses the personal Lando path to the container mount.
4. Literal for the account label.
5. Regex for the host home directory.

Confirm each matches something you want gone and that the replacement is inert.

**Rules 3–5 are belt-and-braces, and a green run does not exercise them.**
Rule 1 deletes the only line rule 3 could match; rule 2's regex swallows the
region rule 4 acts on; and rule 5's only targets are inside `phpinfo.txt`,
which `--invert-paths` deletes wholesale. Keep them — if a future run scrubs
`phpinfo.txt` in place rather than deleting it, rule 5 becomes load-bearing
having never once fired.

Note for anyone editing these: `filter-repo` compiles them with a bare
`re.compile`, with **no** `MULTILINE` flag. `^` will not match line starts.
Anchor on explicit `\n`. Literals are also applied before regexes, regardless
of file order.

## Step 2 — Dry-run the rewrite on a throwaway clone

Never rehearse on the working repo. `--no-local` forces a real object copy
rather than hardlinks into the source `.git`.

Set an absolute path to the repo first; `filter-repo` changes what the working
directory means, so every path below is absolute on purpose:

```bash
# Run this from inside the repo — it derives the path rather than trusting you
# to type one. Do not hand-write it as CLERK="~/..." : tilde expansion does not
# happen inside quotes, so the variable would hold a literal ~ that no command
# can resolve, which looks exactly like the variable being unset.
cd /wherever/clerk/is
CLERK=$(git rev-parse --show-toplevel)
echo "[$CLERK]"                      # sanity check: must be an absolute path

git clone --no-local "$CLERK" /tmp/clerk-scrubtest
cd /tmp/clerk-scrubtest

git filter-repo --force \
  --invert-paths \
  --path phpinfo.txt \
  --path .lando/php/extensions \
  --replace-text "$CLERK/.scrub/replacements.txt"
```

`/tmp/clerk-scrubtest` is the **system** temp directory, not the repo's `tmp/`.
That is deliberate, and the distinction matters: this clone holds the complete
un-scrubbed history — the entire private payload. Keeping it outside the repo
means its containment does not depend on a single `.gitignore` line continuing
to hold. The repo's `tmp/` is also application runtime state (`config/paths.php`
defines `TMP` and `CACHE` under it), not developer scratch space.

## Step 3 — Verify the throwaway clone

```bash
"$CLERK/.scrub/verify-scrub.sh" /tmp/clerk-scrubtest
```

Seven checks, all of which must pass:

1. **Every blob in every commit** is scanned for each private literal. This is
   stronger than the plan's `git log --all -p | grep` wording: it walks the
   object graph via `git rev-list --all --objects`, so it sees content that
   never renders in a diff (including binaries).
2. **Removed paths touch no commit** — `phpinfo.txt` and both `.so` files.
3. **The plan's literal acceptance grep** over `git log --all -p`.
4. **No ionCube loader binary** survives, at any size, matched by path. Size
   alone is the wrong test — a small artifact would pass a 1 MB threshold.
5. **(4b) Commit messages** carry no private literal. `--replace-text` does not
   touch messages, so a reword during an interactive rebase is outside the
   scrub entirely. This is the easiest way for something to creep back in.
6. **(4c) The ionCube build directive is gone** from every blob. Completeness,
   not privacy — the surviving reference was benign, but removing it was a
   deliberate decision, so it is asserted rather than assumed.
7. **The current working tree** is clean of the same literals.

Exit 0 and a green `SCRUB VERIFIED` means safe to proceed. Anything else: stop.

The verifier has been sanity-checked as a *negative* control — run against the
un-scrubbed repo it reports 10 private-literal hits, both `.so` paths, and the
build directive, and exits non-zero. A verifier that has never been observed
failing is not evidence of anything.

Two false-positive classes were found and fixed while building it, both the
same mistake: the files that *document* the scrub necessarily contain the
strings the scrub looks for. `.gitleaks.toml` and `.githooks/pre-commit` define
the patterns; `docs/plan.md` and this runbook describe the removal in prose.
Checks that flag them train you to skim output, which is how a real finding
gets waved through. Excluding them is not a weakening of the check — but
widening a pattern to accommodate prose would be.

> Implementation note worth preserving: the verifier never pipes into
> `grep -q`. `grep -q` exits on first match, the writer takes SIGPIPE, and
> under `set -o pipefail` the pipeline reports failure — silently converting a
> detection into a pass. Findings are captured into variables or matched with
> here-strings instead.

## Step 4 — Rewrite the real repository

Only after step 3 is green:

```bash
cd "$CLERK"
git filter-repo --force \
  --invert-paths \
  --path phpinfo.txt \
  --path .lando/php/extensions \
  --replace-text "$CLERK/.scrub/replacements.txt"

./.scrub/verify-scrub.sh .
```

`filter-repo` refuses to run on a dirty tree. `.scrub/` is gitignored, so it
does not count against that — and it survives the rewrite, which is why the
verifier is still there to run on the line above.

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

### Decision (2026-08-13): force-push and wait for GitHub's own GC

**This is an accepted, still-open exposure, not a closed item.** The
alternatives considered:

| | Old objects purged | Commit dates | Repo created date |
| --- | --- | --- | --- |
| Wait for GitHub's GC *(chosen)* | Eventually, on their schedule | 2024 preserved | 2024 preserved |
| Force-push + GitHub Support purge | On support turnaround | 2024 preserved | 2024 preserved |
| Delete and recreate | Immediately | 2024 preserved | Resets to today |

Delete-and-recreate is the only hard guarantee, and `docs/plan.md` sanctions it
(*"recreate repo from scrubbed tree + force-push — acceptable given no
forks/consumers"* — 0 forks, 0 stars, 1 watcher). It was declined because it
resets the repository creation date, and the two-year gap between the 2024
commits and the 2026 resumption is worth keeping on the record.

What the exposed content actually is, so the risk can be re-judged later
without re-deriving it: a home directory path and local username, a Google
Drive path containing the maintainer's email, one institution + card last-four
label, and a `phpinfo()` environment dump. **No credentials** — the dump was
checked for keys, tokens, and passwords and had none.

The escalation path if this needs closing sooner is GitHub Support; nothing
about the local rewrite has to be redone.

### Verify from the outside

`git filter-repo` preserves author and committer dates verbatim (it has no
option that mutates them; `--date-order` is a traversal flag), so the 2024
commits keep their dates through the rewrite with only their SHAs changing.

```bash
git clone https://github.com/miquelbrazil/clerk.git /tmp/clerk-fresh
"$CLERK/.scrub/verify-scrub.sh" /tmp/clerk-fresh

# Are the old SHAs still reachable? 200 = still exposed, 404 = collected.
while read -r sha; do
  printf '%s ' "$sha"
  gh api "repos/miquelbrazil/clerk/commits/$sha" --silent 2>/dev/null \
    && echo "STILL REACHABLE" || echo "404 — collected"
done < "$CLERK/.scrub/old-shas.txt"
```

Re-run that loop periodically. GitHub publishes no schedule for collecting
unreachable objects and does not guarantee it, so treat the old SHAs as live
until this prints 404 for both — do not assume a date.

## Rollback

If anything looks wrong before step 6, the rewrite is entirely local:

```bash
rm -rf "$CLERK"
cp -a "$CLERK-backup-pre-phase0" "$CLERK"
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
