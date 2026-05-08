<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    /**
     * /kien-thuc/{slug}
     * Chưa có view riêng. 301 về trang landing chính cho đến khi có content.
     */
    public function knowledge($slug)
    {
        return redirect()->route('landing', [], 301);
    }

    /**
     * /so-sanh/{slug}
     * 301 về trang so sánh sản phẩm.
     */
    public function comparison($slug)
    {
        return redirect()->route('compare.index', [], 301);
    }

    /**
     * /huong-dan-chon-mua/{slug}
     * 301 về landing page cho đến khi có view riêng.
     */
    public function buyingGuide($slug)
    {
        return redirect()->route('landing', [], 301);
    }

    /**
     * /lap-dat-bao-tri/{slug}
     * 301 về trang dự án/case studies.
     */
    public function maintenance($slug)
    {
        return redirect()->route('case-studies.index', [], 301);
    }

    public function faq()
    {
        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->get();
        return view('pages.faq', compact('faqs'));
    }
}
