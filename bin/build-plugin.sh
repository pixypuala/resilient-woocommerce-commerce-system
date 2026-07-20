#!/usr/bin/env bash
#
# Build the distributable plugin from the repository.
#
# The repository root doubles as the plugin, so a checkout contains a great deal
# that must never reach a production site: the test suite and its WordPress
# function doubles, CI config, and a vendor/ directory that holds nothing but
# dev tooling. This script produces the artifact that actually ships —
# everything in .distignore removed — and is what Plugin Check should be pointed
# at.
#
# The plugin has no runtime Composer dependencies (see composer.json: php and
# ext-json only), so no vendor/ is shipped and the built-in PSR-4 fallback
# autoloader in the plugin bootstrap is the loading path in production.
#
# Usage:
#   bin/build-plugin.sh          # build dist/resilient-commerce/
#   bin/build-plugin.sh --zip    # ...and dist/resilient-commerce.zip
#
set -euo pipefail

SLUG="resilient-commerce"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
TARGET="${DIST}/${SLUG}"

rm -rf "${TARGET}"
mkdir -p "${TARGET}"

# Build the rsync exclude list from .distignore, ignoring blanks and comments.
EXCLUDES=()
while IFS= read -r line; do
	[ -z "${line}" ] && continue
	case "${line}" in \#*) continue ;; esac
	EXCLUDES+=("--exclude=${line}")
done < "${ROOT}/.distignore"

rsync -a "${EXCLUDES[@]}" "${ROOT}/" "${TARGET}/"

echo "Built ${TARGET}"

if [ "${1:-}" = "--zip" ]; then
	( cd "${DIST}" && rm -f "${SLUG}.zip" && zip -qr "${SLUG}.zip" "${SLUG}" )
	echo "Packaged ${DIST}/${SLUG}.zip"
fi
