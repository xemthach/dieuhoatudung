<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CoolingTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_canonical_cooling_types_are_accepted(): void
    {
        $oneWay = Product::factory()->create(['cooling_type' => '1_chieu']);
        $twoWay = Product::factory()->create(['cooling_type' => '2_chieu']);

        $this->assertSame([$oneWay->id], $this->ids('1_chieu'));
        $this->assertSame([$twoWay->id], $this->ids('2_chieu'));
        $this->assertEqualsCanonicalizing([$oneWay->id, $twoWay->id], $this->ids('invalid'));
    }

    private function ids(string $value): array
    {
        return app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['cooling_type' => $value]),
        )->pluck('id')->all();
    }
}
