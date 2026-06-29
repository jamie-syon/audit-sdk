<?php

namespace Syon\AuditSdk\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Contracts\ProvidesPushPayload;
use Syon\AuditSdk\Exceptions\AuditSdkException;

/**
 * Builds the verification payload from your bound ProvidesPushPayload implementation
 * and sends it to the platform. Schedule it on your push cadence so the platform's
 * liveness check stays green even when you're idle.
 *
 * Exits non-zero on any failure (unbound provider, rejected payload, bad signature,
 * unreachable platform) so it's safe to wire into cron/CI alerting.
 */
class PushCommand extends Command
{
    protected $signature = 'audit:push';

    protected $description = 'Build and send a verification push to the audit platform';

    public function handle(Container $container, AuditClient $client): int
    {
        if (! $container->bound(ProvidesPushPayload::class)) {
            $this->components->error(
                'No push payload provider is bound. Bind '.ProvidesPushPayload::class.' to your implementation in a service provider.'
            );

            return self::FAILURE;
        }

        try {
            $payload = $container->make(ProvidesPushPayload::class)->build();
            $result = $client->push($payload);
        } catch (AuditSdkException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->accepted()) {
            $this->components->info("Push accepted ({$payload->pushId()}).");

            return self::SUCCESS;
        }

        $this->components->error(
            $result->unauthorized()
                ? 'Push rejected: invalid signature — check the push secret and clock skew.'
                : 'Push rejected: '.($result->error() ?? 'unexpected response').' (HTTP '.$result->status.').'
        );

        return self::FAILURE;
    }
}
