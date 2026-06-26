<?php

namespace Syon\AuditSdk\View\Concerns;

use Illuminate\Support\Facades\Cache;
use Syon\AuditSdk\Client\AuditClient;

/**
 * Shared notice retrieval for the view components: cached (config: audit-sdk.notice_ttl)
 * so it isn't fetched on every render, and fail-soft — a transient platform outage falls
 * back to the last known copy rather than breaking the page. Returns null when no notice
 * is in force for the point.
 */
trait FetchesNotice
{
    protected function noticeHtml(string $point): ?string
    {
        $client = app(AuditClient::class);
        $key = 'audit-sdk:notice:'.$client->projectId().':'.$point;
        $ttl = (int) config('audit-sdk.notice_ttl', 300);

        // Within the fresh window, serve the cached copy ('' means "no notice in force",
        // cached too so we don't re-hit the endpoint on every render).
        $fresh = Cache::get($key);
        if ($fresh !== null) {
            return $fresh === '' ? null : $fresh;
        }

        try {
            $html = $client->notice($point)?->html ?? '';
            Cache::put($key, $html, $ttl);
            Cache::forever($key.':last', $html); // last-known fallback
        } catch (\Throwable) {
            $last = Cache::get($key.':last');

            return is_string($last) && $last !== '' ? $last : null;
        }

        return $html === '' ? null : $html;
    }
}
