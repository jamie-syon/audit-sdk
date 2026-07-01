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

it('posts a signed, counts-only log scan to /log-scan/{project}', function () {
    Http::fake(['platform.test/log-scan/*' => Http::response(['status' => 'accepted'], 202)]);

    $result = Audit::pushLogScan([
        'scanned_at' => '2026-07-01T10:00:00+00:00',
        'findings' => [['type' => 'email', 'label' => 'Email address', 'file' => 'storage/logs/laravel.log', 'line' => 42]],
    ]);

    expect($result->accepted())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->body();
        $expectedSig = hash_hmac('sha256', $request->header('X-Timestamp')[0].'.'.$body, 'test-secret-key');

        return $request->url() === 'https://platform.test/log-scan/proj_123'
            && $request->method() === 'POST'
            && $request->header('X-Signature')[0] === $expectedSig
            && str_contains($body, '"type":"email"');
    });
});

it('dispatches PushAccepted with the payload on a 202', function () {
    Illuminate\Support\Facades\Event::fake();
    Http::fake(['platform.test/ingest/*' => Http::response(['status' => 'accepted'], 202)]);

    Audit::push($this->payload);

    Illuminate\Support\Facades\Event::assertDispatched(Syon\AuditSdk\Events\PushAccepted::class, function ($e) {
        return $e->payload->pushId() === 'push_abcd1234' && $e->result->accepted();
    });
    Illuminate\Support\Facades\Event::assertNotDispatched(Syon\AuditSdk\Events\PushRejected::class);
});

it('dispatches PushRejected on a deterministic rejection (401/422)', function () {
    Illuminate\Support\Facades\Event::fake();
    Http::fake(['platform.test/ingest/*' => Http::response(['error' => 'non_conforming_payload'], 422)]);

    Audit::push($this->payload);

    Illuminate\Support\Facades\Event::assertDispatched(Syon\AuditSdk\Events\PushRejected::class, function ($e) {
        return $e->payload->pushId() === 'push_abcd1234' && $e->result->rejected();
    });
    Illuminate\Support\Facades\Event::assertNotDispatched(Syon\AuditSdk\Events\PushAccepted::class);
});

it('dispatches PushFailed and still throws on a transport failure', function () {
    Illuminate\Support\Facades\Event::fake();
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('boom'));

    expect(fn () => Audit::push($this->payload))->toThrow(Syon\AuditSdk\Exceptions\TransportException::class);

    Illuminate\Support\Facades\Event::assertDispatched(Syon\AuditSdk\Events\PushFailed::class, function ($e) {
        return $e->payload->pushId() === 'push_abcd1234' && $e->exception instanceof Illuminate\Http\Client\ConnectionException;
    });
});
