<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogFileScanner
{
    private const EXTENSIONS = ['pdf', 'xlsx', 'xls', 'csv', 'json', 'txt'];

    private const DEFAULT_DIRS = [
        'storage/app',
        'storage/app/public',
        'storage/catalogs',
        'storage/imports',
        'storage/uploads',
        'public/uploads',
        'public/storage',
        'database/seeders/data',
        'resources/data',
        'app/Data',
        'data dieu hoa',
    ];

    /**
     * @return Collection<int, array<string,mixed>>
     */
    public function scan(array $extraDirs = []): Collection
    {
        $dirs = array_values(array_unique(array_filter(array_merge(self::DEFAULT_DIRS, $extraDirs))));
        $results = collect();

        foreach ($dirs as $dir) {
            $absolute = base_path($dir);
            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $ext = Str::lower($file->getExtension());
                if (! in_array($ext, self::EXTENSIONS, true)) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $results->push([
                    'path' => $path,
                    'relative_path' => str_replace('\\', '/', Str::after($path, str_replace('\\', '/', base_path()).'/')),
                    'extension' => $ext,
                    'size_bytes' => $file->getSize(),
                    'updated_at' => date('c', $file->getMTime()),
                    'brand' => $this->inferBrand($path),
                ]);
            }
        }

        return $results
            ->unique('path')
            ->sortBy('path')
            ->values();
    }

    private function inferBrand(string $path): string
    {
        $ascii = Str::ascii(Str::lower($path));

        foreach (['daikin', 'gree', 'panasonic', 'lg', 'mitsubishi', 'toshiba', 'casper', 'funiki', 'reetech'] as $brand) {
            if (Str::contains($ascii, $brand)) {
                return $brand;
            }
        }

        return 'unknown';
    }
}

