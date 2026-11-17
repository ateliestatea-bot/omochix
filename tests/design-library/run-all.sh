#!/bin/bash
# Design Library D1 — every check, against a THROWAWAY local WordPress only.
#   DL_BASE=http://127.0.0.1:8961 DL_SITE=/path/to/site NODE_PATH=<playwright>:<axe-core> \
#     tests/design-library/run-all.sh [baseline.json]
# Writes one log per suite to docs/design-library/qa/logs/. Exit 1 if any suite fails.
set -u
cd "$(dirname "$0")/../.."
case "${DL_BASE:-}" in http://127.0.0.1:*) ;; *) echo "DL_BASE must be http://127.0.0.1:PORT"; exit 2;; esac
OUT=docs/design-library/qa/logs; mkdir -p "$OUT"; rc=0
run() { local name=$1; shift; "$@" > "$OUT/$name.log" 2>&1; local r=$?; printf '%-12s %s  %s\n' "$name" "$([ $r -eq 0 ] && echo OK || echo FAIL)" "$( (grep -E ' passed, [0-9]+ failed|differ$' "$OUT/$name.log" || tail -1 "$OUT/$name.log") | tail -1)"; [ $r -eq 0 ] || rc=1; }
run catalog   node tests/design-library/catalog-check.js
run unit      php tests/design-library/run.php
run http      php tests/design-library/http-check.php
run isolation node tests/design-library/browser/isolation.js
run firefox   node tests/design-library/browser/firefox.js
run ui        node tests/design-library/browser/ui.js
run layout    node tests/design-library/browser/list-layout.js
run d2d-video node tests/design-library/browser/d2d-video.js
run d2d-new   node tests/design-library/browser/new-works.js ${DL_D2D_WEB:-ai-workspace micro-interactions}
run d2d-web   node tests/design-library/browser/d2d-web.js ${DL_D2D_WEB:-}
run preview   node tests/design-library/browser/preview.js
run perf      node tests/design-library/browser/perf.js
run heal      php tests/design-library/rewrite-heal-check.php   # DL_ROLLBACK_THEME / DL_MYSQL optional
if [ -n "${1:-}" ]; then run regression php tests/design-library/regression-snapshot.php --compare "$1"; fi
exit $rc
