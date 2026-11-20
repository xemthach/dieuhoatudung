<?php

return [
    'tile_attribution' => env('DEALER_TILE_ATTRIBUTION', '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    'tile_url' => env('DEALER_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'current_version' => 'v5.2.0',
    'legacy_version' => 'v2.4.1',
    'radii' => [5, 10, 20, 50, 100],
];
