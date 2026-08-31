<?php

namespace App\Services\AI\Adapters;

use App\Models\AiProvider;
use App\Models\AiRequestLog;

/** Deterministic, network-free adapter used only by isolated bulk runtime harnesses. */
class FakeBulkAdapter implements AIAdapterInterface
{
    public function testConnection(AiProvider $provider): array
    {
        return ['success' => true, 'message' => 'isolated fake provider'];
    }

    public function generate(AiProvider $provider, array $payload, array $options = []): array
    {
        $contextId = $options['context_id'] ?? null;
        $priorRateLimits = $contextId ? AiRequestLog::where('context_id', $contextId)->where('status', 'rate_limited')->count() : 0;
        $priorFailures = $contextId ? AiRequestLog::where('context_id', $contextId)->where('status', 'failed')->count() : 0;
        $rateLimitAttempts = (int) ($options['fake_429_attempts'] ?? 0);
        if ($rateLimitAttempts > $priorRateLimits) throw new \RuntimeException(json_encode(['is_rate_limit' => true, 'code' => '429', 'message' => 'fake rate limited']));
        if ((int) ($options['fake_timeout_attempts'] ?? 0) > $priorFailures) throw new \RuntimeException('provider_timeout');
        if ((int) ($options['fake_5xx_attempts'] ?? 0) > $priorFailures) throw new \RuntimeException('provider_5xx');
        $started = microtime(true);
        usleep((int) ($options['fake_delay_us'] ?? 100000));
        $json = [
            'excerpt' => 'Nội dung kiểm thử bulk cô lập.',
            'content_html' => '<h2>Thông tin sản phẩm</h2><h3>Phạm vi kiểm thử</h3><p>'.str_repeat('Nội dung kiểm thử bulk cô lập, không kết nối mạng và không suy diễn thông số. ', 120).'</p>',
            'seo_title' => 'Sản phẩm kiểm thử bulk cô lập',
            'meta_description' => 'Mô tả kiểm thử bulk cô lập.',
            'og_title' => 'Sản phẩm kiểm thử bulk cô lập',
            'og_description' => 'Mô tả Open Graph kiểm thử.',
            'merchant_title' => 'Sản phẩm kiểm thử bulk cô lập',
            'merchant_description' => 'Mô tả Merchant kiểm thử.',
            'tags' => [],
            'faq' => [
                ['question' => 'Sản phẩm này phù hợp với không gian nào?', 'answer' => 'Bản nháp chỉ nêu mục đích sử dụng theo thông tin đã được xác minh.'],
                ['question' => 'Cần lưu ý gì khi lắp đặt?', 'answer' => 'Việc lắp đặt cần tuân thủ hướng dẫn kỹ thuật và do nhân sự phù hợp thực hiện.'],
                ['question' => 'Có thể kiểm tra thêm thông tin ở đâu?', 'answer' => 'Vui lòng đối chiếu tài liệu và thông tin sản phẩm đã được xác minh trước khi quyết định.'],
            ],
            'internal_links' => [], 'warnings' => [], 'blocked_claims' => [],
        ];
        if (! empty($options['fake_governance_failure'])) $json['blocked_claims'] = ['FACT_CHECK_BLOCKED'];
        $case = (string) ($options['fake_output_case'] ?? '');
        if ($case === 'h2_only') {
            $json['content_html'] = '<h2>Thông tin kiểm thử</h2><p>'.str_repeat('Nội dung kiểm thử bulk cô lập. ', 120).'</p>';
        } elseif ($case === 'h3_only') {
            $json['content_html'] = '<h3>Thông tin kiểm thử</h3><p>'.str_repeat('Nội dung kiểm thử bulk cô lập. ', 120).'</p>';
        } elseif ($case === 'no_heading') {
            $json['content_html'] = '<p>'.str_repeat('Nội dung kiểm thử bulk cô lập. ', 120).'</p>';
        } elseif ($case === 'markdown') {
            $json['content_html'] = '## Thông tin kiểm thử'.PHP_EOL.'<p>'.str_repeat('Nội dung kiểm thử bulk cô lập. ', 120).'</p>';
        } elseif ($case === 'attributes') {
            $json['content_html'] = '<h2 class=\"safe\">Thông tin kiểm thử</h2><p>'.str_repeat('Nội dung kiểm thử bulk cô lập. ', 120).'</p><h3 id=\"safe\">Phạm vi</h3>';
        } elseif ($case === 'too_short') {
            $json['content_html'] = '<h2>Thông tin kiểm thử</h2><h3>Phạm vi</h3><p>Ngắn.</p>';
        }
        $content = json_encode($json, JSON_UNESCAPED_UNICODE);
        return [
            'content' => $content, 'json' => $json,
            'tokens_used' => 100, 'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'finish_reason' => 'stop',
            'raw_response_length' => mb_strlen($content, '8bit'),
            'response_fingerprint' => hash('sha256', $content),
            'provider_request_id' => null,
        ];
    }
}
