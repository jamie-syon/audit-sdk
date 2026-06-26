<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function fakeCatalogueResponse(): array
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
        ],
    ];
}

it('prints the catalogue as a table', function () {
    Http::fake(['platform.test/catalogue/*' => Http::response(fakeCatalogueResponse(), 200)]);

    $code = Artisan::call('audit:catalogue');
    $output = Artisan::output();

    expect($code)->toBe(0)
        ->and($output)->toContain('email_marketing')
        ->and($output)->toContain('v5')
        ->and($output)->toContain('newsletter_signup');
});

it('outputs raw JSON with --json', function () {
    Http::fake(['platform.test/catalogue/*' => Http::response(fakeCatalogueResponse(), 200)]);

    $code = Artisan::call('audit:catalogue', ['--json' => true]);
    $output = Artisan::output();

    expect($code)->toBe(0)
        ->and($output)->toContain('"activity_key": "email_marketing"')
        ->and($output)->toContain('"lia_version_in_force": 5');
});

it('fails cleanly when the platform rejects the request', function () {
    Http::fake(['platform.test/*' => Http::response(['error' => 'invalid_signature'], 401)]);

    expect(Artisan::call('audit:catalogue'))->toBe(1);
});
