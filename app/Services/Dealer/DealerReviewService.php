<?php

namespace App\Services\Dealer;

use App\Models\Dealer;
use App\Models\DealerReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

final class DealerReviewService
{
    public function submit(Dealer $dealer, array $data, ?User $user, string $ip): DealerReview
    {
        abort_unless($dealer->is_active && ! $dealer->trashed() && $dealer->review_enabled, 404);
        $key = 'dealer-review:'.hash_hmac('sha256', $ip, config('app.key'));
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Bạn đã gửi quá nhiều đánh giá. Vui lòng thử lại sau.');
        foreach (['customer_name', 'title', 'content'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim(strip_tags($data[$field]));
            }
        }
        $values = Validator::make($data, [
            'customer_name' => 'required|string|max:255', 'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:30', 'rating' => 'required|integer|between:1,5',
            'title' => 'nullable|string|max:255', 'content' => 'required|string|min:10|max:2000', 'honeypot' => 'nullable|max:0',
        ])->validate();
        RateLimiter::hit($key, 3600);
        unset($values['honeypot']);
        $values['customer_name'] = strip_tags($values['customer_name']);
        $values['content'] = strip_tags($values['content']);
        $values['title'] = isset($values['title']) ? strip_tags($values['title']) : null;

        return $dealer->reviews()->create($values + ['user_id' => $user?->id, 'status' => 'pending', 'is_visible' => true]);
    }

    public function moderate(DealerReview $review, string $action, User $actor, ?string $note = null): void
    {
        abort_unless($actor->is_active && $actor->can('dealer_review.moderate'), 403);
        abort_unless(in_array($action, ['approve', 'reject', 'hide', 'show'], true), 422);
        DB::transaction(function () use ($review, $action, $actor, $note) {
            $row = DealerReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            $changes = ['moderated_by' => $actor->id, 'moderated_at' => now()];
            if ($note !== null) {
                $changes['admin_note'] = mb_substr($note, 0, 2000);
            }
            if ($action === 'approve') {
                $changes += ['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()];
            }
            if ($action === 'reject') {
                $changes['status'] = 'rejected';
            }
            if ($action === 'hide' || $action === 'show') {
                $changes['is_visible'] = $action === 'show';
            }
            $row->update($changes);
        });
    }
}
