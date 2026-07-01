<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function commandLogDir(string $contents): string
{
    $dir = sys_get_temp_dir().'/logcmd-'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir.'/laravel.log', $contents);

    return $dir;
}

function dropCommandLogDir(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($dir);
}

it('reports personal data found in the logs and exits non-zero', function () {
    $dir = commandLogDir('production.ERROR: login failed for alice@example.com');

    $code = Artisan::call('audit:scan-logs', ['--path' => [$dir]]);
    $output = Artisan::output();

    expect($code)->toBe(1)
        ->and($output)->toContain('Email address')
        ->and($output)->not->toContain('alice@example.com'); // redacted in the report

    dropCommandLogDir($dir);
});

it('exits zero when the logs are clean', function () {
    $dir = commandLogDir('production.INFO: health check ok');

    expect(Artisan::call('audit:scan-logs', ['--path' => [$dir]]))->toBe(0);

    dropCommandLogDir($dir);
});

it('emits machine-readable findings with --json', function () {
    $dir = commandLogDir('production.ERROR: login failed for alice@example.com');

    $code = Artisan::call('audit:scan-logs', ['--path' => [$dir], '--json' => true]);
    $output = Artisan::output();

    expect($code)->toBe(1)
        ->and($output)->toContain('"type": "email"')
        ->and($output)->not->toContain('alice@example.com');

    dropCommandLogDir($dir);
});

it('pushes a counts-only summary to the platform with --push, without leaking values', function () {
    Http::fake(['platform.test/log-scan/*' => Http::response(['status' => 'accepted'], 202)]);
    $dir = commandLogDir('production.ERROR: login failed for alice@example.com');

    $code = Artisan::call('audit:scan-logs', ['--path' => [$dir], '--push' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), '/log-scan/proj_123')
            && ($body['findings'][0]['type'] ?? null) === 'email'
            && ! str_contains($request->body(), 'alice@example.com'); // counts/locations only
    });
    expect($code)->toBe(1); // findings present → non-zero exit

    dropCommandLogDir($dir);
});
