<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('taxonomy_status', 20)->nullable()->index()->after('product_category_id');
            $table->string('taxonomy_source', 80)->nullable()->after('taxonomy_status');
            $table->string('taxonomy_ledger_digest', 64)->nullable()->after('taxonomy_source');
            $table->json('taxonomy_evidence_json')->nullable()->after('taxonomy_ledger_digest');
            $table->timestamp('taxonomy_verified_at')->nullable()->after('taxonomy_evidence_json');

            $table->string('marketing_capacity_status', 20)->nullable()->index()->after('marketing_capacity_btu');
            $table->string('marketing_capacity_source', 80)->nullable()->after('marketing_capacity_status');
            $table->string('marketing_capacity_mapping_digest', 64)->nullable()->after('marketing_capacity_source');
            $table->json('marketing_capacity_evidence_json')->nullable()->after('marketing_capacity_mapping_digest');
            $table->timestamp('marketing_capacity_verified_at')->nullable()->after('marketing_capacity_evidence_json');
        });

        Schema::create('product_data_governance_events', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 40)->index();
            $table->string('action', 30)->index();
            $table->string('scope_type', 30)->default('LEDGER');
            $table->uuid('batch_uuid')->nullable()->index();
            $table->string('ledger_digest', 64)->index();
            $table->string('source_digest', 64)->nullable()->index();
            $table->string('mapping_digest', 64)->nullable()->index();
            $table->json('product_ids_json');
            $table->json('descriptor_json')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('reason');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_data_governance_events');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['taxonomy_status']);
            $table->dropIndex(['marketing_capacity_status']);
            $table->dropColumn([
                'taxonomy_status', 'taxonomy_source', 'taxonomy_ledger_digest',
                'taxonomy_evidence_json', 'taxonomy_verified_at',
                'marketing_capacity_status', 'marketing_capacity_source',
                'marketing_capacity_mapping_digest', 'marketing_capacity_evidence_json',
                'marketing_capacity_verified_at',
            ]);
        });
    }
};
