<?php

namespace App\Services\AI;

use BackedEnum;

final class AiContentStatusPresenter
{
    /** @return array{key:string,label:string,color:string,active:bool,terminal:bool,review_required:bool} */
    public function present(string|BackedEnum|null $status, ?array $worker = null, bool $applied = false): array
    {
        $raw = $status instanceof BackedEnum ? $status->value : (string) $status;
        $key = $applied ? 'APPLIED' : $this->normalize($raw);

        $view = match ($key) {
            'NOT_GENERATED' => ['Chưa tạo', 'gray', false, true, false],
            'QUEUED' => ['Đang chờ', 'gray', true, false, false],
            'PROCESSING' => ['AI đang tạo nội dung', 'info', true, false, false],
            'VALIDATING' => ['Đang kiểm tra nội dung', 'info', true, false, false],
            'RETRYING' => ['Đang thử lại', 'info', true, false, false],
            'PAUSED' => ['Tạm dừng', 'gray', false, false, false],
            'REVIEW_REQUIRED' => ['Chờ duyệt', 'warning', false, false, true],
            'APPROVED' => ['Đã duyệt', 'success', false, false, false],
            'APPLIED' => ['Đã áp dụng', 'success', false, true, false],
            'COMPLETED' => ['Hoàn tất', 'success', false, true, false],
            'COMPLETED_WITH_ERRORS' => ['Hoàn tất, có lỗi', 'warning', false, true, false],
            'BLOCKED' => ['Bị chặn', 'warning', false, true, false],
            'FAILED' => ['Thất bại', 'danger', false, true, false],
            'CANCELLED' => ['Đã hủy', 'gray', false, true, false],
            'INTERRUPTED' => ['Có thể bị gián đoạn', 'warning', true, false, false],
            default => ['Chưa xác định', 'gray', false, false, false],
        };

        [$label, $color, $active, $terminal, $reviewRequired] = $view;
        $warning = null;
        $desired = strtoupper((string) ($worker['desired_state'] ?? ''));
        $health = strtoupper((string) ($worker['health'] ?? ''));

        if ($key === 'QUEUED' && $desired === 'DISABLED') {
            $warning = 'Đã tạo yêu cầu nhưng AI worker đang tắt.';
        }

        if (in_array($key, ['PROCESSING', 'VALIDATING', 'RETRYING'], true)
            && in_array($health, ['STALE', 'OFFLINE', 'UNKNOWN'], true)) {
            $key = 'INTERRUPTED';
            [$label, $color, $active, $terminal, $reviewRequired] = match ($key) {
                'INTERRUPTED' => ['Có thể bị gián đoạn', 'warning', true, false, false],
            };
            $warning = $health === 'STALE'
                ? 'Worker heartbeat đã cũ; tiến trình có thể bị gián đoạn.'
                : 'Worker hiện không online; tiến trình có thể bị gián đoạn.';
        }

        return [
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'active' => $active,
            'terminal' => $terminal,
            'review_required' => $reviewRequired,
            'warning' => $warning,
            'internal_status' => $raw,
        ];
    }

    public function normalize(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            '', 'not_generated' => 'NOT_GENERATED',
            'draft', 'pending', 'queued' => 'QUEUED',
            'processing', 'running', 'generating' => 'PROCESSING',
            'validating', 'fact_checking' => 'VALIDATING',
            'retrying' => 'RETRYING',
            'paused' => 'PAUSED',
            'needs_review', 'review_required' => 'REVIEW_REQUIRED',
            'approved', 'reviewed' => 'APPROVED',
            'applied' => 'APPLIED',
            'completed', 'completed_verified', 'done' => 'COMPLETED',
            'completed_with_errors', 'completed_with_warnings' => 'COMPLETED_WITH_ERRORS',
            'blocked' => 'BLOCKED',
            'failed', 'failed_terminal', 'stuck' => 'FAILED',
            'cancelled', 'canceled' => 'CANCELLED',
            default => strtoupper(trim((string) $status)),
        };
    }

    public function safeReason(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        return match (strtolower((string) $code)) {
            'content_too_short', 'minimum_length' => 'Nội dung quá ngắn.',
            'missing_heading_structure', 'invalid_heading_structure' => 'Nội dung chưa có cấu trúc H2/H3 hợp lệ.',
            'unsupported_technical_claim', 'hallucinated_technical_claim' => 'Có thông tin kỹ thuật không được dữ liệu hiện có hỗ trợ.',
            'faq_missing_data' => 'FAQ cần dữ liệu hiện chưa có.',
            'provider_timeout', 'provider_unavailable', 'rate_limited' => 'Nhà cung cấp AI tạm thời không phản hồi.',
            'missing_api_key' => 'Nhà cung cấp AI chưa được cấu hình.',
            'job_cancelled' => 'Yêu cầu đã được hủy.',
            'queue_job_stuck_timeout' => 'Worker không hoàn tất yêu cầu trong thời gian cho phép.',
            default => 'Không thể hoàn tất yêu cầu. Operator có thể xem chi tiết kỹ thuật.',
        };
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'content', 'content_html', 'long_description' => 'Nội dung dài',
            'seo', 'seo_title' => 'SEO title',
            'meta', 'meta_description', 'seo_description' => 'Meta description',
            'faq', 'faqs' => 'FAQ',
            'merchant' => 'Google Merchant',
            'tags' => 'Thẻ nội dung',
            'internal_links' => 'Liên kết nội bộ',
            'og' => 'Open Graph',
            default => str($field)->replace('_', ' ')->title()->toString(),
        };
    }
}
