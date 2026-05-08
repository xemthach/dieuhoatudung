<?php

namespace App\Enums;

enum LandingSectionType: string
{
    case Hero = 'hero';
    case Slider = 'slider';
    case QuickCategories = 'quick_categories';
    case FeaturedProducts = 'featured_products';
    case QuickFilter = 'quick_filter';
    case Comparison = 'comparison';
    case AdvisoryContent = 'advisory_content';
    case CaseStudies = 'case_studies';
    case Faq = 'faq';
    case LeadForm = 'lead_form';
    case Policies = 'policies';
    case RelatedPosts = 'related_posts';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero Banner',
            self::Slider => 'Slider/Banner',
            self::QuickCategories => 'Danh mục nhanh',
            self::FeaturedProducts => 'Sản phẩm nổi bật',
            self::QuickFilter => 'Bộ lọc nhanh',
            self::Comparison => 'Bảng so sánh',
            self::AdvisoryContent => 'Nội dung tư vấn',
            self::CaseStudies => 'Dự án thực tế',
            self::Faq => 'Câu hỏi thường gặp',
            self::LeadForm => 'Form báo giá',
            self::Policies => 'Chính sách',
            self::RelatedPosts => 'Bài viết liên quan',
        };
    }
}
