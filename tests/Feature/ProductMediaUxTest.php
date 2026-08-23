<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Media\MediaDiskService;
use App\Services\Media\ProductMediaResolver;
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

    public function test_valid_main_and_gallery_media_are_resolved_once_as_gallery_objects(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/products/main.webp', 'main');
        Storage::disk('public')->put('media/products/gallery/side.webp', 'side');

        $product = Product::factory()->create([
            'main_image' => 'media/products/main.webp',
            'gallery_json' => [
                'media/products/main.webp',
                'media/products/gallery/side.webp',
            ],
        ]);

        $this->assertCount(2, $product->gallery_images);
        $this->assertSame($product->main_image_url, $product->gallery_images[0]['url']);
        $this->assertFalse($product->gallery_images[0]['is_fallback']);
        $this->assertSame(
            ['media/products/main.webp', 'media/products/gallery/side.webp'],
            collect($product->gallery_images)->pluck('path')->all(),
        );
    }

    public function test_missing_main_does_not_add_a_fake_thumbnail_when_gallery_media_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/products/gallery/real.webp', 'real');

        $product = Product::factory()->create([
            'main_image' => 'media/products/missing.webp',
            'gallery_json' => ['media/products/gallery/real.webp'],
        ]);

        $this->assertCount(1, $product->gallery_images);
        $this->assertSame('media/products/gallery/real.webp', $product->gallery_images[0]['path']);
        $this->assertSame($product->gallery_images[0]['url'], $product->main_image_url);
        $this->assertFalse($product->gallery_images[0]['is_fallback']);
    }

    public function test_cdn_paths_use_the_same_product_resolver_without_duplicate_main_image(): void
    {
        $media = $this->mock(MediaDiskService::class);
        $media->shouldReceive('getPublicUrl')
            ->andReturnUsing(fn (?string $path): ?string => $path
                ? 'https://cdn.example.test/'.ltrim($path, '/')
                : null);

        $product = Product::factory()->make([
            'main_image' => 'media/products/main.webp',
            'gallery_json' => ['media/products/main.webp', 'media/products/gallery/side.webp'],
        ]);
        $gallery = app(ProductMediaResolver::class)->gallery($product);

        $this->assertCount(2, $gallery);
        $this->assertSame('https://cdn.example.test/media/products/main.webp', $gallery[0]['url']);
        $this->assertSame($gallery[0]['url'], $product->card_image_url);
        $this->assertSame($gallery[0]['url'], $product->compare_image_url);
    }
}
