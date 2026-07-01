@php($h = 'h'.min(6, max(1, $level)))
@php($hSub = 'h'.min(6, max(1, $level + 1)))

@if ($controller && ! $controller->isEmpty())
    <section data-audit-controller>
        <{{ $h }}>{{ $heading }}</{{ $h }}>

        @foreach ($controller->controllers as $c)
            <div data-audit-controller-entity>
                @if (count($controller->controllers) > 1)
                    <{{ $hSub }}>{{ $c['name'] ?? '' }}</{{ $hSub }}>
                @else
                    <p><strong>{{ $c['name'] ?? '' }}</strong></p>
                @endif
                @if (! empty($c['address']))
                    <p>{!! nl2br(e($c['address'])) !!}</p>
                @endif
                @if (! empty($c['email']))
                    <p>Email: <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></p>
                @endif
                @if (! empty($c['phone']))
                    <p>Phone: {{ $c['phone'] }}</p>
                @endif
            </div>
        @endforeach

        @if (count($controller->controllers) > 1 && $controller->jointArrangement)
            <p data-audit-controller-arrangement>{{ $controller->jointArrangement }}</p>
        @endif

        @if ($controller->dpo)
            <div data-audit-controller-dpo>
                <{{ $hSub }}>Data Protection Officer</{{ $hSub }}>
                @if (! empty($controller->dpo['name']))
                    <p>{{ $controller->dpo['name'] }}</p>
                @endif
                @if (! empty($controller->dpo['email']))
                    <p>Email: <a href="mailto:{{ $controller->dpo['email'] }}">{{ $controller->dpo['email'] }}</a></p>
                @endif
            </div>
        @endif

        @foreach ($controller->representatives as $rep)
            <div data-audit-controller-representative>
                <{{ $hSub }}>{{ ($rep['region'] ?? '') === 'EU' ? 'EU' : 'UK' }} representative</{{ $hSub }}>
                @if (! empty($rep['name']))
                    <p>{{ $rep['name'] }}</p>
                @endif
                @if (! empty($rep['address']))
                    <p>{!! nl2br(e($rep['address'])) !!}</p>
                @endif
                @if (! empty($rep['email']))
                    <p>Email: <a href="mailto:{{ $rep['email'] }}">{{ $rep['email'] }}</a></p>
                @endif
            </div>
        @endforeach
    </section>
@endif
