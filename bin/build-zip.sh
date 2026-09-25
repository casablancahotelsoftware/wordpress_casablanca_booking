#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
SLUG="casablanca-booking"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$DIST"

rsync -a "$ROOT/" "$TMP/$SLUG/" \
  --exclude '.git' \
  --exclude 'dist' \
  --exclude 'tests' \
  --exclude 'bin' \
  --exclude 'vendor' \
  --exclude 'composer.lock' \
  --exclude 'phpunit.xml.dist' \
  --exclude '.phpunit.result.cache' \
  --exclude 'node_modules' \
  --exclude '*.zip'

rm -f "$DIST/$SLUG.zip"
(cd "$TMP" && zip -r "$DIST/$SLUG.zip" "$SLUG")

echo "Built $DIST/$SLUG.zip"
