<?php

namespace Syon\AuditSdk\Notice;

/**
 * The in-force Article 13 notice for a collection point, as served by the
 * platform — the approved, adopted copy to render at the point of collection.
 */
class Notice
{
    public function __construct(
        public readonly string $collectionPoint,
        public readonly int $version,
        public readonly string $html,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['collection_point'] ?? ''),
            (int) ($data['version'] ?? 0),
            (string) ($data['notice'] ?? ''),
        );
    }
}
