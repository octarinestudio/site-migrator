#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

status=0

while IFS= read -r -d '' file; do
	if ! php -l "$file" >/dev/null; then
		php -l "$file"
		status=1
	fi
done < <(find . -name '*.php' -not -path './vendor/*' -print0)

exit "$status"
