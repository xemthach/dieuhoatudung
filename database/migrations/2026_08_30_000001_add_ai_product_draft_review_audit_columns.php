<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_product_drafts', function (Blueprint $table): void {
            $table->boolean('warning_override')->default(false)->after('review_note');
            $table->json('warnings_at_approval')->nullable()->after('warning_override');
            $table->string('approved_content_hash', 64)->nullable()->after('approved_payload_hash');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->unsignedBigInteger('discarded_by')->nullable()->after('rejected_at');
            $table->timestamp('discarded_at')->nullable()->after('discarded_by');

            $table->index('rejected_by');
            $table->index('discarded_by');
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_drafts', function (Blueprint $table): void {
            $table->dropIndex(['rejected_by']);
            $table->dropIndex(['discarded_by']);
            $table->dropColumn([
                'warning_override',
                'warnings_at_approval',
                'approved_content_hash',
                'rejected_by',
                'rejected_at',
                'discarded_by',
                'discarded_at',
            ]);
        });
    }
};
