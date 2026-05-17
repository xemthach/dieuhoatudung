<?php

namespace App\Services\AI\Governance;

use App\Support\IssueList;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ForbiddenClaimEngine
{
    public function detect(string $text, array $context): array
    {
        $ascii = Str::ascii(Str::lower($text));
        $warnings = [];
        $blocked = [];
        $matches = [];

        foreach ((array) config('ai_claim_rules.claims', []) as $code => $rule) {
            foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                if (! preg_match($pattern, $ascii) && ! preg_match($pattern, $text)) {
                    continue;
                }

                if ($this->isAllowed($rule, $context)) {
                    $matches[] = [
                        'code' => $code,
                        'status' => 'verified',
                        'required_source' => $rule['required_source'] ?? null,
                    ];

                    continue 2;
                }

                $severity = $rule['severity'] ?? 'block';
                $warnings[] = $severity === 'block'
                    ? 'blocked_claim:'.$code
                    : 'claim_requires_rewrite:'.$code;

                if ($severity === 'block') {
                    $blocked[] = $code;
                }

                $matches[] = [
                    'code' => $code,
                    'status' => 'forbidden',
                    'required_source' => $rule['required_source'] ?? null,
                    'rewrite_strategy' => $rule['rewrite_strategy'] ?? 'remove',
                    'severity' => $severity,
                ];

                continue 2;
            }
        }

        return [
            'status' => $blocked === [] ? 'verified' : 'blocked',
            'warnings' => IssueList::normalize($warnings),
            'blocked_claims' => IssueList::normalize($blocked),
            'matches' => $matches,
        ];
    }

    public function rewriteText(string $text, array $context): array
    {
        $removed = [];

        foreach ((array) config('ai_claim_rules.claims', []) as $code => $rule) {
            if ($code === 'percent_100') {
                continue;
            }

            if ($this->isAllowed($rule, $context)) {
                continue;
            }

            foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                $replacement = $this->replacementFor($code);
                $newText = preg_replace($pattern, $replacement, $text) ?? $text;
                if ($newText !== $text) {
                    $text = $newText;
                    $removed[] = $code;
                }
            }
        }

        foreach ($this->detect($text, $context)['matches'] ?? [] as $match) {
            $code = (string) ($match['code'] ?? '');
            if ($code === '' || $code === 'percent_100' || ($match['status'] ?? null) !== 'forbidden') {
                continue;
            }

            $rewritten = $this->rewriteMatchingSentences($text, $code, $context);
            if ($rewritten !== $text) {
                $text = $rewritten;
                $removed[] = $code;
            }
        }

        return [
            'text' => $text,
            'removed_claims' => IssueList::normalize($removed),
        ];
    }

    private function isAllowed(array $rule, array $context): bool
    {
        foreach ((array) ($rule['allow_if'] ?? []) as $key) {
            if ($this->sourceValueAllowsClaim(Arr::get($context, 'allowed_facts.'.$key.'.value'))) {
                return true;
            }

            foreach ((array) Arr::get($context, 'verified_fact_registry', []) as $fact) {
                if (($fact['fact_key'] ?? null) === $key && $this->sourceValueAllowsClaim($fact['original_value'] ?? null)) {
                    return true;
                }
            }
        }

        $required = $rule['required_source'] ?? null;
        if (is_string($required) && $required !== '') {
            return $this->sourceValueAllowsClaim(Arr::get($context, 'allowed_facts.'.$required.'.value'));
        }

        return false;
    }

    private function sourceValueAllowsClaim(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filled($value);
    }

    private function replacementFor(string $code): string
    {
        return match ($code) {
            'vat' => 'chinh sach gia',
            'mien_phi' => 'lien he de duoc tu van',
            'chinh_hang' => 'theo thong tin san pham da luu',
            'bao_hanh' => 'thong tin bao hanh can xac nhan',
            'tot_nhat', 'gia_tot_nhat' => 'muc gia can xac nhan',
            'tiet_kiem_nhat', 'vuot_troi' => 'phu hop voi nhu cau su dung',
            'co_cq' => 'ho so chung tu can xac nhan',
            'percent_100' => '',
            default => '',
        };
    }

    private function rewriteMatchingSentences(string $text, string $code, array $context): string
    {
        $replacement = $this->replacementFor($code);
        if ($replacement === '') {
            return $text;
        }

        $parts = preg_split('/(?<=[.!?。！？])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $changed = false;

        foreach ($parts as &$part) {
            foreach ($this->detect($part, $context)['matches'] ?? [] as $match) {
                if (($match['code'] ?? null) === $code && ($match['status'] ?? null) === 'forbidden') {
                    $part = $replacement;
                    $changed = true;
                    break 2;
                }
            }
        }

        return $changed ? implode(' ', $parts) : $text;
    }
}
