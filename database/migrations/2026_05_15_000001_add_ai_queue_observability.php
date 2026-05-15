<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTechnicalColumns('ai_content_jobs');
        $this->addTechnicalColumns('ai_product_jobs');
        $this->addTechnicalColumns('ai_product_job_items');

        Schema::create('queue_worker_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('worker_name');
            $table->string('queue')->nullable();
            $table->string('hostname')->nullable();
            $table->unsignedInteger('pid')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status')->default('running');
            $table->timestamps();

            $table->unique(['worker_name', 'queue', 'hostname']);
            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('ai_technical_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('ai_job_type', 80)->nullable();
            $table->unsignedBigInteger('ai_job_id')->nullable();
            $table->string('level', 20)->default('info');
            $table->string('event', 80);
            $table->text('message')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->index(['module', 'ai_job_type', 'ai_job_id']);
            $table->index(['event', 'level']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_technical_logs');
        Schema::dropIfExists('queue_worker_heartbeats');

        $this->dropTechnicalColumns('ai_product_job_items');
        $this->dropTechnicalColumns('ai_product_jobs');
        $this->dropTechnicalColumns('ai_content_jobs');
    }

    private function addTechnicalColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            if (! Schema::hasColumn($table->getTable(), 'module')) {
                $table->string('module', 80)->nullable()->after('status');
            }
            if (! Schema::hasColumn($table->getTable(), 'provider')) {
                $table->string('provider')->nullable()->after('module');
            }
            if (! Schema::hasColumn($table->getTable(), 'model')) {
                $table->string('model')->nullable()->after('provider');
            }
            if (! Schema::hasColumn($table->getTable(), 'queue_name')) {
                $table->string('queue_name', 80)->nullable()->after('model');
            }
            if (! Schema::hasColumn($table->getTable(), 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('queue_name');
            }
            if (! Schema::hasColumn($table->getTable(), 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0)->after('attempts');
            }
            if (! Schema::hasColumn($table->getTable(), 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('retry_count');
            }
            if (! Schema::hasColumn($table->getTable(), 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn($table->getTable(), 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('finished_at');
            }
            if (! Schema::hasColumn($table->getTable(), 'last_error_code')) {
                $table->string('last_error_code', 80)->nullable()->after('duration_ms');
            }
            if (! Schema::hasColumn($table->getTable(), 'last_error_message')) {
                $table->text('last_error_message')->nullable()->after('last_error_code');
            }
            if (! Schema::hasColumn($table->getTable(), 'failed_reason')) {
                $table->string('failed_reason', 80)->nullable()->after('last_error_message');
            }
            if (! Schema::hasColumn($table->getTable(), 'exception_class')) {
                $table->string('exception_class')->nullable()->after('failed_reason');
            }
            if (! Schema::hasColumn($table->getTable(), 'exception_file')) {
                $table->string('exception_file')->nullable()->after('exception_class');
            }
            if (! Schema::hasColumn($table->getTable(), 'exception_line')) {
                $table->unsignedInteger('exception_line')->nullable()->after('exception_file');
            }
            if (! Schema::hasColumn($table->getTable(), 'stack_trace')) {
                $table->longText('stack_trace')->nullable()->after('exception_line');
            }
            if (! Schema::hasColumn($table->getTable(), 'raw_response_summary')) {
                $table->longText('raw_response_summary')->nullable()->after('stack_trace');
            }
            if (! Schema::hasColumn($table->getTable(), 'validation_errors')) {
                $table->json('validation_errors')->nullable()->after('raw_response_summary');
            }
        });
    }

    private function dropTechnicalColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $columns = [
                'module',
                'provider',
                'model',
                'queue_name',
                'attempts',
                'retry_count',
                'started_at',
                'finished_at',
                'duration_ms',
                'last_error_code',
                'last_error_message',
                'failed_reason',
                'exception_class',
                'exception_file',
                'exception_line',
                'stack_trace',
                'raw_response_summary',
                'validation_errors',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn($table->getTable(), $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
