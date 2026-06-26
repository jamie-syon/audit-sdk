<?php

namespace Syon\AuditSdk\Catalogue;

/**
 * One activity in the platform catalogue: the canonical key to push under, the
 * in-force LIA version (for reference only — don't feed it back into what you
 * push), and the registered collection-point slugs.
 */
class CatalogueActivity
{
    /**
     * @param  list<string>  $collectionPoints
     */
    public function __construct(
        public readonly string $activityKey,
        public readonly string $name,
        public readonly ?int $liaVersionInForce,
        public readonly array $collectionPoints,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['activity_key'] ?? ''),
            (string) ($data['name'] ?? ''),
            isset($data['lia_version_in_force']) ? (int) $data['lia_version_in_force'] : null,
            array_values(array_map('strval', $data['collection_points'] ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'activity_key' => $this->activityKey,
            'name' => $this->name,
            'lia_version_in_force' => $this->liaVersionInForce,
            'collection_points' => $this->collectionPoints,
        ];
    }
}
