<?php

namespace Tests\Feature;

use App\Enums\CatalogSectionType;
use App\Services\Product\ProductImportMapper;
use Tests\TestCase;

class CatalogSectionGovernanceTest extends TestCase
{
    public function test_product_list_capacity_is_marketing_capacity(): void
    {
        $result = app(ProductImportMapper::class)->mapCatalog(
            ['capacity_btu' => '42000'],
            CatalogSectionType::PRODUCT_LIST,
            ['marketing_capacity_btu']
        );

        $this->assertSame(42000, $result['attributes']['marketing_capacity_btu']);
        $this->assertArrayNotHasKey('btu', $result['attributes']);
        $this->assertSame([], $result['rejected']);
    }

    public function test_technical_appendix_capacity_is_technical_capacity(): void
    {
        $result = app(ProductImportMapper::class)->mapCatalog(
            ['capacity_btu' => '42650'],
            CatalogSectionType::TECHNICAL_APPENDIX,
            ['technical_capacity_btu']
        );

        $this->assertSame(42650, $result['attributes']['technical_capacity_btu']);
        $this->assertArrayNotHasKey('btu', $result['attributes']);
        $this->assertSame([], $result['rejected']);
    }

    public function test_combination_capacity_is_rejected_without_authority(): void
    {
        $result = app(ProductImportMapper::class)->mapCatalog(
            ['capacity_btu' => '42650'],
            CatalogSectionType::COMBINATION_TABLE,
            ['technical_capacity_btu']
        );

        $this->assertArrayHasKey('capacity_btu', $result['rejected']);
        $this->assertArrayNotHasKey('btu', $result['attributes']);
        $this->assertContains('capacity_without_authoritative_section: capacity_btu', $result['warnings']);
    }
}
