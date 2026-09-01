# Release worktree audit - v1.33.2

## Version decision

- Old version/tag: `1.33.1` / `v1.33.1`.
- New version: `1.33.2`.
- SemVer: PATCH. The change fixes a released Bulk AI execution regression without changing public API, database schema or migration contract.

## Include

- Runtime: `AiProductLifecycleService`, Bulk entry actions, Batch worker, Single worker and Bulk Regenerate identity parity.
- Tests: `AIProductContentSystemTest`, `AIProductHeaderActionTest`.
- Evidence: AI issue ledger, controlled-provider ledger, post-deploy regression report.
- Release metadata: `VERSION`, `CHANGELOG.md`, this audit, release note and deployment runbook.

## Exclude / local only

- Existing browser PNG changes: unrelated visual artifacts.
- `DAIKIN_*.xlsx` and SkyAir import manifest: import/operator data outside this AI runtime patch.
- `LIVE_DEPLOYMENT_1.33.1_REPORT.md`: previous deployment evidence.
- `tests/browser/ai-product-bulk-real-provider-probe.php`: local-only controlled real-provider harness; it is intentionally not shipped in the release.

No include candidate contains production credentials, provider key, private key, `.env`, DB dump or secret-bearing runtime log. Fixture-only values in PHPUnit tests are non-production test data.
