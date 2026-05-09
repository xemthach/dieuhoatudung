<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Models\R2SyncItem;
use App\Models\R2SyncJob;
use App\Services\Media\R2ConnectionService;
use App\Services\Media\R2SyncService;
use App\Services\Settings\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncR2MediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(public int $jobId)
    {
    }

    public function handle(R2ConnectionService $r2Conn, R2SyncService $r2Sync, SettingService $settingService): void
    {
        $job = R2SyncJob::find($this->jobId);
        if (!$job || $job->status === 'cancelled') return;

        $disk = $r2Conn->getDisk();
        if (!$disk) {
            $job->update(['status' => 'failed', 'error_message' => 'Lỗi kết nối R2 Disk']);
            return;
        }

        $job->update(['status' => 'syncing', 'started_at' => now()]);

        $deleteLocal = (bool) $settingService->get('r2_storage.r2_sync_delete_local_after_upload', false);

        $items = R2SyncItem::where('r2_sync_job_id', $job->id)
            ->where('status', 'pending')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($items as $item) {
            // Respect cancellation mid-job
            if ($job->fresh()->status === 'cancelled') {
                Log::info("[SyncR2MediaJob] Job #{$job->id} cancelled mid-sync.");
                break;
            }

            try {
                $localPath = $item->local_path;
                if (!Storage::disk('public')->exists($localPath)) {
                    $item->update(['status' => 'failed', 'error_message' => 'File không tồn tại ở local']);
                    $failed++;
                    continue;
                }

                $stream = Storage::disk('public')->readStream($localPath);
                $disk->writeStream($item->r2_key, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Verify the object actually exists on R2 after upload
                if (!$disk->fileExists($item->r2_key)) {
                    $item->update(['status' => 'failed', 'error_message' => 'Upload thành công nhưng xác minh R2 thất bại — object không tồn tại']);
                    $failed++;
                    Log::warning("[SyncR2MediaJob] R2 verify failed for: {$item->r2_key}");
                    continue;
                }

                if ($deleteLocal) {
                    Storage::disk('public')->delete($localPath);
                }

                // Build public URL from R2SyncService (uses DB settings, not Storage::disk config)
                $publicUrl = $r2Sync->buildPublicUrl($item->r2_key);

                MediaFile::updateOrCreate(
                    ['path' => $localPath],
                    [
                        'disk' => 'r2',
                        'is_synced_to_r2' => true,
                        'r2_key' => $item->r2_key,
                        'public_url' => $publicUrl,
                    ]
                );

                $item->update(['status' => 'uploaded']);
                $synced++;

                Log::info("[SyncR2MediaJob] Uploaded: {$localPath} → {$item->r2_key}");
            } catch (\Exception $e) {
                $item->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $failed++;
                Log::error("[SyncR2MediaJob] Failed: {$item->local_path} — {$e->getMessage()}");
            }
        }

        $finalStatus = match (true) {
            $failed > 0 && $synced > 0 => 'completed_with_errors',
            $failed > 0 && $synced === 0 => 'failed',
            default => 'completed',
        };

        $job->update([
            'status' => $finalStatus,
            'synced_files' => $job->synced_files + $synced,
            'failed_files' => $job->failed_files + $failed,
            'finished_at' => now(),
            'error_message' => $failed > 0 ? "Upload thành công: {$synced}, Thất bại: {$failed}" : null,
        ]);

        Log::info("[SyncR2MediaJob] Job #{$job->id} finished: synced={$synced}, failed={$failed}, status={$finalStatus}");
    }
}
