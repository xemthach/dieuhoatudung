<?php

namespace App\Services\Backup;

use InvalidArgumentException;
use RuntimeException;

final class SafeRestorePayloadBuilder
{
    public function build(string $input, string $output, string $target, string $current = 'dieuhoa-tudung', bool $approvedCurrentRecovery = false): array
    {
        $this->assertTarget($target, $current, $approvedCurrentRecovery);
        if (! is_file($input)) {
            throw new InvalidArgumentException('Backup input does not exist.');
        }

        $in = fopen($input, 'rb');
        $out = fopen($output, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Unable to open restore payload streams.');
        }

        $stats = [
            'input_sha256' => hash_file('sha256', $input),
            'input_bytes' => filesize($input),
            'create_database_removed' => 0,
            'use_current_rewritten' => 0,
            'use_target_emitted' => 0,
            'drop_database_rejected' => 0,
            'qualified_references_rewritten' => 0,
            'unexpected_database_directives' => 0,
        ];

        // A mysqldump created without --databases may contain no USE statement.
        // Emit exactly one guarded target directive before processing any DDL.
        fwrite($out, 'USE `'.$target."`;\n");
        $stats['use_target_emitted'] = 1;

        try {
            while (($line = fgets($in)) !== false) {
                $trimmed = trim($line);

                if (preg_match('/^DROP\s+DATABASE\b/i', $trimmed)) {
                    $stats['drop_database_rejected']++;
                    throw new RuntimeException('Unsafe DROP DATABASE directive found in backup.');
                }

                if (preg_match('/^CREATE\s+DATABASE\b/i', $trimmed)) {
                    if (! $this->isDatabaseDirectiveFor($trimmed, 'CREATE', $current)) {
                        $stats['unexpected_database_directives']++;
                        throw new RuntimeException('Unexpected CREATE DATABASE target found in backup.');
                    }
                    $stats['create_database_removed']++;
                    continue;
                }

                if (preg_match('/^USE\b/i', $trimmed)) {
                    if (! $this->isDatabaseDirectiveFor($trimmed, 'USE', $current)) {
                        $stats['unexpected_database_directives']++;
                        throw new RuntimeException('Unexpected USE database target found in backup.');
                    }
                    if ($stats['use_current_rewritten'] > 0) {
                        throw new RuntimeException('Multiple USE database directives found in backup.');
                    }
                    $stats['use_current_rewritten']++;
                    continue;
                }

                [$transformed, $qualified] = $this->rewriteQualifiedReference($line, $current, $target);
                $stats['qualified_references_rewritten'] += $qualified;
                fwrite($out, $transformed);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($stats['use_target_emitted'] !== 1) {
            throw new RuntimeException('Safe payload must contain exactly one USE target statement.');
        }

        $validation = $this->validatePayload($output, $target, $current, $approvedCurrentRecovery);
        $stats['output_sha256'] = hash_file('sha256', $output);
        $stats['output_bytes'] = filesize($output);
        $stats['validation'] = $validation;

        return $stats;
    }

    public function validatePayload(string $payload, string $target, string $current = 'dieuhoa-tudung', bool $approvedCurrentRecovery = false): array
    {
        $this->assertTarget($target, $current, $approvedCurrentRecovery);
        if (! is_file($payload)) {
            throw new InvalidArgumentException('Payload does not exist.');
        }

        $useTarget = 0;
        $useCurrent = 0;
        $createDatabase = 0;
        $dropDatabase = 0;
        $unexpected = 0;
        $handle = fopen($payload, 'rb');
        if ($handle === false) throw new RuntimeException('Unable to read payload.');

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if (preg_match('/^DROP\s+DATABASE\b/i', $trimmed)) $dropDatabase++;
                if (preg_match('/^CREATE\s+DATABASE\b/i', $trimmed)) $createDatabase++;
                if (preg_match('/^USE\b/i', $trimmed)) {
                    if ($this->isDatabaseDirectiveFor($trimmed, 'USE', $target)) $useTarget++;
                    elseif ($this->isDatabaseDirectiveFor($trimmed, 'USE', $current)) $useCurrent++;
                    else $unexpected++;
                }
                if ($this->containsQualifiedDatabase($line, $current)) $useCurrent++;
            }
        } finally {
            fclose($handle);
        }

        $result = [
            'use_target' => $useTarget,
            'use_current' => $useCurrent,
            'create_database' => $createDatabase,
            'drop_database' => $dropDatabase,
            'unexpected_database_directives' => $unexpected,
            'pass' => $useTarget === 1 && $useCurrent === 0 && $createDatabase === 0 && $dropDatabase === 0 && $unexpected === 0,
        ];
        if (! $result['pass']) throw new RuntimeException('Static safe-payload validation failed: '.json_encode($result));

        return $result;
    }

    public function assertTarget(string $target, string $current = 'dieuhoa-tudung', bool $approvedCurrentRecovery = false): void
    {
        $isApprovedCurrent = $approvedCurrentRecovery && $target === $current && $target === 'dieuhoa-tudung';
        if (! $isApprovedCurrent && ($target === '' || ! preg_match('/^dieuhoatudung_phase(?:1f|1h|2a1|2f_pilot|2h_restore|2i9a|2i9b3)_(?:(?:safe|restore)_)?[0-9]{8}_[0-9]{6}$/', $target))) {
            throw new InvalidArgumentException('Target must match a guarded phase1f, phase1h, phase2a1, or phase2f_pilot clone name with optional safe_/restore_YYYYMMDD_HHMMSS.');
        }
        if ($target === $current && ! $approvedCurrentRecovery) throw new InvalidArgumentException('Target cannot equal current database without approvedCurrentRecovery.');
    }

    private function isDatabaseDirectiveFor(string $line, string $kind, string $database): bool
    {
        $quoted = preg_quote($database, '/');
        $pattern = $kind === 'CREATE'
            ? '/^CREATE\s+DATABASE\b(?:\s+IF\s+NOT\s+EXISTS)?(?:\s+\/\*![^*]*\*\/)?\s+`?'.$quoted.'`?\b.*;\s*(?:--.*)?$/i'
            : '/^USE\s+`?'.$quoted.'`?\s*;\s*(?:--.*)?$/i';

        return preg_match($pattern, $line) === 1;
    }

    private function containsQualifiedDatabase(string $line, string $database): bool
    {
        [$ignored, $count] = $this->rewriteQualifiedReference($line, $database, '__blocked_target__');

        return $count > 0;
    }

    private function rewriteQualifiedReference(string $line, string $source, string $target): array
    {
        if (strpos($line, $source) === false && strpos($line, '`'.$source.'`') === false) {
            return [$line, 0];
        }

        $result = '';
        $count = 0;
        $quote = null;
        $sourcePatterns = ['`'.$source.'`', $source];
        $length = strlen($line);

        for ($i = 0; $i < $length;) {
            $char = $line[$i];
            if ($quote !== null) {
                $result .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $result .= $line[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($char === $quote) $quote = null;
                $i++;
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $result .= $char;
                $i++;
                continue;
            }

            $matched = false;
            foreach ($sourcePatterns as $pattern) {
                if (substr($line, $i, strlen($pattern)) !== $pattern) continue;
                $after = $i + strlen($pattern);
                if (preg_match('/^\s*\./', substr($line, $after)) !== 1) continue;
                $before = $i === 0 ? '' : $line[$i - 1];
                if ($before !== '' && preg_match('/[A-Za-z0-9_]/', $before) === 1) continue;
                $result .= '`'.$target.'`';
                $count++;
                $i += strlen($pattern);
                $matched = true;
                break;
            }
            if ($matched) continue;
            $result .= $char;
            $i++;
        }

        return [$result, $count];
    }
}
