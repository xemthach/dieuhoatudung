<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained()->cascadeOnDelete();
            $table->morphs('faqable');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['faq_id', 'faqable_id', 'faqable_type'], 'faqables_unique_pivot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqables');
    }
};
