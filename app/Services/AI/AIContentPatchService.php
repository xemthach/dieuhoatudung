<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class AIContentPatchService
{
    public function patchField(
        mixed $originalFieldContent,
        array $validationErrors,
        array $verifiedFacts,
        array $businessOptions,
        string $fieldName,
    ): array {
        $content = $originalFieldContent;
        $notes = [];

        foreach ($validationErrors as $error) {
            if (($error['field'] ?? $fieldName) !== $fieldName) {
                continue;
            }

            $claim = (string) ($error['claim'] ?? '');
            $replacement = (string) ($error['replacement'] ?? '');
            $action = (string) ($error['suggested_action'] ?? 'rewrite');

            if (is_string($content) && $claim !== '') {
                $content = $this->patchString($content, $claim, $replacement, $action);
                $notes[] = "{$action}:{$claim}";
            }

            if (is_array($content) && $claim !== '') {
                $content = array_values(array_filter($content, function ($item) use ($claim): bool {
                    $text = is_array($item)
                        ? implode(' ', array_map('strval', $item))
                        : (string) $item;

                    return ! Str::contains($text, $claim);
                }));
                $notes[] = "remove:{$claim}";
            }
        }

        return [
            'patched_field_content' => $content,
            'patch_notes' => $notes,
            'verified_facts' => $verifiedFacts,
            'business_options' => $businessOptions,
        ];
    }

    private function patchString(string $content, string $claim, string $replacement, string $action): string
    {
        if ($action === 'remove' || $replacement === '') {
            $sentences = preg_split('/(?<=[.!?。！？])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [$content];

            return trim(implode(' ', array_filter(
                $sentences,
                fn (string $sentence): bool => ! Str::contains($sentence, $claim)
            )));
        }

        return str_replace($claim, $replacement, $content);
    }
}
