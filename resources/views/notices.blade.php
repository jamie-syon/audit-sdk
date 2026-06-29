{{-- The consolidated set of in-force notices — one section per processing activity. --}}
@if (! empty($notices))
    @php($tag = 'h'.$level)
    <div {{ $attributes->merge(['class' => 'audit-notices']) }}>
        @if ($toc)
            <nav class="audit-notices-toc" aria-label="Contents">
                <ul>
                    @foreach ($notices as $notice)
                        <li><a href="#notice-{{ $notice['activity_key'] }}">{{ $notice['activity'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @foreach ($notices as $notice)
            <section id="notice-{{ $notice['activity_key'] }}" class="audit-notice" data-audit-notice-activity="{{ $notice['activity_key'] }}">
                <{{ $tag }}>{{ $notice['activity'] }}</{{ $tag }}>
                {!! $notice['html'] !!}
            </section>
        @endforeach
    </div>
@endif
