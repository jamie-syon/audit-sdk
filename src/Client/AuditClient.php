<?php

namespace Syon\AuditSdk\Client;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Syon\AuditSdk\Catalogue\Catalogue;
use Syon\AuditSdk\Events\PushAccepted;
use Syon\AuditSdk\Events\PushFailed;
use Syon\AuditSdk\Events\PushRejected;
use Syon\AuditSdk\Exceptions\TransportException;
use Syon\AuditSdk\Notice\Notice;
use Syon\AuditSdk\Notice\PolicyNotice;
use Syon\AuditSdk\Payload\PushPayload;
use Syon\AuditSdk\Responses\IngestResult;

/**
 * Signs and delivers a push to the platform's per-project ingest endpoint:
 *
 *     POST {base_url}/ingest/{project_id}
 *
 * and reads the integration catalogue from its read-side companion:
 *
 *     GET  {base_url}/catalogue/{project_id}
 *
 * Mirrors the platform's HMAC contract: the signature is over "{timestamp}.{body}",
 * sent as the exact same raw bytes that were signed (the body is empty for the GET).
 */
class AuditClient
{
    public function __construct(
        private string $baseUrl,
        private string $projectId,
        private RequestSigner $signer,
        private int $timeout = 10,
        private int $retries = 2,
        // Optional so pure (non-Laravel) unit tests can construct the client without a
        // container; when present, push outcomes are dispatched as events.
        private ?Dispatcher $events = null,
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
            $this->events?->dispatch(new PushFailed($payload, $e));

            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $result = IngestResult::fromStatus($response->status(), (array) $response->json());

        // Fire from the client so every push path (the scheduled command, a manual
        // Audit::push(), a future queued job) reports the same outcome.
        $this->events?->dispatch($result->accepted()
            ? new PushAccepted($payload, $result)
            : new PushRejected($payload, $result));

        return $result;
    }

    /**
     * Push a log-scan summary to the platform:
     *
     *     POST {base_url}/log-scan/{project_id}
     *
     * Counts and locations only — the report carries no matched values, so no
     * personal data leaves the application. Backs central visibility of the
     * `audit:scan-logs` findings.
     *
     * @param  array{scanned_at?: string, findings: list<array{type: string, label: string, file: string, line: int}>}  $report
     */
    public function pushLogScan(array $report): IngestResult
    {
        $body = (string) json_encode($report, JSON_UNESCAPED_SLASHES);

        $signed = $this->signer->sign($body);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'X-Timestamp' => $signed['timestamp'],
                    'X-Signature' => $signed['signature'],
                ])
                ->withBody($body, 'application/json')
                ->retry(max(1, $this->retries), 200, throw: false)
                ->post('/log-scan/'.$this->projectId);
        } catch (ConnectionException $e) {
            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        return IngestResult::fromStatus($response->status(), (array) $response->json());
    }

    /**
     * Fetch the platform's integration catalogue: the canonical activity keys,
     * collection-point slugs, and in-force LIA version per activity. Read-only —
     * use it to configure what you push, never to set the lia_version you report.
     */
    public function catalogue(): Catalogue
    {
        // A GET carries no body; the signature covers "{timestamp}." (empty body).
        $signed = $this->signer->sign('');

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'X-Timestamp' => $signed['timestamp'],
                    'X-Signature' => $signed['signature'],
                ])
                ->retry(max(1, $this->retries), 200, throw: false)
                ->get('/catalogue/'.$this->projectId);
        } catch (ConnectionException $e) {
            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() !== 200) {
            $error = is_array($response->json()) ? ($response->json()['error'] ?? null) : null;

            throw new TransportException(
                "Catalogue request failed ({$response->status()}".($error ? ": {$error}" : '').').'
            );
        }

        return Catalogue::fromArray((array) $response->json());
    }

    /**
     * Fetch the in-force Article 13 notice for a collection point, to render at the
     * point of collection. Returns null when the platform has no notice in force for
     * it (HTTP 404).
     */
    public function notice(string $collectionPoint): ?Notice
    {
        $signed = $this->signer->sign('');

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'X-Timestamp' => $signed['timestamp'],
                    'X-Signature' => $signed['signature'],
                ])
                ->retry(max(1, $this->retries), 200, throw: false)
                ->get('/notice/'.$this->projectId.'/'.rawurlencode($collectionPoint));
        } catch (ConnectionException $e) {
            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->status() !== 200) {
            throw new TransportException("Notice request failed ({$response->status()}).");
        }

        return Notice::fromArray((array) $response->json());
    }

    /**
     * Fetch every in-force notice for the project (Article 13 + 14, one per activity),
     * to render a consolidated privacy policy. Returns an empty array when none are in
     * force.
     *
     * @return list<PolicyNotice>
     */
    public function notices(): array
    {
        $signed = $this->signer->sign('');

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders([
                    'X-Timestamp' => $signed['timestamp'],
                    'X-Signature' => $signed['signature'],
                ])
                ->retry(max(1, $this->retries), 200, throw: false)
                ->get('/notices/'.$this->projectId);
        } catch (ConnectionException $e) {
            throw new TransportException(
                "Could not reach the audit platform at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() !== 200) {
            throw new TransportException("Notices request failed ({$response->status()}).");
        }

        $body = (array) $response->json();
        $items = is_array($body['notices'] ?? null) ? $body['notices'] : [];

        return array_values(array_map(
            static fn (array $notice): PolicyNotice => PolicyNotice::fromArray($notice),
            $items,
        ));
    }

    public function projectId(): string
    {
        return $this->projectId;
    }
}
