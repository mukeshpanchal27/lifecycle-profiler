#!/usr/bin/env bash
# Builds the release zip from the files that .distignore does not exclude,
# then checks what the zip contains.
#
# Usage: bin/build-zip.sh [output folder]
# Writes <output folder>/lifecycle-profiler.zip. The default folder is build/.
set -euo pipefail

SLUG="lifecycle-profiler"
mkdir -p "${1:-build}"
OUT="$(cd "${1:-build}" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$SLUG"
rsync -rc --exclude-from=.distignore ./ "$STAGE/$SLUG/" --exclude "/$(basename "$OUT")"

# The release must have these files.
for file in lifecycle-profiler.php uninstall.php readme.txt LICENSE includes/early.php; do
	if [[ ! -f "$STAGE/$SLUG/$file" ]]; then
		echo "Missing from the release: $file" >&2
		exit 1
	fi
done

# The release must not have development files.
for path in vendor node_modules tests bin docs .github .wordpress-org composer.json package.json; do
	if [[ -e "$STAGE/$SLUG/$path" ]]; then
		echo "Development file in the release: $path" >&2
		exit 1
	fi
done

rm -f "$OUT/$SLUG.zip"
(cd "$STAGE" && zip -qr "$OUT/$SLUG.zip" "$SLUG")

echo "Built $OUT/$SLUG.zip:"
unzip -l "$OUT/$SLUG.zip" | awk 'NR > 3 && $4 != "" { print "  " $4 }' | grep -v '^  -*$'
