<?php

namespace App\Services\Product;

/** Deterministic structural checks for governed content_html. */
final class AIContentStructureValidator
{
    public function inspect(string $html): array
    {
        $html = trim($html);
        $issues = [];

        if ($html === '') {
            return ['valid' => false, 'issues' => ['CONTENT_STRUCTURE_FAILED'], 'h2_count' => 0, 'h3_count' => 0];
        }
        if (preg_match('/(^|\R)\s{0,3}#{1,6}\s+\S/u', $html)) {
            $issues[] = 'MARKDOWN_HEADING_NOT_ALLOWED';
        }
        if (preg_match('/<\/?(?:html|head|body)\b[^>]*>/iu', $html)) {
            $issues[] = 'FULL_DOCUMENT_WRAPPER_NOT_ALLOWED';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div data-ai-fragment="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $issues[] = 'INVALID_HTML_FRAGMENT';
        }

        $h2 = $dom->getElementsByTagName('h2');
        $h3 = $dom->getElementsByTagName('h3');
        if ($h2->length < 1) {
            $issues[] = 'MISSING_H2';
        }
        if ($h3->length < 1) {
            $issues[] = 'MISSING_H3';
        }
        foreach ([$h2, $h3] as $headings) {
            foreach ($headings as $heading) {
                if (trim((string) $heading->textContent) === '') {
                    $issues[] = 'EMPTY_HEADING';
                }
            }
        }

        $issues = array_values(array_unique($issues));
        return [
            'valid' => $issues === [],
            'issues' => $issues,
            'h2_count' => $h2->length,
            'h3_count' => $h3->length,
        ];
    }

    public function assert(string $html): void
    {
        $result = $this->inspect($html);
        if (! $result['valid']) {
            throw new \RuntimeException('CONTENT_STRUCTURE_FAILED: '.implode(',', $result['issues']));
        }
    }
}
