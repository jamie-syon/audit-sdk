<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Facades\Audit;
use Syon\AuditSdk\Notice\ControllerDetails;

function fakeNoticesWithController(?array $controller): array
{
    return [
        'project' => 'proj_123',
        'controller' => $controller,
        'notices' => [[
            'activity_key' => 'email_marketing', 'activity' => 'Email marketing', 'purpose' => 'News',
            'type' => 'article13', 'version' => 1, 'summary' => null, 'notice' => '<p>Notice.</p>',
        ]],
    ];
}

it('parses controller details (controllers, DPO, UK/EU representatives) from the notices response', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesWithController([
        'controllers' => [['name' => 'Acme Ltd', 'email' => 'privacy@acme.test', 'address' => '1 St', 'phone' => '']],
        'dpo' => ['name' => 'Jo', 'email' => 'dpo@acme.test'],
        'representatives' => [['region' => 'EU', 'name' => 'EU Rep', 'email' => 'eu@rep.test', 'address' => '']],
    ]), 200)]);

    $controller = Audit::controllerDetails();

    expect($controller)->toBeInstanceOf(ControllerDetails::class)
        ->and($controller->controllers[0]['name'])->toBe('Acme Ltd')
        ->and($controller->dpo['email'])->toBe('dpo@acme.test')
        ->and($controller->representatives[0]['region'])->toBe('EU');
});

it('returns null when no controller is captured', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesWithController(null), 200)]);

    expect(Audit::controllerDetails())->toBeNull();
});

it('renders the controller identity via <x-audit-controller>', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesWithController([
        'controllers' => [['name' => 'Acme Ltd', 'email' => 'privacy@acme.test', 'address' => '1 High St', 'phone' => '']],
    ]), 200)]);

    $html = Blade::render('<x-audit-controller />');

    expect($html)->toContain('Who we are')
        ->and($html)->toContain('Acme Ltd')
        ->and($html)->toContain('privacy@acme.test')
        ->and($html)->toContain('1 High St');
});

it('renders nothing when no controller is captured', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesWithController(null), 200)]);

    expect(trim(Blade::render('<x-audit-controller />')))->toBe('');
});

it('heads the consolidated notices with the controller identity', function () {
    Http::fake(['platform.test/notices/*' => Http::response(fakeNoticesWithController([
        'controllers' => [['name' => 'Acme Ltd', 'email' => 'privacy@acme.test', 'address' => '', 'phone' => '']],
    ]), 200)]);

    $html = Blade::render('<x-audit-notices />');

    expect($html)->toContain('Who we are')       // controller header
        ->and($html)->toContain('Acme Ltd')
        ->and($html)->toContain('Email marketing'); // the notice still renders beneath it
});
