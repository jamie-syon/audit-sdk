<?php

use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Facades\Audit;
use Syon\AuditSdk\Payload\ActivityReport;
use Syon\AuditSdk\Payload\PushPayload;

beforeEach(function () {
    $this->payload = PushPayload::make('push_abcd1234')
        ->addActivity(ActivityReport::for('email_marketing')->liaVersion(1)->entriesSinceLast(5));
});

it('posts a signed push to /ingest/{project} and maps 202 to accepted', function () {
    Http::fake([
        'platform.test/ingest/*' => Http::response(['status' => 'accepted'], 202),
    ]);

    $result = Audit::push($this->payload);

    expect($result->accepted())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->body();
        $expectedSig = hash_hmac('sha256', $request->header('X-Timestamp')[0].'.'.$body, 'test-secret-key');

        return $request->url() === 'https://platform.test/ingest/proj_123'
            && $request->method() === 'POST'
            // Signature is over the exact transmitted bytes.
            && $request->header('X-Signature')[0] === $expectedSig
            && str_contains($body, '"push_id":"push_abcd1234"');
    });
});

it('maps 401 to unauthorized', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'invalid_signature'], 401)]);

    $result = Audit::push($this->payload);

    expect($result->unauthorized())->toBeTrue()
        ->and($result->error())->toBe('invalid_signature');
});

it('maps 422 to rejected', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'non_conforming_payload'], 422)]);

    $result = Audit::push($this->payload);

    expect($result->rejected())->toBeTrue()
        ->and($result->error())->toBe('non_conforming_payload');
});
