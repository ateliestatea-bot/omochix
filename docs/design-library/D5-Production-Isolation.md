# D5: production isolation of the Design Library (record)

Plan in force: **D5 v3** (adopted 2026-11-25). Release 1.33.0 (G12) allows **no promotion**: every work is `verified-local`.

## Plan (D5 v3)

There is no staging server. The earlier plan, a separate hosting account used as staging with the verdicts "P1 staging isolation" and "P2 staging cache", was **cancelled**. Its verdicts are not part of any rule and must never be filled in.

| Step | What | Decided by |
| --- | --- | --- |
| L1 | Local isolation, B1–B10, per work, on a throwaway local site at the pinned product files. Covers the fail-closed 503 (B9, local test hook only) and the server log showing that no probe request arrived (B5). | Local test records. **Not production evidence.** |
| K | Kill switch: a root `.htaccess` block that answers 403 for `/design/demo/` only while a flag file exists. Verified locally; tried on production with the gate closed before G14a. | Local test record, then the production dry-run |
| G14a | Open the gate (publish the `design` page) with **0 promoted works**. Every work is public as poster and video, noindex, no live demo (404), no design sitemap. | Production checks after publishing |
| Canary | One Web work is promoted in its own release. | The owner approves the release pull request (P3) |
| P4 | Right after that release is deployed: B1 / B9 on the production domain (MISS, HIT, cache-busting, HEAD), the detail iframe (B2), browser B3–B6, no `Set-Cookie`. **Only after P4 does that work count as verified in production.** | Production checks; stop procedure below |
| Rest | The other works are promoted after the canary's P4 PASS, each with its own L1, P3 and P4 (no work-specific check is skipped). | Same as the canary |

Limits that stay true:
- Basic auth and IP limits do not hold against the host's cache, so production checks happen in public. The canary keeps that exposure to one work for minutes.
- A promoted work becomes indexable at once: it appears in `/design-sitemap.xml` (cached up to an hour) and in JSON-LD. The demo answers `public, max-age=300` and may stay cached for about ten minutes.
- A `.htaccess` 403 does not remove a 200 the host's cache already holds. Stopping is always: flag file, cache purge, then three checks within 20 minutes. If that fails: the host's cache off, then the `design` page back to draft, then a 403 for all of `/design`, then a release that returns the work to `verified-local`.

## Rule in this release: `g12-no-promotion`

Enforced by `tests/design-library/catalog-check.js` with `tools/design-library/d5-pins.js`:
- No work may be `verified-production`, whatever evidence is present. The old staging-era evidence (P1 / P2 / P3 / P4 marked PASS, with correct pins) is refused.
- `tests/design-library/d5-evidence.json` must be exactly `{version: 1, rule: "g12-no-promotion", items: []}`.
- A staging-fixture mark anywhere in a work's entry is refused. The check drops accents and every non-letter character before matching, so case, width, separators, dashes and invisible characters do not hide it. Look-alike letters from other scripts cannot be caught in general.
- Pins are checked even on refusal, so the errors say what else is wrong:
  - Each pin is a 64-character lowercase hex SHA-256 of the current file. A missing file never matches.
  - The pinned set is exactly the work's product files, including `functions.php`, which loads the Design Library.
- Shipped code may not touch the catalog at runtime. The catalog / asset filter names (`omochix_design_catalog_data`, `_catalog_raw`, `_catalog_file`, `_asset_file`) may appear in any case only in the 4 `apply_filters()` calls of `inc/design-library.php`. The catalog cache global may be used only there. The WordPress filter table may be read but not written. Hook names built at runtime (concatenation, variables) and writes through further indirection cannot all be found statically: code review of the release diff stays the control for those. The local test mu-plugin under `tests/` is never shipped.

## Next rule: evidence rule v2 (own pull request, with the canary)

Rule v2 will allow a work to be promoted only when its entry binds:
- the release commit and the product-file pins (as above);
- the L1 record (local test run ids and results) at those pins, and the test environment it ran in;
- the K and G14a records;
- the P3 approval (the owner's review of that pull request);
- the pending line, until P4 for that work is PASS.

It will also carry a canary rule: a second work can be promoted only after one work's P4 is PASS with the same shared delivery files.

A string such as `PASS` is never taken as proof on its own. Rule v2 and its tests are reviewed with the canary release.

## Results per work

| id | slug | L1 | P3 | P4 |
| --- | --- | --- | --- | --- |
| dl-web-0001 | liquid-glass | local PASS | — | — |
| dl-web-0002 | minimal-editorial | local PASS | — | — |
| dl-web-0003 | 3d-interactive | local PASS | — | — |
| dl-web-0004 | bento-saas | local PASS | — | — |
| dl-web-0005 | dark-luxury | local PASS | — | — |
| dl-web-0006 | aurora-gradient | local PASS | — | — |
| dl-web-0007 | ai-workspace | local PASS | — | — |
| dl-web-0008 | micro-interactions | local PASS | — | — |
| dl-web-0009 | interactive-story | local PASS | — | — |
| dl-video-0001 | logo-reveal-short | — (no demo) | — | — |
| dl-video-0002 | data-chart-explainer | — (no demo) | — | — |
| dl-video-0003 | brand-typography | — (no demo) | — | — |

- L1 "local PASS": B1–B10 on the local test site at the 1.33.0 product files. That site is not production: no host cache and no production server settings. It counts toward no promotion until rule v2.
- Not stored here: passwords, credentials, the production origin, server names.
