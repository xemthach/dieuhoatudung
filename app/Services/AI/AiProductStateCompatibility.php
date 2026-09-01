<?php

namespace App\Services\AI;

use App\Models\AiProductJobItem;

final class AiProductStateCompatibility
{
    public const ACTIVE = [
        AIJobStateMachine::QUEUED,
        AIJobStateMachine::RUNNING,
        AIJobStateMachine::VALIDATING,
        AIJobStateMachine::FACT_CHECKING,
    ];

    public const TERMINAL_OR_ACTIONABLE = [
        AIJobStateMachine::REVIEW_REQUIRED,
        AIJobStateMachine::DONE,
        AIJobStateMachine::FAILED,
        AIJobStateMachine::BLOCKED,
        AIJobStateMachine::CANCELLED,
    ];

    /** @return array{status:string,violation:?string,canonical:string,legacy:string} */
    public function item(AiProductJobItem $item): array
    {
        $legacy = AIJobStateMachine::fromLegacy((string) $item->status);
        $canonical = strtoupper((string) ($item->canonical_status ?: $legacy));
        $known = array_merge(self::ACTIVE, self::TERMINAL_OR_ACTIONABLE);

        if (! in_array($canonical, $known, true)) {
            return [
                'status' => $legacy,
                'violation' => 'UNKNOWN_CANONICAL_ITEM_STATE',
                'canonical' => $canonical,
                'legacy' => $legacy,
            ];
        }

        if ($canonical === $legacy) {
            return ['status' => $canonical, 'violation' => null, 'canonical' => $canonical, 'legacy' => $legacy];
        }

        // Historical rows can contain an active canonical default next to a
        // terminal/actionable legacy state. Treating those rows as active would
        // poison Product readiness forever, so the safe compatibility reading
        // is terminal/actionable while reporting the invariant violation.
        if (in_array($legacy, self::TERMINAL_OR_ACTIONABLE, true)) {
            return [
                'status' => $legacy,
                'violation' => 'LEGACY_CANONICAL_ITEM_MISMATCH',
                'canonical' => $canonical,
                'legacy' => $legacy,
            ];
        }

        if (in_array($legacy, self::ACTIVE, true) && in_array($canonical, self::ACTIVE, true)) {
            $rank = array_flip(self::ACTIVE);
            $effective = ($rank[$legacy] ?? 0) > ($rank[$canonical] ?? 0) ? $legacy : $canonical;

            return [
                'status' => $effective,
                'violation' => 'LEGACY_CANONICAL_ITEM_MISMATCH',
                'canonical' => $canonical,
                'legacy' => $legacy,
            ];
        }

        return [
            'status' => $canonical,
            'violation' => 'LEGACY_CANONICAL_ITEM_MISMATCH',
            'canonical' => $canonical,
            'legacy' => $legacy,
        ];
    }

    public function isActive(string $state): bool
    {
        return in_array($state, self::ACTIVE, true);
    }
}
