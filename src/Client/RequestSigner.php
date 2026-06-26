<?php

namespace Syon\AuditSdk\Client;

/**
 * Produces the X-Timestamp / X-Signature pair the platform's SignatureVerifier
 * expects: HMAC-SHA256 over "{timestamp}.{rawBody}" keyed by the per-project
 * push secret.
 *
 * The signature is computed over the EXACT bytes that will be transmitted, so
 * the caller must send the same raw string unchanged — re-encoding the body
 * after signing (e.g. letting the HTTP layer re-serialize it) would invalidate
 * the signature and the platform would reject it 401.
 */
class RequestSigner
{
    public function __construct(private string $secret) {}

    /**
     * @param  int|null  $timestamp  Unix seconds; defaults to now. Reused verbatim
     *                               across transport retries so the freshness
     *                               window and signature remain valid.
     * @return array{timestamp: string, signature: string}
     */
    public function sign(string $rawBody, ?int $timestamp = null): array
    {
        $timestamp = (string) ($timestamp ?? time());

        return [
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->secret),
        ];
    }
}
