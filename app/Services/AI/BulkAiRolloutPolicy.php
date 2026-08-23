<?php

namespace App\Services\AI;

use RuntimeException;

/** Pure policy/simulation boundary used by bulk orchestration and isolated tests. */
class BulkAiRolloutPolicy
{
    public const DRAFT='DRAFT'; public const READY='READY'; public const QUEUED='QUEUED'; public const RUNNING='RUNNING'; public const PAUSED='PAUSED'; public const REVIEW_REQUIRED='REVIEW_REQUIRED'; public const COMPLETED='COMPLETED'; public const COMPLETED_WITH_ERRORS='COMPLETED_WITH_ERRORS'; public const CANCELLED='CANCELLED'; public const FAILED='FAILED';

    public function eligibility(array $item): string
    {
        if (($item['duplicate'] ?? false) === true) return 'DUPLICATE';
        if (($item['already_successful'] ?? false) === true) return 'ALREADY_SUCCESSFUL';
        if (($item['stale'] ?? false) === true) return 'STALE';
        if (($item['verified_context'] ?? true) !== true) return 'MISSING_VERIFIED_CONTEXT';
        if (($item['ambiguous'] ?? false) === true) return 'AMBIGUOUS_PRODUCT';
        if (($item['blocked'] ?? false) === true) return 'BLOCKED';
        if (($item['manual_review'] ?? false) === true) return 'MANUAL_REVIEW_REQUIRED';
        return 'ELIGIBLE';
    }

    public function transition(string $from, string $to): string
    {
        $allowed = [
            self::DRAFT => [self::READY, self::CANCELLED], self::READY => [self::QUEUED, self::CANCELLED],
            self::QUEUED => [self::RUNNING, self::PAUSED, self::CANCELLED], self::RUNNING => [self::PAUSED, self::REVIEW_REQUIRED, self::COMPLETED, self::COMPLETED_WITH_ERRORS, self::FAILED, self::CANCELLED],
            self::PAUSED => [self::RUNNING, self::CANCELLED], self::REVIEW_REQUIRED => [self::COMPLETED, self::CANCELLED],
        ];
        if (! in_array($to, $allowed[$from] ?? [], true)) throw new RuntimeException("INVALID_BATCH_TRANSITION:{$from}:{$to}");
        return $to;
    }

    public function retryable(string $reason): bool
    {
        return in_array($reason, ['provider_timeout','rate_limited','provider_5xx','worker_unavailable'], true);
    }

    public function chunk(array $ids, int $size): array
    {
        if ($size < 1) throw new RuntimeException('INVALID_CHUNK_SIZE');
        return array_chunk(array_values($ids), $size);
    }

    public function consumeBudget(int $used, int $requested, int $max): array
    {
        if ($used + $requested > $max) return ['state'=>'PAUSE_BUDGET_EXCEEDED','used'=>$used,'accepted'=>false];
        return ['state'=>'RUNNING','used'=>$used+$requested,'accepted'=>true];
    }

    public function simulate(array $items, int $concurrency = 3, int $maxTokens = 10000): array
    {
        if ($concurrency < 1) throw new RuntimeException('INVALID_CONCURRENCY');
        $seen=[]; $calls=0; $used=0; $eligible=0; $blocked=0; $statuses=[];
        foreach ($items as $item) {
            $id=(int)($item['id']??0); if(isset($seen[$id])){$statuses[$id]='DUPLICATE';continue;} $seen[$id]=true;
            $status=$this->eligibility($item); $statuses[$id]=$status;
            if($status!=='ELIGIBLE'){ $blocked++; continue; }
            $budget=$this->consumeBudget($used,(int)($item['tokens']??0),$maxTokens); if(!$budget['accepted']){ $statuses[$id]='PAUSE_BUDGET_EXCEEDED'; break; }
            $used=$budget['used']; $eligible++; $calls++;
        }
        return ['target_count'=>count($items),'eligible'=>$eligible,'blocked_or_skipped'=>$blocked,'provider_calls'=>$calls,'tokens'=>$used,'concurrency'=>$concurrency,'statuses'=>$statuses,'paused'=>$calls < count(array_unique(array_column($items,'id'))) && $used >= $maxTokens];
    }
}
