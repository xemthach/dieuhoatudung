<?php

namespace Database\Seeders;

use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    /**
     * Shared base layout wrapper.
     * Usage: self::layout($title, $rows, $cta, $siteName, $hotline)
     */
    private static function layout(string $title, string $body, string $siteName = '{{site_name}}', string $hotline = '{{hotline}}'): string
    {
        return <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif;max-width:580px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <div style="background:linear-gradient(135deg,#1a56db 0%,#1e429f 100%);padding:28px 32px">
    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600">{$title}</h1>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.75);font-size:13px">{$siteName}</p>
  </div>
  <div style="padding:28px 32px">
    {$body}
  </div>
  <div style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:12px">{$siteName}" . ($hotline !== '{{hotline}}' && $hotline ? " | SĐT: {$hotline}" : ($hotline === '{{hotline}}' ? ' | SĐT: {{hotline}}' : '')) . "</p>
    <p style="margin:4px 0 0;color:#9ca3af;font-size:12px"><a href='{{website_url}}' style='color:#6b7280'>{{website_url}}</a></p>
  </div>
</div>
HTML;
    }

    private static function tableRow(string $label, string $value, bool $shaded = false): string
    {
        $bg = $shaded ? 'background:#f8fafc;' : '';
        return "<tr><td style='{$bg}padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>{$label}</td>"
             . "<td style='{$bg}padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{$value}</td></tr>";
    }

    private static function ctaButton(string $url, string $text): string
    {
        return "<p style='margin:24px 0 0'><a href='{$url}' style='display:inline-block;background:#1a56db;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-size:14px'>{$text}</a></p>";
    }

    public function run(): void
    {
        $templates = $this->templates();

        foreach ($templates as $t) {
            MailTemplate::updateOrCreate(
                ['key' => $t['key']],
                [
                    'name'           => $t['name'],
                    'subject'        => $t['subject'],
                    'body_html'      => $t['body_html'],
                    'body_text'      => $t['body_text'] ?? null,
                    'variables_json' => $t['variables'] ?? null,
                    'is_active'      => true,
                    'locale'         => 'vi',
                ]
            );
        }
    }

    private function templates(): array
    {
        return [
            // ── 1. LEAD ADMIN ──────────────────────────────────────────
            [
                'key'     => 'lead_admin_notification',
                'name'    => 'Thông báo admin — Lead mới',
                'subject' => '[Lead mới] {{customer_name}} — {{source}}',
                'body_html' => self::layout(
                    'Có lead mới cần liên hệ',
                    '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px">'
                    . self::tableRow('Họ tên', '{{customer_name}}', true)
                    . self::tableRow('SĐT', '{{customer_phone}}')
                    . self::tableRow('Email', '{{customer_email}}', true)
                    . self::tableRow('Nhu cầu', '{{need_type}}')
                    . self::tableRow('Diện tích', '{{area}} m²', true)
                    . self::tableRow('Ghi chú', '{{message}}')
                    . self::tableRow('Nguồn', '{{source}}', true)
                    . '</table>'
                    . self::ctaButton('{{admin_url}}', 'Xem trong Admin')
                ),
                'variables' => [
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'customer_email', 'example' => 'khach@email.com'],
                    ['name' => 'need_type', 'example' => 'Báo giá'],
                    ['name' => 'area', 'example' => '50'],
                    ['name' => 'message', 'example' => 'Cần điều hòa 18000 BTU'],
                    ['name' => 'source', 'example' => '/cong-cu/tinh-btu'],
                    ['name' => 'admin_url', 'example' => 'https://domain.com/admin'],
                ],
            ],

            // ── 2. LEAD CUSTOMER ───────────────────────────────────────
            [
                'key'     => 'lead_customer_confirmation',
                'name'    => 'Xác nhận khách — Lead',
                'subject' => 'Cảm ơn {{customer_name}}! Chúng tôi đã nhận yêu cầu của bạn',
                'body_html' => self::layout(
                    'Chúng tôi đã nhận yêu cầu của bạn',
                    '<p style="color:#374151;line-height:1.6">Xin chào <strong>{{customer_name}}</strong>,</p>
<p style="color:#374151;line-height:1.6">Chúng tôi đã nhận được yêu cầu liên hệ từ bạn. Đội ngũ tư vấn sẽ liên hệ qua số <strong>{{customer_phone}}</strong> trong thời gian sớm nhất (trong giờ làm việc).</p>
<div style="background:#eff6ff;border-left:4px solid #1a56db;padding:16px;border-radius:4px;margin:20px 0">
  <p style="margin:0;color:#1e429f;font-weight:600">Thông tin liên hệ hỗ trợ</p>
  <p style="margin:8px 0 0;color:#374151">Hotline: <strong>{{hotline}}</strong><br>Website: <a href="{{website_url}}" style="color:#1a56db">{{website_url}}</a></p>
</div>'
                ),
                'variables' => [
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'hotline', 'example' => '0909.123.456'],
                    ['name' => 'website_url', 'example' => 'https://domain.com'],
                ],
            ],

            // ── 3. QUOTE ADMIN ─────────────────────────────────────────
            [
                'key'     => 'quote_admin_notification',
                'name'    => 'Thông báo admin — Báo giá mới',
                'subject' => '[Báo giá #{{quote_id}}] {{customer_name}} — {{project_type}}',
                'body_html' => self::layout(
                    'Yêu cầu báo giá mới #{{quote_id}}',
                    '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px">'
                    . self::tableRow('Họ tên', '{{customer_name}}', true)
                    . self::tableRow('SĐT', '{{customer_phone}}')
                    . self::tableRow('Email', '{{customer_email}}', true)
                    . self::tableRow('Loại công trình', '{{project_type}}')
                    . self::tableRow('Ngân sách', '{{budget_range}}', true)
                    . self::tableRow('BTU đề xuất', '{{btu}}')
                    . self::tableRow('Ghi chú', '{{message}}', true)
                    . self::tableRow('Nguồn', '{{source}}')
                    . '</table>'
                    . self::ctaButton('{{admin_url}}/quote-requests', 'Xem trong Admin')
                ),
                'variables' => [
                    ['name' => 'quote_id', 'example' => '42'],
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'customer_email', 'example' => 'khach@email.com'],
                    ['name' => 'project_type', 'example' => 'Nhà hàng'],
                    ['name' => 'budget_range', 'example' => '20-40 triệu'],
                    ['name' => 'btu', 'example' => '24,000'],
                    ['name' => 'message', 'example' => 'Cần lắp đặt gấp'],
                    ['name' => 'source', 'example' => '/bao-gia'],
                ],
            ],

            // ── 4. QUOTE CUSTOMER ──────────────────────────────────────
            [
                'key'     => 'quote_customer_confirmation',
                'name'    => 'Xác nhận khách — Báo giá',
                'subject' => 'Xác nhận yêu cầu báo giá #{{quote_id}} — {{site_name}}',
                'body_html' => self::layout(
                    'Yêu cầu báo giá #{{quote_id}} đã được tiếp nhận',
                    '<p style="color:#374151;line-height:1.6">Xin chào <strong>{{customer_name}}</strong>,</p>
<p style="color:#374151;line-height:1.6">Chúng tôi đã nhận được yêu cầu báo giá của bạn. Đội ngũ chuyên gia sẽ phân tích và gửi báo giá chi tiết qua số <strong>{{customer_phone}}</strong>.</p>
<div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:16px;border-radius:4px;margin:20px 0">
  <p style="margin:0;color:#15803d;font-weight:600">Thông tin yêu cầu của bạn</p>
  <p style="margin:8px 0 0;color:#374151">Mã yêu cầu: <strong>#{{quote_id}}</strong><br>Loại công trình: {{project_type}}<br>BTU phù hợp: {{btu}} BTU</p>
</div>
<p style="color:#374151;line-height:1.6">Nếu cần hỗ trợ gấp, vui lòng gọi hotline <strong>{{hotline}}</strong>.</p>'
                ),
                'variables' => [
                    ['name' => 'quote_id', 'example' => '42'],
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'project_type', 'example' => 'Nhà hàng'],
                    ['name' => 'btu', 'example' => '24,000'],
                    ['name' => 'hotline', 'example' => '0909.123.456'],
                ],
            ],

            // ── 5. REVIEW ADMIN ────────────────────────────────────────
            [
                'key'     => 'review_admin_notification',
                'name'    => 'Thông báo admin — Đánh giá mới',
                'subject' => '[Đánh giá {{rating}}/5] {{product_name}} — {{customer_name}}',
                'body_html' => self::layout(
                    'Đánh giá sản phẩm mới cần duyệt',
                    '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px">'
                    . self::tableRow('Sản phẩm', '{{product_name}}', true)
                    . self::tableRow('Người đánh giá', '{{customer_name}}')
                    . self::tableRow('SĐT', '{{customer_phone}}', true)
                    . self::tableRow('Đánh giá', '{{rating}}/5 sao')
                    . self::tableRow('Nội dung', '{{content}}', true)
                    . self::tableRow('Trạng thái', '{{status}}')
                    . '</table>'
                    . self::ctaButton('{{admin_url}}/product-reviews', 'Duyệt trong Admin')
                ),
                'variables' => [
                    ['name' => 'product_name', 'example' => 'Điều Hòa Daikin 24000 BTU'],
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'rating', 'example' => '5'],
                    ['name' => 'content', 'example' => 'Sản phẩm rất tốt'],
                    ['name' => 'status', 'example' => 'Chờ duyệt'],
                ],
            ],

            // ── 6. REVIEW CUSTOMER (approved) ──────────────────────────
            [
                'key'     => 'review_approved_customer',
                'name'    => 'Thông báo khách — Đánh giá được duyệt',
                'subject' => 'Đánh giá của bạn về {{product_name}} đã được duyệt',
                'body_html' => self::layout(
                    'Đánh giá của bạn đã được duyệt',
                    '<p style="color:#374151;line-height:1.6">Xin chào <strong>{{customer_name}}</strong>,</p>
<p style="color:#374151;line-height:1.6">Đánh giá của bạn về sản phẩm <strong>{{product_name}}</strong> đã được kiểm duyệt và hiển thị trên website.</p>
<p style="color:#374151;line-height:1.6">Cảm ơn bạn đã dành thời gian chia sẻ trải nghiệm. Đánh giá của bạn giúp ích rất nhiều cho cộng đồng người dùng!</p>'
                ),
                'variables' => [
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'product_name', 'example' => 'Điều Hòa Daikin 24000 BTU'],
                ],
            ],

            // ── 7. QUESTION ADMIN ──────────────────────────────────────
            [
                'key'     => 'question_admin_notification',
                'name'    => 'Thông báo admin — Câu hỏi mới',
                'subject' => '[Câu hỏi mới] {{product_name}} — {{customer_name}}',
                'body_html' => self::layout(
                    'Câu hỏi sản phẩm mới cần trả lời',
                    '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:8px">'
                    . self::tableRow('Sản phẩm', '{{product_name}}', true)
                    . self::tableRow('Người hỏi', '{{customer_name}}')
                    . self::tableRow('SĐT', '{{customer_phone}}', true)
                    . self::tableRow('Email', '{{customer_email}}')
                    . self::tableRow('Câu hỏi', '{{question}}', true)
                    . '</table>'
                    . self::ctaButton('{{admin_url}}/product-questions', 'Trả lời trong Admin')
                ),
                'variables' => [
                    ['name' => 'product_name', 'example' => 'Điều Hòa Daikin 24000 BTU'],
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'customer_phone', 'example' => '0909123456'],
                    ['name' => 'customer_email', 'example' => 'khach@email.com'],
                    ['name' => 'question', 'example' => 'Sản phẩm có bảo hành không?'],
                ],
            ],

            // ── 8. QUESTION CUSTOMER (answered) ───────────────────────
            [
                'key'     => 'question_answered_customer',
                'name'    => 'Thông báo khách — Câu hỏi được trả lời',
                'subject' => 'Câu hỏi của bạn về {{product_name}} đã được trả lời',
                'body_html' => self::layout(
                    'Câu hỏi của bạn đã được trả lời',
                    '<p style="color:#374151;line-height:1.6">Xin chào <strong>{{customer_name}}</strong>,</p>
<p style="color:#374151;line-height:1.6">Câu hỏi của bạn về sản phẩm <strong>{{product_name}}</strong> đã được đội ngũ tư vấn trả lời:</p>
<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin:16px 0">
  <p style="margin:0;color:#6b7280;font-size:13px;font-style:italic">Câu hỏi của bạn:</p>
  <p style="margin:8px 0;color:#374151">{{question}}</p>
  <p style="margin:12px 0 0;color:#6b7280;font-size:13px;font-style:italic">Trả lời:</p>
  <p style="margin:8px 0 0;color:#374151;font-weight:500">{{answer}}</p>
</div>
<p style="color:#374151;line-height:1.6">Nếu cần hỗ trợ thêm, hãy liên hệ hotline <strong>{{hotline}}</strong>.</p>'
                ),
                'variables' => [
                    ['name' => 'customer_name', 'example' => 'Nguyễn Văn A'],
                    ['name' => 'product_name', 'example' => 'Điều Hòa Daikin 24000 BTU'],
                    ['name' => 'question', 'example' => 'Sản phẩm có bảo hành không?'],
                    ['name' => 'answer', 'example' => 'Sản phẩm được bảo hành chính hãng 12 tháng.'],
                    ['name' => 'hotline', 'example' => '0909.123.456'],
                ],
            ],

            // ── 9. SYSTEM ALERT ────────────────────────────────────────
            [
                'key'     => 'system_alert',
                'name'    => 'Cảnh báo hệ thống',
                'subject' => '[Cảnh báo] {{alert_type}} — {{site_name}}',
                'body_html' => self::layout(
                    'Cảnh báo hệ thống: {{alert_type}}',
                    '<div style="background:#fef2f2;border-left:4px solid #dc2626;padding:16px;border-radius:4px;margin-bottom:20px">
  <p style="margin:0;color:#991b1b;font-weight:600">{{alert_type}}</p>
</div>
<p style="color:#374151;line-height:1.6">{{message}}</p>
<p style="color:#374151;line-height:1.6"><strong>Thời gian:</strong> {{occurred_at}}</p>'
                ),
                'variables' => [
                    ['name' => 'alert_type', 'example' => 'R2 Sync Failed'],
                    ['name' => 'message', 'example' => 'Connection timeout after 30s'],
                    ['name' => 'occurred_at', 'example' => '2026-05-06 12:00:00'],
                ],
            ],
        ];
    }
}
