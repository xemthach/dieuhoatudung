<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\PostCategory;
use App\Models\Author;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Faq;
use App\Models\LandingSection;
use App\Enums\LandingSectionType;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Vài brand
        $brands = [
            Brand::factory()->create(['name' => 'Daikin', 'slug' => 'daikin']),
            Brand::factory()->create(['name' => 'LG', 'slug' => 'lg']),
            Brand::factory()->create(['name' => 'Panasonic', 'slug' => 'panasonic']),
            Brand::factory()->create(['name' => 'Gree', 'slug' => 'gree']),
        ];

        // Vài category
        $mainCat = ProductCategory::factory()->create(['name' => 'Điều hòa tủ đứng', 'slug' => 'dieu-hoa-tu-dung']);
        $subCats = [
            ProductCategory::factory()->create(['name' => 'Inverter', 'slug' => 'dieu-hoa-tu-dung-inverter', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => 'Non-Inverter', 'slug' => 'dieu-hoa-tu-dung-non-inverter', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => '1 Chiều', 'slug' => 'dieu-hoa-tu-dung-1-chieu', 'parent_id' => $mainCat->id]),
            ProductCategory::factory()->create(['name' => '2 Chiều', 'slug' => 'dieu-hoa-tu-dung-2-chieu', 'parent_id' => $mainCat->id]),
        ];

        // Vài product
        foreach ($brands as $brand) {
            Product::factory(3)->create([
                'brand_id' => $brand->id,
                'product_category_id' => $mainCat->id,
            ]);
        }

        // Vài post category & author
        $postCat = PostCategory::factory()->create(['name' => 'Kiến thức', 'slug' => 'kien-thuc']);
        $author = Author::factory()->create(['name' => 'Admin', 'slug' => 'admin']);

        // Vài post
        Post::factory(5)->create([
            'post_category_id' => $postCat->id,
            'author_id' => $author->id,
        ]);

        // Vài FAQ
        Faq::factory(5)->create();

        // Vài landing section (hero, featured, faq)
        LandingSection::factory()->create([
            'page_key' => 'home',
            'section_type' => LandingSectionType::Hero,
            'title' => 'Điều Hòa Tủ Đứng Chính Hãng',
            'sort_order' => 1,
        ]);
        LandingSection::factory()->create([
            'page_key' => 'home',
            'section_type' => LandingSectionType::FeaturedProducts,
            'title' => 'Sản Phẩm Nổi Bật',
            'sort_order' => 2,
        ]);
        LandingSection::factory()->create([
            'page_key' => 'home',
            'section_type' => LandingSectionType::Faq,
            'title' => 'Câu Hỏi Thường Gặp',
            'sort_order' => 3,
        ]);
    }
}
