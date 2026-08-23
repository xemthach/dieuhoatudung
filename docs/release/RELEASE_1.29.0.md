# v1.29.0

Date: 2026-08-24

## Highlights

This backward-compatible feature release activates Calculator V2, adds a true volume method, introduces brand-neutral equipment-type guidance, and streamlines the Quote funnel around known Calculator/Product context.

## Calculator

- Active area rule: `consumer-estimate-v2`.
- Active volume rule: `volume-consumer-estimate-v2`.
- Frozen replay rules remain available: `consumer-estimate-v1` and `volume-consumer-estimate-v1`.
- V2 is category-specific and hybrid: HIGH/MEDIUM-confidence calibrated factors are active; LOW-confidence categories retain their exact V1 values.
- Method B computes `area × height × W/m³`; it does not apply the Method A height multiplier again.
- Results distinguish estimated load, calculated market tier and actual catalog availability.
- The calculator remains a consumer estimate, not an engineering heat-load, zoning, electrical or duct design.

## Product Recommendation

- Added user preference for wall-mounted, cassette, ducted, ceiling-exposed, floor-standing or consultation.
- Recommendations are brand-neutral and deterministically ranked by verified type, sufficient canonical capacity and availability.
- Under-sized, VRF, GMV, OTHER, UNKNOWN and conflicting/unverified Product classes fail closed.
- Large or uncertain cases return technical-consultation guidance; the application does not invent unit quantity or automatically design a VRF solution.
- Market reference envelopes and the live site catalog envelope remain separate.

## Quote / Sales Funnel

- Replaced the long flat Quote journey with three truthful steps: need, optional context and contact.
- Calculator and Product entry points prefill known context server-side; no customer PII is added to URLs.
- Only name and phone are mandatory in the primary funnel; technical inputs remain optional/unknown-capable.
- A unique submission token makes browser/network retries idempotent.
- QuoteRequest and linked Lead creation now share one database transaction.

## Admin UX

- Added read-only Calculator governance showing active Area/Volume rules, factors, source confidence and V1-retained categories.
- Calculator history and Quote administration expose method/rule/context evidence needed by operators.

## Reliability

- Added V1/V2 golden, property, boundary, catalog-gap, quote idempotency and equipment-suitability regression tests.
- Corrected Product HVAC classification so generic categories do not suppress a valid explicit type, while conflicting recognized sources remain excluded.

## Security

- Method and equipment-type inputs use server-side allowlists.
- Generic quote analytics contain workflow context only, not name, phone, email, address or message.
- No AI provider is used to decide BTU, equipment type, Product eligibility or unit quantity.
- No Product/catalog technical mutation is part of this release.

## Database Changes

Run all three migrations after a verified production backup:

- `2026_08_23_120000_add_rule_version_to_btu_calculations_table.php`
- `2026_08_23_130000_add_calculation_method_to_btu_calculations_table.php`
- `2026_08_24_000000_add_workflow_context_to_quote_requests_table.php`

Expected repository/current validated migration count: **93**.

## Upgrade Notes

1. Capture worker desired/actual state, queue and scheduler evidence.
2. Back up the authoritative database.
3. Deploy tag `v1.29.0`, install production dependencies and use the committed production assets or rebuild with the repository-supported Node toolchain.
4. Run `php artisan migrate --force` and verify all 93 migrations.
5. Rebuild config, route and view caches.
6. Restart the reviewed OS-managed AI worker even when desired state is disabled.
7. Verify web/worker version, DB and queue alignment; verify the scheduler; restore the original desired state intentionally.

See [Live Server Update Guide](../UPDATE_LIVE_SERVER.md).

## Mandatory Worker Deployment

Canonical command:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Admin controls desired state only. The OS process manager controls lifecycle. Restarting the service must load v1.29.0 but must not turn AI processing on when the operator intended `DISABLED`.

## Scheduler

The production scheduler must run `php artisan schedule:run` every minute. It provides queue-health heartbeat, stale-job recovery, retention and other scheduled operations. `SCHEDULER_UNHEALTHY` keeps the AI runtime not ready.

## Validation

- Focused changed/risk-area suite: 137 tests, 707 assertions, PASS.
- Full Laravel suite: 449 tests, 448 passed, one existing skip, 1,686 assertions, zero failures/errors.
- Composer validate/audit: PASS; no advisories.
- npm audit: PASS; zero vulnerabilities at high threshold.
- Vite build and Laravel config/route/view caches: PASS.
- PHP lint: 46 changed/new files, PASS.
- `git diff --check`, staged secret scan and private-artifact scan: PASS.
- Read-only data: 81 / 212 / 36,453 / 656,507; 93 migrations; canonical BTU hash unchanged.
- AI desired state remained `DISABLED`; release validation caused zero provider calls.

## Known Limitations

- Strict current Product evidence supplies eligible canonical-capacity models mainly for cassette and ducted types; other market-capable types can truthfully return `NO_MATCHING_PRODUCT`.
- LOW-confidence Calculator categories retain V1 factors pending stronger methodology authority.
- Browser/CDP certification was not run for this release; HTTP/feature evidence is used.
- Production process-manager name and scheduler health must be verified on the live host; local stale scheduler evidence is not production certification.

## Rollback

1. Record the current queue state and set desired AI processing to `DISABLED` when new claims must stop.
2. Allow active work to settle; do not kill a processing job or purge queues.
3. Check out the reviewed previous tag `v1.28.2` and install its matching dependencies/assets.
4. The three additive migrations are backward-compatible; normally leave them in place during code rollback. Roll back/restore the database only after a reviewed schema/data decision.
5. Rebuild config, route and view caches.
6. Restart the OS-managed worker again so it loads rollback code.
7. Verify web/worker version, authoritative DB, queue `ai_governed`, scheduler and non-provider worker self-test.
8. Restore the pre-rollback desired state only after all health checks pass.
9. Smoke-test public pages, Calculator A/B, Quote flows, Product media and Admin.
