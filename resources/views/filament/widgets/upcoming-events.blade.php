{{-- „Evenimente apropiate" — listă în Section nativă (light/dark automat), stilul triajului.
     Fiecare rând: icon pe tip + titlu localizat + meta (tip · oră), cu data la dreapta; link la Calendar. --}}
<x-filament::section
    :heading="__('panel.widgets.upcoming_events.heading')"
    icon="heroicon-o-calendar-days"
    compact
>
    @if ($events === [])
        <p class="fi-events__empty">{{ __('panel.widgets.upcoming_events.empty') }}</p>
    @else
        <div class="fi-triage">
            @foreach ($events as $event)
                <a href="{{ $calendarUrl }}" class="fi-triage__row">
                    <span class="fi-triage__left">
                        <span class="fi-triage__icon">
                            @svg($event['icon'], '', ['style' => 'width:1.05rem;height:1.05rem'])
                        </span>
                        <span class="fi-event__body">
                            <span class="fi-triage__label">{{ $event['title'] }}</span>
                            <span class="fi-event__meta">
                                {{ $event['type'] }}@if ($event['time']) · {{ $event['time'] }}@endif
                            </span>
                        </span>
                    </span>
                    <span class="fi-event__date">{{ $event['date'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</x-filament::section>
