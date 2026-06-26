<?php

namespace Syon\AuditSdk\Exceptions;

/**
 * Thrown when a payload is built in a way the platform's schema would reject —
 * raised locally, before the network call, so callers get a precise reason
 * instead of an opaque 422.
 */
class InvalidPayloadException extends \InvalidArgumentException implements AuditSdkException {}
