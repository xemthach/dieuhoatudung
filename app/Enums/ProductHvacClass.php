<?php

namespace App\Enums;

enum ProductHvacClass: string
{
    case RAC_SPLIT = 'RAC_SPLIT';
    case RAC_CASSETTE = 'RAC_CASSETTE';
    case RAC_DUCTED = 'RAC_DUCTED';
    case RAC_FLOOR_CEILING = 'RAC_FLOOR_CEILING';
    case RAC_FLOOR_STANDING = 'RAC_FLOOR_STANDING';
    case VRF_OUTDOOR = 'VRF_OUTDOOR';
    case VRF_INDOOR = 'VRF_INDOOR';
    case VRF_SYSTEM = 'VRF_SYSTEM';
    case OTHER = 'OTHER';
    case UNKNOWN = 'UNKNOWN';
}
