<?php

namespace Syon\AuditSdk\View\Concerns;

use Illuminate\Support\Facades\Cache;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Notice\ControllerDetails;
use Syon\AuditSdk\Notice\PolicyNotice;

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

    /**
     * Every in-force notice for the project, for a consolidated privacy policy. Cached
     * and fail-soft, like resolveNotice(). Each row is one activity's approved copy.
     *
     * @return list<array{activity_key: string, activity: string, purpose: string|null, type: string, version: int, summary: string|null, html: string}>
     */
    protected function resolveNoticeList(): array
    {
        $client = app(AuditClient::class);
        $key = 'audit-sdk:notices:'.$client->projectId();
        $ttl = (int) config('audit-sdk.notice_ttl', 300);

        $fresh = Cache::get($key);
        if (is_array($fresh)) {
            return $fresh;
        }

        try {
            $data = array_map(static fn (PolicyNotice $notice): array => [
                'activity_key' => $notice->activityKey,
                'activity' => $notice->activity,
                'purpose' => $notice->purpose,
                'type' => $notice->type,
                'version' => $notice->version,
                'summary' => $notice->summary,
                'html' => $notice->html,
            ], $client->notices());
            Cache::put($key, $data, $ttl);
            Cache::forever($key.':last', $data); // last-known fallback
        } catch (\Throwable) {
            $last = Cache::get($key.':last');

            return is_array($last) ? $last : [];
        }

        return $data;
    }

    /**
     * The project's data controller identity + contact details (Article 13(1)(a)/(b)),
     * cached and fail-soft. Null when none have been captured. The result is wrapped in
     * an array so a legitimate "no controller" (null) still caches rather than re-fetching.
     */
    protected function resolveController(): ?ControllerDetails
    {
        $client = app(AuditClient::class);
        $key = 'audit-sdk:controller:'.$client->projectId();
        $ttl = (int) config('audit-sdk.notice_ttl', 300);

        $cached = Cache::get($key);
        if (is_array($cached) && array_key_exists('controller', $cached)) {
            return $cached['controller'];
        }

        try {
            $controller = $client->controllerDetails();
            Cache::put($key, ['controller' => $controller], $ttl);
            Cache::forever($key.':last', ['controller' => $controller]);

            return $controller;
        } catch (\Throwable) {
            $last = Cache::get($key.':last');

            return is_array($last) ? ($last['controller'] ?? null) : null;
        }
    }
}
