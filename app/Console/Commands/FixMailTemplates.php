<?php

namespace App\Console\Commands;

use App\Models\MailTemplate;
use Illuminate\Console\Command;

class FixMailTemplates extends Command
{
    protected $signature = 'mail:fix-templates {--dry-run : Preview changes without saving}';
    protected $description = 'Fix broken footer expressions and rebuild quote templates';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // ═══════════════════════════════════════════════════════════════
        // 1. Fix broken JS-expression footer in ALL templates
        // ═══════════════════════════════════════════════════════════════
        $badPattern = '{{site_name}}" . ({{hotline}} !== \'{{hotline}}\' && {{hotline}} ? " | SĐT: {{hotline}}" : ({{hotline}} === \'{{hotline}}\' ? \' | SĐT: {{hotline}}\' : \'\')) . "';
        $goodFooter = '{{site_name}} | Hotline: {{hotline}}';

        $fixedCount = 0;
        foreach (MailTemplate::all() as $t) {
            $changed = false;
            if ($t->content_html && str_contains($t->content_html, $badPattern)) {
                if (!$dryRun) {
                    $t->content_html = str_replace($badPattern, $goodFooter, $t->content_html);
                }
                $changed = true;
            }
            if ($t->body_html && str_contains($t->body_html, $badPattern)) {
                if (!$dryRun) {
                    $t->body_html = str_replace($badPattern, $goodFooter, $t->body_html);
                }
                $changed = true;
            }
            if ($changed) {
                if (!$dryRun) $t->save();
                $fixedCount++;
                $this->info(($dryRun ? '[DRY] ' : '✅ ') . "[{$t->id}] {$t->key} — footer fixed");
            }
        }
        $this->info("Footer: {$fixedCount} templates " . ($dryRun ? 'would be' : '') . " fixed");

        // ═══════════════════════════════════════════════════════════════
        // 2. Rebuild Quote Admin template (ID=3)
        // ═══════════════════════════════════════════════════════════════
        $adminTemplate = MailTemplate::where('key', 'quote_admin_notification')->first();
        if ($adminTemplate) {
            $adminTemplate->content_html = $this->getAdminHtml();
            if (!$dryRun) $adminTemplate->save();
            $this->info(($dryRun ? '[DRY] ' : '✅ ') . 'Quote admin template rebuilt');
        }

        // ═══════════════════════════════════════════════════════════════
        // 3. Rebuild Quote Customer template (ID=4)
        // ═══════════════════════════════════════════════════════════════
        $customerTemplate = MailTemplate::where('key', 'quote_customer_confirmation')->first();
        if ($customerTemplate) {
            $customerTemplate->content_html = $this->getCustomerHtml();
            if (!$dryRun) $customerTemplate->save();
            $this->info(($dryRun ? '[DRY] ' : '✅ ') . 'Quote customer template rebuilt');
        }

        $this->info($dryRun ? 'Dry run complete — no changes saved.' : 'All done.');
        return self::SUCCESS;
    }

    private function getAdminHtml(): string
    {
        return <<<'HTML'
<div style="font-family:'Segoe UI',Arial,sans-serif;max-width:580px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <div style="background:linear-gradient(135deg,#1a56db 0%,#1e429f 100%);padding:28px 32px">
    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600">Yêu cầu báo giá mới #{{quote_id}}</h1>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.75);font-size:13px">{{site_name}}</p>
  </div>
  <div style="padding:28px 32px">
    <h2 style="font-size:16px;color:#1e3a5f;margin:0 0 12px;border-bottom:2px solid #e5e7eb;padding-bottom:8px">👤 Thông tin khách hàng</h2>
    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:20px">
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Họ tên</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{customer_name}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>SĐT</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{customer_phone}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Email</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{customer_email}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Tỉnh/TP</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{province_city}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Địa chỉ</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{address}}</td></tr>
    </table>

    <h2 style="font-size:16px;color:#1e3a5f;margin:0 0 12px;border-bottom:2px solid #e5e7eb;padding-bottom:8px">📦 Sản phẩm quan tâm</h2>
    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:20px">
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Sản phẩm</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{product_name}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>SKU</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{product_sku}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Thương hiệu</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{product_brand}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Danh mục</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{product_category}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Công suất</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{product_capacity_btu}}</td></tr>
    </table>

    <h2 style="font-size:16px;color:#1e3a5f;margin:0 0 12px;border-bottom:2px solid #e5e7eb;padding-bottom:8px">🏢 Thông tin công trình</h2>
    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:20px">
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Loại công trình</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{project_type}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Diện tích</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{area_m2}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>BTU đề xuất</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{btu}}</td></tr>
      <tr><td style='padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Ngân sách</td><td style='padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{budget_range}}</td></tr>
      <tr><td style='background:#f8fafc;padding:8px 12px;width:150px;font-weight:600;color:#374151;border-bottom:1px solid #f3f4f6'>Nguồn</td><td style='background:#f8fafc;padding:8px 12px;color:#4b5563;border-bottom:1px solid #f3f4f6'>{{source}}</td></tr>
    </table>

    <h2 style="font-size:16px;color:#1e3a5f;margin:0 0 12px;border-bottom:2px solid #e5e7eb;padding-bottom:8px">💬 Ghi chú khách hàng</h2>
    <div style="background:#f8fafc;padding:12px 16px;border-radius:6px;color:#4b5563;margin-bottom:20px">{{message}}</div>

    <p style='margin:24px 0 0'><a href='{{admin_url}}/quote-requests' style='display:inline-block;background:#1a56db;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-size:14px'>Xem trong Admin</a></p>
  </div>
  <div style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:12px">{{site_name}} | Hotline: {{hotline}}</p>
    <p style="margin:4px 0 0;color:#9ca3af;font-size:12px"><a href='{{website_url}}' style='color:#6b7280'>{{website_url}}</a></p>
  </div>
</div>
HTML;
    }

    private function getCustomerHtml(): string
    {
        return <<<'HTML'
<div style="font-family:'Segoe UI',Arial,sans-serif;max-width:580px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <div style="background:linear-gradient(135deg,#1a56db 0%,#1e429f 100%);padding:28px 32px">
    <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600">Yêu cầu báo giá #{{quote_id}} đã được tiếp nhận</h1>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.75);font-size:13px">{{site_name}}</p>
  </div>
  <div style="padding:28px 32px">
    <p style="color:#374151;line-height:1.6">Xin chào <strong>{{customer_name}}</strong>,</p>
    <p style="color:#374151;line-height:1.6">Chúng tôi đã nhận được yêu cầu báo giá của bạn. Đội ngũ chuyên gia sẽ phân tích và gửi báo giá chi tiết trong thời gian sớm nhất.</p>

    <div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:16px;border-radius:4px;margin:20px 0">
      <p style="margin:0;color:#15803d;font-weight:600">Thông tin yêu cầu của bạn</p>
      <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-top:10px">
        <tr><td style='padding:4px 0;color:#374151;font-weight:600;width:130px'>Mã yêu cầu:</td><td style='padding:4px 0;color:#374151'>#{{quote_id}}</td></tr>
        <tr><td style='padding:4px 0;color:#374151;font-weight:600'>SĐT liên hệ:</td><td style='padding:4px 0;color:#374151'>{{customer_phone}}</td></tr>
        <tr><td style='padding:4px 0;color:#374151;font-weight:600'>Sản phẩm:</td><td style='padding:4px 0;color:#374151'>{{product_name}}</td></tr>
        <tr><td style='padding:4px 0;color:#374151;font-weight:600'>Loại công trình:</td><td style='padding:4px 0;color:#374151'>{{project_type}}</td></tr>
        <tr><td style='padding:4px 0;color:#374151;font-weight:600'>BTU phù hợp:</td><td style='padding:4px 0;color:#374151'>{{btu}}</td></tr>
      </table>
    </div>

    <p style="color:#374151;line-height:1.6">Nếu cần hỗ trợ gấp, vui lòng gọi hotline <strong>{{hotline}}</strong>.</p>
    <p style="color:#374151;line-height:1.6">Trân trọng,<br><strong>{{site_name}}</strong></p>
  </div>
  <div style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:12px">{{site_name}} | Hotline: {{hotline}}</p>
    <p style="margin:4px 0 0;color:#9ca3af;font-size:12px"><a href='{{website_url}}' style='color:#6b7280'>{{website_url}}</a></p>
  </div>
</div>
HTML;
    }
}
