<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->unique()->after('id');
            $table->string('entry_context', 20)->default('direct')->after('intent_score');
            $table->json('calculator_context')->nullable()->after('selected_product_snapshot');
            $table->json('provided_fields')->nullable()->after('calculator_context');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn(['submission_token', 'entry_context', 'calculator_context', 'provided_fields']);
        });
    }
};
