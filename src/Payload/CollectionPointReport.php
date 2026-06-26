<?php

namespace Syon\AuditSdk\Payload;

use DateTimeInterface;
use Syon\AuditSdk\Payload\Concerns\NormalisesContractValues;

/**
 * An Article 13 collection point within an activity. Required: collection_point,
 * notice_present, submissions_since_last.
 */
class CollectionPointReport
{
    use NormalisesContractValues;

    private ?int $noticeVersion = null;
    private ?string $latestSubmissionAt = null;

    private function __construct(
        private readonly string $collectionPoint,
        private readonly bool $noticePresent,
        private readonly int $submissionsSinceLast,
    ) {}

    public static function make(string $collectionPoint, bool $noticePresent, int $submissionsSinceLast): self
    {
        return new self(
            self::snakeKey($collectionPoint, 'collection_point'),
            $noticePresent,
            self::nonNegative($submissionsSinceLast, 'submissions_since_last'),
        );
    }

    public function noticeVersion(int $version): self
    {
        $this->noticeVersion = self::nonNegative($version, 'notice_version');

        return $this;
    }

    public function latestSubmissionAt(DateTimeInterface|string|null $at): self
    {
        $this->latestSubmissionAt = self::dateTime($at);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'collection_point' => $this->collectionPoint,
            'notice_present' => $this->noticePresent,
            'submissions_since_last' => $this->submissionsSinceLast,
        ];

        if ($this->noticeVersion !== null) {
            $out['notice_version'] = $this->noticeVersion;
        }

        if ($this->latestSubmissionAt !== null) {
            $out['latest_submission_at'] = $this->latestSubmissionAt;
        }

        return $out;
    }
}
