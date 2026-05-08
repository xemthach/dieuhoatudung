<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductQuestion;
use App\Services\Mail\MailDispatchService;
use App\Services\Media\MediaDiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductInteractionController extends Controller
{
    public function __construct(
        private MediaDiskService    $mediaDisk,
        private MailDispatchService $mailService
    ) {}

    /**
     * Submit a product review.
     */
    public function storeReview(Request $request, string $slug)
    {
        if (!setting('product_detail.enable_reviews', true)) {
            abort(404);
        }

        // Rate limiting — 5 per IP per hour
        $key = 'review-submit:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Bạn đã gửi quá nhiều đánh giá. Vui lòng thử lại sau.');
        }
        RateLimiter::hit($key, 3600);

        $product = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $requirePhone = setting('product_detail.review_require_phone', false);
        $allowImages  = setting('product_detail.review_allow_images', true);
        $maxImages    = (int) setting('product_detail.review_max_images', 3);
        $maxSizeMb    = (int) setting('product_detail.review_max_image_size_mb', 3);
        $maxSizeKb    = $maxSizeMb * 1024;
        $autoApprove  = setting('product_detail.review_auto_approve', false);

        $rules = [
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => $requirePhone ? 'required|string|max:20' : 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'rating'         => 'required|integer|min:1|max:5',
            'content'        => 'required|string|min:10|max:2000',
            'honeypot'       => 'max:0',
        ];

        if ($allowImages) {
            $rules['images']   = 'nullable|array|max:' . $maxImages;
            $rules['images.*'] = "image|mimes:jpg,jpeg,png,webp|max:{$maxSizeKb}";
        }

        $validated = $request->validate($rules, [
            'images.max'      => "Bạn chỉ được gửi tối đa {$maxImages} ảnh.",
            'images.*.image'  => 'File phải là hình ảnh.',
            'images.*.mimes'  => 'Hình ảnh phải ở định dạng JPG, PNG hoặc WebP.',
            'images.*.max'    => "Mỗi hình ảnh không được vượt quá {$maxSizeMb}MB.",
            'content.min'     => 'Nội dung đánh giá cần ít nhất 10 ký tự.',
            'rating.required' => 'Vui lòng chọn số sao đánh giá.',
        ]);

        // Sanitize text
        $validated['content']       = strip_tags($validated['content']);
        $validated['customer_name'] = strip_tags($validated['customer_name']);

        // Handle image uploads
        $imagePaths = [];
        if ($allowImages && $request->hasFile('images')) {
            $this->mediaDisk->configureR2Disk();
            $disk      = $this->mediaDisk->getUploadDisk();
            $directory = 'reviews/' . $product->id;

            foreach ($request->file('images') as $image) {
                // Verify it is truly an image (double-check MIME beyond extension)
                if (!str_starts_with($image->getMimeType(), 'image/')) {
                    continue;
                }

                $extension  = $image->getClientOriginalExtension();
                $filename   = Str::ulid() . '.' . strtolower($extension);
                $path       = $directory . '/' . $filename;

                Storage::disk($disk)->put($path, file_get_contents($image->getRealPath()));
                $imagePaths[] = $path;
            }
        }

        ProductReview::create([
            'product_id'     => $product->id,
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'rating'         => $validated['rating'],
            'content'        => $validated['content'],
            'images_json'    => !empty($imagePaths) ? $imagePaths : null,
            'status'         => $autoApprove ? 'approved' : 'pending',
            'approved_at'    => $autoApprove ? now() : null,
        ]);

        $message = $autoApprove
            ? 'Cảm ơn bạn đã đánh giá sản phẩm!'
            : 'Cảm ơn bạn đã gửi đánh giá! Đánh giá sẽ được hiển thị sau khi duyệt.';

        // ── Gửi mail thông báo admin (via MailDispatchService) ────
        try {
            $review = ProductReview::where('product_id', $product->id)->latest()->first();
            $this->mailService->sendEvent(
                event:       'review_admin',
                vars: [
                    'product_name'   => $product->name,
                    'customer_name'  => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'] ?? '—',
                    'rating'         => $validated['rating'],
                    'review_content' => $validated['content'],
                    'content'        => $validated['content'], // BC compat with existing templates
                    'status'         => $autoApprove ? 'Đã duyệt tự động' : 'Chờ duyệt',
                ],
                adminEmail:  setting('mail_notify.review_notify_email') ?: setting('lead.lead_notify_email', ''),
                relatedType: 'ProductReview',
                relatedId:   $review?->id
            );
        } catch (\Throwable $e) {
            Log::error('Review admin mail failed: ' . $e->getMessage());
        }

        return back()->with('review_success', $message)->withFragment('reviews');
    }

    /**
     * Submit a product question.
     */
    public function storeQuestion(Request $request, string $slug)
    {
        if (!setting('product_detail.enable_questions', true)) {
            abort(404);
        }

        $key = 'question-submit:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Bạn đã gửi quá nhiều câu hỏi. Vui lòng thử lại sau.');
        }
        RateLimiter::hit($key, 3600);

        $product      = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $requirePhone = setting('product_detail.question_require_phone', false);

        $rules = [
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => $requirePhone ? 'required|string|max:20' : 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'question'       => 'required|string|min:10|max:2000',
            'honeypot'       => 'max:0',
        ];

        $validated = $request->validate($rules, [
            'question.min'     => 'Câu hỏi cần ít nhất 10 ký tự.',
            'question.required'=> 'Vui lòng nhập câu hỏi của bạn.',
        ]);

        $validated['question']       = strip_tags($validated['question']);
        $validated['customer_name']  = strip_tags($validated['customer_name']);

        $autoApprove = setting('product_detail.question_auto_approve', false);

        ProductQuestion::create([
            'product_id'     => $product->id,
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'question'       => $validated['question'],
            'status'         => $autoApprove ? 'approved' : 'pending',
            'is_public'      => true,
        ]);

        $message = 'Câu hỏi của bạn đã được gửi và sẽ được phản hồi sớm.';

        // ── Gửi mail thông báo admin (via MailDispatchService) ────
        try {
            $pq = ProductQuestion::where('product_id', $product->id)->latest()->first();
            $this->mailService->sendEvent(
                event:       'question_admin',
                vars: [
                    'product_name'   => $product->name,
                    'customer_name'  => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'] ?? '—',
                    'customer_email' => $validated['customer_email'] ?? '—',
                    'question'       => $validated['question'],
                ],
                adminEmail:  setting('mail_notify.question_notify_email') ?: setting('lead.lead_notify_email', ''),
                relatedType: 'ProductQuestion',
                relatedId:   $pq?->id
            );
        } catch (\Throwable $e) {
            Log::error('Question admin mail failed: ' . $e->getMessage());
        }

        return back()->with('question_success', $message)->withFragment('questions');
    }
}
