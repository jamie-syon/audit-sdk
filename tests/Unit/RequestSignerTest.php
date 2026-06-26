<?php

use Syon\AuditSdk\Client\RequestSigner;

it('signs with the platform scheme: HMAC-SHA256 over "{timestamp}.{body}"', function () {
    $signer = new RequestSigner('test-secret-key');

    $signed = $signer->sign('{"schema_version":1}', 1700000000);

    expect($signed['timestamp'])->toBe('1700000000')
        // Cross-checked against the platform's SignatureVerifier formula.
        ->and($signed['signature'])->toBe('d7f1ccbf9b1132bea6443f76729bc165cb243ec08e2fe4e66884991b648830ab');
});

it('defaults the timestamp to now', function () {
    $before = time();
    $signed = (new RequestSigner('s'))->sign('body');

    expect((int) $signed['timestamp'])->toBeGreaterThanOrEqual($before);
});

it('produces a different signature when the body changes', function () {
    $signer = new RequestSigner('s');

    expect($signer->sign('a', 1)['signature'])
        ->not->toBe($signer->sign('b', 1)['signature']);
});
