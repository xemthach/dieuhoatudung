<?php

namespace App\Services\Catalog;

use App\Models\CatalogModel;
use Illuminate\Support\Str;

class CatalogSourcePriorityResolver
{
    /**
     * @param  \Illuminate\Support\Collection<int,CatalogModel>  $candidates
     * @return array{selected: ?CatalogModel, ambiguous: bool, ranked: array<int,array{id:int,score:int}>}
     */
    public function resolve($candidates, array $context = []): array
    {
        if ($candidates->isEmpty()) {
            return ['selected' => null, 'ambiguous' => false, 'ranked' => []];
        }

        $ranked = $candidates->map(function (CatalogModel $model) use ($context): array {
            return [
                'id' => $model->id,
                'score' => $this->score($model, $context),
            ];
        })->sortByDesc('score')->values();

        $top = $ranked->first();
        $second = $ranked->get(1);

        $selected = $top ? $candidates->firstWhere('id', $top['id']) : null;
        $ambiguous = false;

        if ($top && $second) {
            $margin = (int) $top['score'] - (int) $second['score'];
            if ($margin < 5) {
                $ambiguous = true;
            }
        }

        return [
            'selected' => $ambiguous ? null : $selected,
            'ambiguous' => $ambiguous,
            'ranked' => $ranked->all(),
        ];
    }

    private function score(CatalogModel $model, array $context = []): int
    {
        $source = $model->source;
        $score = 0;

        $sourceType = Str::lower((string) ($source?->source_type ?? ''));
        $score += match ($sourceType) {
            'xlsx', 'xls', 'csv' => 50,
            'pdf' => 40,
            'json' => 30,
            'txt' => 20,
            default => 10,
        };

        if (in_array(Str::lower((string) ($source?->imported_status ?? '')), ['verified', 'approved'], true)) {
            $score += 100;
        }
        if (in_array(Str::lower((string) ($source?->parsed_status ?? '')), ['verified', 'approved', 'parsed'], true)) {
            $score += 30;
        }

        $sourcePath = Str::ascii(Str::lower((string) ($source?->uploaded_file ?? '').' '.(string) ($source?->source_name ?? '')));
        if (Str::contains($sourcePath, ['import_output', 'official_import', 'verified'])) {
            $score += 80;
        }
        if (Str::contains($sourcePath, ['/storage/app/private/data-imports/'])) {
            $score += 120;
        }
        if (Str::contains($sourcePath, ['/storage/app/private/data-exports/'])) {
            $score -= 120;
        }
        if (Str::contains($sourcePath, ['/storage/app/private/reports/'])) {
            $score -= 180;
        }
        if (Str::contains($sourcePath, ['lac2025_strict'])) {
            $score += 15;
        }
        if (Str::contains($sourcePath, ['_strict.json', ' strict'])) {
            $score += 10;
        }
        if (Str::contains($sourcePath, ['catalogue', 'catalog'])) {
            $score += 45;
        }
        if (Str::contains($sourcePath, ['service manual'])) {
            $score -= 10;
        }

        $confidence = (float) ($model->confidence_score ?? 0);
        if ($confidence >= 0.95) {
            $score += 25;
        } elseif ($confidence >= 0.85) {
            $score += 15;
        } elseif ($confidence > 0) {
            $score += 5;
        }

        $fieldCount = $model->fields->count();
        if ($fieldCount >= 10) {
            $score += 20;
        } elseif ($fieldCount >= 5) {
            $score += 10;
        } elseif ($fieldCount > 0) {
            $score += 5;
        }

        $normalizedSku = (string) ($context['normalized_sku'] ?? '');
        $normalizedModel = (string) ($context['normalized_model'] ?? '');
        if ($normalizedSku !== '' && (string) $model->normalized_sku === $normalizedSku) {
            $score += 60;
        } elseif ($normalizedModel !== '' && (string) $model->normalized_model === $normalizedModel) {
            $score += 35;
        }

        return $score;
    }
}
