<?php

namespace App\Services\Navigation;

final class HomepageLayout
{
    public const SECTIONS = [
        'hero'=>'Giới thiệu', 'benefits'=>'Lợi ích', 'dealers'=>'Đại lý',
        'featured_products'=>'Sản phẩm nổi bật', 'brands'=>'Thương hiệu',
        'articles'=>'Bài viết', 'faq'=>'Câu hỏi thường gặp', 'cta'=>'Tư vấn',
    ];

    public function rows(): array
    {
        return $this->normalize(setting('display.homepage_section_order'));
    }

    public function normalize(mixed $configured): array
    {
        $configured = is_array($configured) ? $configured : [];
        usort($configured, static fn ($a, $b) => (int) (is_array($a) ? ($a['sort_order'] ?? 0) : 0) <=> (int) (is_array($b) ? ($b['sort_order'] ?? 0) : 0));
        $rows = [];
        foreach ($configured as $item) {
            $key = is_array($item) ? ($item['section'] ?? '') : $item;
            if (! is_string($key) || ! isset(self::SECTIONS[$key]) || isset($rows[$key])) continue;
            $rows[$key] = [
                'section'=>$key,
                'enabled'=>is_array($item) && array_key_exists('enabled', $item)
                    ? filter_var($item['enabled'], FILTER_VALIDATE_BOOLEAN) : $this->legacyEnabled($key),
            ];
        }
        foreach (self::SECTIONS as $key => $label) {
            $rows[$key] ??= ['section'=>$key, 'enabled'=>$this->legacyEnabled($key)];
        }

        return array_map(static fn ($row, $index) => $row + ['sort_order'=>($index + 1) * 10], array_values($rows), range(0, count($rows) - 1));
    }

    public function enabled(string $section): bool
    {
        foreach ($this->rows() as $row) if ($row['section'] === $section) return $row['enabled'];
        return false;
    }

    public function order(): array
    {
        return array_column(array_filter($this->rows(), static fn ($row) => $row['enabled']), 'section');
    }

    private function legacyEnabled(string $key): bool
    {
        return $key !== 'dealers' || (bool) setting('display.homepage_dealers_enabled', false);
    }
}
