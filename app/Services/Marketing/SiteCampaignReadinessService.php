<?php

namespace App\Services\Marketing;

use App\Models\SiteCampaign;

final class SiteCampaignReadinessService
{
    /** @return array{code:string,label:string,color:string,reason:string} */
    public function present(SiteCampaign $campaign): array
    {
        if (! array_key_exists((string) $campaign->type, SiteCampaign::typeOptions())) {
            return $this->state('NO_RENDERER', 'Không có renderer', 'danger', 'Loại campaign không được runtime hỗ trợ.');
        }

        if (! array_key_exists((string) $campaign->placement, SiteCampaign::placementOptions())) {
            return $this->state('NO_MATCHING_PLACEMENT', 'Placement không hợp lệ', 'danger', 'Placement không ánh xạ tới route runtime.');
        }

        $content = (array) $campaign->content_json;
        if ($campaign->type === 'image_popup' && blank(data_get($content, 'image'))) {
            return $this->state('MISCONFIGURED', 'Thiếu hình ảnh', 'danger', 'Image popup cần một media path hợp lệ.');
        }
        if ($campaign->type === 'video_popup' && blank(data_get($content, 'video_url'))) {
            return $this->state('MISCONFIGURED', 'Thiếu video', 'danger', 'Video popup cần URL YouTube hoặc Vimeo.');
        }
        if (! collect(['title', 'subtitle', 'content', 'image', 'video_url', 'button_primary_text', 'phone', 'zalo_url'])
            ->contains(fn (string $key): bool => filled(data_get($content, $key)))) {
            return $this->state('MISCONFIGURED', 'Thiếu nội dung', 'danger', 'Campaign không có nội dung có thể hiển thị.');
        }

        if ($campaign->status !== 'active') {
            return $this->state('INACTIVE', 'Không hoạt động', 'gray', 'Trạng thái hiện tại là '.$campaign->status.'.');
        }

        if ($campaign->start_at?->isFuture()) {
            return $this->state('SCHEDULED', 'Đã lên lịch', 'info', 'Campaign chưa đến thời điểm bắt đầu.');
        }

        if ($campaign->end_at?->isPast()) {
            return $this->state('EXPIRED', 'Đã hết hạn', 'warning', 'Campaign đã qua thời điểm kết thúc.');
        }

        return $this->state('READY', 'Sẵn sàng', 'success', 'Campaign hợp lệ và có thể được resolver chọn trên route phù hợp.');
    }

    /** @return array{code:string,label:string,color:string,reason:string} */
    private function state(string $code, string $label, string $color, string $reason): array
    {
        return compact('code', 'label', 'color', 'reason');
    }
}
