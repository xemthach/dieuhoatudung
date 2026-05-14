<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\PostCategory;
use App\Models\Author;
use App\Models\Post;
use App\Models\Faq;
use App\Models\LandingSection;
use App\Enums\LandingSectionType;
use Illuminate\Database\Seeder;

/**
 * Demo data seeder — creates sample brands, products, posts, FAQs, landing sections.
 *
 * NOT for production unless explicitly called:
 *   php artisan db:seed --class=DemoDataSeeder --force
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding demo data...');

        // ── Brands ────────────────────────────────────────────────────
        $brands = [
            Brand::factory()->create(['name' => 'Daikin', 'slug' => 'daikin']),
            Brand::factory()->create(['name' => 'LG', 'slug' => 'lg']),
            Brand::factory()->create(['name' => 'Panasonic', 'slug' => 'panasonic']),
            Brand::factory()->create(['name' => 'Gree', 'slug' => 'gree']),
        ];

        // ── Product Categories ────────────────────────────────────────
        $mainCat = ProductCategory::factory()->create(['name' => 'Điều hòa tủ đứng', 'slug' => 'dieu-hoa-tu-dung']);
        $subCats = [
            ProductCategory::factory()->create(['name' => 'Inverter', 'slug' => 'dieu-hoa-tu-dung-inverter', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => 'Non-Inverter', 'slug' => 'dieu-hoa-tu-dung-non-inverter', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => '1 Chieu', 'slug' => 'dieu-hoa-tu-dung-1-chieu', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => '2 Chieu', 'slug' => 'dieu-hoa-tu-dung-2-chieu', 'parent_id' => $mainCat->id]),
        ];

        // ── Products (3 per brand) ────────────────────────────────────
        foreach ($brands as $brand) {
            Product::factory(3)->create([
                'brand_id' => $brand->id,
                'product_category_id' => $mainCat->id,
            ]);
        }

        // ── Posts ──────────────────────────────────────────────────────
        $postCat = PostCategory::factory()->create(['name' => 'Kien thuc', 'slug' => 'kien-thuc']);
        $author  = Author::factory()->create(['name' => 'Admin', 'slug' => 'admin']);

        Post::factory(5)->create([
            'post_category_id' => $postCat->id,
            'author_id' => $author->id,
        ]);

        // ── FAQs ──────────────────────────────────────────────────────
        Faq::factory(5)->create();

        // ── Landing Sections ──────────────────────────────────────────
        LandingSection::factory()->create([
            'page_key'     => 'home',
            'section_type' => LandingSectionType::Hero,
            'title'        => 'Dieu Hoa Tu Dung Chinh Hang',
            'sort_order'   => 1,
        ]);
        LandingSection::factory()->create([
            'page_key'     => 'home',
            'section_type' => LandingSectionType::FeaturedProducts,
            'title'        => 'San Pham Noi Bat',
            'sort_order'   => 2,
        ]);
        LandingSection::factory()->create([
            'page_key'     => 'home',
            'section_type' => LandingSectionType::Faq,
            'title'        => 'Cau Hoi Thuong Gap',
            'sort_order'   => 3,
        ]);

        $this->command->info('Demo data seeded: 4 brands, 12 products, 5 posts, 5 FAQs, 3 landing sections.');
    }
}
