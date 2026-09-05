<?php

namespace Tests\Unit;

use App\Models\DataImportJob;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataImportResultStateTest extends TestCase
{
    #[DataProvider('terminalStates')]
    public function test_terminal_state_is_derived_from_execution_counts(int $total, int $success, int $failed, string $expected): void
    {
        $this->assertSame($expected, DataImportJob::terminalStatusFor($total, $success, $failed));
    }

    public static function terminalStates(): array
    {
        return [
            'all succeeded' => [81, 81, 0, 'completed'],
            'mixed result' => [81, 80, 1, 'completed_with_errors'],
            'all failed' => [81, 0, 81, 'failed'],
            'empty input' => [0, 0, 0, 'empty'],
        ];
    }
}
