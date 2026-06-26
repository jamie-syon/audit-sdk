<?php

namespace Syon\AuditSdk\Console;

use Illuminate\Console\Command;
use Syon\AuditSdk\Catalogue\CatalogueActivity;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Exceptions\AuditSdkException;

/**
 * Prints the platform's integration catalogue so you can see exactly which
 * activity keys and collection-point slugs to configure your pushes against.
 */
class CatalogueCommand extends Command
{
    protected $signature = 'audit:catalogue {--json : Output the raw catalogue as JSON}';

    protected $description = 'Fetch the platform integration catalogue (activity keys, in-force LIA versions, collection points)';

    public function handle(AuditClient $client): int
    {
        try {
            $catalogue = $client->catalogue();
        } catch (AuditSdkException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($catalogue->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($catalogue->activities === []) {
            $this->components->info('The platform has no activities to report on yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Activity key', 'Name', 'In-force LIA', 'Collection points'],
            array_map(static fn (CatalogueActivity $activity): array => [
                $activity->activityKey,
                $activity->name,
                $activity->liaVersionInForce === null ? '—' : 'v'.$activity->liaVersionInForce,
                $activity->collectionPoints === [] ? '—' : implode(', ', $activity->collectionPoints),
            ], $catalogue->activities),
        );

        $this->newLine();
        $this->components->info('Configure your pushes to use these activity keys and collection-point slugs.');

        return self::SUCCESS;
    }
}
