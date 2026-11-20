<?php

namespace App\Services\Dealer;

use Illuminate\Validation\ValidationException;

final class MapCoordinateParser
{
    public const HOSTS = ['google.com', 'www.google.com', 'maps.google.com', 'maps.app.goo.gl', 'openstreetmap.org', 'www.openstreetmap.org'];

    public function validateUrl(string $url, string $field = 'map_url'): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $allowed = self::HOSTS;
        if ($field === 'google_maps_url') {
            $allowed = array_slice(self::HOSTS, 0, 4);
        }
        if ($field === 'openstreetmap_url') {
            $allowed = array_slice(self::HOSTS, 4);
        }
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ! in_array($host, $allowed, true)
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw ValidationException::withMessages([$field => 'Nhập liên kết HTTPS Google Maps hoặc OpenStreetMap hợp lệ.']);
        }
        if (str_contains($host, 'google.com') && ! str_starts_with($parts['path'] ?? '/', '/maps')
            && $host !== 'maps.google.com') {
            throw ValidationException::withMessages([$field => 'Liên kết phải dẫn tới Google Maps.']);
        }

        return $parts;
    }

    public function parse(string $url): ?array
    {
        $parts = $this->validateUrl($url);
        parse_str($parts['query'] ?? '', $query);
        $candidate = $query['q'] ?? $query['query'] ?? null;
        $matches = [];
        if (is_string($candidate) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/D', $candidate, $matches)) {
            return $this->coordinates($matches[1], $matches[2]);
        }
        if (preg_match('~@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)~', rawurldecode($parts['path'] ?? ''), $matches)
            || preg_match('~^map=\d+(?:\.\d+)?/(-?\d+(?:\.\d+)?)/(-?\d+(?:\.\d+)?)$~', $parts['fragment'] ?? '', $matches)) {
            return $this->coordinates($matches[1], $matches[2]);
        }

        return null;
    }

    private function coordinates(string $lat, string $lng): ?array
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        return is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180
            ? ['latitude' => $lat, 'longitude' => $lng] : null;
    }
}
