# Live Product Transfer / Governance / BTU — v1.33.8

This is the release-owned report template. The deployment runner writes its
machine-captured report to `storage/logs/deployments/` on Production. Final Live
acceptance evidence must be copied here after execution; pending values must not
be represented as PASS.

## Release identity

- Version/tag: `1.33.8` / `v1.33.8`
- Rollback: `v1.33.7` / `43c620e878542ee63f34d2075b6e350f2b251c67`
- Release SHA: populated after release commit

## Local gates

- Focused: 56 passed, 1,477 assertions.
- Browser: 5 passed.
- Full PHPUnit: 587 total, 586 passed, 1 skipped, 0 failed, 3,667 assertions.
- Static/build: PASS.

## Production gates

- Predeploy identity: PENDING
- Database backup path/size/SHA-256: PENDING
- Migration/cache/assets: PENDING
- Generic and managed worker lifecycle: PENDING
- AI desired-state parity: PENDING
- Scheduler: PENDING
- Governance UI/policy smoke: PENDING
- Product Transfer preview: PENDING
- 81-row transfer: NOT AUTHORIZED / NOT RUN
- Marketing-capacity and BTU filter acceptance: PENDING DATA
- SkyAir/wall-mounted/technical edit: PENDING
- Logs and read-only audit package: PENDING
- Local/Live parity: PENDING

## Safety

No automatic Product backfill, lineage detach, 81-row transfer or historical
job retry is part of code deployment. Any later data mutation requires reviewed
preview evidence and separate operator authorization.
