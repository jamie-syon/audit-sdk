<?php

namespace Syon\AuditSdk\Payload\Concerns;

use DateTimeInterface;
use Syon\AuditSdk\Exceptions\InvalidPayloadException;

/** Shared coercion + guards so every builder emits contract-conforming scalars. */
trait NormalisesContractValues
{
    /**
     * Coerce a date-time to the RFC 3339 string the schema's "date-time" format
     * expects. A DateTimeInterface is formatted; a string is trusted as-is
     * (assumed already RFC 3339); null stays null.
     */
    private static function dateTime(DateTimeInterface|string|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::RFC3339);
        }

        return $value;
    }

    /** Guard the schema's `"minimum": 0` integer fields locally. */
    private static function nonNegative(int $value, string $field): int
    {
        if ($value < 0) {
            throw new InvalidPayloadException("{$field} must be >= 0, got {$value}");
        }

        return $value;
    }

    /** Guard the `^[a-z0-9]+(_[a-z0-9]+)*$` (max 64) key pattern shared by keys. */
    private static function snakeKey(string $value, string $field): string
    {
        if ($value === '' || strlen($value) > 64 || ! preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $value)) {
            throw new InvalidPayloadException(
                "{$field} must match ^[a-z0-9]+(_[a-z0-9]+)*\$ and be 1-64 chars, got: {$value}"
            );
        }

        return $value;
    }
}
