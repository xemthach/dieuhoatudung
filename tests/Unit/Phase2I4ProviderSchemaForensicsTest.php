<?php

namespace Tests\Unit;

use App\Services\AI\AIJsonResponseParser;
use App\Services\AI\AITechnicalLogger;
use App\Models\AiProvider;
use App\Services\AI\Adapters\OpenAIAdapter;
use App\Services\Product\AIContentStructureValidator;
use App\Services\Product\AIProductContentSanitizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase2I4ProviderSchemaForensicsTest extends TestCase
{
    public function test_h2_h3_failure_is_not_classified_as_provider_schema_failure(): void
    {
        $code = app(AITechnicalLogger::class)->classifyFailure('AI output thiếu H2/H3.');

        $this->assertSame('content_quality_validation_failed', $code);
    }

    public function test_markdown_wrapped_json_is_narrowly_normalized(): void
    {
        $payload = app(AIJsonResponseParser::class)->parse("```json\n{\"ok\":true}\n```", true);

        $this->assertSame(['ok' => true], $payload);
    }

    public function test_truncated_json_remains_invalid(): void
    {
        $this->expectException(\RuntimeException::class);

        app(AIJsonResponseParser::class)->parse('{"content_layer":{"content_html":"unfinished"}', true);
    }

    public function test_missing_content_is_not_silently_repaired(): void
    {
        $code = app(AITechnicalLogger::class)->classifyFailure('AI output thiếu content_html.');

        $this->assertSame('json_schema_validation_failed', $code);
    }

    public function test_content_structure_matrix_is_deterministic(): void
    {
        $validator = app(AIContentStructureValidator::class);
        $cases = [
            'valid' => ['<h2>Title</h2><p>Body</p><h3>Subtitle</h3>', true],
            'h2_only' => ['<h2>Title</h2><p>Body</p>', false],
            'h3_only' => ['<h3>Subtitle</h3><p>Body</p>', false],
            'no_heading' => ['<p>Body</p>', false],
            'markdown' => ['## Title' . PHP_EOL . '<p>Body</p>', false],
            'attributes' => ['<h2 class="safe">Title</h2><h3 id="safe">Subtitle</h3>', true],
            'empty_heading' => ['<h2></h2><h3>Subtitle</h3>', false],
            'escaped_heading' => ['&lt;h2&gt;Title&lt;/h2&gt;<h3>Subtitle</h3>', false],
        ];

        foreach ($cases as $name => [$html, $expected]) {
            $this->assertSame($expected, $validator->inspect($html)['valid'], $name);
        }
    }

    public function test_truncation_diagnostic_is_distinct(): void
    {
        $code = app(AITechnicalLogger::class)->classifyFailure('{"code":"PROVIDER_OUTPUT_TRUNCATED","finish_reason":"length"}');

        $this->assertSame('provider_output_truncated', $code);
    }

    public function test_content_taxonomy_codes_are_distinct(): void
    {
        $logger = app(AITechnicalLogger::class);

        $this->assertSame('content_structure_failed', $logger->classifyFailure('CONTENT_STRUCTURE_FAILED: MISSING_H2'));
        $this->assertSame('content_too_short', $logger->classifyFailure('CONTENT_TOO_SHORT: 200/800'));
        $this->assertSame('payload_contract_failed', $logger->classifyFailure('PAYLOAD_CONTRACT_FAILED: product mismatch'));
        $this->assertSame('internal_code_leak_detected', $logger->classifyFailure('AI output chua ngon ngu noi bo hoac cu phap giong code.'));
    }

    public function test_adapter_exposes_safe_response_diagnostics(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"ok":true}'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['total_tokens' => 7],
            ], 200, ['x-request-id' => 'request-test']),
        ]);

        $provider = new AiProvider([
            'model' => 'test-model',
            'endpoint' => 'https://example.test/v1/chat/completions',
            'supports_json_mode' => true,
        ]);
        $result = app(OpenAIAdapter::class)->generate($provider, ['prompt' => 'test'], ['require_json' => true]);

        $this->assertSame('stop', $result['finish_reason']);
        $this->assertSame(11, $result['raw_response_length']);
        $this->assertSame(hash('sha256', '{"ok":true}'), $result['response_fingerprint']);
        $this->assertSame('request-test', $result['provider_request_id']);
    }

    public function test_adapter_classifies_length_finish_reason_as_truncation(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"unfinished":'],
                    'finish_reason' => 'length',
                ]],
            ], 200),
        ]);

        $provider = new AiProvider([
            'model' => 'test-model',
            'endpoint' => 'https://example.test/v1/chat/completions',
        ]);

        try {
            app(OpenAIAdapter::class)->generate($provider, ['prompt' => 'test'], ['require_json' => true]);
            $this->fail('Expected truncation exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('PROVIDER_OUTPUT_TRUNCATED', $e->getMessage());
        }
    }

    public function test_hvac_content_collision_matrix_passes_safe_content_and_blocks_code_patterns(): void
    {
        $sanitizer = app(AIProductContentSanitizer::class);
        $good = [
            'excerpt' => 'Giải pháp điều hòa dân dụng cho không gian phù hợp.',
            'content_html' => '<h2>Điều hòa GDC36S6I/GMC36S6I</h2><p>Công suất 36.000 BTU, lưu lượng 1.200 m³/h, môi chất R32, ống Ø6.35 mm và diện tích 40m².</p><h3>Thông số kỹ thuật</h3><p>Thiết kế phục vụ nhu cầu sử dụng thực tế.</p>',
            'seo_title' => 'Điều hòa GDC36S6I/GMC36S6I', 'meta_description' => 'Thông tin kỹ thuật điều hòa.',
            'og_title' => 'Điều hòa GDC36S6I/GMC36S6I', 'og_description' => 'Thông tin sản phẩm.',
            'merchant_title' => 'Điều hòa GDC36S6I/GMC36S6I', 'merchant_description' => 'Thông tin tham khảo.',
            'tags' => ['GREE', 'R32', '42.000 BTU'], 'faq' => [], 'warnings' => [],
        ];
        $clean = $sanitizer->sanitizePayload($good);
        $this->assertStringContainsString('<h2>', $clean['content_html']);
        $this->assertStringContainsString('m³/h', $clean['content_html']);

        foreach (['ProductService', 'calculate()', 'payload.content_html'] as $bad) {
            $probe = $good;
            $probe['content_html'] = '<h2>Thông tin</h2><p>Công suất sản phẩm '.$bad.' được mô tả trong tài liệu.</p><h3>Ứng dụng</h3>';
            $diagnostics = $sanitizer->internalLanguageDiagnostics($probe);
            $this->assertNotNull($diagnostics, $bad);
            $this->assertArrayHasKey('rule_id', $diagnostics);
            $this->assertArrayNotHasKey('matched_text', $diagnostics);
        }
    }

    public function test_internal_language_diagnostics_are_safe_and_field_scoped(): void
    {
        $sanitizer = app(AIProductContentSanitizer::class);
        $payload = ['content_html' => '<h2>Thông tin</h2><p>System prompt: assistant must return payload.content_html.</p><h3>Ứng dụng</h3>'];
        $diagnostics = $sanitizer->internalLanguageDiagnostics($payload);

        $this->assertSame('INTERNAL_FIELD_PATH', $diagnostics['rule_id']);
        $this->assertSame('content_html', $diagnostics['field']);
        $this->assertArrayNotHasKey('matched_text', $diagnostics);
        $this->assertIsInt($diagnostics['offset']);
    }
}
