<?php

namespace Tests\Feature;

use App\Support\EncodingGuard;
use Tests\TestCase;

class EncodingGuardTest extends TestCase
{
    public function test_repairs_common_utf8_mojibake_without_losing_vietnamese(): void
    {
        $original = 'Không có nhà cung cấp';
        $mojibake = @iconv('Windows-1252', 'UTF-8//IGNORE', $original);

        $this->assertIsString($mojibake);
        $this->assertNotSame($original, $mojibake);

        $this->assertSame($original, EncodingGuard::ensureUtf8($mojibake));
    }

    public function test_converts_windows_1258_bytes_to_utf8(): void
    {
        $legacy = @iconv('UTF-8', 'Windows-1258//IGNORE', 'Điều hòa âm trần');

        if (! is_string($legacy) || $legacy === '') {
            $this->markTestSkipped('Windows-1258 conversion is not available on this platform.');
        }

        $this->assertFalse(EncodingGuard::isValidUtf8($legacy));
        $this->assertSame('Điều hòa âm trần', EncodingGuard::ensureUtf8($legacy));
    }

    public function test_json_encode_keeps_unicode_readable(): void
    {
        $json = EncodingGuard::jsonEncode(['message' => 'Điều hòa âm trần']);

        $this->assertStringContainsString('Điều hòa âm trần', $json);
        $this->assertStringNotContainsString('\\u0110', $json);
    }

    public function test_source_audit_command_passes_with_intentional_pattern_files_exempted(): void
    {
        $this->artisan('encoding:source-audit', ['--path' => ['app/Support/EncodingGuard.php']])
            ->assertExitCode(0);
    }
}
