# BTU Calculator Full Audit Report

Audit date: 2026-08-23
Repository baseline: `v1.28.2` / `b9a470c`
Rule versions: area `consumer-estimate-v1`; volume `volume-consumer-estimate-v1`

## 1. Executive Verdict

**CALCULATOR = PASS**

The module now provides two deterministic **consumer sizing estimates**, not engineering cooling-load design: Method A by area and Method B by volume. Method A remains byte-for-byte equivalent at the calculation contract; Method B uses a separate configured W/m³ rule and the same fail-closed RAC recommendation service.

The remaining methodology-authority gap is MEDIUM and controlled by explicit reference wording, disclaimer, versioning and golden tests.

## 2. Current Architecture

- GET/POST routes are defined in `routes/web.php`.
- `BtuCalculatorController` owns validation, optional contact workflow and redirect/session UX.
- `BtuCalculatorService` is the only calculation and recommendation service.
- Formula is server-side only; JavaScript only scrolls to the result.
- `BtuCalculationResource` is an RBAC-protected history/operations surface.
- AI worker/provider are not runtime dependencies.

Complete inventory: `artifacts/calculator_code_inventory.json`.

## 3. Current Formula

Method A: `area × W/m² → W × 3.412 → sequential height/sun/equipment/people adjustments → raw BTU → ceil to configured market tier`.

Method B: `area × height → m³ × W/m³ → W × 3.412 → explicit volume-method sun/equipment/people adjustments → raw BTU → the same market tier`.

Exact operations, order and rounding are in `docs/calculator/CALCULATION_METHOD.md` and `artifacts/calculator_formula_current.json`.

## 4. Input Contract

All visible calculation inputs are active. Priority does not affect calculation and is now labeled/explained as recommendation sorting only. Unsupported energy/durability/premium options were removed. HTTP and domain layers enforce bounds.

See `artifacts/calculator_input_contract.csv`.

## 5. Rule Sources

- Space factors: code-owned historical table in `BtuCalculatorService`.
- Conversion, adjustments, bounds, tiers, version and FAQ: `config/hvac.php`.
- RAC capacity: `products.marketing_capacity_btu` through the canonical adapter.
- HVAC class: `ProductHvacClassResolver`.

The historical Excel/source named by old comments is not in the repository. No claim is made that these values are a verified HVAC standard.

## 6. Space-Type Factors

There are 27 enabled space keys. Method A ranges from 95 to 490 W/m². Method B has a separate W/m³ config table with the same keys and explicit derivation metadata. Unit-neutral UI labels prevent displaying W/m² while volume mode is selected.

See `artifacts/calculator_factor_matrix.csv`.

## 7. Height — Method A

Baseline is 3 m. Above 3 m, the current BTU is multiplied by `round(height / 3, 2)`. At or below 3 m no reduction occurs. This asymmetric behavior is now documented; it was not altered without methodology authority.

## 8. People

The first 10 people are treated as included in the space-type base load. Each additional person adds 400 BTU/h. The source is project configuration, not a verified external standard.

## 9. Solar Load

Direct sun applies a 1.10 multiplier at its exact sequence position. The UI checkbox is functional.

## 10. Equipment Load

Heat-producing equipment applies a 1.10 multiplier after solar adjustment. When both are active they compound to 1.21. The UI checkbox is functional.

## 11. Capacity Mapping

Raw need and market tier are separate outputs. The market tier is always greater than or equal to raw need. Above 100,000 BTU/h, values round upward to 1,000 BTU/h and the UI requests technical survey instead of fabricating a catalog match.

Configured and observed buckets: `artifacts/calculator_capacity_buckets.csv`.

## 12. BTU/HP/kW Semantics

HP is a marketing-class display using 9,000 BTU/h per HP; it is explicitly not mechanical 746 W. No VRF kW-to-BTU conversion is performed. Rated/technical capacity remains distinct from marketing capacity.

## 13. RAC/VRF Boundary

Recommendation now fails closed to verified RAC classes. VRF outdoor/indoor/system, OTHER and UNKNOWN are excluded. Runtime inventory before the fix contained 28 VRF and 17 UNKNOWN products, so this was a material boundary.

## 14. Product Recommendation

Eligibility is active + available + canonical marketing capacity + verified RAC. Search starts at target capacity; it never looks below target. If the primary range has fewer than four products, only the upper bound is widened by 12,000 BTU/h.

Before remediation, 28,000 returned 24,000 and 60,000 returned 55,000/48,000. After remediation, 28,000 returns verified 36,000 RAC products and 60,000 returns no result. See `artifacts/calculator_product_matching.json`.

## 15. Product Ranking

Default ranking is absolute capacity delta. The only optional ranking is effective price ascending with capacity delta as tie-breaker. Price never changes calculated requirement.

## 16. UI/UX

Fixed exactness claims, separated raw calculation from market tier, added formula version and a visible disclaimer, clarified sorting, improved no-result language, and removed unsupported recommendation preferences. An accessible radio group selects area/volume while retaining compatible fields. Results show method, correct factor unit, raw need, market tier and volume details where applicable. `aria-live` and full browser accessibility automation remain LOW follow-ups.

See `artifacts/calculator_ui_findings.json`.

## 17. Admin Management

Admin currently manages history, not coefficients. Records display/filter calculation method and display rule version, raw BTU and canonical tier. This is intentional: moving unverified methodology into an editable DB form would create a second source and unsafe silent changes.

## 18. Rule Governance

New records carry `calculation_method` and `rule_version`; migrations `2026_08_23_120000_add_rule_version_to_btu_calculations_table.php` and `2026_08_23_130000_add_calculation_method_to_btu_calculations_table.php` were applied. Rule changes require source approval, version increment, golden-test changes, method documentation and release notes. Git/release history is the current audit trail.

Deployment impact is explicit: the audit and Method B work add two calculator-history columns, bringing the applied migration count from 90 to 92. Release must run `php artisan migrate --force` and rebuild config/view caches. No Product/catalog migration or worker restart is required specifically by the calculator, although the normal release worker gate still applies to every deployment.

See `artifacts/calculator_admin_governance.json`.

## 19. FAQ/SEO Consistency

Canonical/title/meta remain explicit. The fixed 24,000-BTU area assertion was removed because type and adjustments change the result. Visible FAQ and `FAQPage` JSON-LD now use the same config array. JSON-LD is encoded with hex-safe flags; breadcrumb and canonical remain server-generated.

## 20. Lead/Privacy

Calculation does not require contact data. Anonymous requests now calculate via session without persistent calculation rows, Lead creation or email. Contact-origin records store formula version but no IP, user-agent or Referer. Phone creates a consultation Lead. Existing Lead logic has no calculator-specific deduplication; rate limiting bounds abuse, but dedupe remains a LOW CRM improvement.

## 21. Performance

Local diagnostic GET: 7 queries, 47.22 ms DB, 136.11 ms execution, 74 MB peak; none are calculator-rule queries. Recommendation at 36k: 3 queries, 11.48 ms DB, 17.89 ms execution, 5 results, no measured per-result explosion.

EXPLAIN uses `ALL`, estimates 36 rows and `Using where`. With only 81 Products, an index was not added. Recommendations are not cached because stock/price freshness and the small dataset do not justify it. Local timing is not production latency certification.

See `artifacts/calculator_performance.json`.

## 22. Security

- CSRF on POST.
- Server-side allowlists and numeric bounds.
- Nested/array payload rejection proven.
- Honeypot and 10 requests/hour/IP rate limit.
- Escaped Blade output and safe JSON-LD encoding.
- No raw SQL interpolation, shell, remote fetch, provider call or AI dependency.
- Contact mutation is explicit and bounded.

## 23. Tests

Focused calculator suite: **26 tests / 128 assertions**, green.
Full suite: **388 tests / 1,390 assertions**, 387 passed, 1 pre-existing skip, 0 failures/errors.

Coverage includes golden outputs, monotonic properties, service bounds, under-sizing, RAC/VRF/UNKNOWN, anonymous privacy, rule persistence, nested inputs and FAQ/schema consistency. Golden fixtures: `artifacts/calculator_golden_tests.json`.

## 24. Browser Evidence

No Playwright, Dusk or Cypress harness was found. No browser PASS or screenshots are claimed. A real local HTTPS GET returned 200 and rendered both method copy and the shared FAQ/schema. POST behavior for both methods is covered through Laravel HTTP tests with isolated SQLite.

## 25. Issues Fixed

| Severity | Issue | Resolution |
|---|---|---|
| HIGH | Under-sized Products recommended as suitable | Lower bound is now target capacity |
| HIGH | UNKNOWN products eligible | Fail-closed verified RAC allowlist |
| HIGH | Unsupported “exact” methodology copy | Reference estimate positioning and disclaimer |
| MEDIUM | Decorative/misleading priority options | Deterministic capacity/price options only |
| MEDIUM | FAQ contradicted type-aware formula | Single-source non-universal answer + FAQ schema |
| MEDIUM | Anonymous calculation persisted and mailed | Contact-only persistence/notification |
| MEDIUM | No formula attribution | Rule version config, result and DB column |
| LOW | Partial hard-coded admin tier filter | Canonical service tier options |

## 26. Remaining Limitations

- MEDIUM: original methodology authority document is absent. The tool must remain positioned as an estimate until approved engineering authority is supplied.
- LOW: calculator-specific Lead deduplication/retention policy is not implemented.
- LOW: UTM inputs are validated but not persisted; email/note are API-capable but not visible in this form.
- LOW: product-card presentation is local to the calculator rather than the full catalog component.
- INFO: no real-browser/mobile certification.

None of these limitations reintroduces under-sizing, class mixing, fake facts or hidden Product/catalog writes.

## 27. Final Verdict

**CALCULATOR = PASS**

**AREA METHOD = PASS**
**VOLUME METHOD = PASS as CONFIGURED CONSUMER ESTIMATE**

Acceptance is based on exact code/formula evidence, fail-closed RAC matching, truthful copy, versioned rules, isolated regression coverage and unchanged Product/catalog facts. The calculator remains operational while the AI worker is disabled and makes zero provider calls.

## 28. Method B — Volume

- Formula: `area × height = m³`; `m³ × W/m³`; `W × 3.412`; explicit Method B adjustments; ceil to shared market tier.
- No Method A ceiling multiplier is executed, preventing double height counting.
- No existing W/m³ authority was found in source, config, docs or historical calculator code. The project-owned table is transparently derived from each certified Method A factor at a 3 m reference height and marked not independently verified.
- Request method is required and allowlisted; invalid values cannot resolve arbitrary classes.
- Contact-origin history persists `calculation_method`; all pre-Method-B records are defensibly backfilled `area`.
- Admin history shows and filters method while retaining raw BTU, tier and rule version.
- Both methods call the same RAC-only, never-under-sized Product matcher.
- The certified Method A body intentionally remains in the canonical service to minimize regression risk; only the new Method B arithmetic is isolated in `VolumeCalculationMethod`. There is one allowlisted dispatch point, not switch logic spread across controllers/views.
- Visible FAQ explains when each estimate is useful without claiming either is absolutely accurate.

Migration `2026_08_23_130000_add_calculation_method_to_btu_calculations_table.php` brings the expected migration count to 92. It changes calculator history only and performs no Product/catalog technical writes.

## 29. Final Validation and Data Safety

- Full suite: 388 tests / 1,390 assertions; 387 passed, one pre-existing skip, zero failures/errors.
- Composer validate/audit: PASS / no advisories.
- npm high audit: zero vulnerabilities; Vite build PASS.
- Config, route and view cache: PASS; PHP lint and `git diff --check`: PASS.
- Database: 81 Products / 212 sources / 36,453 models / 656,507 fields; 92 applied migrations.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- Worker desired state remained `DISABLED`; provider log count/timestamp remained 236 / `2026-08-23 10:14:31`, proving zero provider calls during this task.
