<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_link_suggestions', function (Blueprint $table) {
            $table->id();

            $table->string('source_type');   // App\Models\Post, App\Models\Product, etc.
            $table->unsignedBigInteger('source_id');

            $table->string('target_type');
            $table->unsignedBigInteger('target_id');

            $table->string('anchor_text')->nullable();
            $table->text('reason')->nullable();
            $table->integer('score')->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();

            // Prevent duplicate pairs
            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'unique_link_pair');
            $table->index(['source_type', 'source_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_link_suggestions');
    }
};
