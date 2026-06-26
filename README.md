# Audit SDK

A Laravel client for pushing LIA verification data to the **audit-platform**
signed ingest API. Install it in any Laravel application that needs to report
its processing activities to the platform.

## How it works

The platform exposes a single per-project endpoint, `POST /ingest/{project}`,
protected by an HMAC signature and a strict JSON schema. This SDK:

1. Builds a schema-conforming payload through typed builders (so you can't
   accidentally emit a field the platform would reject).
2. Signs the **exact bytes** it sends — `HMAC-SHA256("{timestamp}.{body}", push_secret)` —
   and posts them with `X-Timestamp` / `X-Signature` headers.
3. Maps the response: `202` accepted, `401` invalid signature, `422`
   non-conforming payload.

Retries on a connection failure reuse the same `push_id`, which the platform
deduplicates — so a retry is safe and never double-counts.

## Installation

```bash
composer require syon/audit-sdk
```

During local development against a checked-out platform, point Composer at the
package directory:

```json
"repositories": [
    { "type": "path", "url": "../audit-sdk" }
]
```

Publish the config (optional) and set your credentials:

```bash
php artisan vendor:publish --tag=audit-sdk-config
```

```dotenv
AUDIT_SDK_BASE_URL=https://platform.example.com
AUDIT_SDK_PROJECT_ID=proj_123
AUDIT_SDK_PUSH_SECRET=...        # the per-project secret issued by the platform
```

## Usage

```php
use Syon\AuditSdk\Facades\Audit;
use Syon\AuditSdk\Enums\DataOrigin;
use Syon\AuditSdk\Payload\{PushPayload, ActivityReport, CollectionPointReport, Article14Report};

$payload = PushPayload::make()
    ->addActivity(
        ActivityReport::for('email_marketing')
            ->liaVersion(3)
            ->entriesSinceLast(120)
            ->totalEntries(5400)
            ->dataOrigin(DataOrigin::Direct)
            ->addCollectionPoint(
                CollectionPointReport::make('newsletter_signup', noticePresent: true, submissionsSinceLast: 120)
                    ->noticeVersion(2)
            )
            ->article14(
                Article14Report::make(recordsAcquiredIndirectlySinceLast: 0, noticesSentSinceLast: 0, noticesPending: 0)
            )
    );

$result = Audit::push($payload);

if ($result->accepted()) {
    // 202 — stored and reconciled by the platform
} elseif ($result->rejected()) {
    // 422 — fix the payload; $result->error() === 'non_conforming_payload'
} elseif ($result->unauthorized()) {
    // 401 — check the push secret / clock skew (300s freshness window)
}
```

`Audit::push()` throws `Syon\AuditSdk\Exceptions\TransportException` if the
platform can't be reached after retries, and
`Syon\AuditSdk\Exceptions\InvalidPayloadException` if a builder is given a value
the schema would reject (raised locally, before any network call).

## Discovering what to push

The platform knows the canonical activity keys and collection-point slugs it
expects, plus the in-force LIA version per activity. Read that catalogue to
configure your pushes correctly:

```bash
php artisan audit:catalogue
```

```
+-----------------+-----------------+--------------+-------------------+
| Activity key    | Name            | In-force LIA | Collection points |
+-----------------+-----------------+--------------+-------------------+
| email_marketing | Email marketing | v5           | newsletter_signup |
| analytics       | Analytics       | —            | —                 |
+-----------------+-----------------+--------------+-------------------+
```

Add `--json` for machine-readable output. Programmatically:

```php
$catalogue = Audit::catalogue();           // Syon\AuditSdk\Catalogue\Catalogue
foreach ($catalogue->activities as $activity) {
    $activity->activityKey;        // 'email_marketing'
    $activity->liaVersionInForce;  // 5 or null
    $activity->collectionPoints;   // ['newsletter_signup']
}
```

> **Important — this is a *configuration-time* aid, not a runtime feed.** Use it
> to align the **keys and slugs** you report under. Do **not** copy
> `liaVersionInForce` into the `lia_version` you push: that value must reflect the
> version your application actually operates under, so the platform can still
> detect when you've fallen behind an in-force LIA. The catalogue is read-only and
> never wired into the push path.

## Rendering notices on your forms

The platform is the source of truth for your Article 13 notice copy — you author,
seal and adopt it there. Render the in-force notice straight onto the form at its
point of collection, so the live page always matches the approved, versioned copy:

```blade
<form method="POST" action="/contact">
    @csrf
    <x-audit-notice point="newsletter_signup" />
    {{-- your fields --}}
</form>
```

`point` is the collection-point slug from the catalogue. The component fetches the
in-force notice for it and renders the HTML. It's **cached** (`audit-sdk.notice_ttl`,
default 300s) so it isn't fetched on every render, and **fail-soft** — a transient
platform outage falls back to the last copy it held rather than breaking the form.
It renders nothing when no notice is in force for that point.

Programmatically:

```php
$notice = Audit::notice('newsletter_signup');   // ?Syon\AuditSdk\Notice\Notice
$notice?->html;        // the approved Article 13 copy
$notice?->version;     // the in-force version
```

> The notice HTML is authored and sealed by your own people on the platform, so the
> component renders it unescaped (trusted content). Publish the view to customise the
> wrapper: `php artisan vendor:publish --tag=audit-sdk-views`.

## Payload reference

Mirrors the platform's `ingest-v1` schema:

| Builder | Required | Optional |
|---|---|---|
| `PushPayload` | `schema_version` (fixed 1), `push_id`, `generated_at`, `activities` | — |
| `ActivityReport` | `activity_key`, `lia_version`, `entries_since_last` | `total_entries`, `latest_entry_at`, `data_origin`, `article13`, `article14` |
| `CollectionPointReport` | `collection_point`, `notice_present`, `submissions_since_last` | `notice_version`, `latest_submission_at` |
| `Article14Report` | `records_acquired_indirectly_since_last`, `notices_sent_since_last`, `notices_pending` | `notices_bounced_since_last`, `notices_suppressed_since_last`, `latest_notice_at` |

## Testing

```bash
composer install
composer test
```

`tests/fixtures/ingest-v1.json` is a vendored copy of the platform's schema;
`PayloadSchemaTest` validates builder output against it, so any drift between
the two surfaces immediately.
