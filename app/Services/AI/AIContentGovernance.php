<?php

namespace App\Services\AI;

use App\Models\AiContentJob;
use App\Models\Product;
use App\Services\Calculator\BtuCalculatorService;
use App\Support\IssueList;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AIContentGovernance
{
    public const PROMPT_VERSION = 'ai-content-governance-v1';

    private const NUMERIC_UNITS = [
        'btu', 'kw', 'hp', 'm2', 'm²', 'db', 'pa', 'mm', 'kg', 'w', 'a', 'vnd',
    ];

    private const TECHNICAL_FIELDS = [
        'btu' => 'missing_btu',
        'capacity_kw' => 'missing_capacity_kw',
        'hp' => 'missing_hp',
        'airflow' => 'missing_airflow',
        'noise_level' => 'missing_noise_level',
        'refrigerant_gas' => 'missing_refrigerant',
        'voltage' => 'missing_phase',
        'indoor_dimensions' => 'missing_indoor_dimensions',
        'outdoor_dimensions' => 'missing_outdoor_dimensions',
        'weight' => 'missing_weight',
        'recommended_area' => 'missing_recommended_area',
    ];

    private const FORBIDDEN_CLAIMS = [
        'tot_nhat' => ['pattern' => '/\btot nhat\b/u', 'source_key' => 'policy.best_claim'],
        're_nhat' => ['pattern' => '/\bre nhat\b/u', 'source_key' => 'policy.price_claim'],
        'tiet_kiem_nhat' => ['pattern' => '/\btiet kiem nhat\b/u', 'source_key' => 'policy.energy_claim'],
        'vuot_troi' => ['pattern' => '/\bvuot troi\b/u', 'source_key' => 'policy.performance_claim'],
        'chinh_hang' => ['pattern' => '/\bchinh hang\b/u', 'source_key' => 'policy.official_goods'],
        'bao_hanh' => ['pattern' => '/\bbao hanh\b/u', 'source_key' => 'product.warranty_info'],
        'mien_phi' => ['pattern' => '/\bmien phi\b/u', 'source_key' => 'policy.free_claim'],
        'co_cq' => ['pattern' => '/\b(co\/cq|co cq)\b/u', 'source_key' => 'policy.co_cq'],
        'vat' => ['pattern' => '/\bvat\b/u', 'source_key' => 'policy.vat'],
        'percent_100' => ['pattern' => '/100\s*%/u', 'source_key' => 'policy.percent_100'],
    ];

    private const INTERNAL_LANGUAGE_PATTERNS = [
        'internal_class_name' => '/\b[A-Z][a-z]+(?:[A-Z][A-Za-z0-9]+)+(?:Service|Controller|Model|Repository|Provider|Gateway|Adapter)?\b/u',
        'internal_layer_name' => '/\b[A-Za-z0-9_]*(?:Service|Controller|Model|Repository|Provider|Gateway|Adapter)\b/u',
        'internal_function_name' => '/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(\s*\)/u',
        'internal_variable_path' => '/\b(?:product|post|blog|config|request|response|job|payload|input|output)\.[a-zA-Z_][a-zA-Z0-9_.]*\b/iu',
        'raw_variable' => '/(?:\{\{|\}\}|\$[a-zA-Z_][a-zA-Z0-9_]*)/u',
    ];

    public function buildProductContext(Product $product, array $contentTask = []): array
    {
        $product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);

        $allowedFacts = [];
        $this->addFact($allowedFacts, 'product.id', $product->id, 'products.id');
        $this->addFact($allowedFacts, 'product.name', $product->name, 'products.name');
        $this->addFact($allowedFacts, 'product.slug', $product->slug, 'products.slug');
        $this->addFact($allowedFacts, 'product.model_code', $product->model_code, 'products.model_code');
        $this->addFact($allowedFacts, 'product.sku', $product->sku, 'products.sku');
        $this->addFact($allowedFacts, 'product.brand', $product->brand?->name, 'brands.name');
        $this->addFact($allowedFacts, 'product.category', $product->category?->name, 'product_categories.name');
        $this->addFact($allowedFacts, 'product.capacity_btu', $product->btu, 'products.btu');
        $this->addFact($allowedFacts, 'product.capacity_kw', $product->capacity_kw, 'products.capacity_kw');
        $this->addFact($allowedFacts, 'product.hp', $product->hp, 'products.hp');
        $this->addFact($allowedFacts, 'product.cooling_type', $product->cooling_type, 'products.cooling_type');
        $this->addFact($allowedFacts, 'product.inverter', $product->inverter, 'products.inverter');
        $this->addFact($allowedFacts, 'product.phase', $product->voltage, 'products.voltage');
        $this->addFact($allowedFacts, 'product.refrigerant', $product->refrigerant_gas, 'products.refrigerant_gas');
        $this->addFact($allowedFacts, 'product.airflow', $product->airflow, 'products.airflow');
        $this->addFact($allowedFacts, 'product.noise_level', $product->noise_level, 'products.noise_level');
        $this->addFact($allowedFacts, 'product.indoor_dimensions', $product->indoor_dimensions, 'products.indoor_dimensions');
        $this->addFact($allowedFacts, 'product.outdoor_dimensions', $product->outdoor_dimensions, 'products.outdoor_dimensions');
        $this->addFact($allowedFacts, 'product.weight', $product->weight, 'products.weight');
        $this->addFact($allowedFacts, 'product.recommended_area', $product->recommended_area, 'products.recommended_area');
        $this->addFact($allowedFacts, 'product.warranty_info', strip_tags((string) $product->warranty_info), 'products.warranty_info');
        $this->addFact($allowedFacts, 'product.regular_price', $product->regular_price, 'products.regular_price');
        $this->addFact($allowedFacts, 'product.sale_price', $product->sale_price, 'products.sale_price');

        foreach ($this->flattenSpecs($product->specs_json ?? []) as $index => $spec) {
            $this->addFact(
                $allowedFacts,
                'product.technical_specs_json.'.$index,
                trim($spec['label'].' '.$spec['value']),
                'products.specs_json'
            );
        }

        $missingFacts = $this->missingProductFacts($product);
        if (! $this->hasVerifiedSourcePage($product)) {
            $missingFacts[] = 'no_verified_source_page';
        }

        return [
            'prompt_version' => self::PROMPT_VERSION,
            'allowed_facts' => $allowedFacts,
            'missing_facts' => array_values(array_unique($missingFacts)),
            'calculation_rules' => [
                'source' => 'verified_hvac_calculation_rules',
                'specific_btu_result_allowed' => false,
                'rule' => 'Do not calculate BTU unless a verified calculation result is provided in allowed facts.',
            ],
            'forbidden_claims' => array_keys(self::FORBIDDEN_CLAIMS),
            'content_task' => $contentTask,
            'data_completeness' => $this->dataCompleteness($product),
        ];
    }

    public function buildBlogContext(AiContentJob $job, array $input = []): array
    {
        $payload = is_array($job->input_payload) ? $job->input_payload : [];
        $product = $input['product'] ?? null;

        $context = $product instanceof Product
            ? $this->buildProductContext($product, [
                'type' => 'blog_content',
                'topic' => $input['topic'] ?? $job->topic,
                'category' => $input['category'] ?? null,
                'intent' => $input['intent'] ?? null,
            ])
            : [
                'prompt_version' => self::PROMPT_VERSION,
                'allowed_facts' => [],
                'missing_facts' => [],
                'calculation_rules' => [],
                'forbidden_claims' => array_keys(self::FORBIDDEN_CLAIMS),
                'content_task' => [
                    'type' => 'blog_content',
                    'topic' => $input['topic'] ?? $job->topic,
                    'category' => $input['category'] ?? null,
                    'intent' => $input['intent'] ?? null,
                ],
                'data_completeness' => ['score' => 0, 'missing_fields' => []],
            ];

        $this->addFact($context['allowed_facts'], 'blog.topic', $job->topic, 'ai_content_jobs.topic');
        $this->addFact($context['allowed_facts'], 'blog.primary_keyword', $job->primary_keyword, 'ai_content_jobs.primary_keyword');
        $this->addFact($context['allowed_facts'], 'blog.category', $job->postCategory?->name, 'post_categories.name');
        $this->addFact($context['allowed_facts'], 'blog.admin_payload', Arr::except($payload, ['api_key', 'secret', 'token']), 'ai_content_jobs.input_payload');

        $btuInputs = $payload['btu_inputs'] ?? [];
        $btuRequired = $this->mentionsBtuCalculation(implode(' ', [
            $job->topic,
            $job->primary_keyword,
            $input['category'] ?? '',
            $payload['topic'] ?? '',
        ]));

        if ($btuRequired) {
            $required = ['area_m2', 'ceiling_height', 'space_type'];
            $missing = array_values(array_filter($required, fn ($key) => blank($btuInputs[$key] ?? null)));
            if ($missing !== []) {
                $context['missing_facts'][] = 'missing_btu_inputs';
                $context['calculation_rules'] = [
                    'source' => 'verified_hvac_calculation_rules',
                    'specific_btu_result_allowed' => false,
                    'missing_inputs' => $missing,
                ];
            } elseif (! array_key_exists((string) $btuInputs['space_type'], BtuCalculatorService::spaceTypeOptions())) {
                $context['missing_facts'][] = 'missing_btu_rule';
                $context['calculation_rules'] = [
                    'source' => 'verified_hvac_calculation_rules',
                    'specific_btu_result_allowed' => false,
                    'unsupported_space_type' => (string) $btuInputs['space_type'],
                ];
            } else {
                $result = app(BtuCalculatorService::class)->calculate(
                    (float) $btuInputs['area_m2'],
                    (float) $btuInputs['ceiling_height'],
                    (string) $btuInputs['space_type'],
                    (int) ($btuInputs['people'] ?? 0),
                    (bool) ($btuInputs['sunlight'] ?? false),
                    (bool) ($btuInputs['heat_equipment'] ?? false),
                );
                $this->addFact($context['allowed_facts'], 'btu_calculator.calculated_btu', $result['calculated_btu'], 'verified_hvac_calculation');
                $this->addFact($context['allowed_facts'], 'btu_calculator.recommended_btu', $result['recommended_btu'], 'verified_hvac_calculation');
                $this->addFact($context['allowed_facts'], 'btu_calculator.recommended_hp', $result['recommended_hp'], 'verified_hvac_calculation');
                $context['calculation_rules'] = [
                    'source' => 'verified_hvac_calculation_rules',
                    'specific_btu_result_allowed' => true,
                    'inputs' => $btuInputs,
                ];
            }
        }

        $context['missing_facts'] = array_values(array_unique($context['missing_facts']));

        return $context;
    }

    public function validatePayload(array $payload, array $context, array $contentKeys): array
    {
        $text = '';
        foreach ($contentKeys as $key) {
            $value = Arr::get($payload, $key);
            if (is_string($value)) {
                $text .= "\n".$value;
            }
        }

        foreach ((array) Arr::get($payload, 'faq', []) as $faq) {
            if (is_array($faq)) {
                $text .= "\n".($faq['question'] ?? '').' '.($faq['answer'] ?? '');
            }
        }

        return $this->validateText($text, $context, (array) Arr::get($payload, 'used_facts', []));
    }

    public function publicContext(array $context): array
    {
        $facts = [];

        foreach ($context['allowed_facts'] ?? [] as $key => $fact) {
            $facts[] = [
                'name' => $this->humanFactName((string) $key),
                'value' => $fact['value'] ?? null,
                'source' => 'dữ liệu đã xác minh',
            ];
        }

        return [
            'allowed_facts' => $facts,
            'missing_facts' => $context['missing_facts'] ?? [],
            'calculation_notes' => [
                'specific_btu_result_allowed' => (bool) Arr::get($context, 'calculation_rules.specific_btu_result_allowed', false),
                'missing_inputs' => Arr::get($context, 'calculation_rules.missing_inputs', []),
            ],
            'forbidden_claims' => $context['forbidden_claims'] ?? [],
            'content_task' => $context['content_task'] ?? [],
            'data_completeness' => $context['data_completeness'] ?? [],
        ];
    }

    public function validateText(string $html, array $context, array $usedFacts = []): array
    {
        $plain = $this->plainText($html);
        $ascii = Str::ascii(Str::lower($plain));
        $allowedNumbers = $this->allowedNumbers($context);
        $allowedRanges = $this->allowedNumberRanges($context);

        $warnings = [];
        $blockedClaims = [];
        $used = $this->validateUsedFacts($usedFacts, $context, $warnings);

        if (preg_match_all('/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(BTU|kW|HP|m2|m²|dB|Pa|mm|kg|W|A)\b/iu', $plain, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (preg_match('/[A-Za-z]'.preg_quote(trim($match[0]), '/').'\b/u', $plain)) {
                    continue;
                }

                $unit = Str::lower($match[2]);
                $normalizedUnit = $unit === 'm²' ? 'm2' : $unit;
                $number = $this->normalizeNumber($match[1]);

                if (! $this->numberAllowed($number, $normalizedUnit, $allowedNumbers, $allowedRanges)) {
                    $claim = trim($match[0]);
                    $warnings[] = 'unverified_numeric_claim:'.$claim;
                    $blockedClaims[] = 'unverified_numeric_claim:'.$claim;
                }
            }
        }

        if (preg_match_all('/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(VND|VNĐ|đ|dong)\b/iu', $plain, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = $this->normalizeNumber($match[1]);
                if (! $this->numberAllowed($number, 'vnd', $allowedNumbers, $allowedRanges)) {
                    $claim = trim($match[0]);
                    $warnings[] = 'unverified_price_claim:'.$claim;
                    $blockedClaims[] = 'unverified_price_claim:'.$claim;
                }
            }
        }

        foreach (self::FORBIDDEN_CLAIMS as $code => $rule) {
            if (preg_match($rule['pattern'], $ascii) && ! $this->hasAllowedFact($context, $rule['source_key'])) {
                $warnings[] = 'blocked_claim:'.$code;
                $blockedClaims[] = $code;
            }
        }

        foreach (self::INTERNAL_LANGUAGE_PATTERNS as $code => $pattern) {
            if (preg_match($pattern, $plain)) {
                $warnings[] = 'internal_language_detected:'.$code;
                $blockedClaims[] = $code;
            }
        }

        if (in_array('missing_airflow', $context['missing_facts'] ?? [], true)
            && preg_match('/\b(luu luong gio|gio manh|thoi gio manh|phan phoi gio manh)\b/u', $ascii)) {
            $warnings[] = 'missing_airflow';
            $blockedClaims[] = 'airflow_claim_without_source';
        }

        if (in_array('missing_noise_level', $context['missing_facts'] ?? [], true)
            && preg_match('/\b(em ai|do on thap|van hanh em)\b/u', $ascii)) {
            $warnings[] = 'missing_noise_level';
            $blockedClaims[] = 'noise_claim_without_source';
        }

        return [
            'status' => $blockedClaims === [] ? 'verified' : 'blocked',
            'warnings' => IssueList::normalize($warnings),
            'blocked_claims' => IssueList::normalize($blockedClaims),
            'used_facts' => IssueList::normalize($used),
            'calculation_source' => Arr::get($context, 'calculation_rules.specific_btu_result_allowed')
                ? 'verified_hvac_calculation'
                : null,
        ];
    }

    public function dataCompleteness(Product $product): array
    {
        $missing = $this->missingProductFacts($product);
        $total = count(self::TECHNICAL_FIELDS);
        $score = (int) round((($total - count($missing)) / max(1, $total)) * 100);

        return [
            'score' => max(0, min(100, $score)),
            'missing_fields' => $missing,
        ];
    }

    private function addFact(array &$facts, string $key, mixed $value, string $source): void
    {
        if (blank($value) && $value !== 0 && $value !== false) {
            return;
        }

        $facts[$key] = [
            'value' => $value,
            'source' => $source,
        ];
    }

    private function missingProductFacts(Product $product): array
    {
        $missing = [];
        foreach (self::TECHNICAL_FIELDS as $field => $warning) {
            if (blank($product->{$field}) && $product->{$field} !== 0) {
                $missing[] = $warning;
            }
        }

        if (blank($product->warranty_info)) {
            $missing[] = 'missing_warranty_policy';
        }

        if (blank($product->sale_price) && blank($product->regular_price)) {
            $missing[] = 'missing_price';
        }

        if (! $this->hasVerifiedSourcePage($product)) {
            $missing[] = 'no_verified_source_page';
        }

        return array_values(array_unique($missing));
    }

    private function hasVerifiedSourcePage(Product $product): bool
    {
        $specs = $this->flattenSpecs($product->specs_json ?? []);

        foreach ($specs as $spec) {
            $label = Str::lower($spec['label']);
            if (Str::contains($label, ['source_catalogue', 'source page', 'source_page', 'catalogue'])) {
                return true;
            }
        }

        return filled($product->source_catalogue ?? null) || filled($product->source_page ?? null);
    }

    private function flattenSpecs(array $specs): array
    {
        return collect($specs)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                return [
                    'label' => (string) ($item['label'] ?? $item['key'] ?? $item['name'] ?? ''),
                    'value' => (string) ($item['value'] ?? $item['text'] ?? ''),
                ];
            })
            ->filter(fn ($item) => $item['label'] !== '' || $item['value'] !== '')
            ->values()
            ->all();
    }

    private function mentionsBtuCalculation(string $text): bool
    {
        $ascii = Str::ascii(Str::lower($text));

        return Str::contains($ascii, [
            'btu',
            'cong suat',
            'dien tich',
            'tai lanh',
            'm2',
            'nha xuong',
            'van phong',
            'showroom',
        ]);
    }

    private function validateUsedFacts(array $usedFacts, array $context, array &$warnings): array
    {
        $allowedKeys = array_keys($context['allowed_facts'] ?? []);
        $used = [];

        foreach (IssueList::normalize($usedFacts) as $fact) {
            if ($fact === '') {
                continue;
            }

            if (! in_array($fact, $allowedKeys, true)) {
                $warnings[] = 'unverified_used_fact:'.$fact;

                continue;
            }

            $used[] = $fact;
        }

        return $used;
    }

    private function allowedNumbers(array $context): array
    {
        $numbers = [];
        foreach ($context['allowed_facts'] ?? [] as $key => $fact) {
            $value = $fact['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $haystack = $key.' '.$this->plainText(is_scalar($value) ? (string) $value : json_encode($value));
            if (! preg_match_all('/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(BTU|kW|HP|m2|m²|dB|Pa|mm|kg|W|A)?\b/iu', $haystack, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $unit = Str::lower((string) ($match[2] ?? ''));
                $unit = $unit === 'm²' ? 'm2' : $unit;
                $number = $this->normalizeNumber($match[1]);

                if ($unit === '') {
                    $unit = $this->inferUnitFromFactKey($key);
                }

                if ($unit !== '') {
                    $numbers[$unit][] = $number;
                }
            }
        }

        return array_map(fn ($values) => array_values(array_unique($values)), $numbers);
    }

    private function allowedNumberRanges(array $context): array
    {
        $ranges = [];

        foreach ($context['allowed_facts'] ?? [] as $key => $fact) {
            $value = $fact['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $haystack = $key.' '.$this->plainText(is_scalar($value) ? (string) $value : json_encode($value));
            if (! preg_match_all('/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(?:-|–|đến|to)\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(BTU|kW|HP|m2|mÂ²|dB|Pa|mm|kg|W|A)?\b/iu', $haystack, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $unit = Str::lower((string) ($match[3] ?? ''));
                $unit = $unit === 'mÂ²' ? 'm2' : $unit;

                if ($unit === '') {
                    $unit = $this->inferUnitFromFactKey($key);
                }

                if ($unit === '') {
                    continue;
                }

                $from = $this->normalizeNumber($match[1]);
                $to = $this->normalizeNumber($match[2]);
                $ranges[$unit][] = [
                    'min' => min($from, $to),
                    'max' => max($from, $to),
                ];
            }
        }

        return $ranges;
    }

    private function inferUnitFromFactKey(string $key): string
    {
        return match (true) {
            Str::contains($key, ['capacity_btu', 'calculated_btu', 'recommended_btu']) => 'btu',
            Str::contains($key, 'capacity_kw') => 'kw',
            Str::contains($key, 'hp') => 'hp',
            Str::contains($key, 'noise') => 'db',
            Str::contains($key, 'weight') => 'kg',
            Str::contains($key, 'dimensions') => 'mm',
            Str::contains($key, 'area') => 'm2',
            Str::contains($key, ['regular_price', 'sale_price']) => 'vnd',
            default => '',
        };
    }

    private function numberAllowed(float $number, string $unit, array $allowedNumbers, array $allowedRanges = []): bool
    {
        $unit = Str::lower($unit);
        if (! in_array($unit, self::NUMERIC_UNITS, true)) {
            return true;
        }

        foreach ($allowedNumbers[$unit] ?? [] as $allowed) {
            $tolerance = max(0.01, abs($allowed) * 0.01);
            if (abs($number - $allowed) <= $tolerance) {
                return true;
            }
        }

        foreach ($allowedRanges[$unit] ?? [] as $range) {
            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? 0);
            $tolerance = max(0.01, max(abs($min), abs($max)) * 0.01);
            if ($number >= ($min - $tolerance) && $number <= ($max + $tolerance)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeNumber(string $number): float
    {
        $number = trim($number);
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?$/', $number)) {
            $lastSeparator = max(strrpos($number, '.'), strrpos($number, ','));
            $decimalLength = strlen($number) - $lastSeparator - 1;

            if ($decimalLength === 3) {
                return (float) str_replace(['.', ','], '', $number);
            }
        }

        if (str_contains($number, ',') && ! str_contains($number, '.')) {
            $number = str_replace(',', '.', $number);
        } else {
            $number = str_replace(',', '', $number);
        }

        return (float) $number;
    }

    private function hasAllowedFact(array $context, string $key): bool
    {
        return filled($context['allowed_facts'][$key]['value'] ?? null);
    }

    private function humanFactName(string $key): string
    {
        return match (true) {
            Str::contains($key, 'name') => 'tên sản phẩm',
            Str::contains($key, 'model_code') => 'mã model',
            Str::contains($key, 'sku') => 'mã hàng',
            Str::contains($key, 'brand') => 'thương hiệu',
            Str::contains($key, 'category') => 'danh mục',
            Str::contains($key, 'capacity_btu'), Str::contains($key, 'recommended_btu'), Str::contains($key, 'calculated_btu') => 'công suất BTU',
            Str::contains($key, 'capacity_kw') => 'công suất kW',
            Str::contains($key, 'hp') => 'công suất HP',
            Str::contains($key, 'airflow') => 'lưu lượng gió',
            Str::contains($key, 'noise') => 'độ ồn',
            Str::contains($key, 'refrigerant') => 'gas lạnh',
            Str::contains($key, 'phase'), Str::contains($key, 'voltage') => 'điện áp',
            Str::contains($key, 'dimensions') => 'kích thước',
            Str::contains($key, 'weight') => 'trọng lượng',
            Str::contains($key, 'area') => 'diện tích đề nghị',
            Str::contains($key, 'warranty') => 'thông tin bảo hành',
            Str::contains($key, 'price') => 'giá sản phẩm',
            Str::contains($key, 'topic') => 'chủ đề bài viết',
            Str::contains($key, 'keyword') => 'từ khóa chính',
            default => 'dữ liệu sản phẩm đã xác minh',
        };
    }

    private function plainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
