<?php

namespace Syon\AuditSdk\Console;

use Illuminate\Console\Command;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Exceptions\AuditSdkException;
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
        {--push : Send a counts-only summary (no matched values) to the platform}
        {--json : Output findings as JSON}';

    protected $description = 'Scan application log files for personal data that should not be logged';

    public function handle(LogScanner $scanner, AuditClient $client): int
    {
        $paths = $this->option('path') ?: config('audit-sdk.log_scan.paths', [storage_path('logs')]);

        $findings = $scanner->scan($paths);

        if ($this->option('json')) {
            $this->line((string) json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } elseif ($findings === []) {
            $this->components->info('No personal data found in the scanned logs.');
        } else {
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
        }

        if ($this->option('push') && ! $this->pushSummary($client, $findings)) {
            return self::FAILURE;
        }

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Send a counts-and-locations-only summary to the platform — never the matched
     * values. An empty summary clears the platform's alert. Returns false on failure.
     *
     * @param  list<array{type: string, label: string, file: string, line: int, context: string}>  $findings
     */
    private function pushSummary(AuditClient $client, array $findings): bool
    {
        $report = [
            'scanned_at' => now()->toIso8601String(),
            'findings' => array_map(fn (array $f): array => [
                'type' => $f['type'],
                'label' => $f['label'],
                'file' => $this->relativePath($f['file']),
                'line' => $f['line'],
            ], $findings),
        ];

        try {
            $result = $client->pushLogScan($report);
        } catch (AuditSdkException $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        if (! $result->accepted()) {
            $this->components->error('The platform did not accept the log scan'.($result->error() ? ": {$result->error()}" : '.'));

            return false;
        }

        $this->components->info('Log scan summary sent to the platform (counts and locations only).');

        return true;
    }

    /** Trim the app base path so citations read as project-relative. */
    private function relativePath(string $file): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
