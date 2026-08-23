# Release 1.25.0

## Highlights

Production-ready release after the completed Phase 2–9 audit roadmap. Validation: 320 tests, 1,008 assertions, 0 failures, 1 skipped.

## AI Content

Governed content generation now has safe draft, human review, field approval, content-only apply, idempotency, rollback readiness, and zero technical-data writes.

## Catalog/Product UX

Improved product navigation, detail presentation, class-aware capacity display, admin usability, and safe empty-data handling.

## Search/Filter/Calculator

Exact model ranking, normalized Vietnamese search, stable query state, class-aware filters, and RAC-only calculator recommendations were validated.

## SEO/Merchant

Deterministic metadata, canonical/indexation policy, structured data, sitemap, and honest Merchant eligibility were validated without inventing price, stock, image, GTIN, or warranty data.

## Performance and operations

Representative populated-data query baselines were rechecked; queue reconciliation, health visibility, admin controls, and security safeguards were retained.

## Database/Recovery

Verified dataset: 81 / 212 / 36,453 / 656,507, migration 90, BTU hash `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

## Deployment requirements

Use production HTTPS, `APP_DEBUG=false`, secure cookies, protected environment secrets, verified backup, compatible cache/route/view compilation, and an explicit scheduler deployment check. The AI worker remains disabled until separately authorized.

## Known non-blocking backlogs

Missing historical media, mojibake, category-schema mismatches, unavailable browser harness, and post-deployment scheduler heartbeat verification remain documented limitations.
