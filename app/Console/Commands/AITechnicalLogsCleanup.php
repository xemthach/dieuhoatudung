<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AITechnicalLogsCleanup extends Command
{
    protected $signature = 'ai:technical-logs-cleanup {--days=30 : Keep logs newer than this number of days}';

    protected $description = 'Delete old AI technical logs.';

    public function handle(): int
    {
        if (! Schema::hasTable('ai_technical_logs')) {
            $this->warn('ai_technical_logs table does not exist.');

            return self::SUCCESS;
        }

        $deleted = DB::table('ai_technical_logs')
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('days'))))
            ->delete();

        $this->info("Deleted {$deleted} old AI technical logs.");

        return self::SUCCESS;
    }
}
