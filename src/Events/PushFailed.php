<?php

namespace Syon\AuditSdk\Events;

use Syon\AuditSdk\Payload\PushPayload;
use Throwable;

/**
 * Dispatched when a push could not reach the platform at all — a transport failure
 * (timeout, DNS, connection refused). The `TransportException` is still thrown after
 * this fires, so callers/exit codes are unaffected. Useful for failure alerting.
 */
class PushFailed
{
    public function __construct(
        public readonly PushPayload $payload,
        public readonly Throwable $exception,
    ) {}
}
