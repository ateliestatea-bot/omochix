# Release tools

Python 3 (standard library only). Packages are built from a git commit, never from a working tree.

| Command | What it does |
| --- | --- |
| `python3 tools/release/build.py --commit <sha> --out <dir>` | Reproducible `omochix-core-<v>.zip` / `omochix-theme-<v>.zip`, `.zip.sha256` and `.manifest.sha256` (sha256sum format, paths relative to the package root). Refuses a `-dev` version. |
| `python3 tools/release/verify.py --commit <sha> --dir <dir>` | Zip hash, every zip entry against the manifest, the manifest against the git tree of the commit. |
| `python3 tools/release/plan.py --commit <sha> --out <dir> [--baseline-dir <production files>]` | Deploy plan against the production baseline (`baseline/*.sha256`): refuses unless the changed files are exactly the approved order in `release.json`. Writes `release-plan.json` (bound to the commit and to the SHA-256 of each zip and manifest), `release-plan.json.sha256`, `.changes.txt`, `.rollback.manifest.sha256`, and with `--baseline-dir` the rollback files. |
| `python3 tools/release/deploy.py status|preflight|lock|backup|apply|verify|rollback|unlock ...` | The production helper for this release (self-contained, stdlib only): writes only to `~/web/wp-content/plugins/omochix-core` and `~/web/wp-content/themes/omochix-theme`, takes files straight out of the zips, trusts the plan only by the SHA-256 recorded at approval, needs a lock token, a confirmation phrase and the WordPress version for every write, stops at gate A / B, and never touches the database or caches. Operator steps: `docs/INDUSTRY-HUB-DEPLOY-RUNBOOK.md`. |
| `python3 tools/release/rehearse.py preflight|apply|verify|rollback --plan P [--plan-sha256 X] --manifests M ...` | Rehearses the staged replace (temporary name in the same folder, SHA-256 check, rename; resumable; audit log) on a **throwaway** copy only: it refuses a target without `.omochix-rehearsal`. |

Safety rules (all tools): every path from a zip, a manifest, a plan or git must be relative with no `..`, `.`, empty segment, backslash or NUL. A zip with a duplicate entry, an entry outside its top folder or a non-regular (symlink) entry is refused, and so is a manifest with a duplicate or malformed line; git symlinks and submodules are never packaged. `rehearse.py` checks the plan against `release-plan.json.sha256` (record that value, or pass it with `--plan-sha256`), the manifests against the plan, and every step against its manifest; it opens folders one at a time without following symlinks, so nothing outside the target can be written and a folder swapped for a symlink after the checks is refused. The audit log holds only paths relative to the target, SHA-256 values and results: a probe's output is never logged, only its exit status.

Production is deployed by the operator's replace tool (v2) with the same `release-plan.json`; connection details stay in the operator's private runbook, never in this repository.

The production baseline (`baseline/`) holds only SHA-256 values: Core 1.9.3 as released on 2026-11-06 (`docs/P0-RELEASE-RECORD-20261106.md`), and theme 1.28.1 as released on 2026-11-12 (release commit 422399a: 1.28.0 = 599efe9 with the three Learn-card files; 161 files, sha256 92bbc7b7…). The 1.28.0 manifest (38dd3dd5…) stays for configs/theme-1.28.1.json.

Checks: `php tests/release/release-gate.php` and `python3 -m unittest discover -s tests/release` (CI: `.github/workflows/release.yml`).
