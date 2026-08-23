<?php

namespace App\Console\Commands;

use App\Models\AiProductJob;
use App\Services\AI\AIJobStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AIQueueDisposition extends Command
{
    protected $signature = 'ai:queue-disposition
        {--apply : Apply the exact reviewed disposition}
        {--approved : Explicit operator approval}
        {--ids=1,2,3,4,5,6,7,22,23 : Exact legacy AI job IDs}';

    protected $description = 'Disposition only the reviewed legacy AI job rows; never deletes queue evidence.';

    public function handle(): int
    {
        $ids = collect(explode(',', (string) $this->option('ids')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->values();
        $jobs = AiProductJob::query()->whereKey($ids->all())->orderBy('id')->get();

        $this->table(['ID', 'Legacy', 'Canonical', 'Disposition'], $jobs->map(function (AiProductJob $job): array {
            $disposition = $job->id <= 7 ? 'LEGACY_CANCELLED' : 'ORPHANED_BLOCKED';
            return [$job->id, $job->status, $job->canonical_status, $disposition];
        })->all());

        if (! $this->option('apply')) {
            $this->info('DRY-RUN: no queue or AI rows changed.');
            return self::SUCCESS;
        }
        if (! $this->option('approved')) {
            $this->error('Write requires --approved.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($jobs): void {
            foreach ($jobs as $job) {
                $target = $job->id <= 7 ? AIJobStateMachine::CANCELLED : AIJobStateMachine::BLOCKED;
                AIJobStateMachine::transition($job, $target, $job->id <= 7 ? 'LEGACY_CANCELLED' : 'orphaned_legacy_worker');
                $job->forceFill([
                    'status' => $job->id <= 7 ? 'cancelled' : 'blocked',
                    'status_reason' => $job->id <= 7 ? 'LEGACY_CANCELLED' : 'orphaned_legacy_worker',
                    'finished_at' => now(),
                ])->saveQuietly();
            }
        });

        $this->info('Applied exact row-level disposition; evidence rows retained.');
        return self::SUCCESS;
    }
}
