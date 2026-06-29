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
                'notice' => '<h2>What we collect</h2><p>Email notice.</p>',
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
        ->and($notices[1]->html)->toBe('<h2>What we collect</h2><p>Email notice.</p>');

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

it('renders activity names as <h2> and pushes the copy headings down to <h3>', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices />');

    expect($html)->toContain('<h2>Email marketing</h2>')   // activity at the section level
        ->and($html)->toContain('<h3>What we collect</h3>') // copy heading nested beneath it
        ->and($html)->not->toContain('<h2>What we collect</h2>');
});

it('nests deeper when given a higher level', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices :level="3" />');

    expect($html)->toContain('<h3>Email marketing</h3>')   // activity at h3
        ->and($html)->toContain('<h4>What we collect</h4>'); // copy heading shifted to h4
});

it('gives each section a stable id and omits the contents list by default', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices />');

    expect($html)->toContain('id="notice-email_marketing"')
        ->and($html)->not->toContain('audit-notices-toc');
});

it('prepends a jump-link contents list when :toc is set', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices :toc="true" />');

    expect($html)->toContain('audit-notices-toc')
        ->and($html)->toContain('href="#notice-analytics"')
        ->and($html)->toContain('href="#notice-email_marketing"')
        ->and($html)->toContain('id="notice-email_marketing"'); // the link target exists
});

it('wraps each activity in an exclusive <details> when :collapsible, with the jump target on the summary', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices :collapsible="true" :toc="true" />');

    expect($html)->toContain('<details name="audit-notices"')          // exclusive group → one open at a time
        ->and($html)->toContain('<summary id="notice-email_marketing">') // jump target inside the details → auto-expands
        ->and($html)->toContain('<h2>Email marketing</h2>')              // activity heading lives in the summary
        ->and($html)->not->toContain('<section id="notice-email_marketing"');
});

it('renders plain sections (no <details>) when not collapsible', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesResponse(), 200)]);

    $html = Blade::render('<x-audit-notices />');

    expect($html)->toContain('<section id="notice-email_marketing"')
        ->and($html)->not->toContain('<details');
});
