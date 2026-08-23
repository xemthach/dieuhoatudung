<?php

namespace Tests\Feature;

use App\Support\DiagnosticDatabaseGuard;
use RuntimeException;
use Tests\TestCase;

class DiagnosticDatabaseGuardTest extends TestCase
{
    public function test_current_database_target_is_rejected_before_fixture_write(): void
    {
        $this->expectException(RuntimeException::class);
        app(DiagnosticDatabaseGuard::class)->assertWritableTarget('dieuhoa-tudung');
    }

    public function test_testing_memory_database_is_allowed_for_fixtures(): void
    {
        app(DiagnosticDatabaseGuard::class)->assertWritableTarget(':memory:');
        $this->assertTrue(true);
    }

    public function test_unapproved_local_database_name_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(DiagnosticDatabaseGuard::class)->assertWritableTarget('dieuhoa-tudung_copy');
    }
}
