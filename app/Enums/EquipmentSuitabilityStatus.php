<?php

namespace App\Enums;

enum EquipmentSuitabilityStatus: string
{
    case SUITABLE_FOR_CONSIDERATION = 'SUITABLE_FOR_CONSIDERATION';
    case POSSIBLE_BUT_REVIEW_REQUIRED = 'POSSIBLE_BUT_REVIEW_REQUIRED';
    case NOT_RECOMMENDED_FOR_THIS_LOAD = 'NOT_RECOMMENDED_FOR_THIS_LOAD';
    case INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';
    case NO_MATCHING_PRODUCT = 'NO_MATCHING_PRODUCT';
    case TECHNICAL_CONSULTATION_REQUIRED = 'TECHNICAL_CONSULTATION_REQUIRED';

    public function label(): string
    {
        return match ($this) {
            self::SUITABLE_FOR_CONSIDERATION => 'Phù hợp để xem xét',
            self::POSSIBLE_BUT_REVIEW_REQUIRED => 'Có thể phù hợp — cần kiểm tra lắp đặt',
            self::NOT_RECOMMENDED_FOR_THIS_LOAD => 'Không khuyến nghị với tải hiện tại',
            self::INSUFFICIENT_DATA => 'Cần thêm thông tin',
            self::NO_MATCHING_PRODUCT => 'Catalog chưa có model phù hợp',
            self::TECHNICAL_CONSULTATION_REQUIRED => 'Cần tư vấn kỹ thuật',
        };
    }
}
