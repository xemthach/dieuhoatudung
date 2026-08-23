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

Focused coverage verifies workflow groups and labels, canonical version display, safe PHPUnit bootstrap, dashboard widget order/discovery, compact diagnostics, R2 permission hierarchy, and authorized rendering of Dashboard, System Health, R2/CDN, AI Queue, AI Jobs, and Marketing Integrations. Final result: 326 tests, 1,053 assertions, zero failures/errors, and one pre-existing skipped test. Composer validate/audit, npm audit, Vite build, config/route/view cache, PHP lint, and `git diff --check` passed.

## 18. Remaining UX Backlog

- Several older resources still use generic rectangle-stack icons; functionality and grouping are clear, but icon refinement can be handled incrementally.
- A real authenticated browser screenshot pass remains desirable after deployment transport is available.
- The Marketing Integrations health service is bounded but settings-heavy; cache changes were intentionally avoided without a separate invalidation requirement.

## Decision

Admin UX / IA consolidation passes its code, test, build, security, performance, and data-safety gates. The existing `v1.25.0` tag remains immutable; operator authorization selected a new semantic minor release, `v1.26.0`, for this feature set.
