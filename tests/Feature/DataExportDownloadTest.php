<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\DataTransfer\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DataExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_checks_owner_contract_permission_expiry_and_integrity(): void
    {
        Product::factory()->create();
        $owner = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $exportPermission = Permission::findOrCreate('product.export', 'web');
        $restorePermission = Permission::findOrCreate('system_restore.run', 'web');
        $owner->givePermissionTo($exportPermission, $restorePermission);
        $other->givePermissionTo($exportPermission, $restorePermission);
        $job = app(DataExportService::class)->export('product', 'xlsx', userId: $owner->id, intent: 'system_backup');
        $url = route('admin.export.download', $job);

        try {
            $this->getJson($url)->assertUnauthorized();
            $this->actingAs($other)->get($url)->assertForbidden();
            $this->actingAs($owner)->get($url)->assertDownload($job->file_name);
            $owner->revokePermissionTo($restorePermission);
            $this->get($url)->assertForbidden();
            $owner->givePermissionTo($restorePermission);
            $job->update(['expires_at' => now()->subMinute()]);
            $this->get($url)->assertNotFound();
            $job->update(['expires_at' => now()->addMinute()]);
            Storage::disk('local')->put($job->file_path, 'changed');
            $this->get($url)->assertConflict();
        } finally {
            Storage::disk('local')->delete($job->file_path);
        }
    }
}
