<?php

namespace Syon\AuditSdk\Events;

use Syon\AuditSdk\Payload\PushPayload;
use Syon\AuditSdk\Responses\IngestResult;

/**
 * Dispatched when the platform rejects a push deterministically — a bad signature
 * (401) or a non-conforming payload (422). The push reached the platform; check
 * `$event->result` for which. Useful for failure alerting; watermarks stay put.
 */
class PushRejected
{
    public function __construct(
        public readonly PushPayload $payload,
        public readonly IngestResult $result,
    ) {}
}
