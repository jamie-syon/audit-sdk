<?php

use Syon\AuditSdk\Logs\LogScanner;

function logDir(string $contents): string
{
    $dir = sys_get_temp_dir().'/logscan-'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir.'/laravel.log', $contents);

    return $dir;
}

function dropLogDir(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($dir);
}

it('flags emails, credentials and card numbers, but not clean lines', function () {
    $dir = logDir(implode("\n", [
        '[2026-01-01] production.ERROR: Login failed for alice@example.com',
        '[2026-01-01] local.DEBUG: request {"password":"hunter2","name":"Bob"}',
        '[2026-01-01] production.INFO: Charged card 4111 1111 1111 1111',
        '[2026-01-01] production.INFO: Health check ok',
    ]));

    $findings = (new LogScanner)->scan([$dir]);
    $types = array_map(fn (array $f): string => $f['type'], $findings);

    expect($types)->toContain('email')
        ->and($types)->toContain('credential')
        ->and($types)->toContain('credit_card')
        ->and(collect($findings)->pluck('line')->unique()->sort()->values()->all())->toBe([1, 2, 3]); // line 4 is clean

    dropLogDir($dir);
});

it('redacts the matched values so its own output never leaks personal data', function () {
    $dir = logDir('production.ERROR: user alice@example.com password="hunter2" card 4111111111111111');

    $findings = (new LogScanner)->scan([$dir]);
    $context = $findings[0]['context'];

    expect($context)->not->toContain('alice@example.com')
        ->and($context)->not->toContain('hunter2')
        ->and($context)->not->toContain('4111111111111111')
        ->and($context)->toContain('[redacted]');

    dropLogDir($dir);
});

it('does not flag digit runs that fail the Luhn check', function () {
    $dir = logDir('production.INFO: reference number 1234567890123456'); // 16 digits, not a valid card

    expect((new LogScanner)->scan([$dir]))->toBe([]);

    dropLogDir($dir);
});

it('flags a personal detail logged under a recognised key, but not a name in free text', function () {
    $keyed = logDir(implode("\n", [
        '[2026-01-01] local.DEBUG: context {"first_name":"Jane","surname":"Doe"}',
        '[2026-01-01] local.INFO: phone=07700900123',
    ]));
    $freeText = logDir('[2026-01-01] production.INFO: Order shipped to Jane Doe in London');

    $keyedTypes = array_map(fn (array $f): string => $f['type'], (new LogScanner)->scan([$keyed]));

    expect($keyedTypes)->toContain('personal_field')                 // "first_name"/"surname"/"phone" keys
        ->and((new LogScanner)->scan([$freeText]))->toBe([]);         // a name in prose is not matched

    dropLogDir($keyed);
    dropLogDir($freeText);
});

it('redacts personal field values so a keyed name never leaks', function () {
    $dir = logDir('local.DEBUG: user {"first_name":"Jane"}');

    $context = (new LogScanner)->scan([$dir])[0]['context'];

    expect($context)->not->toContain('Jane')
        ->and($context)->toContain('[redacted]');

    dropLogDir($dir);
});
