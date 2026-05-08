<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductCompareSpecService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeProductSpecs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:normalize-specs {--force : Ghi đè nếu column đã có dữ liệu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chuẩn hóa specs_json và backfill vào các column cứng của products';

    /**
     * Execute the console command.
     */
    public function handle(ProductCompareSpecService $service)
    {
        $this->info('Bắt đầu chuẩn hóa specs_json...');
        $products = Product::whereNotNull('specs_json')->get();
        
        $updatedCount = 0;
        $force = $this->option('force');

        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        foreach ($products as $product) {
            $specs = $service->normalizeSpecs($product->specs_json);
            if (empty($specs)) {
                $bar->advance();
                continue;
            }

            $updates = [];

            // Mapping từ spec sang column
            $mapping = [
                'btu' => 'btu',
                'voltage' => 'voltage',
                'refrigerant_gas' => 'refrigerant_gas',
                'power_consumption' => 'power_consumption',
                'airflow' => 'airflow',
                'noise_level' => 'noise_level',
                'indoor_dimensions' => 'indoor_dimensions',
                'outdoor_dimensions' => 'outdoor_dimensions',
                'weight' => 'weight',
            ];

            foreach ($mapping as $specKey => $column) {
                if (isset($specs[$specKey]) && !empty($specs[$specKey])) {
                    // Nếu force, hoặc nếu DB đang trống
                    if ($force || empty($product->{$column})) {
                        $updates[$column] = $specs[$specKey];
                        // Nếu là btu, thử lấy chỉ số từ chuỗi
                        if ($specKey === 'btu') {
                            preg_match('/\d+[\d\.,]*/', $specs[$specKey], $matches);
                            if (isset($matches[0])) {
                                $updates['btu'] = (int)str_replace([',', '.'], '', $matches[0]);
                            }
                        }
                    }
                }
            }

            if (!empty($updates)) {
                DB::table('products')->where('id', $product->id)->update($updates);
                $updatedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Đã chuẩn hóa và backfill thành công {$updatedCount} sản phẩm.");
    }
}
