<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_commitment_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quote_commitment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_commitment_block_id')
                  ->constrained('quote_commitment_blocks')
                  ->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('icon_type', 20)->default('heroicon');
            $table->string('icon_name', 80)->nullable();
            $table->string('icon_image', 500)->nullable();
            $table->text('icon_svg')->nullable();
            $table->string('icon_color', 30)->default('text-green-500');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['quote_commitment_block_id', 'is_active', 'sort_order'], 'qci_block_active_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_commitment_items');
        Schema::dropIfExists('quote_commitment_blocks');
    }
};
