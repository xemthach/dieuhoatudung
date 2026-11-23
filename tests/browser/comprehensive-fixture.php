<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || basename(config('database.connections.sqlite.database')) !== 'product-data-governance-browser-isolated.sqlite') {
    throw new RuntimeException('Isolated browser database required.');
}
$stateFile = storage_path('framework/testing/comprehensive-browser.json');
$mode = $argv[1] ?? 'setup';
if ($mode === 'snapshot') {
    $state = json_decode(file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode([
        'price'=>App\Models\Product::findOrFail($state['product_id'])->regular_price,
        'draft'=>App\Models\AiProductDraft::findOrFail($state['draft_id'])->only(['approval_status', 'applied_at']),
        'reviews'=>App\Models\DealerReview::where('dealer_id', $state['dealer_id'])->count(),
        'layout'=>app(App\Services\Settings\SettingService::class)->get('display.homepage_section_order'),
    ], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'cleanup') {
    if (is_file($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true);
        App\Models\Dealer::whereKey($state['dealer_id'])->forceDelete();
        $draft = App\Models\AiProductDraft::find($state['draft_id']);
        if ($draft) {
            App\Models\AiProductJobItem::where('draft_id', $draft->id)->update(['draft_id'=>null]);
            $jobId = $draft->job_id;
            $draft->delete();
            App\Models\AiProductJobItem::where('ai_product_job_id', $jobId)->delete();
            App\Models\AiProductJob::whereKey($jobId)->delete();
        }
        App\Models\Product::whereKey($state['product_id'])->forceDelete();
        App\Models\ProductCategory::whereKey($state['category_id'])->forceDelete();
        foreach (array_reverse($state['created_units'] ?? []) as $id) App\Models\AdministrativeUnit::whereKey($id)->delete();
        // The seeded operator account belongs to the isolated browser database, not this fixture.
    }
    @unlink($stateFile);
    echo '{}';
    exit;
}
$actor = App\Models\User::findOrFail((int) config('ai.single_operator.operator_user_id', 1));
$password = (string) env('ADMIN_PASSWORD', 'ChangeMe!2024');
$category = App\Models\ProductCategory::factory()->create(['name'=>'Browser Business Category', 'slug'=>'browser-business-category', 'is_active'=>true, 'technical_schema_status'=>'active', 'technical_schema_json'=>['fields'=>[['key'=>'btu', 'label'=>'BTU', 'type'=>'number']]]]);
$product = App\Models\Product::factory()->create(['name'=>'Browser Comprehensive Product', 'sku'=>'COMPREHENSIVE-001', 'brand_id'=>App\Models\Brand::where('is_active',true)->firstOrFail()->id, 'product_category_id'=>$category->id, 'is_active'=>true, 'regular_price'=>123456789, 'sale_price'=>null]);
$createdUnits = [];
$province = App\Models\AdministrativeUnit::where('scheme','current')->where('kind','province')->first();
if (! $province) {
    $province = App\Models\AdministrativeUnit::create(['scheme'=>'current', 'dataset_version'=>config('dealers.current_version'), 'kind'=>'province', 'code'=>'cxp', 'name'=>'Province', 'full_name'=>'Province', 'slug'=>'comprehensive-province']);
    $createdUnits[] = $province->id;
}
$ward = App\Models\AdministrativeUnit::where('scheme','current')->where('kind','ward')->where('parent_id',$province->id)->first();
if (! $ward) {
    $ward = App\Models\AdministrativeUnit::create(['scheme'=>'current', 'dataset_version'=>config('dealers.current_version'), 'kind'=>'ward', 'code'=>'cxw', 'name'=>'Ward', 'full_name'=>'Ward', 'slug'=>'comprehensive-ward', 'parent_id'=>$province->id]);
    $createdUnits[] = $ward->id;
}
$dealer = App\Models\Dealer::create(['name'=>'Browser Comprehensive Dealer', 'province_id'=>$province->id, 'ward_id'=>$ward->id, 'address_line'=>'123 Test Street', 'is_active'=>true, 'review_enabled'=>true, 'reviews_visible'=>true]);
$job = App\Models\AiProductJob::create(['type'=>'product_content', 'scope'=>'selected', 'status'=>'needs_review', 'created_by'=>$actor->id]);
$payload = ['content_html'=>'<h2>Nội dung đã kiểm chứng</h2><p>Mô tả sản phẩm dùng cho kiểm thử duyệt và áp dụng.</p>', 'fact_check'=>['status'=>'passed','blocked_claims'=>[]], 'blocked_claims'=>[]];
$draft = App\Models\AiProductDraft::create(['job_id'=>$job->id, 'product_id'=>$product->id, 'module'=>'ai_product', 'normalized_output_json'=>$payload, 'raw_output_json'=>$payload, 'status'=>'needs_review', 'warnings_json'=>[], 'validation_errors_json'=>[], 'field_status_json'=>['content_html'=>'passed'], 'token_usage_json'=>['technical_context_hash'=>app(App\Services\Product\AIProductContentSystem::class)->technicalContextHash($product)]]);
App\Models\AiProductJobItem::create(['ai_product_job_id'=>$job->id, 'product_id'=>$product->id, 'draft_id'=>$draft->id, 'status'=>'needs_review', 'canonical_status'=>'REVIEW_REQUIRED', 'generated_payload_json'=>$payload]);
$operator = App\Models\User::findOrFail((int) config('ai.single_operator.operator_user_id', 1));
$state = ['email'=>$actor->email, 'password'=>$password, 'operator_email'=>$operator->email, 'operator_password'=>(string) env('ADMIN_PASSWORD', 'ChangeMe!2024'), 'product_id'=>$product->id, 'category_id'=>$category->id, 'created_units'=>$createdUnits, 'confirmation'=>'APPLY '.$product->model_code.'#'.$product->id, 'draft_id'=>$draft->id, 'dealer_id'=>$dealer->id, 'dealer_url'=>route('dealers.show',$dealer->slug)];
file_put_contents($stateFile, json_encode($state, JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
