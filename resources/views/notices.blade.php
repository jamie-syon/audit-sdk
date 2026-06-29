{{-- The consolidated set of in-force notices — one section per processing activity. --}}
@if (! empty($notices))
    @php($tag = 'h'.$level)
    <div {{ $attributes->merge(['class' => 'audit-notices']) }}>
        @foreach ($notices as $notice)
            <section class="audit-notice" data-audit-notice-activity="{{ $notice['activity_key'] }}">
                <{{ $tag }}>{{ $notice['activity'] }}</{{ $tag }}>
                {!! $notice['html'] !!}
            </section>
        @endforeach
    </div>
@endif
