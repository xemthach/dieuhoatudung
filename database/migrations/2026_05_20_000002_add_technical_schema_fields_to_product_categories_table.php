<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('technical_schema_status', 30)->default('missing')->after('content');
            $table->string('technical_schema_version')->nullable()->after('technical_schema_status');
            $table->json('technical_schema_json')->nullable()->after('technical_schema_version');
            $table->timestamp('technical_schema_locked_at')->nullable()->after('technical_schema_json');
            $table->longText('technical_schema_notes')->nullable()->after('technical_schema_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'technical_schema_status',
                'technical_schema_version',
                'technical_schema_json',
                'technical_schema_locked_at',
                'technical_schema_notes',
            ]);
        });
    }
};
