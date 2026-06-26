<?php

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Syon\AuditSdk\Enums\DataOrigin;
use Syon\AuditSdk\Payload\ActivityReport;
use Syon\AuditSdk\Payload\Article14Report;
use Syon\AuditSdk\Payload\CollectionPointReport;
use Syon\AuditSdk\Payload\PushPayload;

/**
 * Proves builder output conforms to a vendored copy of the platform's
 * authoritative ingest-v1 schema. If the platform's schema and this copy ever
 * drift, this test is the early warning.
 */
function assertConforms(PushPayload $payload): void
{
    $validator = new Validator;
    $schema = json_decode((string) file_get_contents(__DIR__.'/../fixtures/ingest-v1.json'));

    // Validate the actual transmitted bytes, decoded — exactly what the platform sees.
    $data = json_decode($payload->toJson());
    $result = $validator->validate($data, $schema);

    $reason = $result->isValid()
        ? null
        : json_encode((new ErrorFormatter)->format($result->error()));

    expect($result->isValid())->toBeTrue($reason ?? '');
}

it('produces a conforming minimal payload', function () {
    assertConforms(PushPayload::make());
});

it('produces a conforming fully-populated payload', function () {
    $payload = PushPayload::make('push_abcd1234')
        ->addActivity(
            ActivityReport::for('email_marketing')
                ->liaVersion(3)
                ->entriesSinceLast(120)
                ->totalEntries(5400)
                ->latestEntryAt(new DateTimeImmutable('2026-06-26T10:00:00+00:00'))
                ->dataOrigin(DataOrigin::Mixed)
                ->addCollectionPoint(
                    CollectionPointReport::make('newsletter_signup', noticePresent: true, submissionsSinceLast: 120)
                        ->noticeVersion(2)
                        ->latestSubmissionAt(new DateTimeImmutable('2026-06-26T09:00:00+00:00'))
                )
                ->article14(
                    Article14Report::make(recordsAcquiredIndirectlySinceLast: 50, noticesSentSinceLast: 48, noticesPending: 2)
                        ->noticesBouncedSinceLast(1)
                        ->noticesSuppressedSinceLast(0)
                        ->latestNoticeAt(new DateTimeImmutable('2026-06-26T08:00:00+00:00'))
                )
        );

    assertConforms($payload);
});

it('rejects a non-conforming push_id locally', function () {
    PushPayload::make('short'); // < 8 chars
})->throws(Syon\AuditSdk\Exceptions\InvalidPayloadException::class);

it('rejects a non-conforming activity_key locally', function () {
    ActivityReport::for('Email-Marketing'); // uppercase + hyphen
})->throws(Syon\AuditSdk\Exceptions\InvalidPayloadException::class);

it('rejects negative counts locally', function () {
    ActivityReport::for('email')->entriesSinceLast(-1);
})->throws(Syon\AuditSdk\Exceptions\InvalidPayloadException::class);
