<?php

declare(strict_types=1);

$compiledConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

// A production-style config cache freezes DB settings before PHPUnit can apply
// phpunit.xml environment overrides. Remove only that generated artifact so the
// isolated SQLite test contract is authoritative for every test invocation.
if (is_file($compiledConfig) && ! unlink($compiledConfig)) {
    throw new RuntimeException('Unable to remove compiled config before PHPUnit bootstrap.');
}

require dirname(__DIR__).'/vendor/autoload.php';
