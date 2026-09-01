# Product Detail Numeric Formatting Pre-release Gate

## Verdict

`NEW_FIX_REQUIRED` was proven on Local and fixed. `PRE_RELEASE_PRODUCT_DETAIL_GATE = PASS`.

## Production evidence

The supplied production log repeatedly reported `number_format(): Argument #1 ($num) must be of type int|float, string given` from the compiled `products/show.blade.php` path between 2026-08-31 21:34:28 and approximately 21:37:16. Production was not patched by this audit.

## Exact Local reproduction

Product #1316 (`GVH24AKXF-K6DNC8A`) has no dedicated `technical_capacity_btu`. Its canonical display fallback reads `specs_json.capacity_btu = "24225.2 / 28660.8"`. `ProductTechnicalFactResolver::getDisplay()` returned this source range as a string and Product detail source line 262 passed it directly to `number_format()`. Before remediation, the real Local route returned HTTP 500. Product #1238 demonstrates that a plain numeric string can be accepted by PHP, which explains why the defect affected only particular records.

## Reachable formatting audit

Product detail direct calls were audited:

- Price calls receive `float|null` from `PromotionPriceResolver`.
- Marketing/technical BTU calls previously accepted unchecked source strings; now they consume `formatBtuDisplay()` output.
- Product document `file_size` is Eloquent-cast to integer before arithmetic/formatting.
- Related Product cards and the quote modal used the same unchecked BTU path and now use the same strict formatter.

## Domain contracts

- Monetary values accept only int, float, or plain non-negative decimal strings from DECIMAL storage.
- `null` and empty values use the existing contact-price fallback.
- Formatted strings such as `12.500.000`, `12,500,000`, and business text such as `Liên hệ` are not silently coerced.
- BTU display accepts numeric scalars and explicit `number / number` ranges. Ambiguous text is not sent to `number_format()`.

## Verification

- Focused: 11 tests passed, 57 assertions.
- Full PHPUnit: 546 tests, 545 passed, 1 skipped, 3,333 assertions, 0 failures/errors, exit code 0.
- Playwright: Product #1316 range, Product #1238 decimal price and Product #1243 no-price fallback all returned HTTP 200; 1/1 test passed with 0 relevant console/page/network/HTTP 500 errors.
- Composer strict validation/audit, npm high audit and production build: PASS, 0 vulnerabilities/advisories.

This was not already fixed in the current Local source before the audit.
