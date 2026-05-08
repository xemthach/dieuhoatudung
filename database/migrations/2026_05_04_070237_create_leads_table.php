<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('need_type')->nullable();
            $table->foreignId('interested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('area')->nullable();
            $table->string('budget')->nullable();
            $table->text('message')->nullable();
            $table->string('source_page')->nullable();
            $table->string('status')->default('new'); // Enum LeadStatus
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
