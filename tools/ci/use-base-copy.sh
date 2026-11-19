#!/usr/bin/env bash
# Copy tools/ci/ from a trusted commit into a temporary folder and print that folder.
# The gates run from this copy, so a pull request that edits tools/ci/ cannot change the
# checks that judge it (it can only change them for later pull requests, after review).
#
#   dir=$(bash tools/ci/use-base-copy.sh <base-rev>)
#
# When the base has no tools/ci/ yet (only the pull request that introduces it, or a new
# branch), the working tree's copy is used and BOOTSTRAP is written to stderr and to
# $dir/BOOTSTRAP so the caller can report it. Read-only: writes only under $RUNNER_TEMP
# (or a mktemp folder locally).
set -euo pipefail
base="${1:-}"
root="${RUNNER_TEMP:-$(mktemp -d)}"
dir="$root/ci-base-copy"
rm -rf "$dir"; mkdir -p "$dir"
if [ -n "$base" ] && [ "$base" != "0000000000000000000000000000000000000000" ] \
   && git cat-file -e "$base:tools/ci/cilib.py" 2>/dev/null; then
  git archive "$base" tools/ci | tar -x -C "$dir"
  echo "tools/ci from the base commit $(git rev-parse --short "$base")" >&2
else
  mkdir -p "$dir/tools"
  cp -R tools/ci "$dir/tools/ci"
  echo "the base has no tools/ci/" > "$dir/BOOTSTRAP"
  echo "BOOTSTRAP: the base has no tools/ci/; using this commit's copy" >&2
fi
echo "$dir/tools/ci"
