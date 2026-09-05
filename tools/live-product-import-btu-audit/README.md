# PRODUCT IMPORT / BTU Live Audit Package

This package is read-only. It writes reports only to `storage/logs/audits/` and aborts if a database write query is attempted.

## Terminal

Upload/extract the package at the Laravel project root, then run exactly one command: `bash tools/live-product-import-btu-audit/RUN_LIVE_AUDIT.sh`. It prints JSON, Markdown, HTML paths and SHA-256 values.

## Windows Local

Double-click `RUN_LOCAL_AUDIT.cmd`. It produces the same report schema with the `LOCAL_PRODUCT_IMPORT_BTU_AUDIT` filename prefix.

## No SSH / browser fallback

The package build produces a randomly named, token-protected PHP file and a local-only `AUDIT_RUN_INFO.txt`. Upload that one random PHP file to the Laravel `public` directory, open the tokenized URL from the info file, click **CHẠY KIỂM TRA READ-ONLY**, download all reports, then delete the PHP file immediately. Do not upload `AUDIT_RUN_INFO.txt`.

The web artifact is temporary, does not reveal its token after a failed request, uses `hash_equals()`, allows download only for signed audit report basenames, and creates reports only under `storage/logs/audits/`.
