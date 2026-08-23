<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PilotRuntimeGuard
{
    public static function assert(array $config): void
    {
        if (! ($config['pilot_runtime'] ?? false)) return;

        $connection = (string) config('database.default');
        $database = (string) DB::connection()->getDatabaseName();
        if ($connection !== 'mysql' || ! preg_match('/^dieuhoatudung_phase2f_pilot_[0-9]{8}_[0-9]{6}$/', $database) || $database === 'dieuhoa-tudung') {
            throw new RuntimeException('PHASE2F1_PILOT_DB_GUARD_FAILED');
        }
    }
}
