<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Contracts\ProvidesPushPayload;
use Syon\AuditSdk\Payload\ActivityReport;
use Syon\AuditSdk\Payload\PushPayload;

function bindPushPayload(): void
{
    app()->bind(ProvidesPushPayload::class, fn () => new class implements ProvidesPushPayload
    {
        public function build(): PushPayload
        {
            return PushPayload::make('push-test-0001')
                ->addActivity(ActivityReport::for('email_marketing')->liaVersion(1)->entriesSinceLast(0));
        }
    });
}

it('builds the payload from the bound provider and sends it', function () {
    Http::fake(['platform.test/ingest/*' => Http::response(['status' => 'accepted'], 202)]);
    bindPushPayload();

    $code = Artisan::call('audit:push');

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('Push accepted');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/ingest/proj_123'));
});

it('fails clearly when no payload provider is bound', function () {
    $code = Artisan::call('audit:push');

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('No push payload provider is bound');
});

it('fails when the platform rejects the payload', function () {
    Http::fake(['platform.test/ingest/*' => Http::response(['error' => 'non_conforming_payload'], 422)]);
    bindPushPayload();

    $code = Artisan::call('audit:push');

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('non_conforming_payload');
});
