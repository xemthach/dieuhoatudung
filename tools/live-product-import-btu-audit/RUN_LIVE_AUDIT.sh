#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

if [ ! -f "$ROOT/artisan" ]; then
    echo "FAIL: Laravel artisan not found at $ROOT" >&2
    exit 2
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "FAIL: PHP binary not found: $PHP_BIN" >&2
    exit 2
fi

echo "PRODUCT IMPORT / BTU LIVE AUDIT - READ ONLY"
"$PHP_BIN" "$ROOT/tools/live-product-import-btu-audit/LIVE_AUDIT.php"
