<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('payload_hash', 64);
            $table->string('result', 20);
            $table->timestamp('created_at');
            $table->unique(['data_import_job_id', 'row_number']);
        });
    }

    public function down(): void { Schema::dropIfExists('data_import_receipts'); }
};
