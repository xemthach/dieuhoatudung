<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StrictBooleanFilterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('acceptedBooleanProvider')]
    public function test_inverter_accepts_only_explicit_boolean_values(string $input, bool $expected): void
    {
        $matching = Product::factory()->create(['is_active' => true, 'inverter' => $expected]);
        Product::factory()->create(['is_active' => true, 'inverter' => ! $expected]);

        $this->assertSame([$matching->id], $this->idsFor($input));
    }

    /** @return array<string, array{string, bool}> */
    public static function acceptedBooleanProvider(): array
    {
        return [
            'integer true' => ['1', true],
            'word true' => ['true', true],
            'integer false' => ['0', false],
            'word false' => ['false', false],
        ];
    }

    #[DataProvider('invalidBooleanProvider')]
    public function test_invalid_boolean_values_are_ignored_instead_of_becoming_false(string $input): void
    {
        $first = Product::factory()->create(['is_active' => true, 'inverter' => true]);
        $second = Product::factory()->create(['is_active' => true, 'inverter' => false]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->idsFor($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidBooleanProvider(): array
    {
        return [
            'arbitrary text' => ['abc'],
            'integer two' => ['2'],
            'negative integer' => ['-1'],
            'empty' => [''],
        ];
    }

    /** @return list<int> */
    private function idsFor(string $input): array
    {
        $query = Product::query()->where('is_active', true);
        app(ProductFilterService::class)->apply(
            $query,
            Request::create('/san-pham', 'GET', ['inverter' => $input]),
        );

        return $query->pluck('id')->all();
    }
}
