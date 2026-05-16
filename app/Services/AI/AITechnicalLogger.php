<?php

namespace App\Services\AI;

use App\Models\AiTechnicalLog;
use App\Support\EncodingGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AITechnicalLogger
{
    public function event(string $module, string $event, string $message = '', array $context = [], ?Model $job = null, string $level = 'info'): void
    {
        $context = $this->sanitizeContext($context);

        Log::channel('ai-jobs')->log($level, $message !== '' ? $message : $event, array_merge([
            'module' => $module,
            'event' => $event,
            'ai_job_type' => $job ? $job::class : null,
            'ai_job_id' => $job?->getKey(),
        ], $context));

        if (! Schema::hasTable('ai_technical_logs')) {
            return;
        }

        AiTechnicalLog::create([
            'module' => $module,
            'ai_job_type' => $job ? class_basename($job) : null,
            'ai_job_id' => $job?->getKey(),
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context_json' => $context,
        ]);
    }

    public function exception(string $module, Throwable $exception, ?Model $job = null, array $context = [], string $event = 'job_failed'): array
    {
        $technical = $this->technicalError($exception, $context);

        $this->event(
            module: $module,
            event: $event,
            message: $exception->getMessage(),
            context: $technical,
            job: $job,
            level: 'error',
        );

        return $technical;
    }

    public function technicalError(Throwable $exception, array $context = []): array
    {
        $message = $exception->getMessage();
        $reason = $this->classifyFailure($exception, $context);

        return [
            'failed_reason' => $reason,
            'last_error_code' => $reason,
            'last_error_message' => Str::limit($message, 2000, ''),
            'exception_class' => $exception::class,
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'stack_trace' => $this->compactTrace($exception),
            'validation_errors' => $context['validation_errors'] ?? null,
            'raw_response_summary' => isset($context['raw_response'])
                ? Str::limit((string) $context['raw_response'], 4000, '')
                : ($context['raw_response_summary'] ?? null),
        ];
    }

    public function classifyFailure(Throwable|string $error, array $context = []): string
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $lower = Str::lower($message.' '.EncodingGuard::jsonEncode($context));

        return match (true) {
            Str::contains($lower, ['no ai providers available', 'không có ai provider']) => 'missing_api_key',
            Str::contains($lower, ['401', '403', 'unauthorized', 'forbidden', 'invalid api key']) => 'invalid_api_key',
            Str::contains($lower, ['429', 'rate limit', 'rate_limited']) => 'provider_rate_limit',
            Str::contains($lower, ['timeout', 'timed out', 'cURL error 28']) => 'provider_timeout',
            Str::contains($lower, ['internal_language_detected', 'btucalculatorservice', 'technical_specs_json', 'product.capacity_btu']) => 'internal_code_leak_detected',
            Str::contains($lower, ['invalid json', 'json', 'không hợp lệ']) => 'invalid_json_response',
            Str::contains($lower, ['schema', 'thiếu', 'chưa đạt chuẩn', 'validation']) => 'json_schema_validation_failed',
            Str::contains($lower, ['fact-check', 'unverified', 'blocked_claim']) => 'fact_check_failed',
            Str::contains($lower, ['missing_required_product_data', 'không khớp sản phẩm']) => 'missing_required_product_data',
            Str::contains($lower, ['permission', '403']) => 'permission_denied',
            Str::contains($lower, ['sqlstate', 'database', 'base table', 'column not found']) => 'database_save_failed',
            default => 'unknown_exception',
        };
    }

    public function compactTrace(Throwable $exception, int $lines = 12): string
    {
        return collect(explode("\n", $exception->getTraceAsString()))
            ->take($lines)
            ->implode("\n");
    }

    public function publicContext(array $context): array
    {
        return $this->sanitizeContext($context);
    }

    private function sanitizeContext(array $context): array
    {
        array_walk_recursive($context, function (&$value, string|int $key): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/api[_-]?key|secret|password|token|authorization/i', (string) $key)) {
                $value = '[redacted]';
            }
        });

        return $context;
    }
}
