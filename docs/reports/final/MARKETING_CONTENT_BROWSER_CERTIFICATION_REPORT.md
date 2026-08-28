# Marketing / Content Browser Certification Report

## 1. Environment

- Application: final release candidate `1.30.0` (audit baseline `1.29.0`), local isolated browser server at `http://127.0.0.1:8098`.
- Browser: Google Chrome `152.0.7977.64`, controlled by Playwright `1.62.1` in headless mode.
- UI stack: Filament `5.7.6`, Livewire `4.4.1`.
- Database: dedicated SQLite file under `storage/framework/testing`; migrated from zero and never pointed at MySQL `dieuhoa-tudung`.
- Queue: `sync` only for the isolated process. AI worker desired state was not changed. AI provider rows were absent.

## 2. Browser Harness

The repository had no Playwright, Dusk, Cypress, Selenium or Puppeteer harness. Playwright was added as a development dependency only. It reuses installed Chrome and has no PHP production runtime dependency. The deterministic fixture creates one isolated operator, Post, AI job, six Campaigns and five Promotions, records all IDs, and deletes only those IDs after the suite.

## 3. Authentication

The suite uses the normal Filament login page with an isolated active `super_admin` fixture. No real password, user record or global authorization bypass was changed.

## 4. Website Campaign

PASS. An active home modal rendered through the production component. Draft/inactive and future-scheduled Campaigns were absent. Production rendering was proven at desktop size. Image and YouTube video popup renderers were proven through the authorized preview action.

## 5. Campaign Preview

PASS. Draft/inactive Campaign preview used `x-site-campaigns` with `data-campaign-preview=1`. The Campaign event count was captured before and after preview and remained unchanged. No public preview route exists.

## 6. Post RichEditor

PASS. Chrome proved click, focus, cursor movement, Vietnamese typing, delete, mouse drag selection, the Filament `In đậm` toolbar control, clipboard paste, save and reload persistence. `elementFromPoint()` returned the editor hit target with pointer events enabled. Persisted HTML contained no script, event handler, `contenteditable`, fixed overlay style or `javascript:` URL from the unsafe synthetic fixture.

## 7. AI Post Workflow

PASS using a deterministic persisted non-provider AI draft. Compare, approve and apply ran from the same Post Edit page. Post ID remained `1` in the isolated database and Post count remained `1`. The second apply was a no-op and created no Post. Existing feature coverage separately proves stale-content rejection with `AI_POST_TARGET_CONTENT_CHANGED`.

## 8. Promotion Banner

PASS. The home banner rendered through the shared public layout at 1366px and 390px.

## 9. Promotion Landing

PASS. A `landing` Promotion rendered only on the actual named route `/dieu-hoa-tu-dung`.

## 10. Promotion Popup

PASS. Popup rendered, dismissed through its real button and did not reappear without navigation. When a modal Campaign is also active, its backdrop correctly owns pointer input until the modal is dismissed. Mobile rendering at 390x844 remained usable.

## 11. Promotion AI

PASS. The normal Promotion Edit action used the local governed draft path because no provider was configured. Both `Mô tả chương trình` and `Nội dung chi tiết` were filled, saved and survived reload. Discount type/value, start/end timestamps and Product scope remained byte-for-byte equivalent in the fixture snapshot.

## 12. Console / Network Errors

PASS. The suite captured browser console errors, page errors, same-origin failed requests, HTTP failures from Livewire, and wrote `runtime-issues.json`. Final result: `[]`.

## 13. Responsive Proof

Desktop 1366x900 and mobile 390x844 were exercised for Campaign/Promotion surfaces. The Post editor was exercised in desktop operator layout. No relevant JavaScript error occurred.

## 14. Screenshots

Eleven PII-free screenshots are stored in `artifacts/browser/`: Campaign production/preview/image/video; Post AI preview/apply/editor; Promotion banner/popup/landing/mobile/AI content.

## 15. Fixes Discovered During Browser Testing

No remaining production source defect was reproduced. Harness locators were aligned with actual Filament accessibility labels. Campaign modal layering over a Promotion popup was classified as expected modal behavior, not bypassed in application code.

## 16. Full Regression

Browser suite: `6 passed`, `0 failed`, provider calls `0`. Full Laravel/build/security results are recorded in the parent audit after final release validation.

## 17. Remaining Limitations

- Video proof validates the renderer and iframe URL; it does not certify third-party YouTube availability.
- AI completion was represented by deterministic persisted state/local governed Promotion draft. No real provider generation was authorized or called.
- Screenshots certify local fixture rendering, not a production deployment smoke test.

## 18. Final Verdict

**BROWSER CERTIFICATION = PASS.**

Website Campaign, Post RichEditor, AI Post and Promotion browser-specific blockers are closed. Release still depends on the full repository regression and release gates.
