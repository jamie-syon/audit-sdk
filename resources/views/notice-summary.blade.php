{{-- The first-layer summary: plain text authored on the platform. Rendered escaped. --}}
@if (! empty($summary))
    <p {{ $attributes->merge(['class' => 'audit-notice-summary']) }} data-audit-notice-summary="{{ $point }}">
        {{ $summary }}
    </p>
@endif
