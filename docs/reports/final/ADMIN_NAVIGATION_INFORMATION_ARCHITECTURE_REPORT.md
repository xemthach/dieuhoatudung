# Admin Navigation Information Architecture Report

Audit date: 2026-08-23

Scope: Filament admin navigation only

Verdict: **ADMIN NAVIGATION IA = PASS**

## 1. Current Sidebar

The pre-change sidebar exposed resources according to the order in which Filament resources had been added. The largest symptom was **Nội dung**: code registers 12 peer items (the screenshot did not show the complete group) mixing published entities, blog taxonomies and page composition. This made the operator understand the internal data model before choosing a task, and opening the group displaced most other domains below the viewport.

The screenshot was used only as UX evidence. The inventory and decision came from the current Filament classes, models, routes, forms, relation managers, controllers and Blade consumers.

## 2. Code Inventory

The audit covers 44 registered, permission-dependent, hidden or user-menu surfaces. The authoritative row-level inventory is [admin_navigation_full_inventory.csv](admin_navigation_full_inventory.csv). It records label, class, file, route, type, model, group, sort, icon, permission, access mode, usage, domain, relationships, frontend location, frequency and visibility.

Files inspected included `app/Filament`, the panel provider, models, services, controllers, routes, configuration and frontend views. No route, model, table or permission name was changed.

## 3. Domain Mapping

The resulting major domains are:

1. Overview (ungrouped dashboard)
2. Bán hàng
3. Sản phẩm
4. Nội dung
5. Trang & Giao diện
6. SEO & Marketing
7. Nội dung AI
8. Hệ thống
9. Vận hành (collapsed diagnostics)

There are eight grouped domains plus the dashboard. The additional page-composition domain reduces cognitive load more than it adds group-switching cost.

## 4. Primary vs Supporting Resources

- **Primary content:** Bài viết, Dự án thực tế, Cảm nhận khách hàng and Câu hỏi thường gặp have independent editorial or reuse lifecycles.
- **Supporting blog entities:** Danh mục bài viết, Tác giả and Thẻ nội dung support Post through `belongsTo`/`morphToMany` relationships.
- **Page composition:** Bố cục trang đích, Banner trang chủ and Lợi ích trang chủ control placement or page modules.
- **Site/page configuration:** Trang chính sách is independently published but belongs to page/site management rather than the editorial feed.
- **Sales configuration:** Cam kết báo giá is rendered only in the quote flow.
- **Operational/diagnostic:** health, queue, mail logs and synchronization surfaces remain separate from daily content work.

Supporting blog CRUD screens are preserved. They are hidden only from the global sidebar and exposed from the Post list through a permission-aware **Cấu hình bài viết** action group.

## 5. Content Group Analysis

The former group contained 12 code-registered peer rows. The implemented group contains four primary entities:

```text
Nội dung
├── Bài viết
├── Dự án thực tế
├── Cảm nhận khách hàng
└── Câu hỏi thường gặp
```

PostCategory, Author and Tag are not deleted or merged. Existing deep links remain valid, and server-side resource policies still decide access. This reduces the open Content group by eight rows (67%).

FAQ is not product-only. Its polymorphic relationships and frontend consumers prove that it is shared global content for Product, Post, ProductCategory, PostCategory and CaseStudy contexts.

CaseStudy has independent public routes and relates to Product, FAQ, Tag and Testimonial. Testimonial is reusable social proof consumed by product, case-study and landing surfaces. Both therefore remain primary content rather than blog taxonomy.

## 6. Page Composition Analysis

`HeroSlide` and `HomeBenefitItem` are queried only by homepage components. `LandingSection` is a page composer: `LandingController` resolves finite section types and assembles Product, Category, CaseStudy, FAQ, Policy and Post content. `QuoteCommitmentBlock` is queried only by the quote page component.

Implemented ownership:

```text
Trang & Giao diện
├── Bố cục trang đích
├── Banner trang chủ
├── Lợi ích trang chủ
└── Trang chính sách

Bán hàng
└── Cam kết báo giá
```

The shorter label **Bố cục trang đích** is more accurate than treating LandingSection as a published content entity.

## 7. Duplicate/Overlap Surfaces

The detailed classification is in [admin_navigation_overlap_report.md](admin_navigation_overlap_report.md).

- Landing FAQ/Product/Policy/CaseStudy/Post sections are intentional composition references, not duplicate source-of-truth records.
- HeroSlide and LandingSection hero target different frontend consumers.
- Three persisted `LandingSection` rows use `page_key=home`, while the current controller consumes `dieu-hoa-tu-dung`. This is an unresolved legacy/data-ownership question and was not mutated.
- CaseStudy has both legacy testimonial text and a Testimonial relation; canonical ownership is not explicit.
- Site Settings controls cross-site settings and display limits, not the page-content records themselves.

## 8. Role/Permission Analysis

Current permissions remain the source of truth. Navigation visibility does not grant access, and hidden buttons are not used as authorization.

- Super Admin retains all resources.
- Editor permissions cover Post and its supporting category/author/tag resources plus shared editorial entities.
- Viewer permissions cover the permitted read-only content subset.
- SEO and AI roles naturally see only their authorized resources.
- Supporting-resource header links call each resource's `canViewAny()` before rendering.
- Focused HTTP tests prove authorized supporting deep links still return successfully.

No navigation badge or dynamic count was added, so menu construction adds no new database query.

Filament's normal collapsible-group behavior is retained. Daily domains are not forced closed, while the diagnostic **Vận hành** group remains collapsed by default. This avoids hiding common work and does not add custom state or JavaScript. The existing orange active state remains sufficient; only icons with proven semantic value were differentiated.

## 9. Option A — Conservative

Keep all resources in existing groups, reorder primary resources above supporting resources, improve labels and icons, and move only Cam kết báo giá.

Advantages: minimal visual change and nearly zero discoverability adjustment.

Drawbacks: the Content group remains long, supporting tables remain peers of the Post workflow, and page composition still reads like editorial content.

## 10. Option B — Domain-Oriented

Create **Trang & Giao diện**, move page composition there, move quote configuration to Sales, keep four primary Content items, and expose blog taxonomies from the Post workflow. Rename **AI Content** to **Nội dung AI**.

Advantages: reflects business ownership, reduces horizontal menu count, separates published content from page arrangement and preserves permission-based discoverability.

Drawbacks: operators familiar with the old taxonomy rows need one extra click through Bài viết.

A Filament Cluster was considered but rejected for this change because cluster routing can alter route prefixes and bookmarked URLs. The implemented ActionGroup provides the workflow relationship without route migration risk.

## 11. Recommended IA

Option B is recommended and implemented. The project has enough resources for the domain split, while its size does not justify deeper multi-level clusters.

```text
Bảng điều khiển

Bán hàng
├── Khách hàng tiềm năng
├── Báo giá
├── Tư vấn công suất BTU
├── Cam kết báo giá
├── Đánh giá
└── Hỏi đáp

Sản phẩm
├── Sản phẩm
├── Danh mục sản phẩm
├── Thương hiệu
└── Khuyến mãi

Nội dung
├── Bài viết
│   └── Cấu hình bài viết: Danh mục, Tác giả, Thẻ
├── Dự án thực tế
├── Cảm nhận khách hàng
└── Câu hỏi thường gặp

Trang & Giao diện
├── Bố cục trang đích
├── Banner trang chủ
├── Lợi ích trang chủ
└── Trang chính sách

SEO & Marketing
├── Tổng quan SEO
├── Tích hợp Marketing
├── Liên kết nội bộ
├── Chuyển hướng 301/302
└── Chiến dịch website

Nội dung AI
├── Công việc nội dung AI
├── Công việc AI bài viết
└── Nhà cung cấp AI

Hệ thống
├── Nhập / Xuất dữ liệu
├── Media & CDN
├── Mẫu email
├── Nhật ký email
├── Người dùng
├── Vai trò & quyền
└── Cài đặt website

Vận hành (collapsed)
└── Trạng thái AI
```

Labels for AI job resources are retained where they identify distinct runtime record types; their group is consistently Vietnamese.

## 12. Implemented Changes

| From | To | Why |
|---|---|---|
| Nội dung / Danh mục bài viết | Bài viết / Cấu hình bài viết | Post taxonomy |
| Nội dung / Tác giả | Bài viết / Cấu hình bài viết | Post supporting identity |
| Nội dung / Thẻ nội dung | Bài viết / Cấu hình bài viết | Shared tagging support led from primary Post workflow |
| Nội dung / Khối trang đích | Trang & Giao diện / Bố cục trang đích | Page composition, not independent editorial content |
| Nội dung / Banner trang chủ | Trang & Giao diện / Banner trang chủ | Homepage-only component |
| Nội dung / Lợi ích trang chủ | Trang & Giao diện / Lợi ích trang chủ | Homepage-only component |
| Nội dung / Trang chính sách | Trang & Giao diện / Trang chính sách | Site/page publishing configuration |
| Nội dung / Cam kết báo giá | Bán hàng / Cam kết báo giá | Quote-flow consumer |
| AI Content | Nội dung AI | Vietnamese group consistency |

Primary and supporting icons were differentiated. Sort order now places primary resources before secondary/review resources. No resource class, model, relation, business logic or URL was removed.

## 13. Before/After Navigation

- Before: Content had 12 code-registered equal-level rows and mixed four mental models.
- After: Content has four primary rows; page composition has a dedicated group; three Post support screens are contextual actions; quote configuration is under Sales.
- Existing resource route names and URLs are unchanged.
- Existing `shouldRegisterNavigation` behavior changed only for the three supporting Post resources; direct route authorization remains intact.

The detailed before/after matrix is [admin_ia_matrix.csv](admin_ia_matrix.csv), and the code-proven entity graph is [admin_navigation_relationships.json](admin_navigation_relationships.json).

## 14. Tests

Focused coverage verifies:

- expected domain groups and labels;
- primary Content resources remain registered;
- PostCategory, Author and Tag do not register globally;
- permission checks guard contextual links;
- authorized deep links for all three supporting resources still return 200;
- existing route and resource access behavior remains unchanged.

Validation result:

- focused navigation tests: 7 tests, 67 assertions, PASS;
- full suite: 327 tests, 1 skipped, 1,075 assertions, 0 failures/errors;
- PHP lint, admin route listing, Blade cache and `git diff --check`: PASS;
- database: 81 Products / 212 sources / 36,453 models / 656,507 fields / 90 migrations;
- canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`;
- worker desired state defaults to disabled; provider calls made by this audit: 0;
- no Product/catalog mutation and no browser pixel-position claim.

## 15. Remaining IA Questions

1. Decide whether the unused `LandingSection.page_key=home` records are historical or should eventually drive a homepage composer.
2. Decide whether CaseStudy's legacy testimonial text or related Testimonial records should become canonical in a future data-model task.
3. Consider translating the two AI job labels only when the operator vocabulary for their distinct semantics is agreed; this is not an IA blocker.
4. The current eight grouped domains plus overview are appropriate for scale; no deeper cluster is recommended until a domain acquires substantially more primary workflows.

## Final Decision

**ADMIN NAVIGATION IA = PASS.** The sidebar now reflects operator tasks and proven ownership rather than one menu row per table, while preserving functionality, routes, RBAC and Product/catalog data.
