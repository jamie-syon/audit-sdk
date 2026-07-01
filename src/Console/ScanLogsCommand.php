<?php

namespace Syon\AuditSdk\Console;

use Illuminate\Console\Command;
use Syon\AuditSdk\Logs\LogScanner;

/**
 * Scans this application's log files for personal data that shouldn't be in them
 * — emails, card numbers, credentials, and the like. Runs entirely locally: it
 * reports where and what kind, with values redacted, and never transmits log
 * contents anywhere. Exits non-zero when anything is found, so it can gate CI.
 */
class ScanLogsCommand extends Command
{
    protected $signature = 'audit:scan-logs
        {--path=* : Log files or directories to scan (defaults to the configured paths)}
        {--json : Output findings as JSON}';

    protected $description = 'Scan application log files for personal data that should not be logged';

    public function handle(LogScanner $scanner): int
    {
        $paths = $this->option('path') ?: config('audit-sdk.log_scan.paths', [storage_path('logs')]);

        $findings = $scanner->scan($paths);

        if ($this->option('json')) {
            $this->line((string) json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $findings === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($findings === []) {
            $this->components->info('No personal data found in the scanned logs.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'File', 'Line', 'Context (redacted)'],
            array_map(fn (array $f): array => [
                $f['label'],
                $this->relativePath($f['file']),
                $f['line'],
                $f['context'],
            ], $findings),
        );

        $this->newLine();
        $this->components->warn(count($findings).' occurrence(s) of personal data found in your logs.');
        $this->components->info('Logs are a data store too: stop writing personal data you don\'t need, and give them a retention policy. Values above are redacted — open the file to see them.');

        return self::FAILURE;
    }

    /** Trim the app base path so citations read as project-relative. */
    private function relativePath(string $file): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
