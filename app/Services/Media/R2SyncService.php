<?php

namespace App\Services\Media;

use App\Models\R2SyncJob;
use App\Models\R2SyncItem;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class R2SyncService
{
    public function __construct(
        private R2ConnectionService $r2Connection,
        private SettingService $settingService
    ) {}

    public function scanLocalMedia(R2SyncJob $job): void
    {
        $job->update(['status' => 'scanning', 'started_at' => $job->started_at ?? now()]);
        
        $localDisk = Storage::disk('public');
        $allFiles = $localDisk->allFiles();
        
        $count = 0;
        $skipped = 0;

        foreach ($allFiles as $file) {
            // skip hidden files
            if (Str::startsWith(basename($file), '.')) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'doc', 'docx'];
            
            if (!in_array($extension, $allowedExtensions)) {
                continue;
            }

            // Check if already exists in this job
            $exists = R2SyncItem::where('r2_sync_job_id', $job->id)
                ->where('local_path', $file)
                ->exists();

            if (!$exists) {
                R2SyncItem::create([
                    'r2_sync_job_id' => $job->id,
                    'local_path' => $file,
                    'file_size' => $localDisk->size($file),
                    'mime_type' => $localDisk->mimeType($file),
                    'status' => 'scanned',
                    'action' => 'upload',
                    'r2_key' => $this->buildR2Key($file),
                ]);
                $count++;
            } else {
                $skipped++;
            }
        }

        $job->update([
            'total_files' => $count,
            'skipped_files' => $skipped,
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    }

    /**
     * Prepare upload items from the latest completed scan job.
     */
    public function prepareUploadItems(R2SyncJob $uploadJob): void
    {
        $latestScan = R2SyncJob::where('mode', 'scan_only')
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (!$latestScan) {
            throw new Exception('Chua co scan job nao hoan thanh. Hay chay Scan Local Media truoc.');
        }

        // Clone scanned items to the upload job
        $scannedItems = R2SyncItem::where('r2_sync_job_id', $latestScan->id)
            ->where('status', 'scanned')
            ->get();

        $count = 0;
        foreach ($scannedItems as $item) {
            R2SyncItem::create([
                'r2_sync_job_id' => $uploadJob->id,
                'local_path' => $item->local_path,
                'file_size' => $item->file_size,
                'mime_type' => $item->mime_type,
                'status' => 'pending',
                'action' => 'upload',
                'r2_key' => $item->r2_key,
            ]);
            $count++;
        }

        $uploadJob->update(['total_files' => $count]);
    }

    public function buildR2Key(string $localPath): string
    {
        $defaultFolder = $this->settingService->get('r2_storage.r2_default_folder');
        if ($defaultFolder) {
            return trim($defaultFolder, '/') . '/' . trim($localPath, '/');
        }
        return trim($localPath, '/');
    }

    public function buildPublicUrl(string $r2Key): string
    {
        $publicUrl = $this->settingService->get('r2_storage.r2_public_url');
        return rtrim($publicUrl, '/') . '/' . ltrim($r2Key, '/');
    }
}
