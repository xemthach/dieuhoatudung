<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\AI\DraftOnlyWriteGuard;
use Tests\TestCase;

class DraftOnlyWriteGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DraftOnlyWriteGuard::isActive()) DraftOnlyWriteGuard::end();
        DraftOnlyWriteGuard::resetAttempts();
        parent::tearDown();
    }

    public function test_strict_draft_only_blocks_product_insert_before_sql(): void
    {
        DraftOnlyWriteGuard::begin('unit_test');
        $product = new Product(['name' => 'blocked pilot product']);

        self::assertFalse($product->save());
        self::assertSame(['name'], DraftOnlyWriteGuard::attempts()[0]['dirty']);
    }
}
