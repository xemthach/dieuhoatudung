<?php

namespace App\Http\Controllers;

use App\Enums\CaseStudyStatus;
use App\Enums\LeadStatus;
use App\Enums\LandingSectionType;
use App\Enums\PostStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\LandingSection;
use App\Models\PolicyPage;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Mail\MailDispatchService;
use Illuminate\Support\Facades\Log;

class LandingController extends Controller
{
    public function __construct(
        private readonly MailDispatchService $mailService
    ) {}

    /**
     * Trang landing chính cho keyword "điều hòa tủ đứng".
     * Render từng section dựa trên LandingSection model.
     */
    public function index()
    {
        $pageKey = 'dieu-hoa-tu-dung';

        $sections = LandingSection::where('page_key', $pageKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Nếu chưa có sections được tạo từ admin, dùng cấu hình mặc định
        if ($sections->isEmpty()) {
            $sections = $this->getDefaultSections();
        }

        // Preload data cho từng loại section
        $data = $this->loadSectionData($sections);
        $data['sections'] = $sections;
        $data['featuredTestimonials'] = \App\Models\Testimonial::active()->featured()->orderBy('sort_order')->get();

        return view('landing.index', $data);
    }

    /**
     * Load data tương ứng cho từng section type.
     */
    private function loadSectionData($sections): array
    {
        $data = [];

        foreach ($sections as $section) {
            $type = $section->section_type;

            if ($type === LandingSectionType::QuickCategories && !isset($data['categories'])) {
                $data['categories'] = ProductCategory::where('is_active', true)
                    ->withCount('products')
                    ->orderBy('sort_order')
                    ->get();
            }

            if ($type === LandingSectionType::FeaturedProducts && !isset($data['featuredProducts'])) {
                $data['featuredProducts'] = Product::with('brand')
                    ->where('is_active', true)
                    ->orderByDesc('is_featured')
                    ->orderByDesc('is_bestseller')
                    ->orderBy('sort_order')
                    ->take(8)
                    ->get();
            }

            if ($type === LandingSectionType::Comparison && !isset($data['comparisonProducts'])) {
                $data['comparisonProducts'] = Product::with('brand')
                    ->where('is_active', true)
                    ->where('is_featured', true)
                    ->orderBy('regular_price')
                    ->take(6)
                    ->get();
            }

            if ($type === LandingSectionType::CaseStudies && !isset($data['caseStudies'])) {
                $data['caseStudies'] = CaseStudy::with('product.brand')
                    ->where('status', CaseStudyStatus::Published)
                    ->orderByDesc('published_at')
                    ->take(3)
                    ->get();
            }

            if ($type === LandingSectionType::Faq && !isset($data['faqs'])) {
                $data['faqs'] = Faq::where('is_active', true)
                    ->orderBy('sort_order')
                    ->take(8)
                    ->get();
            }

            if ($type === LandingSectionType::Policies && !isset($data['policies'])) {
                $data['policies'] = PolicyPage::where('is_active', true)
                    ->orderBy('id')
                    ->get();
            }

            if ($type === LandingSectionType::RelatedPosts && !isset($data['relatedPosts'])) {
                $data['relatedPosts'] = Post::with(['category', 'author'])
                    ->where('status', PostStatus::Published)
                    ->whereNotNull('published_at')
                    ->orderByDesc('published_at')
                    ->take(4)
                    ->get();
            }
        }

        // Always load brands (used in hero or comparison)
        if (!isset($data['brands'])) {
            $data['brands'] = Brand::where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return $data;
    }

    /**
     * Cấu hình sections mặc định khi admin chưa tạo sections.
     */
    private function getDefaultSections()
    {
        return collect([
            new LandingSection(['section_type' => LandingSectionType::Hero, 'title' => 'Điều Hòa Tủ Đứng Chính Hãng', 'subtitle' => 'Giải pháp làm mát chuyên nghiệp cho không gian lớn', 'sort_order' => 1, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::QuickCategories, 'title' => 'Danh Mục Điều Hòa Tủ Đứng', 'subtitle' => 'Chọn loại điều hòa phù hợp nhu cầu của bạn', 'sort_order' => 2, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::FeaturedProducts, 'title' => 'Sản Phẩm Bán Chạy', 'subtitle' => 'Được khách hàng tin tưởng lựa chọn', 'sort_order' => 3, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::Comparison, 'title' => 'Bảng Giá Điều Hòa Tủ Đứng', 'subtitle' => 'So sánh giá & thông số kỹ thuật các model phổ biến', 'sort_order' => 4, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::AdvisoryContent, 'title' => 'Hướng Dẫn Chọn Mua Điều Hòa Tủ Đứng', 'content' => $this->defaultAdvisoryContent(), 'sort_order' => 5, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::CaseStudies, 'title' => 'Dự Án Thực Tế', 'subtitle' => 'Các công trình lắp đặt điều hòa tủ đứng đã hoàn thành', 'sort_order' => 6, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::LeadForm, 'title' => 'Nhận Báo Giá Miễn Phí', 'subtitle' => 'Đội ngũ tư vấn phản hồi trong 30 phút', 'sort_order' => 7, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::Faq, 'title' => 'Câu Hỏi Thường Gặp Về Điều Hòa Tủ Đứng', 'sort_order' => 8, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::Policies, 'title' => 'Cam Kết Dịch Vụ', 'sort_order' => 9, 'is_active' => true]),
            new LandingSection(['section_type' => LandingSectionType::RelatedPosts, 'title' => 'Bài Viết Hữu Ích', 'subtitle' => 'Kiến thức giúp bạn sử dụng điều hòa hiệu quả', 'sort_order' => 10, 'is_active' => true]),
        ]);
    }

    private function defaultAdvisoryContent(): string
    {
        return '<h3>Điều hòa tủ đứng là gì?</h3>
<p>Điều hòa tủ đứng (hay còn gọi là máy lạnh tủ đứng) là loại điều hòa có thiết kế dạng tủ đứng, công suất lớn từ 24.000 BTU đến 100.000 BTU trở lên. Đây là giải pháp làm mát lý tưởng cho các không gian rộng như: nhà hàng, hội trường, văn phòng, showroom, nhà xưởng.</p>

<h3>Khi nào nên chọn điều hòa tủ đứng?</h3>
<ul>
<li>Diện tích phòng từ 40m² trở lên</li>
<li>Trần nhà cao, không gian mở</li>
<li>Cần công suất lạnh lớn, phân phối gió đều</li>
<li>Không muốn khoan tường hoặc lắp đặt phức tạp</li>
</ul>

<h3>Cách tính công suất BTU phù hợp</h3>
<p>Công thức cơ bản: <strong>Diện tích (m²) × 600 BTU = Công suất cần thiết</strong>. Ví dụ: phòng 50m² cần máy 30.000 BTU. Tuy nhiên, cần cộng thêm hệ số khi phòng có nhiều người, nhiều thiết bị tỏa nhiệt, hoặc tiếp xúc nắng trực tiếp.</p>';
    }

    /**
     * Xử lý form báo giá từ landing page.
     */
    public function storeLead(StoreLeadRequest $request)
    {
        $lead = Lead::createGeneralLead([
            'full_name'   => $request->name,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'source_page' => $request->source ?? 'landing_page',
            'status'      => LeadStatus::New,
            'ip_address'  => $request->ip(),
        ], [
            'area'    => $request->room_area ?? null,
            'message' => trim(($request->room_area ? 'Diện tích: ' . $request->room_area . 'm². ' : '') . ($request->note ?? '')),
        ]);

        // ── Gửi mail thông báo admin (via MailDispatchService) ────
        try {
            $this->mailService->sendEvent(
                event:       'lead_admin',
                vars: [
                    'customer_name'  => $lead->full_name,
                    'customer_phone' => $lead->phone ?? '—',
                    'customer_email' => $lead->email ?? '—',
                    'need_type'      => 'Landing page',
                    'area'           => $request->room_area ? $request->room_area . 'm²' : '—',
                    'message'        => $lead->message ?? '—',
                    'source'         => $lead->source_page,
                ],
                adminEmail:  setting('lead.lead_notify_email', ''),
                relatedType: 'Lead',
                relatedId:   $lead->id
            );
        } catch (\Throwable $e) {
            Log::error('Landing lead admin mail failed: ' . $e->getMessage());
        }

        // ── Gửi mail xác nhận cho khách (toggle nằm ở MailProviderService) ────
        if (!empty($lead->email)) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'lead_customer',
                    customerEmail: $lead->email,
                    vars: [
                        'customer_name' => $lead->full_name,
                    ],
                    relatedType: 'Lead',
                    relatedId:   $lead->id
                );
            } catch (\Throwable $e) {
                Log::error('Landing lead customer mail failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('landing')->with('lead_success', setting('lead.lead_success_message', 'Cảm ơn bạn! Chúng tôi sẽ liên hệ trong vòng 30 phút.'));
    }
}
