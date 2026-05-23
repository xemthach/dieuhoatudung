<?php

namespace Tests\Feature;

use App\Services\DataTransfer\ModuleRegistry;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    public function test_data_transfer_permissions_are_registered(): void
    {
        $registry = config('permissions');

        foreach (array_keys(ModuleRegistry::modules()) as $module) {
            $this->assertArrayHasKey($module, $registry);
            $this->assertArrayHasKey('import', $registry[$module]['permissions']);
            $this->assertArrayHasKey('export', $registry[$module]['permissions']);
        }
    }

    public function test_encoding_commands_join_like_clauses_with_spaces(): void
    {
        $auditContents = file_get_contents(app_path('Console/Commands/EncodingAuditCommand.php'));
        $repairContents = file_get_contents(app_path('Console/Commands/EncodingRepairCommand.php'));

        $this->assertStringContainsString("implode(' OR ', \$wheres)", $auditContents);
        $this->assertStringContainsString('BINARY `{$col}` LIKE ?', $auditContents);
        $this->assertStringNotContainsString("implode('OR ', \$wheres)", $auditContents);

        // The repair command moved away from LIKE-clause string building;
        // guard only against the old malformed join fragment.
        $this->assertStringNotContainsString("implode('OR ', \$wheres)", $repairContents);
    }

    public function test_quote_controller_does_not_log_full_mail_payloads(): void
    {
        $contents = file_get_contents(app_path('Http/Controllers/QuoteController.php'));

        $this->assertStringNotContainsString("'vars'     => \$mailVars", $contents);
        $this->assertStringNotContainsString("'vars' => \$mailVars", $contents);
        $this->assertStringContainsString('mail payload prepared', $contents);
    }
}
