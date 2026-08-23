# Release v1.27.0 — Domain-Oriented Admin Navigation

Release date: 2026-08-23

Status: RELEASE

## Highlights

This backward-compatible minor release reorganizes the Filament sidebar around operator workflows and proven business ownership. It preserves every resource, route and server-side permission while reducing navigation density.

## Navigation

- Reduced **Nội dung** from 12 registered peer items to four primary workflows: Bài viết, Dự án thực tế, Cảm nhận khách hàng and Câu hỏi thường gặp.
- Moved PostCategory, Author and Tag access into the permission-aware **Cấu hình bài viết** action on the Post list.
- Added **Trang & Giao diện** for landing composition, homepage banners, homepage benefits and policy pages.
- Moved **Cam kết báo giá** to **Bán hàng**, matching its quote-page consumer.
- Renamed **AI Content** to **Nội dung AI** and standardized navigation ordering and icons.

## Code-proven ownership

- Post categories, authors and tags are supporting entities for the Post workflow.
- FAQ is shared content used by products, posts, categories, case studies and landing pages.
- HeroSlide and HomeBenefitItem are homepage composition resources.
- LandingSection is a page-composition surface that references existing content entities.
- QuoteCommitmentBlock belongs to the quote/sales workflow.

## Compatibility and security

- Existing Filament resource route names and deep links are unchanged.
- Resource policies and server-side RBAC remain authoritative.
- Authorized supporting-resource links return normally; limited users remain denied.
- No database migration or dependency update is included.
- Product/catalog technical data is unchanged.
- AI worker remains disabled and provider calls remain zero.

## Validation

- Focused navigation suite: 7 tests / 67 assertions.
- Full suite: 327 tests / 1,075 assertions / 0 failures or errors / 1 skipped.
- PHP lint, admin route listing, Blade cache, JSON/CSV validation and `git diff --check`: PASS.
- Database: 81 Products / 212 catalog sources / 36,453 catalog models / 656,507 catalog fields / 90 migrations.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

## Documentation

- `docs/reports/final/ADMIN_NAVIGATION_INFORMATION_ARCHITECTURE_REPORT.md`
- `docs/reports/final/admin_navigation_full_inventory.csv`
- `docs/reports/final/admin_ia_matrix.csv`
- `docs/reports/final/admin_navigation_relationships.json`
- `docs/reports/final/admin_navigation_overlap_report.md`

## Known non-blocking questions

- Three historical LandingSection rows use `page_key=home` but are not consumed by the current landing controller.
- CaseStudy retains both a legacy testimonial text field and a Testimonial relationship.

Neither issue was mutated or treated as a release blocker because this release changes navigation ownership only.
