<?php

namespace App\Services\Calculator;

use App\Enums\EquipmentSuitabilityStatus;
use App\Enums\EquipmentType;
use App\Models\Product;
use App\Services\Product\ProductEquipmentTypeResolver;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class EquipmentTypeRecommendationService
{
    public function __construct(
        private readonly ProductEquipmentTypeResolver $types,
        private readonly ProductMarketingCapacityQueryAdapter $capacities,
    ) {}

    /** @return array<string, string> */
    public function options(): array
    {
        return collect(config('hvac_equipment_types.types', []))
            ->filter(fn (array $rule): bool => (bool) ($rule['selectable'] ?? false))
            ->sortBy('sort')
            ->mapWithKeys(fn (array $rule, string $key): array => [$key => (string) $rule['label']])
            ->all();
    }

    /**
     * @param array<string, string|null> $installationAnswers
     * @return array{summary: array<string, mixed>, products: EloquentCollection<int, Product>}
     */
    public function recommend(
        int $targetBtu,
        string $requestedType = EquipmentType::UNSURE->value,
        array $installationAnswers = [],
        string $priority = '',
    ): array {
        $requested = EquipmentType::tryFrom($requestedType) ?? EquipmentType::UNSURE;
        $rules = config('hvac_equipment_types.types', []);
        $catalog = $this->eligibleCatalog();
        $envelopes = $this->catalogEnvelopes($catalog);
        $globalMax = $this->globalVerifiedMax($rules, $envelopes);

        if ($targetBtu > $globalMax) {
            $status = EquipmentSuitabilityStatus::TECHNICAL_CONSULTATION_REQUIRED;
            $products = new EloquentCollection();
            $alternatives = [];
            $reason = 'Nhu cầu tải lạnh đã vượt dải single-unit được xác minh cho các loại máy thông thường trong phạm vi công cụ.';
        } elseif ($requested === EquipmentType::UNSURE) {
            $products = $this->matchingProducts($catalog, null, $targetBtu, $priority);
            $alternatives = $this->alternativeTypes($catalog, $envelopes, $targetBtu, null);
            $status = $products->isEmpty()
                ? EquipmentSuitabilityStatus::NO_MATCHING_PRODUCT
                : EquipmentSuitabilityStatus::INSUFFICIENT_DATA;
            $reason = $products->isEmpty()
                ? 'Catalog hiện chưa có model đủ công suất với type và capacity đã được xác minh.'
                : 'Bạn chưa chọn loại máy. Các lựa chọn bên dưới chỉ là nhóm có model đủ công suất trong catalog; điều kiện lắp đặt vẫn cần được xác nhận.';
        } else {
            $rule = $rules[$requested->value] ?? [];
            $siteEnvelope = $envelopes[$requested->value] ?? $this->emptyEnvelope();
            $decisionMax = max((int) ($rule['market_verified_max_btu'] ?? 0), (int) ($siteEnvelope['max_btu'] ?? 0));
            $answerKey = $rule['installation_question'] ?? null;
            $installationAnswer = $answerKey ? ($installationAnswers[$answerKey] ?? 'unknown') : null;

            if ($decisionMax === 0 || $targetBtu > $decisionMax) {
                $status = EquipmentSuitabilityStatus::NOT_RECOMMENDED_FOR_THIS_LOAD;
                $products = new EloquentCollection();
                $reason = 'Mục tiêu công suất vượt dải single-unit hiện được xác minh cho loại máy bạn chọn.';
            } elseif ($installationAnswer === 'no') {
                $status = EquipmentSuitabilityStatus::NOT_RECOMMENDED_FOR_THIS_LOAD;
                $products = new EloquentCollection();
                $reason = 'Điều kiện lắp đặt đã chọn không đáp ứng yêu cầu cơ bản của loại máy này.';
            } else {
                $products = $this->matchingProducts($catalog, $requested, $targetBtu, $priority);

                if ($products->isEmpty()) {
                    $status = EquipmentSuitabilityStatus::NO_MATCHING_PRODUCT;
                    $reason = 'Dải tham chiếu cho phép xem xét loại máy này, nhưng catalog hiện chưa có model đủ công suất đã qua các gate type/capacity.';
                } elseif (($rule['always_review'] ?? false) || $installationAnswer === 'unknown') {
                    $status = EquipmentSuitabilityStatus::POSSIBLE_BUT_REVIEW_REQUIRED;
                    $reason = 'Công suất có thể phù hợp, nhưng công cụ chưa có đủ dữ liệu để xác nhận thiết kế và điều kiện lắp đặt.';
                } else {
                    $status = EquipmentSuitabilityStatus::SUITABLE_FOR_CONSIDERATION;
                    $reason = 'Có model đúng loại với công suất không thấp hơn mục tiêu. Đây vẫn là gợi ý để xem xét, không phải xác nhận thiết kế lắp đặt.';
                }
            }

            $alternatives = $this->alternativeTypes($catalog, $envelopes, $targetBtu, $requested);
        }

        $nearest = $products
            ->map(fn (Product $product): ?int => $this->capacities->value($product))
            ->filter()
            ->min();
        $requestedRule = $rules[$requested->value] ?? [];

        return [
            'summary' => [
                'requested_type' => $requested->value,
                'requested_type_label' => $requestedRule['label'] ?? $requested->label(),
                'status' => $status->value,
                'status_label' => $status->label(),
                'reason' => $reason,
                'market_reference_envelope' => $this->marketEnvelope($requestedRule),
                'site_catalog_envelope' => $envelopes[$requested->value] ?? $this->emptyEnvelope(),
                'nearest_available_product_btu' => $nearest,
                'catalog_gap_btu' => $nearest === null ? null : max(0, $nearest - $targetBtu),
                'installation_notes' => array_values($requestedRule['installation_notes'] ?? []),
                'alternatives' => $alternatives,
                'technical_consultation_required' => $status === EquipmentSuitabilityStatus::TECHNICAL_CONSULTATION_REQUIRED,
                'brand_neutral' => true,
                'multi_unit_quantity' => null,
                'ai_required' => false,
            ],
            'products' => $products,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function governance(): array
    {
        $catalog = $this->eligibleCatalog();
        $envelopes = $this->catalogEnvelopes($catalog);

        return collect(config('hvac_equipment_types.types', []))
            ->except(EquipmentType::UNSURE->value)
            ->map(function (array $rule, string $key) use ($envelopes): array {
                return [
                    'key' => $key,
                    'label' => $rule['label'],
                    'market' => $this->marketEnvelope($rule),
                    'catalog' => $envelopes[$key] ?? $this->emptyEnvelope(),
                    'confidence' => $rule['confidence'],
                    'status' => $rule['rule_status'],
                    'sources' => $rule['sources'],
                ];
            })
            ->all();
    }

    /** @return EloquentCollection<int, Product> */
    private function eligibleCatalog(): EloquentCollection
    {
        $query = $this->capacities->applyPresent(
            Product::query()->with(['category', 'brand'])->where('is_active', true),
        );

        if (Schema::hasColumn('products', 'stock_status')) {
            $query->where(fn ($q) => $q->whereNull('stock_status')->orWhere('stock_status', '!=', 'out_of_stock'));
        }

        return $query->get()->filter(function (Product $product): bool {
            $resolved = $this->types->resolve($product);

            return $resolved['verified']
                && $resolved['type'] instanceof EquipmentType
                && $this->capacities->value($product) !== null;
        })->values();
    }

    /** @return array<string, array{count: int, min_btu: int|null, max_btu: int|null, tiers: list<int>}> */
    private function catalogEnvelopes(EloquentCollection $catalog): array
    {
        return $catalog
            ->groupBy(fn (Product $product): string => $this->types->resolve($product)['type']->value)
            ->map(function (Collection $products): array {
                $capacities = $products
                    ->map(fn (Product $product): ?int => $this->capacities->value($product))
                    ->filter()
                    ->sort()
                    ->values();

                return [
                    'count' => $products->count(),
                    'min_btu' => $capacities->min(),
                    'max_btu' => $capacities->max(),
                    'tiers' => $capacities->unique()->values()->all(),
                ];
            })
            ->all();
    }

    /** @return EloquentCollection<int, Product> */
    private function matchingProducts(
        EloquentCollection $catalog,
        ?EquipmentType $type,
        int $targetBtu,
        string $priority,
    ): EloquentCollection {
        $maxDelta = (int) config('hvac_equipment_types.model_max_oversize_delta_btu', 12000);

        $products = $catalog
            ->filter(function (Product $product) use ($type, $targetBtu, $maxDelta): bool {
                $capacity = $this->capacities->value($product);
                $resolvedType = $this->types->resolve($product)['type'];

                return ($type === null || $resolvedType === $type)
                    && $capacity !== null
                    && $capacity >= $targetBtu
                    && $capacity <= $targetBtu + $maxDelta;
            })
            ->sortBy(function (Product $product) use ($targetBtu, $priority): array {
                $capacity = $this->capacities->value($product) ?? PHP_INT_MAX;
                $price = $product->sale_price ?? $product->regular_price ?? PHP_INT_MAX;

                return [
                    $capacity - $targetBtu,
                    $priority === 'gia_tot' ? (float) $price : (int) ($product->sort_order ?? 0),
                    (int) $product->id,
                ];
            })
            ->take(5)
            ->values();

        return new EloquentCollection($products->all());
    }

    /** @return list<array<string, mixed>> */
    private function alternativeTypes(
        EloquentCollection $catalog,
        array $envelopes,
        int $targetBtu,
        ?EquipmentType $exclude,
    ): array {
        $rules = config('hvac_equipment_types.types', []);

        return collect($rules)
            ->except(EquipmentType::UNSURE->value)
            ->filter(function (array $rule, string $key) use ($catalog, $envelopes, $targetBtu, $exclude): bool {
                if ($exclude?->value === $key) {
                    return false;
                }

                $type = EquipmentType::tryFrom($key);
                if ($type === null) {
                    return false;
                }

                $decisionMax = max(
                    (int) ($rule['market_verified_max_btu'] ?? 0),
                    (int) ($envelopes[$key]['max_btu'] ?? 0),
                );

                return $targetBtu <= $decisionMax
                    && $this->matchingProducts($catalog, $type, $targetBtu, '')->isNotEmpty();
            })
            ->map(function (array $rule, string $key) use ($catalog, $targetBtu, $envelopes): array {
                $type = EquipmentType::from($key);
                $models = $this->matchingProducts($catalog, $type, $targetBtu, '');
                $nearest = $models->map(fn (Product $product): ?int => $this->capacities->value($product))->filter()->min();

                return [
                    'type' => $key,
                    'label' => $rule['label'],
                    'nearest_btu' => $nearest,
                    'model_count' => $models->count(),
                    'capacity_delta_btu' => $nearest === null ? null : $nearest - $targetBtu,
                    'catalog_envelope' => $envelopes[$key] ?? $this->emptyEnvelope(),
                    'sort' => $rule['sort'],
                ];
            })
            ->sortBy(fn (array $alternative): array => [
                $alternative['capacity_delta_btu'] ?? PHP_INT_MAX,
                $alternative['sort'],
            ])
            ->values()
            ->all();
    }

    private function globalVerifiedMax(array $rules, array $envelopes): int
    {
        return (int) collect($rules)
            ->except(EquipmentType::UNSURE->value)
            ->map(fn (array $rule, string $key): int => max(
                (int) ($rule['market_verified_max_btu'] ?? 0),
                (int) ($envelopes[$key]['max_btu'] ?? 0),
            ))
            ->max();
    }

    /** @return array{min_btu: int|null, common_max_btu: int|null, verified_max_btu: int|null, confidence: string|null} */
    private function marketEnvelope(array $rule): array
    {
        return [
            'min_btu' => $rule['market_min_btu'] ?? null,
            'common_max_btu' => $rule['market_common_max_btu'] ?? null,
            'verified_max_btu' => $rule['market_verified_max_btu'] ?? null,
            'confidence' => $rule['confidence'] ?? null,
        ];
    }

    /** @return array{count: int, min_btu: null, max_btu: null, tiers: array} */
    private function emptyEnvelope(): array
    {
        return ['count' => 0, 'min_btu' => null, 'max_btu' => null, 'tiers' => []];
    }
}
