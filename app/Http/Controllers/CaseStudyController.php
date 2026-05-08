<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CaseStudyController extends Controller
{
    public function index()
    {
        $caseStudies = \App\Models\CaseStudy::where('status', \App\Enums\CaseStudyStatus::Published)
            ->when(request('project_type'), function ($query, $type) {
                return $query->where('project_type', $type);
            })
            ->latest('published_at')
            ->paginate(12);

        $projectTypes = \App\Models\CaseStudy::where('status', \App\Enums\CaseStudyStatus::Published)
            ->whereNotNull('project_type')
            ->select('project_type')
            ->distinct()
            ->pluck('project_type');

        $seoTitle = 'Dự Án Lắp Đặt Điều Hòa Tủ Đứng Thực Tế';
        $seoDescription = 'Tham khảo các dự án lắp đặt điều hòa tủ đứng thực tế cho nhà hàng, văn phòng, nhà xưởng, showroom... Hình ảnh thi công chân thực, chi tiết kỹ thuật.';

        return view('pages.case-studies.index', compact('caseStudies', 'projectTypes', 'seoTitle', 'seoDescription'));
    }

    public function show($slug)
    {
        $caseStudy = \App\Models\CaseStudy::where('slug', $slug)
            ->where('status', \App\Enums\CaseStudyStatus::Published)
            ->with(['product', 'activeTestimonials'])
            ->firstOrFail();

        $relatedCaseStudies = \App\Models\CaseStudy::where('status', \App\Enums\CaseStudyStatus::Published)
            ->where('id', '!=', $caseStudy->id)
            ->where(function($query) use ($caseStudy) {
                $query->where('project_type', $caseStudy->project_type)
                      ->orWhere('product_id', $caseStudy->product_id);
            })
            ->latest('published_at')
            ->limit(3)
            ->get();

        $seoTitle = $caseStudy->seo_title ?: "Dự án: {$caseStudy->title} | Điều Hòa Tủ Đứng";
        $seoDescription = $caseStudy->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($caseStudy->problem ?? ''), 150);
        $canonical = $caseStudy->canonical_url ?: route('case-studies.show', $caseStudy->slug);
        
        return view('pages.case-studies.show', compact('caseStudy', 'relatedCaseStudies', 'seoTitle', 'seoDescription', 'canonical'));
    }
}
