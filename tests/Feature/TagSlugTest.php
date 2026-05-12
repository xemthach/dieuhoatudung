<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_slug_is_generated_when_created_with_only_name(): void
    {
        $tag = Tag::create([
            'name' => 'Gud',
        ]);

        $this->assertSame('gud', $tag->slug);
    }

    public function test_generated_tag_slug_is_unique(): void
    {
        Tag::create([
            'name' => 'Gud',
        ]);

        $tag = Tag::create([
            'name' => 'Gud',
        ]);

        $this->assertSame('gud-1', $tag->slug);
    }

    public function test_tag_created_through_product_relation_gets_a_slug(): void
    {
        $product = Product::factory()->create();

        $tag = $product->tags()->create([
            'name' => 'Gud',
        ]);

        $this->assertSame('gud', $tag->slug);
        $this->assertTrue($product->tags()->whereKey($tag)->exists());
    }
}
