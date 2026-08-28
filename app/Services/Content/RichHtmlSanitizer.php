<?php

namespace App\Services\Content;

final class RichHtmlSanitizer
{
    private const ALLOWED_TAGS = '<h1><h2><h3><h4><p><br><ul><ol><li><blockquote><strong><b><em><i><u><s><code><pre><table><thead><tbody><tr><th><td><a><hr>';

    public function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|svg|math)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace('/\s(on[a-z]+|style|class|id|contenteditable)=(["\'].*?["\']|[^\s>]*)/iu', '', $html) ?? '';
        $html = preg_replace('/href=(["\'])\s*(?:javascript|data):.*?\1/iu', 'href="#"', $html) ?? '';

        return trim($html);
    }
}
