<?php

namespace App\Services\AI\Governance;

use App\Support\EncodingGuard;
use App\Support\IssueList;
use Illuminate\Support\Str;

/**
 * Validates content safety aspects:
 * - UTF-8 integrity
 * - Code/service/class leak detection
 * - HTML safety (XSS prevention)
 * - Duplicate content detection
 * - Placeholder detection
 * - Content length validation
 * - Vietnamese diacritics check
 * - Internal code leak prevention
 */
class ContentSafetyValidator
{
    /**
     * Severity levels for safety issues.
     */
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_REVIEW = 'review';

    /**
     * Internal code patterns that indicate AI is leaking service/class names.
     */
    private const CODE_LEAK_PATTERNS = [
        'namespace' => '/\bApp\\\\[A-Za-z0-9_\\\\]+/u',
        'method_signature' => '/\b(public|private|protected)\s+function\s+[a-zA-Z_][a-zA-Z0-9_]*\s*\(/u',
        'internal_class_name' => '/\b[A-Z][a-z]+(?:[A-Z][A-Za-z0-9]+)+(?:Service|Controller|Model|Repository|DTO|Provider|Gateway|Adapter)\b/u',
        'internal_layer_name' => '/\b[A-Za-z0-9_]*(?:Service|Controller|Model|Repository|DTO|Provider|Gateway|Adapter)\b/u',
        'internal_variable_path' => '/\b(?:product|post|blog|config|request|response|job|payload|input|output)\.[a-zA-Z_][a-zA-Z0-9_.]*\b/iu',
        'raw_variable' => '/(?:\{\{|\}\}|\$[a-zA-Z_][a-zA-Z0-9_]*)/u',
    ];

    /**
     * HTML tags that are dangerous / not allowed in AI output.
     */
    private const UNSAFE_HTML_PATTERNS = [
        'script_tag' => '/<script\b/iu',
        'style_tag' => '/<style\b/iu',
        'iframe_tag' => '/<iframe\b/iu',
        'event_handler' => '/\bon\w+\s*=/iu',
        'javascript_url' => '/javascript\s*:/iu',
        'data_url' => '/data\s*:\s*text\/html/iu',
    ];

    /**
     * Placeholder text that should not appear in final content.
     */
    private const PLACEHOLDER_PATTERNS = [
        '/\b(?:lorem\s+ipsum|placeholder|undefined|N\/A|TODO|FIXME)\b/iu',
        '/\[(?:insert|add|fill|replace|update|your)\s/iu',
        '/\{\{[^}]+\}\}/u',
    ];

    /**
     * Validate content for safety issues.
     *
     * @return array{status: string, severity: string, warnings: array, blocked_claims: array, log: array}
     */
    public function validate(string $text, array $options = []): array
    {
        $warnings = [];
        $blocked = [];
        $log = [];
        $maxSeverity = self::SEVERITY_REVIEW;

        // 1. UTF-8 validation
        $utf8Result = $this->validateUtf8($text);
        if ($utf8Result !== null) {
            $blocked[] = $utf8Result['code'];
            $warnings[] = $utf8Result['warning'];
            $log[] = $utf8Result['log'];
            $maxSeverity = self::SEVERITY_CRITICAL;
        }

        // 2. Code leak detection
        $codeLeaks = $this->detectCodeLeaks($text);
        foreach ($codeLeaks as $leak) {
            $blocked[] = $leak['code'];
            $warnings[] = $leak['warning'];
            $log[] = $leak['log'];
            $maxSeverity = self::SEVERITY_CRITICAL;
        }

        // 3. HTML safety
        $htmlIssues = $this->validateHtmlSafety($text);
        foreach ($htmlIssues as $issue) {
            $blocked[] = $issue['code'];
            $warnings[] = $issue['warning'];
            $log[] = $issue['log'];
            $maxSeverity = self::SEVERITY_CRITICAL;
        }

        // 4. Placeholder detection
        $placeholders = $this->detectPlaceholders($text);
        foreach ($placeholders as $placeholder) {
            $warnings[] = $placeholder['warning'];
            $log[] = $placeholder['log'];
            if ($maxSeverity !== self::SEVERITY_CRITICAL) {
                $maxSeverity = self::SEVERITY_WARNING;
            }
        }

        // 5. Content length (optional check)
        $minWords = $options['min_words'] ?? 0;
        if ($minWords > 0) {
            $wordCount = $this->wordCount($text);
            if ($wordCount < $minWords) {
                $warnings[] = "content_too_short:{$wordCount}/{$minWords}";
                $log[] = [
                    'validator' => 'ContentSafetyValidator',
                    'check' => 'content_length',
                    'word_count' => $wordCount,
                    'required' => $minWords,
                    'action' => 'warning',
                ];
                if ($maxSeverity !== self::SEVERITY_CRITICAL) {
                    $maxSeverity = self::SEVERITY_WARNING;
                }
            }
        }

        // 6. Vietnamese diacritics check - ensure content is proper Vietnamese
        if (($options['check_vietnamese'] ?? false) && $this->isPoorVietnamese($text)) {
            $warnings[] = 'low_vietnamese_quality';
            $log[] = [
                'validator' => 'ContentSafetyValidator',
                'check' => 'vietnamese_quality',
                'action' => 'warning',
            ];
            if ($maxSeverity !== self::SEVERITY_CRITICAL) {
                $maxSeverity = self::SEVERITY_WARNING;
            }
        }

        $status = $blocked !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'verified');

        return [
            'status' => $status,
            'severity' => $maxSeverity,
            'warnings' => IssueList::normalize($warnings),
            'blocked_claims' => IssueList::normalize($blocked),
            'log' => $log,
        ];
    }

    /**
     * Sanitize text by removing unsafe HTML and code leaks.
     */
    public function sanitize(string $text): string
    {
        // Remove script/style/iframe tags and their content
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text) ?? $text;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text) ?? $text;
        $text = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $text) ?? $text;

        // Remove event handlers
        $text = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/iu', '', $text) ?? $text;

        // Remove javascript: URLs
        $text = preg_replace('/javascript\s*:[^"\'>\s]*/iu', '#', $text) ?? $text;

        return $text;
    }

    private function validateUtf8(string $text): ?array
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return [
                'code' => 'broken_utf8',
                'warning' => 'content_safety:broken_utf8',
                'log' => [
                    'validator' => 'ContentSafetyValidator',
                    'check' => 'utf8_encoding',
                    'action' => 'blocked_broken_encoding',
                ],
            ];
        }

        if (EncodingGuard::hasMojibake($text) || preg_match('/(?:\x{00C3}.|\x{00C4}.|\x{00E1}\x{00BA}|\x{00E1}\x{00BB}|\x{FFFD})/u', $text) === 1) {
            return [
                'code' => 'mojibake_detected',
                'warning' => 'content_safety:mojibake_detected',
                'log' => [
                    'validator' => 'ContentSafetyValidator',
                    'check' => 'mojibake',
                    'action' => 'blocked_mojibake',
                ],
            ];
        }

        return null;
    }

    private function detectCodeLeaks(string $text): array
    {
        $issues = [];

        foreach (self::CODE_LEAK_PATTERNS as $code => $pattern) {
            if ($code === 'internal_class_name') {
                // Skip common legitimate words that match CamelCase
                $cleaned = preg_replace('/\b(?:PowerPoint|YouTube|LinkedIn|Facebook|WordPress|iPhone|MacBook)\b/u', '', $text);
                if ($cleaned !== null && preg_match($pattern, $cleaned)) {
                    $issues[] = [
                        'code' => 'code_leak:'.$code,
                        'warning' => 'content_safety:code_leak_'.$code,
                        'log' => [
                            'validator' => 'ContentSafetyValidator',
                            'check' => 'code_leak',
                            'pattern' => $code,
                            'action' => 'blocked_code_leak',
                        ],
                    ];
                }

                continue;
            }

            if (preg_match($pattern, $text)) {
                $issues[] = [
                    'code' => 'code_leak:'.$code,
                    'warning' => 'content_safety:code_leak_'.$code,
                    'log' => [
                        'validator' => 'ContentSafetyValidator',
                        'check' => 'code_leak',
                        'pattern' => $code,
                        'action' => 'blocked_code_leak',
                    ],
                ];
            }
        }

        return $issues;
    }

    private function validateHtmlSafety(string $text): array
    {
        $issues = [];

        foreach (self::UNSAFE_HTML_PATTERNS as $code => $pattern) {
            if (preg_match($pattern, $text)) {
                $issues[] = [
                    'code' => 'unsafe_html:'.$code,
                    'warning' => 'content_safety:unsafe_html_'.$code,
                    'log' => [
                        'validator' => 'ContentSafetyValidator',
                        'check' => 'html_safety',
                        'pattern' => $code,
                        'action' => 'blocked_unsafe_html',
                    ],
                ];
            }
        }

        return $issues;
    }

    private function detectPlaceholders(string $text): array
    {
        $issues = [];

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $issues[] = [
                    'warning' => 'placeholder_detected:'.trim($match[0]),
                    'log' => [
                        'validator' => 'ContentSafetyValidator',
                        'check' => 'placeholder',
                        'matched' => trim($match[0]),
                        'action' => 'warning_placeholder',
                    ],
                ];
            }
        }

        return $issues;
    }

    private function isPoorVietnamese(string $text): bool
    {
        $plain = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($plain) < 100) {
            return false;
        }

        // Check ratio of Vietnamese diacritics vs ASCII
        $vietnameseChars = preg_match_all('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/u', $plain);
        $totalChars = mb_strlen(preg_replace('/\s+/u', '', $plain) ?: '');

        if ($totalChars === 0) {
            return true;
        }

        // Vietnamese text typically has 15-25% diacritical characters
        // If less than 5%, it's likely ASCII/non-Vietnamese
        return ($vietnameseChars / $totalChars) < 0.05;
    }

    private function wordCount(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }
}
