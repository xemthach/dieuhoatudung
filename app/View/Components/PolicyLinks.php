<?php

namespace App\View\Components;

use App\Models\PolicyPage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class PolicyLinks extends Component
{
    public Collection $policies;
    public string $location;

    /**
     * Display locations (constants to avoid hardcoding):
     */
    public const FOOTER = 'footer';
    public const HEADER_TOP = 'header_top';
    public const LEAD_FORM = 'lead_form';
    public const PRODUCT_DETAIL = 'product_detail';

    public const ALL_LOCATIONS = [
        self::FOOTER         => 'Footer',
        self::HEADER_TOP     => 'Header phụ',
        self::LEAD_FORM      => 'Form liên hệ/Báo giá',
        self::PRODUCT_DETAIL => 'Chi tiết sản phẩm',
    ];

    public function __construct(
        public string $displayLocation = 'footer',
        public string $variant = 'list',  // list | inline | checkbox
    ) {
        $this->location = $displayLocation;
        $this->policies = PolicyPage::active()
            ->displayedIn($displayLocation)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function shouldRender(): bool
    {
        return $this->policies->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.policy-links');
    }
}
