<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class ProductRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_products_have_an_inverse_relationship_for_filament_attach_action(): void
    {
        $product = Product::factory()->create();
        $relatedProduct = Product::factory()->create();

        $product->relatedProducts()->attach($relatedProduct);

        $this->assertTrue($relatedProduct->relatedToProducts->contains($product));
    }

    public function test_related_products_relation_manager_uses_the_explicit_inverse_relationship(): void
    {
        $property = (new ReflectionClass(RelatedProductsRelationManager::class))
            ->getProperty('inverseRelationship');

        $this->assertSame('relatedToProducts', $property->getValue());
    }
}
