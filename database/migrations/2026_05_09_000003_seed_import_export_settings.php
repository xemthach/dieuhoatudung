<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'group' => 'import_export',
                'key' => 'import_export.max_file_size_mb',
                'value' => '10',
                'type' => 'text',
            ],
            [
                'group' => 'import_export',
                'key' => 'import_export.allowed_formats',
                'value' => 'xlsx,csv,xml,json',
                'type' => 'text',
            ],
            [
                'group' => 'import_export',
                'key' => 'import_export.export_chunk_size',
                'value' => '1000',
                'type' => 'text',
            ],
            [
                'group' => 'import_export',
                'key' => 'import_export.import_chunk_size',
                'value' => '100',
                'type' => 'text',
            ],
            [
                'group' => 'import_export',
                'key' => 'import_export.keep_files_days',
                'value' => '30',
                'type' => 'text',
            ],
            [
                'group' => 'import_export',
                'key' => 'import_export.csv_utf8_bom',
                'value' => '1',
                'type' => 'text',
            ],
        ];

        foreach ($settings as $s) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $s['key']],
                $s
            );
        }
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('group', 'import_export')
            ->delete();
    }
};
