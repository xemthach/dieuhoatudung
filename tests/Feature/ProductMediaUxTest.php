<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_product_media_uses_the_public_placeholder(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create([
            'main_image' => 'media/products/not-present.webp',
            'gallery_json' => [],
        ]);

        $this->assertSame(asset('images/placeholders/product-default.jpg'), $product->main_image_url);
        $this->assertSame(asset('images/placeholders/product-default.jpg'), $product->card_image_url);
        $this->assertSame(asset('images/placeholders/product-default.jpg'), $product->gallery_image_urls[0]);
    }
}
