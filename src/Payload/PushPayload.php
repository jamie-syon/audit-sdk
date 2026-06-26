<?php

namespace Syon\AuditSdk\Payload;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Syon\AuditSdk\Exceptions\InvalidPayloadException;

/**
 * The root of an ingest push (schema ingest-v1). Always carries schema_version
 * 1; generates a conforming push_id by default. The push_id is stable for the
 * life of this object, so re-sending the same instance is idempotent on the
 * platform.
 */
class PushPayload
{
    private const PUSH_ID_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /** @var ActivityReport[] */
    private array $activities = [];

    private function __construct(
        private readonly string $pushId,
        private readonly string $generatedAt,
    ) {}

    public static function make(?string $pushId = null, DateTimeInterface|string|null $generatedAt = null): self
    {
        $pushId ??= bin2hex(random_bytes(16)); // 32 hex chars — within the 8-64 pattern

        if (! preg_match(self::PUSH_ID_PATTERN, $pushId)) {
            throw new InvalidPayloadException("push_id must match ^[A-Za-z0-9_-]{8,64}\$, got: {$pushId}");
        }

        if ($generatedAt instanceof DateTimeInterface) {
            $generatedAt = $generatedAt->format(DateTimeInterface::RFC3339);
        }

        $generatedAt ??= (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::RFC3339);

        return new self($pushId, $generatedAt);
    }

    public function addActivity(ActivityReport $activity): self
    {
        $this->activities[] = $activity;

        return $this;
    }

    public function pushId(): string
    {
        return $this->pushId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'push_id' => $this->pushId,
            'generated_at' => $this->generatedAt,
            'activities' => array_map(static fn (ActivityReport $a): array => $a->toArray(), $this->activities),
        ];
    }

    /** The exact bytes to sign and transmit. Slashes are left unescaped for readability; both forms are valid JSON. */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
