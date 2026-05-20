<?php

namespace App\Models;

use App\Enums\ProductCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ProductCategoryType::class,
            'is_indexable' => 'boolean',
            'is_active' => 'boolean',
            'technical_schema_json' => 'array',
            'technical_schema_locked_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'category_promotion')->withTimestamps();
    }

    public function faqs(): MorphToMany
    {
        return $this->morphToMany(Faq::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function activeFaqs(): MorphToMany
    {
        return $this->faqs()->where('faqs.is_active', true);
    }

    public function technicalSchema(): array
    {
        return is_array($this->technical_schema_json) ? $this->technical_schema_json : [];
    }

    public function technicalSchemaStatus(): string
    {
        return (string) ($this->technical_schema_status ?: 'missing');
    }

    public function hasTechnicalSchema(): bool
    {
        if ($this->technicalSchemaStatus() !== 'active' && $this->technicalSchemaStatus() !== 'locked') {
            return false;
        }

        $schema = $this->technicalSchema();

        return $schema !== []
            && (
                $this->technicalSchemaAllowedFields() !== []
                || $this->technicalSchemaFieldDefinitions() !== []
            );
    }

    public function technicalSchemaAllowedFields(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : null,
            (array) Arr::get($this->technicalSchema(), 'allowed_fields', [])
        )));
    }

    public function technicalSchemaPermittedFields(): array
    {
        $fields = array_merge(
            $this->technicalSchemaAllowedFields(),
            array_map(
                fn (array $definition) => $definition['key'] ?? null,
                $this->technicalSchemaFieldDefinitions()
            )
        );

        return array_values(array_unique(array_filter($fields)));
    }

    public function technicalSchemaRequiredFields(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : null,
            (array) Arr::get($this->technicalSchema(), 'required_fields', [])
        )));
    }

    public function technicalSchemaAllowedUnits(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : null,
            (array) Arr::get($this->technicalSchema(), 'allowed_units', [])
        )));
    }

    public function technicalSchemaFieldAliases(): array
    {
        $aliases = Arr::get($this->technicalSchema(), 'field_aliases', []);

        if (! is_array($aliases)) {
            return [];
        }

        return collect($aliases)
            ->filter(fn ($value, $key) => is_string($key) && is_string($value))
            ->mapWithKeys(fn (string $value, string $key) => [mb_strtolower(trim($key)) => mb_strtolower(trim($value))])
            ->all();
    }

    public function normalizeTechnicalSchemaKey(string $key): string
    {
        $key = trim(mb_strtolower($key));
        $aliases = $this->technicalSchemaFieldAliases();

        if (isset($aliases[$key]) && is_string($aliases[$key])) {
            return trim(mb_strtolower($aliases[$key]));
        }

        return $key;
    }

    public function technicalSchemaFieldDefinitions(): array
    {
        $fields = Arr::get($this->technicalSchema(), 'fields', []);

        if (! is_array($fields)) {
            return [];
        }

        $definitions = [];

        foreach ($fields as $key => $definition) {
            if (is_string($key) && is_array($definition)) {
                $definition['key'] = $key;
                $definitions[] = $definition;
                continue;
            }

            if (is_array($definition)) {
                $definitions[] = $definition;
            }
        }

        return array_values(array_filter(array_map(function (array $definition): ?array {
            $key = $definition['key'] ?? $definition['name'] ?? null;

            if (! is_string($key) || trim($key) === '') {
                return null;
            }

            return [
                'key' => mb_strtolower(trim($key)),
                'label' => is_string($definition['label'] ?? null) ? trim((string) $definition['label']) : null,
                'required' => filter_var($definition['required'] ?? false, FILTER_VALIDATE_BOOL),
                'unit' => is_string($definition['unit'] ?? null) ? mb_strtolower(trim((string) $definition['unit'])) : null,
                'type' => is_string($definition['type'] ?? null) ? mb_strtolower(trim((string) $definition['type'])) : null,
            ];
        }, $definitions)));
    }

    public function technicalSchemaIsLocked(): bool
    {
        return in_array($this->technicalSchemaStatus(), ['locked', 'active'], true)
            && $this->hasTechnicalSchema();
    }

    public function technicalSchemaFieldCount(): int
    {
        $definitions = $this->technicalSchemaFieldDefinitions();

        if ($definitions !== []) {
            return count($definitions);
        }

        return count($this->technicalSchemaAllowedFields());
    }

    public function technicalSchemaSummary(): string
    {
        $status = $this->technicalSchemaStatus();
        $fields = $this->technicalSchemaFieldCount();
        $required = count($this->technicalSchemaRequiredFields());
        $units = count($this->technicalSchemaAllowedUnits());
        $aliases = count($this->technicalSchemaFieldAliases());

        return sprintf('%s | fields:%d required:%d units:%d aliases:%d', $status, $fields, $required, $units, $aliases);
    }

    public function technicalSchemaIssues(): array
    {
        $issues = [];
        $status = $this->technicalSchemaStatus();
        $schema = $this->technicalSchema();

        if (in_array($status, ['active', 'locked'], true) && $schema === []) {
            $issues[] = 'schema_payload_missing';
        }

        if ($status === 'missing') {
            $issues[] = 'schema_status_missing';
        }

        if (in_array($status, ['active', 'locked'], true) && ! $this->hasTechnicalSchema()) {
            $issues[] = 'schema_inactive_or_empty';
        }

        if ($this->technicalSchemaFieldCount() === 0) {
            $issues[] = 'schema_field_definition_missing';
        }

        if ($this->technicalSchemaRequiredFields() !== [] && $this->technicalSchemaAllowedFields() === [] && $this->technicalSchemaFieldDefinitions() === []) {
            $issues[] = 'required_fields_without_allowed_fields';
        }

        if ($this->technicalSchemaRequiredFields() !== [] && $this->technicalSchemaAllowedFields() !== []) {
            $missingInAllowed = array_diff($this->technicalSchemaRequiredFields(), $this->technicalSchemaAllowedFields());
            if ($missingInAllowed !== []) {
                $issues[] = 'required_fields_not_in_allowed_fields';
            }
        }

        return array_values(array_unique($issues));
    }

    public function technicalSchemaHasIssues(): bool
    {
        return $this->technicalSchemaIssues() !== [];
    }
}
