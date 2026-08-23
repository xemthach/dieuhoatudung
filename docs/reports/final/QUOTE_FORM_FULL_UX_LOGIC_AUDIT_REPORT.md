# Quote Form Full UX / Logic Audit

## 1. Executive Verdict

**QUOTE FORM AUDIT = PASS (code and HTTP/test evidence).**

The canonical form is now a three-step, entity-aware funnel. Name and phone are the only customer-required fields. Product and Calculator journeys retain their known context. Quote and Lead persistence is transactional and idempotent; notification failure cannot erase the request.

Browser viewport certification was not claimed because this repository has no Playwright/Dusk harness and no safe CDP transport was available in this run.

## 2. Current Funnel

Before implementation, `/bao-gia` rendered four actual steps while the page header claimed “5 steps — 2 minutes”. Five fields carried required markers, but only name and phone were required server-side. The backend accepted many legacy HVAC/project fields not shown by the canonical form. Two unreferenced `quote-steps-*.blade.php` partials still describe the historical five-step implementation but are not rendered.

After implementation:

1. **Nhu cầu** — optional type, short description, scale; known Product/Calculator/Brand context is shown.
2. **Thông tin hữu ích** — optional area; dependent height; secondary conditions and budget are disclosed on demand. Calculator-origin values are summarized rather than requested again.
3. **Liên hệ** — name and normalized phone required; email, province and note optional.

## 3. Backend Flow

Routes remain `GET/POST /bao-gia`, `POST /bao-gia/nhanh`, and the landing CTA endpoint. Dedicated FormRequests own allowlists and normalization. All three submission paths now reuse `QuoteSubmissionService` for the QuoteRequest + linked Lead transaction. Google Ads conversion evidence and mail remain downstream side effects.

The form persists first, then attempts notification. Mail exceptions are caught with quote-ID-only diagnostics. CSRF, honeypot and per-IP rate limits remain intact.

## 4. Field Inventory

The complete inventory is [quote_form_field_inventory.csv](artifacts/quote_form_field_inventory.csv). All accepted fields were classified P0–P3. Technical installation fields remain backward-compatible inputs but are deferred from the public funnel because they are survey/Sales questions, not minimum lead requirements.

## 5. Field Necessity

- P0: name, phone, idempotency and source context.
- P1: known Product/Calculator context, need type, approximate area, urgency, service scope and region.
- P2: budget, sun, existing AC, optional email and explanatory note.
- P3/deferred: electrical phase, pipe distance, outdoor unit position, drainage, existing piping and similar survey data.

No database column was deleted.

## 6. Cognitive Load

The comparative project score fell from **68 to 38**. This is a transparent internal comparison, not an industry benchmark. Actual controls remain available, but five secondary decisions moved behind optional disclosure; contradictory required markers and three false defaults were removed from the UI.

## 7. Step Architecture

The header, labels, `totalSteps`, progress calculation and rendered sections now all state three steps. Progress reports 33/67/100% of the journey instead of 0/33/67/100 with contradictory five-step copy. Back navigation retains Alpine form state. With JavaScript unavailable, sections remain ordinary HTML and the final form can still submit.

## 8. Product Entry

Product query slugs and quick-modal IDs are resolved against active/current database records. Product name, model, brand, category, capacity and URL are snapshotted by the server. The customer does not reselect the Product.

## 9. Calculator Entry

The previous Calculator CTA incorrectly returned to a separate landing anchor. It now links to `/bao-gia?source=calculator`. A non-PII session bridge carries method, rule version, area, height, space type, people, sun, equipment, calculated need and market tier. The Quote stores this provenance in `calculator_context`; it does not trust technical values from the URL or recompute the result.

## 10. Contact Strategy

Contact remains the final step. Full name and phone are justified by the current Sales workflow and non-null database contract. Email and province are genuinely optional. Unsupported “30 minutes”, “1–2 hours”, and “2 minutes” promises were removed.

## 11. Conditional Logic

Height appears only after area for direct journeys. Environmental and budget/timeline questions are optional detail blocks. Calculator journeys replace duplicated sizing inputs with a read-only summary. Explicit unknown choices are supported for project, current AC, budget, timeline and service scope.

## 12. Validation

Phone normalization accepts common Vietnamese presentation formats while persisting one canonical representation. All enums and tracking lengths are server allowlisted. Errors render at the field and in a focused final-step summary; old input and the original submission token survive validation redirects.

## 13. Lead / Quote Persistence

QuoteRequest and Lead now commit atomically. A linked Lead cannot silently fail while the Quote appears successful. `provided_fields` preserves input provenance where legacy non-null/default columns cannot distinguish “not asked” from `false`/`1`.

## 14. Duplicate Handling

A unique UUID submission token prevents accidental duplicates from double click, refresh or network retry. Reusing a token returns the original Quote without another Lead, conversion import or email. A new request from the same phone remains allowed; CRM contact merge policy is not inferred.

## 15. Privacy

PII inventory: [quote_form_pii_inventory.csv](artifacts/quote_form_pii_inventory.csv). No PII is used in Calculator URLs or funnel analytics. No partial form is persisted and no server-side PII autosave was added. Existing retention duration remains an operator-governance limitation.

## 16. Analytics

Privacy-safe `quote_started`, `quote_step_completed`, and existing submitted events provide funnel evidence. Step events contain only step and entry context. No keystroke tracking was added.

## 17. Admin Workflow

The Quote list now exposes a concise entry-source badge. Detail adds Calculator method, rule version, raw need and market tier when present. Existing call, Zalo, status and note actions remain unchanged. There is no current Sales-assignee column or contact merge domain model; both are documented rather than invented.

## 18. Mobile UX

Controls have a minimum 44px height, phone/number inputs request appropriate mobile keyboards, optional groups avoid a long initial viewport, and navigation buttons share width at 390px and below. Sidebar support naturally follows the form in the existing responsive grid.

## 19. Accessibility

Radio groups use fieldset/legend semantics, progress has ARIA values, errors use `role=alert`, step headings receive focus, controls have explicit labels, and keyboard focus is visible. Pixel-level/browser assistive-technology certification remains unavailable.

## 20. Performance

No Product catalog is preloaded. Product/Brand/Category resolution is one bounded lookup only when its slug is present. Calculator context is session data. No new badge count or per-row query was introduced. A direct server-rendered `/bao-gia` request returned HTTP 200 with **8 database queries** and about 60 KB HTML. The CMS commitment block retains its existing bounded block + items query.

## 21. Security

CSRF and rate limiting remain. Product metadata is reloaded from the database. Calculator values come from the session. Free text is rendered through escaped Blade/Filament entries. Submission tokens are unguessable UUIDs and unique in the database. No CAPTCHA was added without spam evidence.

## 22. Implemented Improvements

1. Reconciled actual and displayed step count.
2. Removed unsupported completion/response-time claims.
3. Reduced the canonical funnel to three steps.
4. Made optional fields visually and server-side consistent.
5. Removed false height/service defaults.
6. Added Product, Brand/Category, Campaign and Calculator entry attribution.
7. Added Calculator-to-Quote server-session bridge.
8. Added phone normalization.
9. Added transactional Quote + Lead persistence.
10. Added database-backed idempotency and input provenance.

## 23. Tests

Focused workflow proof: **11 tests / 75 assertions**, covering direct minimal submission, landing submission, Product prefill, Calculator prefill, Vietnamese phone normalization, invalid phone/no persistence, duplicate token idempotency, Quote/Lead linkage, mail-failure durability, and existing Google Ads quote behavior.

Full suite: **435 tests / 1,635 assertions / 1 existing skip / 0 failures or errors**. Composer strict validation and audit, npm high-level audit, Vite build, config/route/view caches, PHP lint, and `git diff --check` passed.

## 24. Browser Evidence

**NOT AVAILABLE.** No Playwright/Dusk harness or safe Chrome CDP transport was found. HTTP/server-rendered tests were used; no browser PASS is claimed.

## 25. Remaining Limitations

1. PII retention duration is an operational policy, not encoded by this workflow.
2. Sales assignment is absent from the QuoteRequest schema.
3. Same-phone contact merge/deduplication is not defined; only accidental request replay is prevented.
4. Legacy five-step partials remain unreferenced source files and should be removed only in a separately scoped repository cleanup.
5. Mobile and accessibility proof is code/HTTP based, not real-device/browser certification.

## 26. Final Verdict

**QUOTE FORM AUDIT = PASS.** Migration `2026_08_24_000000_add_workflow_context_to_quote_requests_table.php` was applied locally and the migration count is **93**. Product/catalog counts and the canonical BTU hash remained exact; worker desired state remained `DISABLED`, and AI request-log count did not change.
