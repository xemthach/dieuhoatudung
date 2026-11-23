<?php

namespace App\Jobs;

use App\Models\DataImportJob;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ExecuteDataImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(public readonly int $importId) { $this->onQueue('default'); }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('data-import:'.$this->importId))->releaseAfter(30)->expireAfter(960)];
    }

    public function handle(DataImportService $service): void
    {
        $job = DataImportJob::findOrFail($this->importId);
        if (in_array($job->status, ['queued', 'importing'], true)) $service->confirmImport($job, true);
    }

    public function failed(?\Throwable $error): void
    {
        DataImportJob::whereKey($this->importId)->whereIn('status', ['queued', 'importing'])->update([
            'status'=>'failed', 'finished_at'=>now(),
            'error_report_json'=>json_encode([['row'=>0, 'errors'=>['Worker chưa hoàn tất import. Kiểm tra tiến độ và receipt trước khi chạy lại.']]], JSON_THROW_ON_ERROR),
        ]);
    }
}
