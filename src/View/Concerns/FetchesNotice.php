<?php

namespace Syon\AuditSdk\View\Concerns;

use Illuminate\Support\Facades\Cache;
use Syon\AuditSdk\Client\AuditClient;

/**
 * Shared notice retrieval for the view components: cached (config: audit-sdk.notice_ttl)
 * so it isn't fetched on every render, and fail-soft — a transient platform outage falls
 * back to the last known copy rather than breaking the page.
 */
trait FetchesNotice
{
    /**
     * The notice's first-layer summary and full HTML for a point. Either may be null
     * (no notice in force, or no summary authored).
     *
     * @return array{html: string|null, summary: string|null}
     */
    protected function resolveNotice(string $point): array
    {
        $client = app(AuditClient::class);
        $key = 'audit-sdk:notice:'.$client->projectId().':'.$point;
        $ttl = (int) config('audit-sdk.notice_ttl', 300);

        $fresh = Cache::get($key);
        if (is_array($fresh)) {
            return $fresh;
        }

        try {
            $notice = $client->notice($point);
            $data = [
                'html' => ($notice?->html ?? '') !== '' ? $notice->html : null,
                'summary' => $notice?->summary,
            ];
            Cache::put($key, $data, $ttl);
            Cache::forever($key.':last', $data); // last-known fallback
        } catch (\Throwable) {
            $last = Cache::get($key.':last');

            return is_array($last) ? $last : ['html' => null, 'summary' => null];
        }

        return $data;
    }
}
