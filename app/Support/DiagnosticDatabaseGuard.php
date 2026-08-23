<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DiagnosticDatabaseGuard
{
    public const CURRENT_DATABASE = 'dieuhoa-tudung';

    /**
     * Allow diagnostic fixture writes only on an isolated test database or an
     * explicitly named isolated clone. The current database is never a valid
     * writable target for this helper.
     */
    public function assertWritableTarget(?string $target = null): void
    {
        $target ??= (string) DB::getDatabaseName();
        $environment = (string) app()->environment();

        if ($target === self::CURRENT_DATABASE || $environment === 'production') {
            throw new RuntimeException('Diagnostic fixture writes are blocked on the current/production database.');
        }

        $isMemoryTest = $environment === 'testing' && ($target === ':memory:' || $target === '' || str_ends_with($target, '.sqlite'));
        $isIsolatedClone = (bool) preg_match('/^dieuhoatudung_phase2b2_[a-z0-9_]+$/i', $target);

        if (! $isMemoryTest && ! $isIsolatedClone) {
            throw new RuntimeException('Diagnostic fixture target must be an isolated test database or phase2b2 clone.');
        }
    }
}
