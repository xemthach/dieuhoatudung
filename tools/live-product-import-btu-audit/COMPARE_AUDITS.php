<?php
declare(strict_types=1);
if ($argc !== 3) { fwrite(STDERR, "Usage: php COMPARE_AUDITS.php <local.json> <live.json>\n"); exit(2); }
$local = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $live = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$paths = ['application.version','application.git_sha','code_hashes','product_population','brands','categories','marketing_capacity','filter_self_tests','job_40','governance','cache'];
$get = static function (array $data, string $path) { foreach (explode('.', $path) as $part) $data = $data[$part] ?? null; return $data; };
$classify = static function (string $path, mixed $a, mixed $b): string {
    if ($a === $b) return 'MATCH';
    if (in_array($path, ['application.git_sha', 'code_hashes'], true)) return 'CODE_DIFFERENCE';
    if (in_array($path, ['product_population', 'brands', 'categories', 'marketing_capacity', 'filter_self_tests', 'job_40'], true)) return 'DATA_DIFFERENCE';
    if ($path === 'governance') return 'POLICY_DIFFERENCE';
    return 'CONFIG_DIFFERENCE';
};
$rows=[]; foreach ($paths as $path) { $a=$get($local,$path); $b=$get($live,$path); $rows[]=['section'=>$path,'classification'=>$classify($path,$a,$b)]; }
$counts = array_count_values(array_column($rows, 'classification'));
$out=dirname($argv[1]).'/PRODUCT_IMPORT_BTU_LOCAL_LIVE_PARITY.md'; $text="# Product Import / BTU Local-Live Parity\n\n"; foreach($rows as $row) $text.="- {$row['section']}: {$row['classification']}\n"; $text.="\n## Summary\n\n".json_encode($counts, JSON_PRETTY_PRINT)."\n"; file_put_contents($out,$text); echo $out.PHP_EOL;
