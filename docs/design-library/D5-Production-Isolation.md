# D5: production isolation of the Design Library (record)

Status: **NOT STARTED** (G12 RC prepared; staging not created). Every work is `verified-local`.

The record has four separate verdicts per work. Each one needs the one before it:

| # | Verdict | Decided by |
| --- | --- | --- |
| P1 | staging isolation PASS | S1b on staging: negative control, B1, B2, B10, index / sitemap / JSON-LD, browsers B3–B9 (`d5-http.py neg / s1b`, `d5-browser.js`), M6 and the real devices |
| P2 | staging cache PASS | S2 on staging with the accelerator in the production mode: B1 / B9 on MISS, HIT, cache-busting and HEAD, detail HIT, the allowlist 403, T1 / T2, and S3 (401 three times, no cached 200) |
| P3 | production promotion approval | the owner approves the release PR that carries the promoted catalog and this record |
| P4 | production B1/B9 PASS | right after G14, on the production domain. **Only then is production verification complete** |

Rules (enforced by `tests/design-library/catalog-check.js` with `tools/design-library/d5-pins.js`):
- `verified-production` is allowed only for a work with exactly one entry in `tests/design-library/d5-evidence.json`. The entry needs:
  - P1 and P2 PASS, and the P3 approval id;
  - the catalog's `checked` date;
  - `item_sha` (the catalog entry without `verification`);
  - the SHA-256 of every pinned product file. These are the shared gate, demo endpoint and CSP, templates, scripts, styles and schema files, plus the work's own demo sources and media.
- A change to any pinned file after D5 makes the claim fail. Re-run the affected D5 tests (design 10.5).
- Until P4 is PASS, the work's `limitations` must keep the line `本番のドメインでの B1/B9 は、公開の直後に確かめる（未完了）`.
- A staging fixture (`STAGING FIXTURE: D5 staging only, not production evidence`) never ships.
- Staging results are not production evidence. Staging is a separate Lolipop account; the server may differ from production (design 10.1).

## What was tested (filled in when D5 runs)

Recorded once D5 runs: the RC commit, the product-file pins (see `PINS.md` in the local D5 kit), the staging server comparison, and the times of S1a, S2 and S3.

## Results per work (filled in when D5 runs)

| id | slug | P1 | P2 | P3 | P4 | notes |
| --- | --- | --- | --- | --- | --- | --- |
| dl-web-0001 | liquid-glass | — | — | — | — | |
| dl-web-0002 | minimal-editorial | — | — | — | — | |
| dl-web-0003 | 3d-interactive | — | — | — | — | |
| dl-web-0004 | bento-saas | — | — | — | — | |
| dl-web-0005 | dark-luxury | — | — | — | — | |
| dl-web-0006 | aurora-gradient | — | — | — | — | |
| dl-web-0007 | ai-workspace | — | — | — | — | |
| dl-web-0008 | micro-interactions | — | — | — | — | |
| dl-web-0009 | interactive-story | — | — | — | — | |
| dl-video-0001 | logo-reveal-short | — | — | — | — | |
| dl-video-0002 | data-chart-explainer | — | — | — | — | |
| dl-video-0003 | brand-typography | — | — | — | — | |

Not stored here: passwords, credentials, the production origin, the staging domain, staging admin names.
