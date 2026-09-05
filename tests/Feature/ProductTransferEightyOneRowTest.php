<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ProductTransferContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductTransferEightyOneRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_data_only_81_row_workbook_fails_strict_provenance_without_writes(): void
    {
        $category = ProductCategory::factory()->create(['technical_schema_status'=>'missing']);
        $book = new Spreadsheet(); $sheet=$book->getActiveSheet();
        $sheet->fromArray([['name','sku','product_category_id','technical_capacity_btu']]);
        for ($i=1;$i<=81;$i++) $sheet->fromArray([["Legacy Gree {$i}","LEGACY-GREE-{$i}",$category->id,18000]], null, 'A'.($i+1));
        $path=storage_path('app/private/legacy-81.xlsx'); IOFactory::createWriter($book,'Xlsx')->save($path); $book->disconnectWorksheets();
        $job=app(DataImportService::class)->uploadAndPreview('product',$path,'legacy-81.xlsx','xlsx','create','sku');
        $this->assertSame('create',$job->mode); $this->assertSame(81,$job->failed_rows); $this->assertNull($job->format_context_json);
        $result=app(DataImportService::class)->confirmImport($job);
        $this->assertSame('failed',$result->status); $this->assertSame(81,$result->failed_rows); $this->assertSame(0,Product::count());
        unlink($path); Storage::disk('local')->delete($job->file_path);
    }

    public function test_signed_81_row_transfer_maps_different_fk_ids_and_preserves_capacity_fields(): void
    {
        $brand=Brand::factory()->create(['slug'=>'dieu-hoa-gree']); $category=ProductCategory::factory()->create(['slug'=>'gree-transfer-category','technical_schema_status'=>'missing']);
        for($i=1;$i<=81;$i++) Product::factory()->create(['brand_id'=>$brand->id,'product_category_id'=>$category->id,'sku'=>"GREE-T-{$i}",'slug'=>"gree-t-{$i}",'marketing_capacity_btu'=>$i%2?18000:24000,'technical_capacity_btu'=>17000+$i,'capacity_kw'=>'5.20','specs_json'=>$i===1?[['key'=>'evidence','value'=>str_repeat('x',32000)]]:[]]);
        $ids=Product::pluck('id')->all();
        $export=app(DataExportService::class)->export('product','xlsx',[],[],$ids,'selected',null,'product_transfer'); $path=storage_path('app/private/'.$export->file_path);
        $book=IOFactory::load($path); $this->assertNotNull($book->getSheetByName(ProductTransferContract::METADATA_SHEET)); $this->assertNotNull($book->getSheetByName(ProductTransferContract::PAYLOAD_SHEET)); $book->disconnectWorksheets();
        Product::query()->forceDelete(); $brand->forceDelete(); $category->forceDelete();
        $targetBrand=Brand::factory()->create(['id'=>201,'slug'=>'dieu-hoa-gree']); $targetCategory=ProductCategory::factory()->create(['id'=>202,'slug'=>'gree-transfer-category','technical_schema_status'=>'missing']);
        $job=app(DataImportService::class)->uploadAndPreview('product',$path,'gree-transfer-81.xlsx','xlsx');
        $this->assertSame('product_transfer',$job->mode); $this->assertSame(81,$job->success_rows); $this->assertSame(0,$job->failed_rows);
        $result=app(DataImportService::class)->confirmImport($job); $this->assertSame('completed',$result->status); $this->assertSame(81,$result->created_rows);
        $this->assertSame(81,Product::count()); $this->assertSame(81,Product::where('brand_id',$targetBrand->id)->where('product_category_id',$targetCategory->id)->count());
        $this->assertSame(81,Product::whereNotNull('marketing_capacity_btu')->whereNotNull('technical_capacity_btu')->whereNotNull('capacity_kw')->count());
        Storage::disk('local')->delete([$export->file_path,$job->file_path]);
    }
}
