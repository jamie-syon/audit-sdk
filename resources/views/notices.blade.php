{{-- The consolidated set of in-force notices — one section per processing activity. --}}
@if (! empty($notices))
    <div {{ $attributes->merge(['class' => 'audit-notices']) }}>
        @foreach ($notices as $notice)
            <section class="audit-notice" data-audit-notice-activity="{{ $notice['activity_key'] }}">
                <h2>{{ $notice['activity'] }}</h2>
                {!! $notice['html'] !!}
            </section>
        @endforeach
    </div>
@endif
