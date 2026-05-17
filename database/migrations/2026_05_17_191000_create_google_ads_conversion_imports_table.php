<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_conversion_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('event_name', 80)->default('submit_quote');
            $table->string('status', 30)->default('pending')->index();
            $table->string('failed_reason')->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('customer_id', 32)->nullable();
            $table->string('conversion_action_resource_name', 180)->nullable();
            $table->string('gclid', 255)->nullable()->index();
            $table->string('gbraid', 255)->nullable()->index();
            $table->string('wbraid', 255)->nullable()->index();
            $table->timestamp('conversion_date_time')->nullable();
            $table->decimal('conversion_value', 12, 2)->default(0);
            $table->string('currency_code', 3)->default('VND');
            $table->string('order_id', 120)->nullable()->unique();
            $table->json('user_identifiers_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('response_json')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('quote_requests', 'gclid')) {
                $table->string('gclid', 255)->nullable()->after('utm_content')->index();
            }

            if (! Schema::hasColumn('quote_requests', 'gbraid')) {
                $table->string('gbraid', 255)->nullable()->after('gclid')->index();
            }

            if (! Schema::hasColumn('quote_requests', 'wbraid')) {
                $table->string('wbraid', 255)->nullable()->after('gbraid')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            foreach (['wbraid', 'gbraid', 'gclid'] as $column) {
                if (Schema::hasColumn('quote_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('google_ads_conversion_imports');
    }
};
