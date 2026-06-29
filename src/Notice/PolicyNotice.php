<?php

namespace Syon\AuditSdk\Notice;

/**
 * One in-force notice from the project-wide set (GET /notices/{project}) — a single
 * processing activity's approved Article 13 or 14 copy, for rendering into a
 * consolidated privacy policy.
 */
class PolicyNotice
{
    public function __construct(
        public readonly string $activityKey,
        public readonly string $activity,
        public readonly ?string $purpose,
        public readonly string $type,       // 'article13' | 'article14'
        public readonly int $version,
        public readonly string $html,
        public readonly ?string $summary = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $purpose = $data['purpose'] ?? null;
        $summary = $data['summary'] ?? null;

        return new self(
            (string) ($data['activity_key'] ?? ''),
            (string) ($data['activity'] ?? ''),
            is_string($purpose) && $purpose !== '' ? $purpose : null,
            (string) ($data['type'] ?? ''),
            (int) ($data['version'] ?? 0),
            (string) ($data['notice'] ?? ''),
            is_string($summary) && $summary !== '' ? $summary : null,
        );
    }
}
