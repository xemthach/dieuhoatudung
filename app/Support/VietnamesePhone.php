<?php

namespace App\Support;

final class VietnamesePhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/[\s.()\-]+/', '', trim($phone));

        if (str_starts_with($normalized, '0084')) {
            $normalized = '0'.substr($normalized, 4);
        } elseif (str_starts_with($normalized, '+84')) {
            $normalized = '0'.substr($normalized, 3);
        }

        return $normalized;
    }
}
