# Admin Navigation Overlap Report

Audit date: 2026-08-23. This report classifies ownership overlap from application code; no resource or data was deleted.

| Surfaces | Classification | Evidence | Navigation consequence |
|---|---|---|---|
| LandingSection `hero` vs HeroSlide | `INTENTIONAL_SEPARATE_CONSUMERS` with `LEGACY_SURFACE/UNKNOWN` data | `LandingController` renders landing section views for `dieu-hoa-tu-dung`; homepage directly queries `HeroSlide`. Current three LandingSection rows use `page_key=home`, which the controller does not consume. | Keep both under **Trang & Giao diện**; flag the unused `home` rows for later data-owner review. |
| LandingSection `faq` vs FAQ Resource | `INTENTIONAL_COMPOSITION_REFERENCE` | The landing controller selects FAQ records for a section; FAQ remains the shared content source used by products, posts, categories and case studies. | FAQ remains primary content; LandingSection remains layout/composition. |
| LandingSection `featured_products` vs Product | `INTENTIONAL_COMPOSITION_REFERENCE` | Section code queries/selects products; it does not own product facts. | Product stays in **Sản phẩm**; layout belongs to **Trang & Giao diện**. |
| LandingSection policies/case studies/posts | `INTENTIONAL_COMPOSITION_REFERENCE` | Section data is assembled from PolicyPage, CaseStudy and Post queries. | Keep source entities in their domains and composition in **Trang & Giao diện**. |
| HomeBenefit vs LandingSection | `NO_DIRECT_DUPLICATE_FOUND` | `LandingSectionType` has no home-benefit type; HomeBenefit is queried only by the homepage benefit component. | HomeBenefit is homepage composition. |
| Site Settings vs homepage/page resources | `SEPARATE_CONFIGURATION_LEVELS` | Settings own branding, contact, SEO defaults, CTA and limits; content resources own records rendered on pages. | Site Settings stays in **Hệ thống**. |
| CaseStudy legacy testimonial text vs Testimonial relation | `LEGACY_SURFACE/POTENTIAL_DUPLICATE_CONFIGURATION` | CaseStudy retains a testimonial text field while also exposing `hasMany(Testimonial)`. | No data-model change in this task; retain both resources and document for future product-owner decision. |
| AI Product Jobs vs AI Content Jobs | `DISTINCT_OPERATIONAL_SCOPES` | Separate resources and runtime records exist; both use the governed content workflow. | Keep both in **Nội dung AI**, with Vietnamese labels and unchanged routes. |

## Unresolved ownership questions

1. The three `LandingSection` records keyed `home` are not consumed by the current `LandingController`, whose key is `dieu-hoa-tu-dung`. They may be historical data or an incomplete homepage composer. No mutation is justified here.
2. The CaseStudy testimonial text field and related Testimonial records can represent separate editorial patterns, but the intended canonical source is not explicit.
3. Supporting post resources remain independent screens because editors need CRUD access, but they are now reached through the Post workflow instead of permanent top-level menu rows.
