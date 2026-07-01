<?php

namespace Syon\AuditSdk\Events;

use Syon\AuditSdk\Payload\PushPayload;
use Syon\AuditSdk\Responses\IngestResult;

/**
 * Dispatched after the platform accepts a push (HTTP 202). Carries the full payload
 * that was sent — correlate by `$event->payload->pushId()` to advance watermarks
 * against exactly what was reported.
 */
class PushAccepted
{
    public function __construct(
        public readonly PushPayload $payload,
        public readonly IngestResult $result,
    ) {}
}
