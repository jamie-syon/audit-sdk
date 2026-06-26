<?php

namespace Syon\AuditSdk\Exceptions;

/** Thrown when the platform could not be reached after exhausting retries. */
class TransportException extends \RuntimeException implements AuditSdkException {}
