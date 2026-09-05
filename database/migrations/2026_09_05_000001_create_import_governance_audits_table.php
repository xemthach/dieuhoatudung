<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_governance_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('policy_key', 120)->index();
            $table->string('old_mode', 20)->nullable();
            $table->string('new_mode', 20);
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->json('context_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('import_governance_audits'); }
};
