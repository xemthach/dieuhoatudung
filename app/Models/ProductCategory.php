<?php

namespace App\Models;

use App\Enums\ProductCategoryType;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use App\Services\SlugGeneratorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (ProductCategory $category): void {
            $slugger = app(SlugGeneratorService::class);

            if (blank($category->slug)) {
                $category->slug = $slugger->unique(static::class, $category->name, $category->getKey(), fallback: 'category');

                return;
            }

            if ($category->isDirty('slug')) {
                $category->slug = $slugger->unique(static::class, $category->slug, $category->getKey(), fallback: 'category');
            }

            if (is_array($category->technical_schema_json)) {
                $category->technical_schema_json = app(CategoryTechnicalSchemaService::class)->normalize(
                    $category->technical_schema_json,
                    (string) ($category->technical_schema_version ?: 'v1'),
                    (string) ($category->technical_schema_status ?: 'draft'),
                );
            }
        });
    }

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
        return app(CategoryTechnicalSchemaService::class)->normalize(
            is_array($this->technical_schema_json) ? $this->technical_schema_json : [],
            (string) ($this->technical_schema_version ?: 'v1'),
            $this->technicalSchemaStatus(),
        );
    }

    public function technicalSchemaStatus(): string
    {
        return (string) ($this->technical_schema_status ?: 'missing');
    }

    public function hasTechnicalSchema(): bool
    {
        if ($this->technicalSchemaStatus() !== 'active') {
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
        return array_values(array_map(
            fn (array $field): string => $field['key'],
            $this->technicalSchemaFieldDefinitions()
        ));
    }

    public function technicalSchemaPermittedFields(): array
    {
        return $this->technicalSchemaAllowedFields();
    }

    public function technicalSchemaRequiredFields(): array
    {
        return array_values(array_map(
            fn (array $field): string => $field['key'],
            array_filter($this->technicalSchemaFieldDefinitions(), fn (array $field): bool => (bool) ($field['required'] ?? false))
        ));
    }

    public function technicalSchemaAllowedUnits(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (array $field): ?string => ($field['unit'] ?? 'none') !== 'none' ? (string) $field['unit'] : null,
            $this->technicalSchemaFieldDefinitions()
        ))));
    }

    public function technicalSchemaFieldAliases(): array
    {
        $aliases = [];

        foreach ($this->technicalSchemaFieldDefinitions() as $field) {
            foreach ((array) ($field['aliases'] ?? []) as $alias) {
                if (is_string($alias) && $alias !== '') {
                    $aliases[mb_strtolower(trim($alias))] = $field['key'];
                }
            }
        }

        $legacyAliases = is_array($this->technical_schema_json)
            ? ($this->technical_schema_json['field_aliases'] ?? [])
            : [];

        foreach ((array) $legacyAliases as $alias => $key) {
            if (is_string($alias) && is_string($key)) {
                $aliases[mb_strtolower(trim($alias))] = mb_strtolower(trim($key));
            }
        }

        return $aliases;
    }

    public function normalizeTechnicalSchemaKey(string $key): string
    {
        return app(CategoryTechnicalSchemaService::class)->normalizeSchemaKey($this, $key);
    }

    public function technicalSchemaFieldDefinitions(): array
    {
        return $this->technicalSchema()['fields'] ?? [];
    }

    public function technicalSchemaFieldsFor(string $purpose): array
    {
        return app(CategoryTechnicalSchemaService::class)->fieldsFor($this, $purpose);
    }

    public function technicalSchemaIsLocked(): bool
    {
        return $this->technicalSchemaStatus() === 'active' && $this->hasTechnicalSchema();
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

        foreach (app(CategoryTechnicalSchemaService::class)->validate($schema) as $error) {
            $issues[] = 'schema_validation:'.$error;
        }

        if ($status === 'active' && $schema === []) {
            $issues[] = 'schema_payload_missing';
        }

        if ($status === 'missing') {
            $issues[] = 'schema_status_missing';
        }

        if ($status === 'active' && ! $this->hasTechnicalSchema()) {
            $issues[] = 'schema_inactive_or_empty';
        }

        if ($this->technicalSchemaFieldCount() === 0) {
            $issues[] = 'schema_field_definition_missing';
        }

        return array_values(array_unique($issues));
    }

    public function technicalSchemaHasIssues(): bool
    {
        return $this->technicalSchemaIssues() !== [];
    }
}
