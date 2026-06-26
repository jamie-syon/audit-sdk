<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform endpoint
    |--------------------------------------------------------------------------
    |
    | The base URL of the audit-platform installation that will receive pushes,
    | and the project this application reports for. Both are issued by the
    | platform when a project is granted a push secret.
    |
    */

    'base_url' => env('AUDIT_SDK_BASE_URL'),

    'project_id' => env('AUDIT_SDK_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Push secret
    |--------------------------------------------------------------------------
    |
    | The per-project HMAC secret. Every push is signed with it; a leaked URL
    | alone cannot inject data. Keep this out of source control — set it via the
    | environment only.
    |
    */

    'push_secret' => env('AUDIT_SDK_PUSH_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | timeout  — seconds to wait for the platform before failing.
    | retries  — how many times to re-send on a *connection* failure only.
    |            Retries reuse the same push_id, so the platform deduplicates
    |            them; deterministic 401/422 responses are never retried.
    |
    */

    'timeout' => (int) env('AUDIT_SDK_TIMEOUT', 10),

    'retries' => (int) env('AUDIT_SDK_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Notice cache
    |--------------------------------------------------------------------------
    |
    | How long (seconds) the <x-audit-notice> component caches a fetched notice
    | before re-checking the platform. The last fetched copy is also kept as a
    | fail-soft fallback if the platform is briefly unreachable.
    |
    */

    'notice_ttl' => (int) env('AUDIT_SDK_NOTICE_TTL', 300),

];
