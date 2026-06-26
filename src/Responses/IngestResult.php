<?php

namespace Syon\AuditSdk\Responses;

/**
 * The outcome of a push, mapped from the platform's documented responses:
 *
 *   202 {"status":"accepted"}            → accepted()
 *   401 {"error":"invalid_signature"}    → unauthorized()
 *   422 {"error":"non_conforming_payload"} → rejected()
 */
class IngestResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    private function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromStatus(int $status, array $body): self
    {
        return new self($status, $body);
    }

    public function accepted(): bool
    {
        return $this->status === 202;
    }

    public function unauthorized(): bool
    {
        return $this->status === 401;
    }

    public function rejected(): bool
    {
        return $this->status === 422;
    }

    /** The platform's machine-readable error code, when the push was not accepted. */
    public function error(): ?string
    {
        $error = $this->body['error'] ?? null;

        return is_string($error) ? $error : null;
    }
}
