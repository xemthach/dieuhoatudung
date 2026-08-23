<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class AiBulkRuntimeMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_failed_partial_ddl_can_resume_without_recreating_existing_tables(): void
    {
        foreach ([
            'ai_bulk_apply_snapshots',
            'ai_bulk_field_operations',
            'ai_bulk_runtime_leases',
            'ai_bulk_runtime_slots',
            'ai_bulk_runtime_batches',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('ai_bulk_runtime_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_uuid')->unique();
        });
        Schema::create('ai_bulk_runtime_slots', function (Blueprint $table): void {
            $table->id();
        });

        DB::table('ai_bulk_runtime_batches')->insert([
            'id' => 99,
            'batch_uuid' => 'partial-ddl-evidence',
        ]);

        $migration = require database_path('migrations/2026_08_16_030000_create_ai_bulk_runtime_executors.php');
        $migration->up();

        $this->assertDatabaseHas('ai_bulk_runtime_batches', [
            'id' => 99,
            'batch_uuid' => 'partial-ddl-evidence',
        ]);
        $this->assertTrue(Schema::hasTable('ai_bulk_runtime_slots'));
        $this->assertTrue(Schema::hasTable('ai_bulk_runtime_leases'));
        $this->assertTrue(Schema::hasTable('ai_bulk_field_operations'));
        $this->assertTrue(Schema::hasTable('ai_bulk_apply_snapshots'));
        $this->assertSame('datetime', Schema::getColumnType('ai_bulk_runtime_leases', 'claimed_at'));
        $this->assertSame('datetime', Schema::getColumnType('ai_bulk_runtime_leases', 'expires_at'));
    }
}
