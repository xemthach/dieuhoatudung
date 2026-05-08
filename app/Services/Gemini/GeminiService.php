<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private array $generationConfig;

    public function __construct()
    {
        $this->apiKey = setting('ai.gemini_api_key', config('gemini.api_key', ''));
        $this->model = setting('ai.gemini_model', config('gemini.model', 'gemini-2.0-flash'));
        $this->endpoint = config('gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/');
        $this->generationConfig = config('gemini.generation', []);
    }

    /**
     * Gọi Gemini API với prompt và trả về text.
     */
    public function generate(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY chưa được cấu hình trong .env');
        }

        $url = $this->endpoint . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $response = Http::timeout(120)
                ->retry(2, 3000)
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $this->generationConfig,
                ]);

            $response->throw();

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? throw new \RuntimeException('Gemini trả về response không hợp lệ.');

        } catch (ConnectionException $e) {
            Log::error('GeminiService: Connection error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Không thể kết nối Gemini API: ' . $e->getMessage());
        } catch (RequestException $e) {
            $status = $e->response->status();
            $body   = $e->response->body();
            Log::error('GeminiService: Request error', ['status' => $status, 'body' => $body]);
            throw new \RuntimeException("Gemini API lỗi HTTP {$status}: {$body}");
        }
    }

    /**
     * Tạo outline cho bài viết.
     */
    public function generateOutline(string $topic, string $keyword, string $intent): string
    {
        $prompt = str_replace(
            ['{topic}', '{keyword}', '{intent}'],
            [$topic, $keyword, $intent],
            config('gemini.prompts.blog_outline')
        );

        return $this->generate($prompt);
    }

    /**
     * Tạo bài viết draft từ outline.
     */
    public function generateDraft(string $outline): string
    {
        $prompt = str_replace(
            ['{outline}'],
            [$outline],
            config('gemini.prompts.blog_draft')
        );

        return $this->generate($prompt);
    }

    /**
     * Gợi ý tags từ nội dung bài viết.
     * Trả về array [['name' => ..., 'type' => ...]]
     */
    public function suggestTags(string $title, string $excerpt): array
    {
        $prompt = str_replace(
            ['{title}', '{excerpt}'],
            [$title, $excerpt],
            config('gemini.prompts.tag_suggestions')
        );

        $raw = $this->generate($prompt);

        // Tách JSON từ response (Gemini có thể thêm text xung quanh)
        if (preg_match('/\[.*\]/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('GeminiService: suggestTags không parse được JSON', ['raw' => $raw]);
        return [];
    }

    public function generateFaq(string $outline): array
    {
        $prompt = str_replace('{outline}', $outline, config('gemini.prompts.blog_faq'));
        $raw = $this->generate($prompt);

        if (preg_match('/\[.*\]/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    public function generateMeta(string $outline): array
    {
        $prompt = str_replace('{outline}', $outline, config('gemini.prompts.blog_meta'));
        $raw = $this->generate($prompt);

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    public function generateInternalLinks(string $draft): array
    {
        $prompt = str_replace('{draft}', $draft, config('gemini.prompts.blog_internal_links'));
        $raw = $this->generate($prompt);

        if (preg_match('/\[.*\]/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    /**
     * Kiểm tra API key có hợp lệ không.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
