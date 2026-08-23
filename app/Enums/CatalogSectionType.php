<?php

namespace App\Enums;

enum CatalogSectionType: string
{
    case PRODUCT_LIST = 'product_list';
    case COMBINATION_TABLE = 'combination_table';
    case TECHNICAL_APPENDIX = 'technical_appendix';
    case MARKETING_FEATURE = 'marketing_feature';
    case UNKNOWN = 'unknown';

    public function isTechnicalAuthority(): bool
    {
        return $this === self::TECHNICAL_APPENDIX;
    }
}
