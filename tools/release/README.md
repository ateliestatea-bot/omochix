# Release tools

Python 3 (standard library only). Packages are built from a git commit, never from a working tree.

| Command | What it does |
| --- | --- |
| `python3 tools/release/build.py --commit <sha> --out <dir>` | Reproducible `omochix-core-<v>.zip` / `omochix-theme-<v>.zip`, `.zip.sha256` and `.manifest.sha256` (sha256sum format, paths relative to the package root). Refuses a `-dev` version. |
| `python3 tools/release/verify.py --commit <sha> --dir <dir>` | Zip hash, every zip entry against the manifest, the manifest against the git tree of the commit. |
| `python3 tools/release/plan.py --commit <sha> --out <dir> [--baseline-dir <production files>]` | Deploy plan against the production baseline (`baseline/*.sha256`): refuses unless the changed files are exactly the approved order in `release.json`. Writes `release-plan.json`, `.changes.txt`, `.rollback.manifest.sha256`, and with `--baseline-dir` the rollback files. |
| `python3 tools/release/rehearse.py preflight|apply|verify|rollback ...` | Rehearses the staged replace (temporary name in the same folder, SHA-256 check, rename; resumable; audit log) on a **throwaway** copy only: it refuses a target without `.omochix-rehearsal`. |

Production is deployed by the operator's replace tool (v2) with the same `release-plan.json`; connection details stay in the operator's private runbook, never in this repository.

The production baseline (`baseline/`) holds only SHA-256 values of the files of Core 1.9.3 and theme 1.27.1 as released on 2026-11-06 (they match `docs/P0-RELEASE-RECORD-20261106.md`).

Checks: `php tests/release/release-gate.php` and `python3 -m unittest discover -s tests/release` (CI: `.github/workflows/release.yml`).
