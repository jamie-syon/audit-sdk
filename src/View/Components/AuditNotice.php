<?php

namespace Syon\AuditSdk\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Syon\AuditSdk\Client\AuditClient;

/**
 * Renders the platform's in-force Article 13 notice at a collection point:
 *
 *     <x-audit-notice point="newsletter_signup" />
 *
 * Cached (config: audit-sdk.notice_ttl) so it isn't fetched on every render, and
 * fail-soft — a transient platform outage falls back to the last known copy and
 * never breaks the form.
 */
class AuditNotice extends Component
{
    public function __construct(public string $point) {}

    public function render(): View
    {
        return view('audit-sdk::notice', ['html' => $this->html()]);
    }

    private function html(): ?string
    {
        $client = app(AuditClient::class);
        $key = 'audit-sdk:notice:'.$client->projectId().':'.$this->point;
        $ttl = (int) config('audit-sdk.notice_ttl', 300);

        // Within the fresh window, serve the cached copy (an empty string means
        // "no notice in force" — cached too, so we don't re-hit the endpoint).
        $fresh = Cache::get($key);
        if ($fresh !== null) {
            return $fresh === '' ? null : $fresh;
        }

        try {
            $html = $client->notice($this->point)?->html ?? '';
            Cache::put($key, $html, $ttl);
            Cache::forever($key.':last', $html); // last-known fallback
        } catch (\Throwable) {
            // Platform unreachable — fall back to the last copy we held, if any.
            $last = Cache::get($key.':last');

            return is_string($last) && $last !== '' ? $last : null;
        }

        return $html === '' ? null : $html;
    }
}
