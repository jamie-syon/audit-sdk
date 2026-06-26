{{-- Renders the platform's approved Article 13 notice. The HTML is authored and
     sealed by the project's own people on the platform, so it's trusted content. --}}
@if (! empty($html))
    <div {{ $attributes->merge(['class' => 'audit-notice']) }} data-audit-notice="{{ $point }}">
        {!! $html !!}
    </div>
@endif
