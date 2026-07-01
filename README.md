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

## Sending pushes on a schedule

The platform's liveness check expects a push on your cadence **even when idle** — silence
is read as *broken*, not *quiet*. So wire pushing into your scheduler. The SDK owns the
plumbing (build → sign → send → map result); you supply only *what to count*, since only
your app knows that.

Implement `ProvidesPushPayload` and bind it in a service provider:

```php
use Syon\AuditSdk\Contracts\ProvidesPushPayload;
use Syon\AuditSdk\Payload\{PushPayload, ActivityReport};

class MyPushPayload implements ProvidesPushPayload
{
    public function build(): PushPayload
    {
        return PushPayload::make()->addActivity(
            ActivityReport::for('email_marketing')
                ->liaVersion(3)                          // the version you actually operate under
                ->entriesSinceLast($this->countSinceLastPush())
        );
    }
}

// In a service provider:
$this->app->bind(ProvidesPushPayload::class, MyPushPayload::class);
```

Then schedule the command on your cadence:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('audit:push')->daily()->withoutOverlapping();
```

`php artisan audit:push` builds the payload, sends it, and **exits non-zero** on any failure
(unbound provider, rejected payload, bad signature, unreachable platform) — so cron/CI alerting
catches a broken push. Run it by hand any time to send immediately.

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

## Scanning your logs for personal data

Logs are a data store too — usually with no retention policy, lawful basis, or
notice. `audit:scan-logs` scans your log files for personal data that shouldn't be
sitting in them (emails, credentials/secrets, UK National Insurance numbers, and
Luhn-checked payment card numbers):

```bash
php artisan audit:scan-logs
```

It reads the paths in `config('audit-sdk.log_scan.paths')` (defaults to
`storage/logs`), reports each hit as *type · file · line · redacted context*, and
**exits non-zero** when anything is found — so it can gate CI. Matched values are
redacted in the output: the scan runs entirely locally and never transmits log
contents anywhere.

- `--path=…` — scan specific files or directories (repeatable).
- `--json` — machine-readable output.
- `--push` — send a **counts-and-locations-only** summary to the platform (never the
  matched values), so log exposure shows up centrally and clears itself on a clean run.

Run it on a schedule with `--push` to keep the platform's view current:

```php
Schedule::command('audit:scan-logs --push')->daily()->withoutOverlapping();
```

The scanner is also usable directly:

```php
$findings = app(\Syon\AuditSdk\Logs\LogScanner::class)->scan([storage_path('logs')]);
// [['type' => 'email', 'label' => 'Email address', 'file' => '…', 'line' => 42, 'context' => '… [redacted] …'], …]
```

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

`point` is the collection-point slug from the catalogue. Rendering is **cached**
(`audit-sdk.notice_ttl`, default 300s) and **fail-soft** — a transient platform outage
falls back to the last copy held rather than breaking the form, and nothing renders when
no notice is in force.

### Two layers

GDPR notices are usually **layered**: a short statement on the form (the first layer)
plus the full notice a click away. Both are authored and versioned on the platform:

```blade
{{-- First layer — a short statement shown right at the point of collection --}}
<x-audit-notice-summary point="newsletter_signup" />
<x-audit-notice-dialog point="newsletter_signup" trigger="Read our privacy notice" />
```

`<x-audit-notice-summary>` renders the managed summary line (nothing if none is set), and
the trigger opens the full notice. Surface the link as a **visible first layer, not a buried
link** — the statement should be easy to see before the form is submitted.

### Ways to render the full notice

**1. Inline content** — `<x-audit-notice>` is the reusable atom: it renders the notice
HTML in a div. Drop it on the page, or *inside any modal*.

**2. Link → native dialog** (no frontend framework needed):

```blade
<x-audit-notice-dialog point="newsletter_signup" trigger="Privacy notice" />
```

A trigger link plus a native HTML `<dialog>` — works on any stack (Bootstrap, vanilla,
TALL) with zero dependencies. The `<dialog>` gives the backdrop, ESC-to-close and focus
handling natively; ships scoped, publishable CSS.

**3. Your own modal** — for a notice styled exactly like your design system, use your own
trigger + modal and put the atom inside it. The SDK still handles fetch/cache/fail-soft:

```blade
{{-- e.g. a FluxUI (TALL) app --}}
<flux:modal.trigger name="privacy"><flux:link>Privacy notice</flux:link></flux:modal.trigger>
<flux:modal name="privacy">
    <x-audit-notice point="newsletter_signup" />
</flux:modal>
```

Programmatically (or for a Vue/React frontend), fetch it as data:

```php
$notice = Audit::notice('newsletter_signup');   // ?Syon\AuditSdk\Notice\Notice
$notice?->html;        // the approved Article 13 copy
$notice?->version;     // the in-force version
```

> The notice HTML is authored and sealed by your own people on the platform, so the
> components render it unescaped (trusted content). Publish the views to customise the
> wrapper/dialog: `php artisan vendor:publish --tag=audit-sdk-views`.

## A consolidated privacy policy

The form components above are the *point-of-collection* layer. For the **comprehensive
layer** — a privacy policy listing all your processing — render every in-force notice at
once:

```blade
{{-- In your privacy policy page --}}
<h1>Privacy policy</h1>
{{-- your controller identity, DPO, rights, how to complain… --}}

<x-audit-notices />
```

`<x-audit-notices>` renders one section per processing activity (its approved Article 13
or 14 copy), in lockstep with what's adopted on the platform. It's **per activity, not per
form** — so notices aren't repeated across collection points, and activities with no form
(indirect/Article 14 processing) are still included. Cached and fail-soft like the others.

The activity name is an `<h2>` and the copy's own headings are pushed down to `<h3>` so the
document outline stays correct. Embedding deeper in your page? Pass `:level` to rebase the
whole block — `<x-audit-notices :level="3" />` renders activity names as `<h3>` and copy
headings as `<h4>`.

Every section gets a stable `id="notice-{activity_key}"` for deep-linking, and `:toc` prepends
a jump-link contents list:

```blade
<x-audit-notices :toc="true" />
```

The `<nav class="audit-notices-toc">` is unstyled markup — style it to taste in your own CSS.

For a long policy, `:collapsible` makes each activity a native `<details>` (no JavaScript),
exclusive so **one is open at a time**, with the jump target on the `<summary>` so contents-list
links auto-expand and scroll to it:

```blade
<x-audit-notices :toc="true" :collapsible="true" />
```

> Collapsed content isn't reliably found by in-page search (Ctrl+F) and won't print expanded —
> a trade-off to weigh for a legal document. Leave it off to keep every notice on the page.

The platform owns the *per-activity processing copy*; the *controller-level* content
(your identity, DPO, the rights section, how to complain to the supervisory authority)
stays yours — wrap the component with it.

Programmatically:

```php
$notices = Audit::notices();         // list<Syon\AuditSdk\Notice\PolicyNotice>
foreach ($notices as $notice) {
    $notice->activity;   // 'Email marketing'
    $notice->type;       // 'article13' | 'article14'
    $notice->version;    // in-force version
    $notice->html;       // approved copy
}
```

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
