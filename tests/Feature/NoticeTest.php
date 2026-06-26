<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Facades\Audit;

beforeEach(fn () => Cache::flush());

it('fetches the in-force notice for a collection point with a signed GET', function () {
    Http::fake(['platform.test/notice/proj_123/newsletter_signup' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 2,
        'summary' => 'We use your email for the newsletter.',
        'notice' => '<p>We use your email.</p>',
    ], 200)]);

    $notice = Audit::notice('newsletter_signup');

    expect($notice)->not->toBeNull()
        ->and($notice->collectionPoint)->toBe('newsletter_signup')
        ->and($notice->version)->toBe(2)
        ->and($notice->summary)->toBe('We use your email for the newsletter.')
        ->and($notice->html)->toBe('<p>We use your email.</p>');

    Http::assertSent(function ($request) {
        $sig = hash_hmac('sha256', $request->header('X-Timestamp')[0].'.', 'test-secret-key');

        return $request->method() === 'GET'
            && $request->url() === 'https://platform.test/notice/proj_123/newsletter_signup'
            && $request->body() === ''
            && $request->header('X-Signature')[0] === $sig;
    });
});

it('returns null when there is no in-force notice (404)', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'no_notice'], 404)]);

    expect(Audit::notice('newsletter_signup'))->toBeNull();
});

it('renders the notice HTML through the <x-audit-notice> component', function () {
    Http::fake(['platform.test/notice/*' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 1, 'notice' => '<p>We use your email to send our newsletter.</p>',
    ], 200)]);

    $html = Blade::render('<x-audit-notice point="newsletter_signup" />');

    expect($html)->toContain('We use your email to send our newsletter.')
        ->and($html)->toContain('data-audit-notice="newsletter_signup"');
});

it('renders nothing when the collection point has no notice', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'no_notice'], 404)]);

    expect(trim(Blade::render('<x-audit-notice point="unmapped_point" />')))->toBe('');
});

it('renders a trigger link and native dialog via <x-audit-notice-dialog>', function () {
    Http::fake(['platform.test/notice/*' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 1, 'notice' => '<p>We use your email.</p>',
    ], 200)]);

    $html = Blade::render('<x-audit-notice-dialog point="newsletter_signup" trigger="Privacy notice" />');

    expect($html)->toContain('data-audit-notice-trigger="newsletter_signup"')
        ->and($html)->toContain('Privacy notice')
        ->and($html)->toContain('<dialog')
        ->and($html)->toContain('data-audit-notice-dialog="newsletter_signup"')
        ->and($html)->toContain('We use your email.')
        ->and($html)->toContain('showModal'); // the self-contained vanilla JS
});

it('renders nothing from the dialog when there is no notice', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'no_notice'], 404)]);

    expect(trim(Blade::render('<x-audit-notice-dialog point="unmapped_point" />')))->toBe('');
});

it('renders the first-layer summary via <x-audit-notice-summary>', function () {
    Http::fake(['platform.test/notice/*' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 1,
        'summary' => 'We use your email to send our newsletter.',
        'notice' => '<p>Full notice.</p>',
    ], 200)]);

    $html = Blade::render('<x-audit-notice-summary point="newsletter_signup" />');

    expect($html)->toContain('We use your email to send our newsletter.')
        ->and($html)->toContain('data-audit-notice-summary="newsletter_signup"');
});

it('renders nothing from the summary when none is authored', function () {
    Http::fake(['platform.test/notice/*' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 1, 'notice' => '<p>Full.</p>', // no summary
    ], 200)]);

    expect(trim(Blade::render('<x-audit-notice-summary point="newsletter_signup" />')))->toBe('');
});

it('falls back to the last known copy when the platform is unreachable', function () {
    // First render caches the copy.
    Http::fake(['platform.test/notice/*' => Http::response([
        'collection_point' => 'newsletter_signup', 'version' => 1, 'notice' => '<p>Cached copy.</p>',
    ], 200)]);
    Blade::render('<x-audit-notice point="newsletter_signup" />');

    // TTL expires, then the platform errors — the last known copy still renders.
    Cache::forget('audit-sdk:notice:proj_123:newsletter_signup');
    Http::fake(['platform.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);

    $html = Blade::render('<x-audit-notice point="newsletter_signup" />');
    expect($html)->toContain('Cached copy.');
});
