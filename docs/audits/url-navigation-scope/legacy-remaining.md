# Tham chiếu legacy còn giữ

Mỗi dòng có giải thích; xem CSV để đối chiếu nội dung đầy đủ.

| File | Dòng | Phân loại | Lý do hợp lệ |
|---|---:|---|---|
| app/Console/Commands/ImportGreeCatalogue.php | 164 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| app/Console/Commands/ImportGreeCatalogue.php | 189 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| app/Http/Controllers/LandingController.php | 33 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 38 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 141 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 142 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 144 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 145 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 146 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 148 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 156 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 157 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Http/Controllers/LandingController.php | 159 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| app/Services/AI/AISeoContentGenerator.php | 103 | VALID_PRODUCT_CATEGORY_REFERENCE | Một loại thiết bị trong danh sách kiến thức HVAC nhiều danh mục. |
| app/Services/AI/HVACSeoContentEngine.php | 336 | VALID_PRODUCT_CATEGORY_REFERENCE | Một loại thiết bị trong danh sách kiến thức HVAC nhiều danh mục. |
| app/Services/Catalog/CategoryTechnicalSchemaService.php | 84 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| app/Services/Mail/MailTemplateRenderer.php | 68 | OTHER | Ví dụ xem trước template email hoặc biến URL; dữ liệu sản phẩm thật dùng named route. |
| app/Services/Product/ProductMarketingCapacityEligibility.php | 26 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| app/Services/Product/ProductTaxonomyAuditService.php | 18 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| app/Support/Catalog/SkyAirTechnicalSchema.php | 13 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| config/hvac_equipment_types.php | 102 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| config/hvac_equipment_types.php | 116 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| database/factories/ProductFactory.php | 14 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/migrations/2026_05_04_070238_create_landing_sections_table.php | 13 | MIGRATION_HISTORY | Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ. |
| database/migrations/2026_05_14_000004_cleanup_public_vietnamese_copy.php | 15 | MIGRATION_HISTORY | Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ. |
| database/migrations/2026_05_14_000004_cleanup_public_vietnamese_copy.php | 70 | MIGRATION_HISTORY | Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ. |
| database/migrations/2026_05_14_000004_cleanup_public_vietnamese_copy.php | 71 | MIGRATION_HISTORY | Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ. |
| database/migrations/2026_05_14_000004_cleanup_public_vietnamese_copy.php | 73 | MIGRATION_HISTORY | Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ. |
| database/seeders/CatalogueGree2026Seeder.php | 122 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/CatalogueGree2026Seeder.php | 123 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoDataSeeder.php | 37 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoDataSeeder.php | 39 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoDataSeeder.php | 40 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoDataSeeder.php | 41 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoDataSeeder.php | 42 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 22 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 25 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 30 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 42 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 43 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 62 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 63 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 66 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 78 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 79 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 90 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 91 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 94 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 106 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 107 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 118 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 119 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 122 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 134 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 135 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 145 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 146 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 149 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 161 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 162 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 172 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 173 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 176 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 188 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 189 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 201 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 202 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 205 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 217 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 218 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 228 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 229 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 232 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 244 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 245 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 255 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| database/seeders/DemoProductSeeder.php | 256 | VALID_PRODUCT_CATEGORY_REFERENCE | Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue. |
| resources/catalog/gree_physical_category_sections_reviewed.json | 13 | VALID_PRODUCT_CATEGORY_REFERENCE | Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này. |
| resources/views/landing/index.blade.php | 2 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/index.blade.php | 3 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/index.blade.php | 22 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/index.blade.php | 23 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/sections/comparison.blade.php | 6 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/sections/hero.blade.php | 12 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| resources/views/landing/sections/quick_categories.blade.php | 6 | SEO_CONTENT | Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site. |
| routes/web.php | 35 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| routes/web.php | 36 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| routes/web.php | 95 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| routes/web.php | 96 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| routes/web.php | 97 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| routes/web.php | 99 | ROUTE | Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view. |
| tests/Feature/AIProviderGatewayTest.php | 222 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 223 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 241 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 268 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 269 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 315 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 322 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 323 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 324 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 326 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 327 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 328 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 331 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 335 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 350 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AIProviderGatewayTest.php | 358 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AdminContentAutomationTest.php | 25 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AdminContentAutomationTest.php | 27 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AdminContentAutomationTest.php | 29 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/AdminContentAutomationTest.php | 35 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/BtuCalculatorGovernanceTest.php | 19 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/BtuCalculatorGovernanceTest.php | 175 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/BtuCalculatorV2ActivationTest.php | 149 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/CalculatorEquipmentTypeRecommendationTest.php | 120 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/CalculatorEquipmentTypeRecommendationTest.php | 196 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/CompareScopeTest.php | 181 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/CompareScopeTest.php | 184 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/ProductComparisonPdfExportTest.php | 113 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/ProductComparisonPdfExportTest.php | 169 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 31 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 32 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 33 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 46 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 47 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Feature/PublicUrlContractTest.php | 48 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/Unit/ProductMarketingCapacityEligibilityTest.php | 57 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/browser/compare-scope.spec.ts | 81 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/browser/marketing-content.spec.ts | 207 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
| tests/browser/product-detail-numeric-formatting.spec.ts | 10 | TEST_FIXTURE | Fixture hoặc assertion regression; không phải nội dung mặc định của website. |
