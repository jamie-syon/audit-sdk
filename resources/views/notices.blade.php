{{-- The consolidated set of in-force notices — one section per processing activity. --}}
@if (! empty($notices))
    @php($tag = 'h'.$level)
    <div {{ $attributes->merge(['class' => 'audit-notices']) }}>
        {{-- Controller identity heads the policy (Article 13(1)(a)); null/suppressed renders nothing. --}}
        @include('audit-sdk::controller', ['controller' => $controllerDetails ?? null, 'level' => $level, 'heading' => 'Who we are and how to contact us'])
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
            @if ($collapsible)
                {{-- Native <details>: zero-JS, one open at a time via name=. The id sits on the
                     <summary> so a jump link auto-expands and scrolls to it. --}}
                <details name="audit-notices" class="audit-notice" data-audit-notice-activity="{{ $notice['activity_key'] }}">
                    <summary id="notice-{{ $notice['activity_key'] }}"><{{ $tag }}>{{ $notice['activity'] }}</{{ $tag }}></summary>
                    {!! $notice['html'] !!}
                </details>
            @else
                <section id="notice-{{ $notice['activity_key'] }}" class="audit-notice" data-audit-notice-activity="{{ $notice['activity_key'] }}">
                    <{{ $tag }}>{{ $notice['activity'] }}</{{ $tag }}>
                    {!! $notice['html'] !!}
                </section>
            @endif
        @endforeach
    </div>
@endif
