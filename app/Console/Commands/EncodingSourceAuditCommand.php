<?php

namespace App\Console\Commands;

use App\Support\EncodingGuard;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class EncodingSourceAuditCommand extends Command
{
    protected $signature = 'encoding:source-audit
                            {--repair : Convert invalid legacy encoded files to UTF-8 and strip BOM}
                            {--repair-mojibake : Also repair likely mojibake text in non-fixture files}
                            {--path=* : Limit scan to one or more paths}';

    protected $description = 'Audit source files for UTF-8 validity, BOM, and mojibake artifacts';

    private const DEFAULT_PATHS = [
        'app',
        'config',
        'database',
        'resources',
        'routes',
        'tests',
    ];

    private const TEXT_EXTENSIONS = [
        'php',
        'js',
        'json',
        'css',
        'md',
        'txt',
        'yml',
        'yaml',
        'xml',
        'env.example',
    ];

    private const EXCLUDED_DIRS = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public/build',
        'public/storage',
    ];

    private const INTENTIONAL_PATTERN_FILES = [
        'app/Support/EncodingGuard.php',
        'app/Console/Commands/EncodingAuditCommand.php',
        'app/Console/Commands/EncodingRepairCommand.php',
        'app/Services/Product/AIProductContentSanitizer.php',
        'tests/Feature/AIProductContentSystemTest.php',
    ];

    public function handle(): int
    {
        $repair = (bool) $this->option('repair');
        $repairMojibake = (bool) $this->option('repair-mojibake');
        $paths = (array) ($this->option('path') ?: self::DEFAULT_PATHS);

        $rows = [];
        $failures = 0;
        $changed = 0;
        $scanned = 0;

        foreach ($this->sourceFiles($paths) as $file) {
            $scanned++;
            $relativePath = $this->relativePath($file->getPathname());
            $content = file_get_contents($file->getPathname());

            if (! is_string($content) || str_contains($content, "\0")) {
                continue;
            }

            $hasBom = EncodingGuard::hasBom($content);
            $validUtf8 = EncodingGuard::isValidUtf8(EncodingGuard::stripBom($content));
            $fixturePattern = in_array($relativePath, self::INTENTIONAL_PATTERN_FILES, true);
            $mojibake = $validUtf8 && EncodingGuard::hasMojibake(EncodingGuard::stripBom($content));
            $status = 'ok';
            $action = '';

            if (! $validUtf8) {
                $status = 'legacy_or_broken';
            } elseif ($hasBom) {
                $status = 'utf8_bom';
            } elseif ($mojibake && $fixturePattern) {
                $status = 'intentional_pattern';
            } elseif ($mojibake) {
                $status = 'mojibake';
            }

            if ($status !== 'ok' && $status !== 'intentional_pattern') {
                $failures++;
            }

            if (($repair || $repairMojibake) && $status !== 'ok' && $status !== 'intentional_pattern') {
                $fixed = $content;

                if ($repair) {
                    $fixed = EncodingGuard::ensureUtf8(
                        $fixed,
                        autoFixMojibake: false,
                        rejectBroken: true,
                        context: $relativePath
                    );
                } else {
                    $fixed = EncodingGuard::stripBom($fixed);
                }

                if ($repairMojibake && EncodingGuard::hasMojibake($fixed)) {
                    $fixed = EncodingGuard::repairMojibake($fixed);
                }

                $fixed = EncodingGuard::stripBom($fixed);
                if ($fixed !== $content) {
                    file_put_contents($file->getPathname(), $fixed);
                    $changed++;
                    $action = 'repaired';
                }
            }

            if ($status !== 'ok') {
                $rows[] = [$relativePath, $status, $hasBom ? 'yes' : 'no', EncodingGuard::mojibakeScore(EncodingGuard::stripBom($content)), $action];
            }
        }

        $this->info("Scanned {$scanned} source files.");

        if ($rows !== []) {
            $this->table(['File', 'Status', 'BOM', 'Mojibake score', 'Action'], $rows);
        }

        if ($changed > 0) {
            $this->info("Repaired {$changed} source files as UTF-8 without BOM.");
        }

        if ($failures > 0 && ! $repair && ! $repairMojibake) {
            $this->warn("Detected {$failures} source encoding issue(s). Re-run with --repair after review.");
            return 1;
        }

        if ($failures > 0 && ($repair || $repairMojibake) && $changed < $failures) {
            $this->warn("Some source encoding issue(s) remain after repair.");
            return 1;
        }

        $this->info('Source encoding audit passed.');

        return 0;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function sourceFiles(array $paths): iterable
    {
        foreach ($paths as $path) {
            $absolutePath = base_path((string) $path);
            if (! file_exists($absolutePath)) {
                continue;
            }

            if (is_file($absolutePath)) {
                $file = new SplFileInfo($absolutePath);
                if ($this->isTextSource($file)) {
                    yield $file;
                }
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if ($this->isExcluded($file) || ! $this->isTextSource($file)) {
                    continue;
                }

                yield $file;
            }
        }
    }

    private function isTextSource(SplFileInfo $file): bool
    {
        $relative = $this->relativePath($file->getPathname());
        $basename = $file->getBasename();
        $extension = strtolower($file->getExtension());

        return in_array($extension, self::TEXT_EXTENSIONS, true)
            || in_array($basename, self::TEXT_EXTENSIONS, true)
            || str_ends_with($relative, '.blade.php');
    }

    private function isExcluded(SplFileInfo $file): bool
    {
        $relative = $this->relativePath($file->getPathname());

        foreach (self::EXCLUDED_DIRS as $excludedDir) {
            if (str_starts_with($relative, $excludedDir.'/') || $relative === $excludedDir) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        $relative = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($relative, $base)
            ? substr($relative, strlen($base))
            : $relative;
    }
}
