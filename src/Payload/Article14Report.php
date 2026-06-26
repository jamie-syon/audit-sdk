<?php

namespace Syon\AuditSdk\Payload;

use DateTimeInterface;
use Syon\AuditSdk\Payload\Concerns\NormalisesContractValues;

/**
 * The Article 14 (indirectly-acquired data) summary for an activity. Required:
 * records_acquired_indirectly_since_last, notices_sent_since_last,
 * notices_pending.
 */
class Article14Report
{
    use NormalisesContractValues;

    private ?int $noticesBouncedSinceLast = null;
    private ?int $noticesSuppressedSinceLast = null;
    private ?string $latestNoticeAt = null;

    private function __construct(
        private readonly int $recordsAcquiredIndirectlySinceLast,
        private readonly int $noticesSentSinceLast,
        private readonly int $noticesPending,
    ) {}

    public static function make(int $recordsAcquiredIndirectlySinceLast, int $noticesSentSinceLast, int $noticesPending): self
    {
        return new self(
            self::nonNegative($recordsAcquiredIndirectlySinceLast, 'records_acquired_indirectly_since_last'),
            self::nonNegative($noticesSentSinceLast, 'notices_sent_since_last'),
            self::nonNegative($noticesPending, 'notices_pending'),
        );
    }

    public function noticesBouncedSinceLast(int $count): self
    {
        $this->noticesBouncedSinceLast = self::nonNegative($count, 'notices_bounced_since_last');

        return $this;
    }

    public function noticesSuppressedSinceLast(int $count): self
    {
        $this->noticesSuppressedSinceLast = self::nonNegative($count, 'notices_suppressed_since_last');

        return $this;
    }

    public function latestNoticeAt(DateTimeInterface|string|null $at): self
    {
        $this->latestNoticeAt = self::dateTime($at);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'records_acquired_indirectly_since_last' => $this->recordsAcquiredIndirectlySinceLast,
            'notices_sent_since_last' => $this->noticesSentSinceLast,
            'notices_pending' => $this->noticesPending,
        ];

        if ($this->noticesBouncedSinceLast !== null) {
            $out['notices_bounced_since_last'] = $this->noticesBouncedSinceLast;
        }

        if ($this->noticesSuppressedSinceLast !== null) {
            $out['notices_suppressed_since_last'] = $this->noticesSuppressedSinceLast;
        }

        if ($this->latestNoticeAt !== null) {
            $out['latest_notice_at'] = $this->latestNoticeAt;
        }

        return $out;
    }
}
