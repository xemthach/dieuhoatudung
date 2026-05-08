<?php

namespace App\Http\Controllers;

use App\Models\PolicyPage;
use Illuminate\Http\Request;

class PolicyPageController extends Controller
{
    public function index()
    {
        $pages = PolicyPage::active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('policy-pages.index', compact('pages'));
    }

    public function show(string $slug)
    {
        $policyPage = PolicyPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $otherPages = PolicyPage::active()
            ->where('id', '!=', $policyPage->id)
            ->orderBy('sort_order')
            ->get();

        return view('policy-pages.show', compact('policyPage', 'otherPages'));
    }
}
