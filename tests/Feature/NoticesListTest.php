<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Facades\Audit;

function fakeNoticesResponse(): array
{
    return [
        'project' => 'proj_123',
        'notices' => [
            [
                'activity_key' => 'analytics',
                'activity' => 'Analytics',
                'purpose' => 'Understand usage',
                'type' => 'article14',
                'version' => 1,
                'summary' => null,
                'notice' => '<p>Analytics notice.</p>',
            ],
            [
                'activity_key' => 'email_marketing',
                'activity' => 'Email marketing',
                'purpose' => 'Send the newsletter',
                'type' => 'article13',
                'version' => 3,
                'summary' => 'We email you.',
                'notice' => '<p>Email notice.</p>',
            ],
        ],
    ];
}

it('fetches every in-force notice for the project with a signed GET', function () {
    Http::fake(['platform.test/notices/proj_123' => Http::response(fakeNoticesResponse(), 200)]);

    $notices = Audit::notices();

    expect($notices)->toHaveCount(2)
        ->and($notices[0]->activity)->toBe('Analytics')
        ->and($notices[0]->type)->toBe('article14')
        ->and($notices[1]->activity)->toBe('Email marketing')
        ->and($notices[1]->version)->toBe(3)
        ->and($notices[1]->summary)->toBe('We email you.')
        ->and($notices[1]->html)->toBe('<p>Email notice.</p>');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Signature') && str_contains($request->url(), '/notices/proj_123'));
});

it('returns an empty array when no notices are in force', function () {
    Http::fake(['platform.test/notices/*' => Http::response(['project' => 'proj_123', 'notices' => []], 200)]);

    expect(Audit::notices())->toBe([]);
});

it('renders all notices as sections via <x-audit-notices>', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices />');

    expect($html)->toContain('Analytics')
        ->and($html)->toContain('<p>Analytics notice.</p>')
        ->and($html)->toContain('Email marketing')
        ->and($html)->toContain('<p>Email notice.</p>')
        ->and($html)->toContain('data-audit-notice-activity="email_marketing"');
});

it('renders nothing when the project has no notices', function () {
    Http::fake(['platform.test/notices/*' => Http::response(['project' => 'proj_123', 'notices' => []], 200)]);

    expect(trim(Blade::render('<x-audit-notices />')))->toBe('');
});
