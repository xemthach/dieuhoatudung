<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_product_drafts')) {
            Schema::table('ai_product_drafts', function (Blueprint $table): void {
                foreach ([
                    'approval_status' => ['string', 40],
                    'approved_by' => ['unsignedBigInteger', null],
                    'approved_at' => ['timestamp', null],
                    'review_note' => ['text', null],
                    'approved_payload_hash' => ['string', 64],
                    'approved_technical_context_hash' => ['string', 64],
                    'approved_identity_json' => ['json', null],
                    'approved_fields_json' => ['json', null],
                    'applied_by' => ['unsignedBigInteger', null],
                    'applied_at' => ['timestamp', null],
                ] as $column => [$type, $length]) {
                    if (Schema::hasColumn('ai_product_drafts', $column)) {
                        continue;
                    }
                    $definition = $length === null ? $table->{$type}($column) : $table->{$type}($column, $length);
                    if ($column === 'approval_status') {
                        $definition->default('REVIEW_REQUIRED');
                    } elseif (in_array($column, ['approved_at', 'applied_at'], true)) {
                        $definition->nullable();
                    } elseif ($column !== 'approval_status') {
                        $definition->nullable();
                    }
                }
            });
        }

        Schema::create('ai_product_draft_apply_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->constrained('ai_product_drafts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('payload_hash', 64);
            $table->string('technical_context_hash', 64);
            $table->json('fields_applied')->nullable();
            $table->string('before_hash', 64);
            $table->string('after_hash', 64)->nullable();
            $table->string('result', 40);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'draft_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_draft_apply_audits');
        if (Schema::hasTable('ai_product_drafts')) {
            Schema::table('ai_product_drafts', function (Blueprint $table): void {
                foreach (['applied_at', 'applied_by', 'approved_fields_json', 'approved_identity_json', 'approved_technical_context_hash', 'approved_payload_hash', 'review_note', 'approved_at', 'approved_by', 'approval_status'] as $column) {
                    if (Schema::hasColumn('ai_product_drafts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
