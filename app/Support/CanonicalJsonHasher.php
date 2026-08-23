<?php

namespace App\Support;

final class CanonicalJsonHasher
{
    public function hash(array $value): string
    {
        return hash('sha256', (string) json_encode(
            $this->canonicalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    public function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        $result = [];
        foreach ($value as $key => $item) $result[$key] = $this->canonicalize($item);
        if (! array_is_list($result)) ksort($result);
        return $result;
    }
}
