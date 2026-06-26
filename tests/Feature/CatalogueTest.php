<?php

use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Exceptions\TransportException;
use Syon\AuditSdk\Facades\Audit;

function fakeCatalogue(): array
{
    return [
        'schema_version' => 1,
        'project' => 'proj_123',
        'generated_at' => '2026-06-26T10:00:00+00:00',
        'activities' => [
            [
                'activity_key' => 'email_marketing',
                'name' => 'Email marketing',
                'lia_version_in_force' => 5,
                'collection_points' => ['newsletter_signup'],
            ],
            [
                'activity_key' => 'analytics',
                'name' => 'Analytics',
                'lia_version_in_force' => null,
                'collection_points' => [],
            ],
        ],
    ];
}

it('fetches and parses the catalogue with a signed GET over an empty body', function () {
    Http::fake(['platform.test/catalogue/*' => Http::response(fakeCatalogue(), 200)]);

    $catalogue = Audit::catalogue();

    expect($catalogue->schemaVersion)->toBe(1)
        ->and($catalogue->activities)->toHaveCount(2)
        ->and($catalogue->activities[0]->activityKey)->toBe('email_marketing')
        ->and($catalogue->activities[0]->liaVersionInForce)->toBe(5)
        ->and($catalogue->activities[0]->collectionPoints)->toBe(['newsletter_signup'])
        ->and($catalogue->activities[1]->liaVersionInForce)->toBeNull();

    Http::assertSent(function ($request) {
        // The signature must be over "{timestamp}." — the empty GET body.
        $expectedSig = hash_hmac('sha256', $request->header('X-Timestamp')[0].'.', 'test-secret-key');

        return $request->method() === 'GET'
            && $request->url() === 'https://platform.test/catalogue/proj_123'
            && $request->body() === ''
            && $request->header('X-Signature')[0] === $expectedSig;
    });
});

it('throws a TransportException on a non-200 response', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'invalid_signature'], 401)]);

    Audit::catalogue();
})->throws(TransportException::class, 'invalid_signature');

it('resolves the client as a singleton from the container', function () {
    expect(app(AuditClient::class))->toBe(app(AuditClient::class));
});
