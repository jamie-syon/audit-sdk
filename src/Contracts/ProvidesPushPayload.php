<?php

namespace Syon\AuditSdk\Contracts;

use Syon\AuditSdk\Payload\PushPayload;

/**
 * Your application implements this to tell the SDK *what* to push — the SDK can't
 * know how you count your own processing. Bind your implementation in a service
 * provider:
 *
 *     $this->app->bind(ProvidesPushPayload::class, MyPushPayload::class);
 *
 * Then `php artisan audit:push` (schedule it on your cadence) builds and sends it.
 */
interface ProvidesPushPayload
{
    /** Assemble the verification payload for the current reporting window. */
    public function build(): PushPayload;
}
