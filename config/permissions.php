<?php

/**
 * Permission Registry — Single source of truth cho toàn bộ RBAC.
 *
 * Format: 'module_prefix' => [
 *     'label' => 'Tên module hiển thị',
 *     'permissions' => ['action1', 'action2', ...],
 * ]
 *
 * Permission name = module_prefix.action  (vd: product.view, r2.sync)
 */

return [

    // ── Dashboard ──────────────────────────────────────────────────────
    'dashboard' => [
        'label' => 'Dashboard',
        'icon'  => 'heroicon-o-home',
        'permissions' => [
            'view' => 'Xem bảng điều khiển',
        ],
    ],

    // ── System: Settings ───────────────────────────────────────────────
    'settings' => [
        'label' => 'Cài đặt hệ thống',
        'icon'  => 'heroicon-o-cog-6-tooth',
        'permissions' => [
            'view'        => 'Xem cài đặt',
            'edit'        => 'Sửa cài đặt',
            'clear_cache' => 'Xóa cache',
        ],
    ],

    // ── System: R2/CDN ─────────────────────────────────────────────────
    'r2' => [
        'label' => 'R2 / CDN Storage',
        'icon'  => 'heroicon-o-cloud',
        'permissions' => [
            'view'         => 'Xem R2 manager',
            'test'         => 'Test kết nối R2',
            'scan'         => 'Scan local files',
            'sync'         => 'Sync upload lên R2',
            'replace_urls' => 'Thay thế URLs',
            'view_logs'    => 'Xem logs đồng bộ',
            'cancel_job'   => 'Hủy job đồng bộ',
        ],
    ],

    // ── System: Mail Template ──────────────────────────────────────────
    'mail_template' => [
        'label' => 'Mẫu Email',
        'icon'  => 'heroicon-o-envelope',
        'permissions' => [
            'view'      => 'Xem danh sách mẫu email',
            'create'    => 'Tạo mẫu email',
            'edit'      => 'Sửa mẫu email',
            'delete'    => 'Xóa mẫu email',
            'preview'   => 'Xem trước mẫu email',
            'send_test' => 'Gửi email test',
            'reset'     => 'Reset mẫu email về mặc định',
        ],
    ],

    // ── System: Mail Log ───────────────────────────────────────────────
    'mail_log' => [
        'label' => 'Log gửi mail',
        'icon'  => 'heroicon-o-paper-airplane',
        'permissions' => [
            'view'   => 'Xem mail logs',
            'delete' => 'Xóa mail logs',
            'resend' => 'Gửi lại mail',
        ],
    ],

    // ── System: Users ──────────────────────────────────────────────────
    'user' => [
        'label' => 'Quản lý Users',
        'icon'  => 'heroicon-o-users',
        'permissions' => [
            'view'           => 'Xem danh sách users',
            'create'         => 'Tạo user mới',
            'edit'           => 'Sửa thông tin user',
            'delete'         => 'Xóa user',
            'reset_password' => 'Reset mật khẩu user',
        ],
    ],

    // ── System: Roles ──────────────────────────────────────────────────
    'role' => [
        'label' => 'Quản lý Roles',
        'icon'  => 'heroicon-o-shield-check',
        'permissions' => [
            'view'   => 'Xem danh sách roles',
            'create' => 'Tạo role mới',
            'edit'   => 'Sửa role / gán quyền',
            'delete' => 'Xóa role',
        ],
    ],

    // ── SEO & AI: AI Provider ──────────────────────────────────────────
    'ai_provider' => [
        'label' => 'AI Providers',
        'icon'  => 'heroicon-o-cpu-chip',
        'permissions' => [
            'view'   => 'Xem danh sách AI providers',
            'create' => 'Thêm AI provider',
            'edit'   => 'Sửa AI provider',
            'delete' => 'Xóa AI provider',
            'test'   => 'Test kết nối AI',
        ],
    ],

    // ── SEO & AI: AI Content Job ───────────────────────────────────────
    'ai_content_job' => [
        'label' => 'AI Content Jobs',
        'icon'  => 'heroicon-o-sparkles',
        'permissions' => [
            'view'   => 'Xem danh sách AI jobs',
            'create' => 'Tạo AI content job',
            'run'    => 'Chạy AI job',
            'cancel' => 'Hủy AI job',
            'delete' => 'Xóa AI job',
        ],
    ],

    // ── SEO & AI: SEO Audit ────────────────────────────────────────────
    'seo_audit' => [
        'label' => 'SEO Audit',
        'icon'  => 'heroicon-o-magnifying-glass',
        'permissions' => [
            'view'        => 'Xem SEO Audit',
            'run'         => 'Chạy SEO audit',
            'clear_cache' => 'Xóa cache SEO',
        ],
    ],

    // ── SEO & AI: Internal Links ───────────────────────────────────────
    'internal_link' => [
        'label' => 'Internal Links',
        'icon'  => 'heroicon-o-link',
        'permissions' => [
            'view'     => 'Xem internal links',
            'create'   => 'Tạo internal link',
            'edit'     => 'Sửa internal link',
            'delete'   => 'Xóa internal link',
            'generate' => 'Tự động generate links',
        ],
    ],

    // ── SEO & AI: Redirects ────────────────────────────────────────────
    'redirect' => [
        'label' => 'Redirects 301/302',
        'icon'  => 'heroicon-o-arrow-right',
        'permissions' => [
            'view'   => 'Xem redirects',
            'create' => 'Tạo redirect',
            'edit'   => 'Sửa redirect',
            'delete' => 'Xóa redirect',
        ],
    ],

    // ── Content: Authors ───────────────────────────────────────────────
    'author' => [
        'label' => 'Tác giả',
        'icon'  => 'heroicon-o-user',
        'permissions' => [
            'view'   => 'Xem tác giả',
            'create' => 'Tạo tác giả',
            'edit'   => 'Sửa tác giả',
            'delete' => 'Xóa tác giả',
        ],
    ],

    // ── Content: Post Categories ───────────────────────────────────────
    'post_category' => [
        'label' => 'Danh mục bài viết',
        'icon'  => 'heroicon-o-folder',
        'permissions' => [
            'view'   => 'Xem danh mục bài viết',
            'create' => 'Tạo danh mục bài viết',
            'edit'   => 'Sửa danh mục bài viết',
            'delete' => 'Xóa danh mục bài viết',
        ],
    ],

    // ── Content: Posts ─────────────────────────────────────────────────
    'post' => [
        'label' => 'Bài viết',
        'icon'  => 'heroicon-o-document-text',
        'permissions' => [
            'view'    => 'Xem bài viết',
            'create'  => 'Tạo bài viết',
            'edit'    => 'Sửa bài viết',
            'delete'  => 'Xóa bài viết',
            'publish' => 'Publish / Unpublish bài viết',
        ],
    ],

    // ── Content: Tags ──────────────────────────────────────────────────
    'tag' => [
        'label' => 'Tags',
        'icon'  => 'heroicon-o-tag',
        'permissions' => [
            'view'   => 'Xem tags',
            'create' => 'Tạo tag',
            'edit'   => 'Sửa tag',
            'delete' => 'Xóa tag',
        ],
    ],

    // ── E-commerce: Brands ─────────────────────────────────────────────
    'brand' => [
        'label' => 'Thương hiệu',
        'icon'  => 'heroicon-o-building-storefront',
        'permissions' => [
            'view'   => 'Xem thương hiệu',
            'create' => 'Tạo thương hiệu',
            'edit'   => 'Sửa thương hiệu',
            'delete' => 'Xóa thương hiệu',
        ],
    ],

    // ── E-commerce: Product Categories ─────────────────────────────────
    'product_category' => [
        'label' => 'Danh mục sản phẩm',
        'icon'  => 'heroicon-o-squares-2x2',
        'permissions' => [
            'view'   => 'Xem danh mục SP',
            'create' => 'Tạo danh mục SP',
            'edit'   => 'Sửa danh mục SP',
            'delete' => 'Xóa danh mục SP',
        ],
    ],

    // ── E-commerce: Products ───────────────────────────────────────────
    'product' => [
        'label' => 'Sản phẩm',
        'icon'  => 'heroicon-o-rectangle-stack',
        'permissions' => [
            'view'   => 'Xem sản phẩm',
            'create' => 'Tạo sản phẩm',
            'edit'   => 'Sửa sản phẩm',
            'delete' => 'Xóa sản phẩm',
            'import' => 'Import sản phẩm',
            'export' => 'Export sản phẩm',
        ],
    ],

    // ── E-commerce: Promotions ─────────────────────────────────────────
    'promotion' => [
        'label' => 'Khuyến mãi',
        'icon'  => 'heroicon-o-gift',
        'permissions' => [
            'view'   => 'Xem khuyến mãi',
            'create' => 'Tạo khuyến mãi',
            'edit'   => 'Sửa khuyến mãi',
            'delete' => 'Xóa khuyến mãi',
        ],
    ],

    // ── Leads & Contacts: Leads ────────────────────────────────────────
    'lead' => [
        'label' => 'Leads',
        'icon'  => 'heroicon-o-user-group',
        'permissions' => [
            'view'   => 'Xem leads',
            'create' => 'Tạo lead',
            'edit'   => 'Sửa lead / đổi trạng thái',
            'delete' => 'Xóa lead',
            'export' => 'Export leads',
        ],
    ],

    // ── Leads & Contacts: Quote Requests ───────────────────────────────
    'quote_request' => [
        'label' => 'Báo giá',
        'icon'  => 'heroicon-o-calculator',
        'permissions' => [
            'view'   => 'Xem yêu cầu báo giá',
            'edit'   => 'Xử lý / phản hồi báo giá',
            'delete' => 'Xóa yêu cầu báo giá',
            'export' => 'Export báo giá',
        ],
    ],

    // ── Leads & Contacts: BTU Calculator ───────────────────────────────
    'btu_calculator' => [
        'label' => 'BTU Calculator',
        'icon'  => 'heroicon-o-calculator',
        'permissions' => [
            'view' => 'Xem BTU calculator',
            'edit' => 'Sửa BTU calculator',
        ],
    ],

    // ── Landing & Pages: FAQ ───────────────────────────────────────────
    'faq' => [
        'label' => 'FAQ',
        'icon'  => 'heroicon-o-question-mark-circle',
        'permissions' => [
            'view'   => 'Xem FAQ',
            'create' => 'Tạo FAQ',
            'edit'   => 'Sửa FAQ',
            'delete' => 'Xóa FAQ',
        ],
    ],

    // ── Landing & Pages: Landing Sections ──────────────────────────────
    'landing_section' => [
        'label' => 'Landing Sections',
        'icon'  => 'heroicon-o-squares-plus',
        'permissions' => [
            'view'    => 'Xem landing sections',
            'create'  => 'Tạo landing section',
            'edit'    => 'Sửa landing section',
            'delete'  => 'Xóa landing section',
            'reorder' => 'Sắp xếp thứ tự sections',
        ],
    ],

    // ── Landing & Pages: Policy Pages ──────────────────────────────────
    'policy_page' => [
        'label' => 'Trang chính sách',
        'icon'  => 'heroicon-o-document',
        'permissions' => [
            'view'   => 'Xem trang chính sách',
            'create' => 'Tạo trang chính sách',
            'edit'   => 'Sửa trang chính sách',
            'delete' => 'Xóa trang chính sách',
        ],
    ],

    // ── Landing & Pages: Case Studies ──────────────────────────────────
    'case_study' => [
        'label' => 'Case Studies',
        'icon'  => 'heroicon-o-briefcase',
        'permissions' => [
            'view'   => 'Xem case studies',
            'create' => 'Tạo case study',
            'edit'   => 'Sửa case study',
            'delete' => 'Xóa case study',
        ],
    ],

    // ── Landing & Pages: Testimonials ──────────────────────────────────
    'testimonial' => [
        'label' => 'Testimonials',
        'icon'  => 'heroicon-o-chat-bubble-left-right',
        'permissions' => [
            'view'   => 'Xem testimonials',
            'create' => 'Tạo testimonial',
            'edit'   => 'Sửa testimonial',
            'delete' => 'Xóa testimonial',
        ],
    ],

    // ── Product Interaction: Reviews ───────────────────────────────────
    'product_review' => [
        'label' => 'Đánh giá sản phẩm',
        'icon'  => 'heroicon-o-star',
        'permissions' => [
            'view'    => 'Xem đánh giá',
            'create'  => 'Tạo đánh giá',
            'edit'    => 'Sửa đánh giá',
            'delete'  => 'Xóa đánh giá',
            'approve' => 'Duyệt / Từ chối đánh giá',
            'reply'   => 'Trả lời đánh giá',
        ],
    ],

    // ── Product Interaction: Questions ──────────────────────────────────
    'product_question' => [
        'label' => 'Hỏi đáp sản phẩm',
        'icon'  => 'heroicon-o-chat-bubble-bottom-center-text',
        'permissions' => [
            'view'   => 'Xem hỏi đáp',
            'create' => 'Tạo câu hỏi',
            'edit'   => 'Sửa câu hỏi',
            'delete' => 'Xóa câu hỏi',
            'answer' => 'Trả lời câu hỏi',
        ],
    ],

];
