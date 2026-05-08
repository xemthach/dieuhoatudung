<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncR2MediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(public int $jobId)
    {
    }

    public function handle(\App\Services\Media\R2ConnectionService $r2Conn, \App\Services\Settings\SettingService $settingService): void
    {
        $job = \App\Models\R2SyncJob::find($this->jobId);
        if (!$job || $job->status === 'cancelled') return;

        $disk = $r2Conn->getDisk();
        if (!$disk) {
            $job->update(['status' => 'failed', 'error_message' => 'Lỗi kết nối R2 Disk']);
            return;
        }

        $job->update(['status' => 'syncing', 'started_at' => now()]);

        $batchSize = (int) $settingService->get('r2_storage.r2_sync_batch_size', 50);
        $deleteLocal = (bool) $settingService->get('r2_storage.r2_sync_delete_local_after_upload', false);

        $items = \App\Models\R2SyncItem::where('r2_sync_job_id', $job->id)
            ->where('status', 'pending')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $localPath = $item->local_path;
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($localPath)) {
                    $item->update(['status' => 'failed', 'error_message' => 'File không tồn tại ở local']);
                    $failed++;
                    continue;
                }

                $stream = \Illuminate\Support\Facades\Storage::disk('public')->readStream($localPath);
                $disk->writeStream($item->r2_key, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($deleteLocal) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($localPath);
                }

                \App\Models\MediaFile::updateOrCreate(
                    ['path' => $localPath],
                    [
                        'disk' => 'r2',
                        'is_synced_to_r2' => true,
                        'r2_key' => $item->r2_key,
                        'public_url' => \Illuminate\Support\Facades\Storage::disk('r2')->url($item->r2_key),
                    ]
                );

                $item->update(['status' => 'uploaded']);
                $synced++;
            } catch (\Exception $e) {
                $item->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $failed++;
            }
        }

        $job->update([
            'status' => 'completed',
            'synced_files' => $job->synced_files + $synced,
            'failed_files' => $job->failed_files + $failed,
            'finished_at' => now(),
        ]);
    }
}
