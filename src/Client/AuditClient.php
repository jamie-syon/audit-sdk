<?php

namespace Syon\AuditSdk\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Exceptions\TransportException;
use Syon\AuditSdk\Payload\PushPayload;
use Syon\AuditSdk\Responses\IngestResult;

/**
 * Signs and delivers a push to the platform's per-project ingest endpoint:
 *
 *     POST {base_url}/ingest/{project_id}
 *
 * Mirrors the platform's ingest contract: HMAC over "{timestamp}.{body}", sent
 * as the exact same raw bytes that were signed.
 */
class AuditClient
{
    public function __construct(
        private string $baseUrl,
        private string $projectId,
        private RequestSigner $signer,
        private int $timeout = 10,
        private int $retries = 2,
    ) {}

    public function push(PushPayload $payload): IngestResult
    {
        // Encode ONCE. The signature is taken over exactly these bytes and the
        // same string is handed to the HTTP layer as the raw body — it is never
        // re-encoded between signing and sending.
        $body = $payload->toJson();

        $signed = $this->signer->sign($body);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'X-Timestamp' => $signed['timestamp'],
                    'X-Signature' => $signed['signature'],
                ])
                ->withBody($body, 'application/json')
                // Retry transport failures only. The push_id makes a re-send
                // idempotent on the platform; 401/422 are deterministic and are
                // returned as responses (never thrown), so they are not retried.
                ->retry(max(1, $this->retries), 200, throw: false)
                ->post('/ingest/'.$this->projectId);
        } catch (ConnectionException $e) {
            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        return IngestResult::fromStatus($response->status(), (array) $response->json());
    }

    public function projectId(): string
    {
        return $this->projectId;
    }
}
