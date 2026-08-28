<?php

namespace App\Services\AI;

use App\Services\Content\RichHtmlSanitizer;
use App\Support\EncodingGuard;

class AIContentFieldPipeline
{
    public const FIELD_LABELS = [
        'intro' => 'Mô tả ngắn',
        'description' => 'Mô tả',
        'content' => 'Nội dung chi tiết',
        'seo_title' => 'SEO title',
        'seo_description' => 'Meta description',
        'og_title' => 'OG title',
        'og_description' => 'OG description',
        'cta_content' => 'CTA content',
        'banner_copy' => 'Banner copy',
    ];

    public function mapGeneratedFields(array $generated, array $fieldMap, array $selectedFields): array
    {
        $updates = [];

        foreach ($selectedFields as $semanticField) {
            $targetField = $fieldMap[$semanticField] ?? $semanticField;
            $value = $generated[$semanticField] ?? $generated[$targetField] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $value = EncodingGuard::ensureUtf8(
                    trim($value),
                    autoFixMojibake: true,
                    rejectBroken: true,
                    context: 'AI field '.$targetField
                );
                $updates[$targetField] = in_array($targetField, ['content', 'content_html'], true)
                    ? app(RichHtmlSanitizer::class)->sanitize($value)
                    : $value;
            }
        }

        EncodingGuard::assertCleanUtf8Array($updates, 'AI field updates');

        return $updates;
    }

    public function mergeValue(?string $current, string $incoming, string $mode): string
    {
        $current = (string) $current;

        return match ($mode) {
            'append' => trim($current) === '' ? $incoming : rtrim($current)."\n\n".$incoming,
            'fill_empty' => trim($current) === '' ? $incoming : $current,
            default => $incoming,
        };
    }
}
