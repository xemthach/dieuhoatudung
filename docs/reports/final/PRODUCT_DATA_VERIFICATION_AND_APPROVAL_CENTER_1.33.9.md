# Product Data Verification and Approval Center — v1.33.9 gate

Date: 2026-09-06
Baseline: `v1.33.8` / `5ead4241b42915dd6172747cdffe213242c0a0ed`
Scope: Local code, read-only Product audits, and isolated test databases. No release or Production operation was performed.

## 1. State-separation root cause

The 372 current Product rows all physically retain the legacy category `dieu-hoa-treo-tuong`. That foreign key cannot prove that the physical taxonomy was reviewed. Likewise, 16 non-null `marketing_capacity_btu` values have no approved commercial-tier evidence; non-null cannot mean verified.

The patch separates four independent concepts: physical category, taxonomy verification state, marketing-capacity value, and marketing-capacity verification state. Technical BTU and kW remain separate manufacturer facts and are never converted into a commercial tier.

## 2. Taxonomy status architecture

Migration `2026_09_06_000001_add_product_data_verification_governance.php` adds nullable taxonomy state/evidence fields. A null legacy state resolves fail-closed as `UNRESOLVED`; supported authoritative states are `VERIFIED`, `UNRESOLVED`, and `CONFLICT`.

The refreshed, checksum-bound taxonomy ledger is:

- digest: `15c02c9ae84dd4a939a94cb6722c8d5095856d56d9455c3b0a6f64952e815c3b`
- KEEP: 64
- PROPOSE_UPDATE: 304
- AMBIGUOUS: 0
- NO_EVIDENCE: 2
- CONFLICT: 2
- deterministic/verifiable after approval: 368

The stronger reviewed Gree source-section evidence reduced the former 44 ambiguous rows to zero. It binds exact Product identity to PDF SHA-256, page, and physical table heading; it does not authorize marketing BTU.

Simulated post-approval physical classes:

| Class | Products | Public capacity eligible |
|---|---:|---|
| Wall-mounted | 64 | Yes |
| Cassette | 104 | Yes |
| Ducted | 70 | Yes |
| Floor-standing | 48 | Yes |
| Floor/ceiling | 46 | Yes |
| VRF/GMV | 36 | No |
| Unknown/unresolved | 4 | No |

No Product row was changed. Current persisted taxonomy status remains null on 372 rows; the counts above are the approved-apply simulation.

## 3. Marketing-capacity status architecture

The same migration adds nullable marketing state/evidence fields. Supported states are `VERIFIED`, `UNVERIFIED`, `MISSING`, and `CONFLICT`. A legacy non-null value with null status resolves as `UNVERIFIED`; a null value resolves as `MISSING`.

All 16 existing numeric values are quarantined as `UNVERIFIED`. The commercial ledger classifies them as conflicts requiring review because no verified Product List or approved mapping currently proves those values. No value was cleared or overwritten.

## 4. Public fail-closed query contract

`ProductMarketingCapacityEligibility` is the single public-capacity eligibility boundary. Both its in-memory predicate and SQL query require the Product to be active, taxonomy status to be `VERIFIED`, its stable category slug to be in the physical-DX allowlist, marketing status to be `VERIFIED`, and `marketing_capacity_btu` to be positive.

The numeric source remains `products.marketing_capacity_btu`. Technical BTU, kW, legacy `btu`, names, model suffixes, translated category names, and VRF/GMV are not fallbacks.

The sidebar keeps the existing fixed configured options, including the inclusive `9.000 - 12.000 BTU` range. Zero-coverage options remain visible; their query returns zero verified Products. This is an explicit stable-navigation UX contract.

## 5. Safe partial taxonomy apply

`catalog:apply-product-taxonomy` defaults to dry-run and requires all of `--apply`, `--approved`, a stable batch, and the exact approved digest. It re-reads and locks every Product, checks ID/SKU/model/Brand/current category, and writes taxonomy only. Per-Product rejection or mark-for-review after group approval excludes that Product from apply.

Final dry-run: `would_verify=368`, `SKIPPED_UNRESOLVED=4`, `PRODUCT_MUTATION=NONE`. Taxonomy and marketing apply remain separate commands and migrations never apply Product data.

## 6. Remaining four Product dispositions

| Product | Model | State | Reason/action |
|---:|---|---|---|
| 1238 | `GCC36S6IA/GMC36S6IA` | CONFLICT | Source has the non-`A` identity. Separate identity correction review required. |
| 1271 | `GULD100T1/A-S/GULD100W1/NhA-S` | NO_EVIDENCE | No exact trusted local source identity. Source required. |
| 1366 | `FTHF71AVMV/RHF71AVMV` | CONFLICT | Source identity includes `71V`; no fuzzy correction is allowed. |
| 1597 | `LOCAL-AI-RETRY-PROBE-20260830` | NO_EVIDENCE | Runtime probe, not catalogue evidence. Remediation/inactivation decision remains separate. It cannot enter a verified BTU result. |

These rows may retain the legacy category for Admin compatibility, but their state is non-authoritative and fail-closed.

## 7. Existing 16-value disposition

- numeric values present: 16
- verified values: 0
- computed state: 16 `UNVERIFIED`
- commercial-ledger conflicts: 16 (`CURRENT_MARKETING_VALUE_UNVERIFIED`)
- certified public filter matches from current Local data: 0

## 8. Marketing-tier Admin approval architecture

Admin page `/admin/product-data-governance` is linked from the existing Import / Export governance page. It provides taxonomy counts/rows/evidence, exact-digest approval, reject, mark-for-review, ledger export, marketing groups, explicit tier input, reject/mark-unresolved controls, and an append-only event stream.

No tier is preselected from technical BTU, kW, HP, nearest bucket, title, or model suffix. High-risk mapping approval requires confirmation and reason. Permissions are explicit: `product_taxonomy.view`, `product_taxonomy.approve`, `marketing_capacity.view`, `marketing_capacity.approve`, and `marketing_capacity.apply`.

## 9. Grouped mapping review

The public rollout denominator is active/public Products, not all historical/inactive rows:

- active Products: 183
- active taxonomy-deterministic: 179
- active physical-DX filter eligible: 143
- active non-eligible or unresolved: 40
- review groups: 69
- exact source explicit-tier Products: 0
- Admin mapping required: 143
- approved Product mappings: 0

Grouping uses Brand, exact source, physical category, exact technical BTU, and exact kW. Grouping only reduces review workload; it does not infer a selected tier.

## 10. Approval digest and audit trail

`product_data_governance_events` is append-only and records domain, action, batch UUID, ledger/source/mapping digests, affected Product IDs, descriptor, previous action, new action, operator, reason, and timestamp. Eloquent update and delete operations are explicitly rejected. Changed source/group membership invalidates an approved marketing mapping. Changed taxonomy data invalidates the approved ledger digest. Authorization and stale guards are tested.

The final commercial-tier ledger digest is `bc4aea94d61b8570c48251ee47f85f6e81a13a9e7c33b03c5502b427bc6833ed`. It contains 316 `NO_EVIDENCE`, 16 `CONFLICT`, and 40 non-eligible rows; it authorizes zero capacity updates.

## 11. Filter safety versus coverage

Safety and completeness are intentionally separate:

- FILTER_SAFETY: PASS
- FILTER_DATA_COVERAGE: EMPTY
- current unverified-value leakage: 0
- unresolved-taxonomy leakage: 0
- VRF/GMV leakage: 0
- active filter-eligible Products after taxonomy simulation: 143
- eligible with verified marketing capacity: 0
- eligible without verified marketing capacity: 143

The all-row audit additionally reports 332 physically eligible, 36 known non-eligible, and 4 unresolved/conflict. Those all-row counts include inactive records and are not the public rollout denominator.

## 12. Browser evidence

Playwright ran against a separately migrated SQLite database. It verified Admin taxonomy approval, marketing group approval, append-only audit events, fixed sidebar options, a verified wall-mounted 18k Product, a verified SkyAir cassette 24k Product, and exclusion of unverified capacity, unresolved taxonomy, and VRF. Result: 1 passed; no HTTP 500, failed request, console error, page error, or Livewire error.

Temporary fixture writes were confined to the isolated SQLite database under `storage/framework/testing`; the Local Product database was not used.

## 13. Full regression and static gates

- focused domain regression: 136 tests, 1,778 assertions, PASS
- full PHPUnit: 616 tests; 615 passed; 1 skipped; 3,767 assertions; 0 failed/error; PASS
- Composer validate strict: PASS
- Composer audit: PASS, no advisories
- npm audit high: PASS, zero vulnerabilities
- Vite production build: PASS
- PHP lint: PASS, 37 release-scope changed/untracked PHP files
- high-confidence secret scan: PASS, zero hits
- `git diff --check`: PASS

The initial full run correctly exposed old calculator fixtures that had numeric capacity but no verification states. Those fixtures were updated to model verified canonical data; unverified/unknown/VRF fixtures remain fail-closed.

## 14. Worktree audit

`WORKTREE_UNKNOWN=0` across 97 changed/untracked paths: 42 `THIS_PATCH`, 6 `SOURCE_EVIDENCE / PATCH_DEPENDENCY`, 6 `GENERATED`, 10 `LOCAL_ONLY / EXCLUDE`, and 33 `PREEXISTING / PRESERVE`. Explicit staging is mandatory later; never use `git add .`.

| Paths | Classification |
|---|---|
| `app/Console/Commands/{ApplyProductTaxonomyCommand,AuditMarketingCapacityFinalGateCommand,AuditProductDataApprovalCenterCommand,AuditProductTaxonomyCommand,BackfillMarketingCapacityCommand,ExportMarketingCapacityInventoryCommand,IngestMarketingCapacityProductListCommand}.php` | THIS_PATCH |
| `app/Filament/Pages/ProductDataGovernancePage.php`, `resources/views/filament/pages/{product-data-governance,import-governance}.blade.php` | THIS_PATCH |
| `app/Models/{Product,ProductDataGovernanceEvent}.php` | THIS_PATCH |
| `app/Enums/ProductHvacClass.php`, `app/Services/Calculator/BtuCalculatorService.php`, `app/Services/Product/{ProductEquipmentTypeResolver,ProductHvacClassResolver}.php` | THIS_PATCH |
| `app/Services/Product/{MarketingCapacityFinalGateService,ProductDataApprovalCenterService,ProductDataApprovalService,ProductDataVerificationService,ProductFilterService,ProductListMarketingCapacityEvidenceService,ProductMarketingCapacityAuditService,ProductMarketingCapacityEligibility,ProductMarketingCapacityQueryAdapter,ProductTaxonomyAuditService}.php` | THIS_PATCH |
| `config/permissions.php`, `database/migrations/2026_09_06_000001_add_product_data_verification_governance.php` | THIS_PATCH |
| `tests/Feature/{BtuCalculatorGovernanceTest,BtuCalculatorV2ActivationTest,CalculatorEquipmentTypeRecommendationTest,MarketingCapacityAuditCommandTest,MarketingCapacityInventoryCommandTest,ProductDataVerificationGovernanceTest,ProductFilterUxTest,ProductListMarketingCapacityEvidenceTest,ProductTaxonomyFinalGateTest}.php`, `tests/Unit/ProductMarketingCapacityEligibilityTest.php` | THIS_PATCH |
| `tests/browser/{product-data-governance-fixture.php,product-data-governance.spec.ts}` | THIS_PATCH |
| `docs/catalog/evidence/gree_physical_category_sections_reviewed.json` and this report | THIS_PATCH |
| `database/migrations/2026_09_05_000001_add_source_hash_to_catalog_sources.php`, `docs/catalog/MARKETING_CAPACITY_PRODUCT_LIST_INGESTION.md`, `docs/catalog/evidence/{daikin_room_air_pages_23_24_reviewed.json,product_identity_conflicts_reviewed.json}`, `docs/catalog/templates/marketing_capacity_product_list_template.csv`, `scripts/audit_marketing_capacity_pdf_sources.py` | SOURCE_EVIDENCE / PATCH_DEPENDENCY |
| `DAIKIN_SKYAIR_2026_IMPORT.xlsx`, `DAIKIN_WALL_MOUNTED_2026_IMPORT.xlsx`, `DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx` | SOURCE_EVIDENCE / LOCAL_ONLY / UNSTAGED |
| `public/build/manifest.json`, deleted old `public/build/assets/{app-DG6zPQSN.css,theme-m_c3rSiL.css}`, new `public/build/assets/{app-Cchfok6M.css,theme-D7sfTN-E.css}` | GENERATED CERTIFIED BUILD |
| `scripts/__pycache__/audit_marketing_capacity_pdf_sources.cpython-314.pyc` | GENERATED / EXCLUDE |
| `artifacts/{AUDIT_RUN_INFO.txt,DEPLOY_V1_33_8.sh,DEPLOY_V1_33_8.sha256,LIVE_PRODUCT_IMPORT_BTU_AUDIT_PACKAGE.sha256,live_product_audit_b181b5bb71011664.php}`, `tools/live-product-import-btu-audit/AUDIT_RUN_INFO.txt`, `product_export_all_378_2026-09-02_151912.xlsx` | PRIVATE_REPORT / LOCAL_ONLY / EXCLUDE |
| modified campaign/post/promotion PNG files under `docs/reports/final/artifacts/browser/` | PREEXISTING / UNRELATED / PRESERVE |
| `docs/release/{LIVE_DEPLOYMENT_1.33.1_REPORT,LIVE_DEPLOY_V1.33.8,LIVE_DEPLOY_V1.33.9_DRAFT,LIVE_PRODUCT_TRANSFER_81_GREE_V1.33.8,LIVE_SYSTEM_PRODUCT_RESTORE_1.33.5,LIVE_VERIFY_V1.33.8,ROLLBACK_V1.33.8_TO_V1.33.7,V1.33.8_OPERATOR_CHECKLIST}.md` | PREEXISTING DOCUMENTATION / PRESERVE |
| modified `docs/release/LIVE_PRODUCT_TRANSFER_GOVERNANCE_BTU_1.33.8.md`; modified `docs/reports/final/{PRODUCT_IMPORT_EXPORT_GOVERNANCE_BTU_COMPLETE_AUDIT,PRODUCT_TRANSFER_GOVERNANCE_AND_BTU_DATA_COMPLETION}.md`; untracked `docs/reports/final/{CATALOG_REFERENCE_IDENTITY_EVIDENCE_AUDIT,MARKETING_CAPACITY_SOURCE_BACKED_REMEDIATION_1.33.9_BLOCKER,PRODUCT_SYSTEM_RESTORE_CROSS_ENV_FK_REMAP,PRODUCT_TAXONOMY_AND_MARKETING_CAPACITY_FINAL_GATE}.md` | PREEXISTING RELATED REPORTS / REVIEW SEPARATELY |
| `docs/reports/final/artifacts/skyair_production_import_manifest.csv`, `tests/browser/ai-product-bulk-real-provider-probe.php`, modified `tools/live-product-import-btu-audit/LIVE_AUDIT.php`, `tools/product-import-btu-audit/{RUN_LIVE_AUDIT.sh,RUN_LOCAL_AUDIT.cmd,audit.php,compare.php}` | PREEXISTING / UNRELATED / PRESERVE |

## 15. Code release gate

`CODE_RELEASE_READY=YES`. Verification states, fail-closed public query, Admin review, exact-digest approvals, partial taxonomy tooling, approved capacity tooling, browser, full PHPUnit, and static/security/build gates pass. No `VERSION`, commit, tag, push, release, or deploy was performed.

## 16. Data rollout gate

`DATA_ROLLOUT_READY=NO`. The taxonomy ledger still requires an operator approval before any apply, and 143 active eligible Products require explicit source-backed commercial-tier decisions. Products 1238/1366 need identity review, Product 1271 needs a trusted source, and Product 1597 needs an explicit non-catalogue remediation decision.

Required future order: deploy code/schema; approve taxonomy digest; apply taxonomy; post-audit; regenerate marketing review; approve selected mappings; run reviewed capacity dry-run; apply only an approved batch; certify filters. No data apply belongs in a migration.

## Final verdict

The v1.33.9 safety code is ready for a separate patch-release task. Product data rollout is not ready and remains intentionally fail-closed. `PRODUCT_MUTATION=NONE`, `PRODUCTION_MUTATION=NONE`, and `V1_33_9_TAG=NOT_CREATED`.
