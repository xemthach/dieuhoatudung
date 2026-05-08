<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('e.g. lead_admin_notification');
            $table->string('name')->comment('Human-readable name for admin');
            $table->string('subject')->comment('Email subject, supports {{variables}}');
            $table->longText('body_html')->comment('HTML body, supports {{variables}}');
            $table->text('body_text')->nullable()->comment('Plain text fallback');
            $table->json('variables_json')->nullable()->comment('Documented variables: [{name, description, example}]');
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('vi');
            $table->timestamp('reset_at')->nullable()->comment('Last reset to default timestamp');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
