<?php

namespace Syon\AuditSdk\Catalogue;

/**
 * The platform's integration catalogue — what a source application should
 * configure its pushes against. Read-only; consumed at configuration time, never
 * wired into the push path.
 */
class Catalogue
{
    /**
     * @param  list<CatalogueActivity>  $activities
     */
    private function __construct(
        public readonly int $schemaVersion,
        public readonly string $project,
        public readonly string $generatedAt,
        public readonly array $activities,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $activities = array_map(
            static fn (array $activity): CatalogueActivity => CatalogueActivity::fromArray($activity),
            $data['activities'] ?? [],
        );

        return new self(
            (int) ($data['schema_version'] ?? 0),
            (string) ($data['project'] ?? ''),
            (string) ($data['generated_at'] ?? ''),
            array_values($activities),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'project' => $this->project,
            'generated_at' => $this->generatedAt,
            'activities' => array_map(
                static fn (CatalogueActivity $activity): array => $activity->toArray(),
                $this->activities,
            ),
        ];
    }
}
