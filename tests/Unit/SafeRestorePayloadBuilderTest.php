<?php

namespace Tests\Unit;

use App\Services\Backup\SafeRestorePayloadBuilder;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SafeRestorePayloadBuilderTest extends TestCase
{
    public function test_database_directives_are_transformed_without_replacing_string_content(): void
    {
        [$input, $output] = $this->files("CREATE DATABASE IF NOT EXISTS `dieuhoa-tudung`;\nUSE dieuhoa-tudung;\nINSERT INTO notes (body) VALUES ('dieuhoa-tudung');\nSELECT `dieuhoa-tudung`.`products`.* FROM `dieuhoa-tudung`.`products`;\n");

        $stats = app(SafeRestorePayloadBuilder::class)->build($input, $output, 'dieuhoatudung_phase1f_20260815_150000');
        $payload = file_get_contents($output);

        $this->assertSame(1, $stats['create_database_removed']);
        $this->assertSame(1, $stats['use_current_rewritten']);
        $this->assertSame(2, $stats['qualified_references_rewritten']);
        $this->assertStringContainsString("'dieuhoa-tudung'", $payload);
        $this->assertStringContainsString('USE `dieuhoatudung_phase1f_20260815_150000`;', $payload);
        $this->assertStringNotContainsString('USE dieuhoa-tudung;', $payload);
        $this->assertStringContainsString('`dieuhoatudung_phase1f_20260815_150000`.`products`', $payload);
    }

    public function test_drop_database_is_rejected(): void
    {
        [$input, $output] = $this->files("DROP DATABASE `dieuhoa-tudung`;\n");

        $this->expectException(RuntimeException::class);
        app(SafeRestorePayloadBuilder::class)->build($input, $output, 'dieuhoatudung_phase1f_20260815_150001');
    }

    public function test_current_database_and_invalid_target_are_rejected(): void
    {
        $builder = app(SafeRestorePayloadBuilder::class);
        $this->expectException(InvalidArgumentException::class);
        $builder->assertTarget('dieuhoa-tudung');
    }

    public function test_same_input_and_target_produce_same_normalized_payload(): void
    {
        [$input, $output1] = $this->files("USE `dieuhoa-tudung`;\nSELECT 1;\n");
        $output2 = $output1.'.second';
        $builder = app(SafeRestorePayloadBuilder::class);
        $builder->build($input, $output1, 'dieuhoatudung_phase1f_20260815_150002');
        $builder->build($input, $output2, 'dieuhoatudung_phase1f_20260815_150002');

        $this->assertSame(hash_file('sha256', $output1), hash_file('sha256', $output2));
    }

    public function test_dump_without_use_statement_gets_one_guarded_target_use(): void
    {
        [$input, $output] = $this->files("CREATE TABLE notes (id INT);\n");

        $stats = app(SafeRestorePayloadBuilder::class)->build($input, $output, 'dieuhoatudung_phase2a1_20260815_150003');
        $payload = file_get_contents($output);

        $this->assertSame(1, $stats['use_target_emitted']);
        $this->assertSame(0, $stats['use_current_rewritten']);
        $this->assertSame(1, substr_count($payload, 'USE `dieuhoatudung_phase2a1_20260815_150003`;'));
    }

    private function files(string $contents): array
    {
        $input = tempnam(sys_get_temp_dir(), 'restore-input-');
        $output = tempnam(sys_get_temp_dir(), 'restore-output-');
        file_put_contents($input, $contents);
        unlink($output);

        return [$input, $output];
    }
}
