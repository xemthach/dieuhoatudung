<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductBulkTechnicalService
{
    public const FIELDS = [
        'technical_capacity_btu' => ['Công suất BTU', 'integer|min:1|max:2000000'],
        'capacity_kw' => ['Công suất kW', 'numeric|min:0.01|max:999999.99|decimal:0,2'],
        'hp' => ['Mã lực HP', 'numeric|min:0.1|max:999.9|decimal:0,1'],
        'inverter' => ['Inverter', 'boolean'],
        'cooling_type' => ['Kiểu làm lạnh', 'in:1_chieu,2_chieu'],
        'voltage' => ['Điện áp', 'string|max:255'],
        'phase' => ['Pha', 'string|max:100'],
        'frequency' => ['Tần số', 'string|max:100'],
        'refrigerant_gas' => ['Loại gas', 'string|max:255'],
        'power_consumption' => ['Điện năng tiêu thụ', 'string|max:255'],
        'airflow' => ['Lưu lượng gió', 'string|max:255'],
        'noise_level' => ['Độ ồn', 'string|max:255'],
        'recommended_area' => ['Diện tích đề nghị', 'string|max:255'],
        'indoor_dimensions' => ['Kích thước dàn lạnh', 'string|max:255'],
        'outdoor_dimensions' => ['Kích thước dàn nóng', 'string|max:255'],
        'weight' => ['Trọng lượng', 'string|max:255'],
    ];

    public function __construct(private ProductTechnicalSpecWriter $writer, private ProductTechnicalAudit $audit) {}

    public function preview(array $ids, array $data, User $actor): array
    {
        $this->authorize($actor);
        $rules = ['reason' => 'required|string|max:1000', 'fields' => 'array',
            'extras' => 'array|max:50', 'extras.*.operation' => 'required|in:ADD_OR_UPDATE,REMOVE',
            'extras.*.key' => 'required|string|max:120',
            'extras.*.value' => 'nullable|string|max:1000'];
        foreach (self::FIELDS as $field => [$label, $rule]) {
            $rules["fields.{$field}"] = 'nullable|array';
            $rules["fields.{$field}.operation"] = 'nullable|in:keep,set,clear';
        }
        Validator::make($data, $rules)->validate();
        $patch = [];
        foreach ($data['fields'] ?? [] as $field => $command) {
            if (! isset(self::FIELDS[$field])) {
                throw ValidationException::withMessages(['fields' => 'Trường kỹ thuật không hợp lệ.']);
            }
            $operation = $command['operation'] ?? 'keep';
            if ($operation === 'keep') {
                continue;
            }
            if ($operation === 'clear') {
                if ($field === 'inverter') {
                    throw ValidationException::withMessages(['fields' => 'Inverter cần chọn Có hoặc Không.']);
                }
                $patch[$field] = null;
            } else {
                Validator::make(['value' => $command['value'] ?? null],
                    ['value' => 'required|'.self::FIELDS[$field][1]])->validate();
                $patch[$field] = $command['value'];
            }
        }
        if ($patch === [] && empty($data['extras'])) {
            throw ValidationException::withMessages(['fields' => 'Chọn ít nhất một trường hoặc thông số mở rộng.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $products = Product::whereKey($ids)->with('category')->orderBy('id')->get();
        if ($ids === [] || $products->count() !== count($ids)) {
            throw ValidationException::withMessages(['fields' => 'Selection chứa sản phẩm đã xóa hoặc không còn tồn tại.']);
        }
        $plan = ['id' => (string) Str::uuid(), 'actor' => $actor->id, 'ids' => $ids,
            'patch' => $patch, 'extras' => $data['extras'] ?? [], 'reason' => trim($data['reason']),
            'hashes' => [], 'preview' => [], 'expires' => now()->addMinutes(30)->timestamp];
        foreach ($products as $product) {
            $plan['hashes'][$product->id] = $this->fingerprint($product);
            $changes = $this->changes($product, $plan);
            if (count($plan['preview']) < 10) {
                $plan['preview'][] = ['id' => $product->id, 'model' => $product->model_code ?: $product->sku,
                    'before' => $product->only(array_keys($changes)), 'after' => $changes];
            }
        }
        session()->put('technical_bulk.'.$plan['id'], $plan);
        return $plan;
    }

    public function apply(string $token, array $selection, User $actor, bool $confirmed): int
    {
        $this->authorize($actor);
        $plan = session()->get('technical_bulk.'.$token);
        $selection = array_values(array_unique(array_map('intval', $selection)));
        sort($selection);
        if (! $confirmed || ! $plan || $plan['actor'] !== $actor->id
            || $plan['expires'] < now()->timestamp || $plan['ids'] !== $selection) {
            throw ValidationException::withMessages(['confirmed' => 'Tạo lại bản xem trước và xác nhận đúng selection.']);
        }
        $count = DB::transaction(function () use ($plan, $actor) {
            $products = Product::whereKey($plan['ids'])->with('category')->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($plan['ids'])) {
                throw ValidationException::withMessages(['confirmed' => 'Sản phẩm đã xóa. Tạo lại bản xem trước.']);
            }
            if (ProductDataGovernanceEvent::where('batch_uuid', $plan['id'])->where('domain', 'TECHNICAL_MANUAL')->exists()) {
                return $products->count();
            }
            $before = [];
            $after = [];
            foreach ($products as $product) {
                if (! hash_equals($plan['hashes'][$product->id], $this->fingerprint($product))) {
                    throw ValidationException::withMessages(['confirmed' => 'Sản phẩm #'.$product->id.' đã thay đổi. Tạo lại bản xem trước.']);
                }
                $changes = $this->changes($product, $plan);
                $before[$product->id] = $product->only(array_keys($changes));
                if (! $product->fill($changes)->saveOrFail()) {
                    throw ValidationException::withMessages(['confirmed' => 'Không thể ghi sản phẩm #'.$product->id]);
                }
                $after[$product->id] = $product->only(array_keys($changes));
            }
            $this->audit->record($actor, $plan['ids'], 'BULK_TECHNICAL_UPDATE', $before, $after,
                $plan['reason'], ['patch' => $plan['patch'], 'extras' => $plan['extras']], $plan['id']);
            DB::afterCommit(fn () => Cache::forever('search:rated:revision', (string) Str::uuid()));
            return $products->count();
        });
        session()->forget('technical_bulk.'.$token);
        return $count;
    }

    private function changes(Product $product, array $plan): array
    {
        $changes = $this->writer->manualOverrideAttributes($product, $plan['patch'], $plan['reason']);
        if (array_key_exists('technical_capacity_btu', $plan['patch'])) {
            $changes['technical_capacity_status'] = 'manual_override';
        }
        $cleared = array_keys(array_filter($plan['patch'], static fn ($v) => $v === null));
        if ($cleared !== []) {
            $changes = array_replace($changes, $this->writer->manualClearAttributes(
                $product, $changes['specs_json'] ?? $product->specs_json ?? [], $cleared, $plan['reason']));
        }
        if ($plan['patch'] !== []) {
            $changes['specs_json'] = $this->writer->scopedManualAttributes(
                $product, $changes['specs_json'] ?? $product->specs_json ?? [], $plan['patch']);
            $changes['technical_specs_source'] = 'manual_override';
            $changes['technical_specs_override_reason'] = $plan['reason'];
            $changes['technical_specs_overridden_at'] = now();
        }
        if ($plan['extras'] !== []) {
            $category = $product->category;
            $aliases = $category ? app(CategoryTechnicalSchemaService::class)->aliasMap($category) : [];
            $specs = $changes['specs_json'] ?? $product->specs_json ?? [];
            $manual = $this->writer->manualRows($specs);
            foreach ($plan['extras'] as $command) {
                $key = Str::slug($command['key'], '_');
                $key = $aliases[$key] ?? $key;
                if (! $this->writer::publicExtraAllowed($key, [])) {
                    throw ValidationException::withMessages(['extras' => 'Thông số trùng trường chuẩn hoặc không được công khai.']);
                }
                $manual = array_values(array_filter($manual, static function ($row) use ($key, $aliases) {
                    $name = Str::slug($row['key'], '_');
                    return ($aliases[$name] ?? $name) !== $key;
                }));
                if ($command['operation'] === 'ADD_OR_UPDATE') {
                    if (blank($command['value'] ?? null)) {
                        throw ValidationException::withMessages(['extras' => 'Nhập giá trị cho thao tác Thêm / cập nhật.']);
                    }
                    $manual[] = ['key' => trim($command['key']), 'value' => trim($command['value'])];
                }
            }
            $changes['specs_json'] = $this->writer->mergeManualRows($specs, $manual, $aliases);
        }
        return $changes;
    }

    private function fingerprint(Product $product): string
    {
        return hash('sha256', json_encode($product->getRawOriginal()));
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_active && $actor->can('product.edit'), 403);
    }
}
