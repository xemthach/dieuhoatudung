# Admin UX / Information Architecture Report

Date: 2026-08-23
Release target: v1.26.0

## 1. Existing Admin Inventory

The inventory was derived from `app/Filament`, panel registration, routes, resource permission maps, widgets, views, and related services. The complete row-level inventory is in [admin_navigation_inventory.csv](admin_navigation_inventory.csv). Hidden import result/preview pages, the security probe, profile, legacy settings compatibility page, and the hidden settings resource remain available without becoming duplicate sidebar entries.

## 2. Existing Navigation Problems

The sidebar reflected implementation history rather than operator workflows: `CRM`, `Leads & Contacts`, `E-commerce`, `Content`, `Landing & Pages`, `SEO`, `SEO & AI`, and `System` overlapped. Case Studies and Testimonials appeared at root. English and Vietnamese labels were mixed. AI queue diagnostics sat beside business content, while AI provider configuration had the same visual weight as routine product work.

## 3. Functional Domain Map

The verified capabilities are organized into seven workflow domains:

1. Bán hàng — leads, quotes, reviews, Q&A, BTU consultations.
2. Sản phẩm — products, categories, brands, promotions.
3. Nội dung — posts, taxonomy, authors, FAQ, case studies, testimonials, landing/home/policy content.
4. SEO & Marketing — SEO audit, integrations, internal links, redirects, campaigns.
5. AI Content — product/blog content jobs and provider configuration.
6. Hệ thống — import/export, media/CDN, email, users, roles, website settings.
7. Vận hành — queue/worker/scheduler diagnostics.

## 4. New Navigation Architecture

`AdminPanelProvider` defines the group order. Individual items retain their semantic icons because Filament 5 prohibits icons on both a group and its children. Resource/page authorization remains unchanged and server-side; navigation visibility is not treated as authorization. Vietnamese labels and deterministic sort positions are applied to daily workflows.

## 5. Dashboard Redesign

Before: System Health and AI Runtime Policy were auto-discovered before business KPIs and rendered as tall key/value text.
Source: `SystemHealthWidget`, `AIRuntimePolicyWidget`, `MainDashboardWidget`, and their Blade views.
After: business KPIs and action-required/recent activity render first. System Health is a compact semantic grid below them. AI Runtime Policy is no longer dashboard-discovered and is available contextually on the AI jobs page. Dashboard duplicate stat reads were removed: measured Main Dashboard widget queries fell from 85 to 21 on the populated current database.

## 6. Import / Export

The four KPI cards and separate import/export histories were retained because their information architecture was already useful. Labels are aligned with the Vietnamese sidebar, filename truncation/tooltips and module/status badges remain, and page-specific inline CSS moved into the compiled Filament theme. Existing owner/module IDOR controls were not changed. Measured render: 19 queries.

## 7. R2/CDN

Before: five equal-weight header actions and a large narrative progress card obscured risk.
Source: `R2SyncManager` and `r2-sync-manager.blade.php`.
After: compact R2/local/synced/missing/failed/last-scan cards, concise latest-operation progress, and a job history section. Connection and scan are utility actions; upload is primary; URL migration is grouped separately. Real URL replacement remains danger-colored, confirmed, dry-run-gated, and now has explicit server-side `r2.sync` checks. Test and scan use `r2.test` and `r2.scan`. Measured render: 6 queries.

## 8. AI Content Jobs

The job table is now primary. Summary cards show queued, processing, review-required, completed, and failed/blocked counts. Runtime policy is collapsed beneath the summary. Default columns focus on operation, scope, status, progress, attempts, and updated time; worker/tokens/internal runtime fields are toggleable and hidden by default. This also prevents the previous three expensive per-row runtime snapshots from being evaluated in the normal table view. Measured render: 5 queries.

## 9. AI Queue Health

Before: warnings, CLI commands, heartbeat JSON, and the last-job JSON dominated the page.
After: status cards distinguish desired and actual worker state, queue, historical failures, scheduler, connection, and last activity. An intentionally disabled worker is gray/disabled rather than critical. Commands and raw JSON remain available only under technical details. Stuck recovery is permission-guarded, confirmed, and disabled when no stuck jobs exist. Measured render: 21 queries.

## 10. Marketing Integrations

Before: repeated raw labels and large text blocks for every integration.
After: summary cards for configured/needs setup/critical/tracked events, two-column integration cards, semantic status badges, explicit missing-field lists, readable capability lists, and collapsed technical values. Header actions now distinguish refresh, navigation to Merchant feed, and confirmed offline conversion upload. Measured render: 37 bounded queries.

## 11. Settings / Users / Roles

Existing settings schemas and security behavior were preserved. Navigation labels are now `Cài đặt website`, `Người dùng`, and `Vai trò & quyền`. Hidden backing settings resources and the legacy compatibility page remain hidden, preventing duplicate settings entry points. No secrets or raw permission arrays were newly exposed.

## 12. Status / Color System

The reusable `x-admin.status-badge` maps healthy/ready/online to success, warnings/setup/stale to warning, critical/error/failed to danger, and disabled/offline/unknown to gray. Shared grid, key/value, diagnostics, and table primitives live in the Filament Vite theme instead of new inline style blocks.

## 13. Action Hierarchy

Normal navigation/read actions use gray/info, primary workflow actions use primary, recover/retry/setup actions use warning, and destructive/high-impact actions use danger plus confirmation. Existing import, AI, and R2 server-side permissions remain the authority.

## 14. RBAC / Security Preservation

- Import preview/result owner and module authorization was unchanged.
- AI retry/recovery permission checks remain server-side.
- R2 mutation actions gained explicit server-side permission checks.
- Hidden pages remain hidden rather than deleted.
- No Product/catalog technical data, AI provider, queue worker, or runtime data was mutated by this redesign.

## 15. Performance

Read-only Livewire renders against the populated current database recorded: Main Dashboard 21 queries (was 85), System Health 25 (was 65), AI Jobs 5, R2/CDN 6, Import/Export 19, AI Queue 21, and Marketing Integrations 37. `SystemHealthService` now reuses one queue health snapshot per request. All measurements are local diagnostics, not production latency certification.

## 16. Browser / Visual Evidence

The six supplied screenshots were used as before-state evidence. No Playwright/Dusk harness, Chrome CDP listener, or authenticated browser transport was available, so no browser PASS or after-screenshot claim is made. Key page composition is covered by Livewire render tests and Vite/Blade compilation.

## 17. Tests

Focused coverage verifies workflow groups and labels, canonical version display, safe PHPUnit bootstrap, dashboard widget order/discovery, compact diagnostics, R2 permission hierarchy, authorized rendering, MySQL-safe summary SQL, and persisted AI live-status behavior. Final result: 336 tests, 1,124 assertions, zero failures/errors, and one pre-existing skipped test. Composer validate/audit, npm audit, Vite build, config/route/view cache, PHP lint, and `git diff --check` passed across the release and this focused follow-up.

## 18. Remaining UX Backlog

- Several older resources still use generic rectangle-stack icons; functionality and grouping are clear, but icon refinement can be handled incrementally.
- A real authenticated browser screenshot pass remains desirable after deployment transport is available.
- The Marketing Integrations health service is bounded but settings-heavy; cache changes were intentionally avoided without a separate invalidation requirement.

## 19. AI CONTENT LIVE STATUS UX

### Persisted state and presentation contract

`AiContentStatusPresenter` is the single presentation authority for Product rows, Product edit, AI Content jobs, AI Product jobs, item rows, status endpoints, and dashboard summaries. It translates persisted legacy/canonical states without changing their database values: queued → `Đang chờ`; running/processing → `AI đang tạo nội dung`; validating/fact checking → `Đang kiểm tra nội dung`; review required → `Chờ duyệt`; completed/applied → success; blocked → amber `Bị chặn`; failed → red `Thất bại`; paused/cancelled → neutral. Safe error codes are translated to bounded operator messages; raw provider responses, prompts, stack traces, credentials, and exception messages are not returned by polling endpoints.

### Polling and progress

Product edit, AI Product job detail, AI job tables, item tables, and the dashboard AI card poll every 10 seconds. Polling reads persisted job/item/draft counters and heartbeat state only. `AIQueueMonitor::liveStatusHealth()` omits technical logs, stuck-job scans, and command diagnostics used by the full operations snapshot. A measured Product-status request for 20 rows executed 14 queries, with bulk loading of latest items, jobs, drafts, and Products; it does not issue a status query per Product.

The AI Product Jobs header uses a dedicated authorization-scoped aggregate query. It excludes the table query's eager loads and `withCount` projection, keeping the five `SUM(CASE...)` expressions compatible with MySQL `ONLY_FULL_GROUP_BY`. The exact query executed successfully against the populated MySQL database and is protected by an SQL-shape regression test.

Single-Product work uses step status only and returns no fabricated percentage. Bulk jobs show percentage only from persisted `processed / total`, plus persisted success, review-required, blocked, and failed counts. Per-field rows are shown only from persisted `field_status_json` or the explicitly requested field envelope. No current-target Product is displayed because the runtime does not persist a trustworthy current-target pointer.

### Product, job, and dashboard UX

- Product table: one `Nội dung AI` column resolves the latest runtime item rather than relying on the legacy Product status column. It is updated in place by the bounded status endpoint.
- Product edit: a compact live panel shows status, last update, safe warning/reason, real batch progress when applicable, and persisted field states. Generate/regenerate immediately confirms request acceptance and explicitly warns when the desired worker state is disabled.
- AI Product job detail: live aggregate panel shows processed/total, running, success, review-required, blocked, failed, optional real token budget counters, and last update. Job/item tables retain 10-second polling.
- Dashboard: the AI card separately shows worker desired state, running, queued, review-required, and blocked work. An intentionally disabled worker is neutral `Đang tắt`, not a false critical state, and historical failed totals are not presented as active running work.

### Worker, failures, review, and apply

Queued work with desired worker state `DISABLED` displays `Đã tạo yêu cầu nhưng AI worker đang tắt.` Processing/validation/retry with stale, offline, or unknown heartbeat changes presentation to `Có thể bị gián đoạn` instead of showing an endless generating state. Blocked governance outcomes remain distinct from runtime/provider failures. Review-required work exposes the existing authorized job/review path; generate and retry still create/update job runtime records only. Product fields are not used as the live status store, and regenerate remains draft → review → approval → apply.

### RBAC, tests, and browser evidence

Polling components and endpoints enforce existing `product.view`, `product.ai_generate`, `bulk_ai_view`, Product scope, and bulk-job authorization server-side. Focused fixtures prove queued, processing, validating, review, completed/applied, blocked, failed, paused, cancelled, worker-disabled, stale-worker, per-field, bulk progress, no fake single-item percentage, status change on the next Livewire refresh, job aggregates, dashboard state separation, bounded query cost, and Product zero-write retry behavior. Provider calls remained zero and the worker remained disabled. No browser transport was available, so no browser live-update PASS is claimed; Livewire render/refresh tests provide server-component proof.

## Decision

Admin UX / IA consolidation and the AI live-status follow-up pass their code, test, performance, RBAC, and data-safety gates. The canonical version remains `v1.27.0`; this audit did not change release versioning.
