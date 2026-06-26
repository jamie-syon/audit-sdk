<?php

namespace Syon\AuditSdk\Payload;

use DateTimeInterface;
use Syon\AuditSdk\Enums\DataOrigin;
use Syon\AuditSdk\Payload\Concerns\NormalisesContractValues;

/**
 * One processing activity in a push. Required by the contract: activity_key,
 * lia_version, entries_since_last. Optional fields are omitted from the output
 * when unset — the platform rejects unknown keys, so absent is safer than null.
 */
class ActivityReport
{
    use NormalisesContractValues;

    private int $liaVersion = 0;
    private int $entriesSinceLast = 0;
    private ?int $totalEntries = null;
    private ?string $latestEntryAt = null;
    private ?DataOrigin $dataOrigin = null;

    /** @var CollectionPointReport[] */
    private array $article13 = [];

    private ?Article14Report $article14 = null;

    private function __construct(private readonly string $activityKey) {}

    public static function for(string $activityKey): self
    {
        return new self(self::snakeKey($activityKey, 'activity_key'));
    }

    public function liaVersion(int $version): self
    {
        $this->liaVersion = self::nonNegative($version, 'lia_version');

        return $this;
    }

    public function entriesSinceLast(int $count): self
    {
        $this->entriesSinceLast = self::nonNegative($count, 'entries_since_last');

        return $this;
    }

    public function totalEntries(int $count): self
    {
        $this->totalEntries = self::nonNegative($count, 'total_entries');

        return $this;
    }

    public function latestEntryAt(DateTimeInterface|string|null $at): self
    {
        $this->latestEntryAt = self::dateTime($at);

        return $this;
    }

    public function dataOrigin(DataOrigin $origin): self
    {
        $this->dataOrigin = $origin;

        return $this;
    }

    public function addCollectionPoint(CollectionPointReport $point): self
    {
        $this->article13[] = $point;

        return $this;
    }

    public function article14(Article14Report $report): self
    {
        $this->article14 = $report;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'activity_key' => $this->activityKey,
            'lia_version' => $this->liaVersion,
            'entries_since_last' => $this->entriesSinceLast,
        ];

        if ($this->totalEntries !== null) {
            $out['total_entries'] = $this->totalEntries;
        }

        if ($this->latestEntryAt !== null) {
            $out['latest_entry_at'] = $this->latestEntryAt;
        }

        if ($this->dataOrigin !== null) {
            $out['data_origin'] = $this->dataOrigin->value;
        }

        if ($this->article13 !== []) {
            $out['article13'] = array_map(static fn (CollectionPointReport $p): array => $p->toArray(), $this->article13);
        }

        if ($this->article14 !== null) {
            $out['article14'] = $this->article14->toArray();
        }

        return $out;
    }
}
