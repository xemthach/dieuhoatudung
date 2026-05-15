<?php

namespace App\Support;

class IssueList
{
    public static function normalize(mixed ...$lists): array
    {
        $items = [];

        foreach ($lists as $list) {
            foreach (self::flatten($list) as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private static function flatten(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            foreach (['code', 'warning', 'claim', 'message', 'value', 'name', 'label', 'reason'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key])) {
                    $items[] = (string) $item[$key];

                    continue 2;
                }
            }

            $encoded = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded)) {
                $items[] = $encoded;
            }
        }

        return $items;
    }
}
