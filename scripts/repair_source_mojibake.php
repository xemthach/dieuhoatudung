<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$paths = ['app', 'resources', 'config', 'database', 'routes'];
$excluded = ['vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public/build', 'public/storage'];
$extensions = ['php', 'blade.php', 'js', 'json', 'css', 'md', 'txt', 'yml', 'yaml', 'xml'];
$encodings = ['Windows-1252', 'ISO-8859-1', 'CP1252', 'CP1258'];

$changed = [];
$scanned = 0;

foreach ($paths as $root) {
    $rootPath = $base.DIRECTORY_SEPARATOR.$root;
    if (! is_dir($rootPath)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        if (isExcluded($relative, $excluded)) {
            continue;
        }

        if (! isTextFile($relative, $extensions)) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if (! is_string($content) || str_contains($content, "\0")) {
            continue;
        }

        $scanned++;

        $score = mojibakeScore($content);
        if ($score === 0) {
            continue;
        }

        $best = $content;
        $bestScore = $score;

        foreach ($encodings as $encoding) {
            try {
                $candidate = @mb_convert_encoding($content, $encoding, 'UTF-8');
            } catch (ValueError) {
                continue;
            }

            if (! is_string($candidate) || $candidate === '' || ! mb_check_encoding($candidate, 'UTF-8')) {
                continue;
            }

            $candidateScore = mojibakeScore($candidate);
            if ($candidateScore < $bestScore) {
                $best = $candidate;
                $bestScore = $candidateScore;
            }
        }

        if ($best !== $content && $bestScore < $score) {
            file_put_contents($file->getPathname(), $best);
            $changed[] = [$relative, $score, $bestScore];
        }
    }
}

echo "Scanned {$scanned} files\n";
echo "Changed ".count($changed)." files\n";

foreach ($changed as [$file, $before, $after]) {
    echo "- {$file} ({$before} -> {$after})\n";
}

function isExcluded(string $relative, array $excluded): bool
{
    foreach ($excluded as $dir) {
        if ($relative === $dir || str_starts_with($relative, $dir.'/')) {
            return true;
        }
    }

    return false;
}

function isTextFile(string $relative, array $extensions): bool
{
    if (str_ends_with($relative, '.blade.php')) {
        return true;
    }

    $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (in_array($ext, $extensions, true)) {
        return true;
    }

    return str_ends_with($relative, '.env.example');
}

function mojibakeScore(string $text): int
{
    $score = 0;

    $patterns = [
        '/Ã.|Ä.|Æ.|áº|á»|â€|â€™|â€œ|â€|â€“|â€”/u',
        '/Â(?=[^\s])/u',
        '/\x{FFFD}/u',
    ];

    foreach ($patterns as $pattern) {
        $matches = [];
        $count = preg_match_all($pattern, $text, $matches);
        if (is_int($count) && $count > 0) {
            $score += $count;
        }
    }

    return $score;
}
