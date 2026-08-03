{{-- Banda de context a dashboard-ului staff: unde sunt · când sunt · ce am azi. Stilurile stau în
     theme.css (widget Livewire cu un singur root — un `<style>` frate ar fi eliminat la morphing).

     Wrapper-ul `<x-filament-widgets::widget>` NU e decorativ: el e singurul loc unde Filament aplică
     `$columnSpan` (`gridColumn(...)` în vendor/filament/widgets/.../widget.blade.php). Un view care
     randează direct propriul `<div>` primește `auto`, oricât ar declara widget-ul — de-aceea banda
     apărea pe jumătate de lățime deși cerea 'full'. --}}
<x-filament-widgets::widget>
<div class="fi-welcome">

    {{-- 1. Identitate + perimetrul rolului activ. Fără avatar / pastilă de rol / dată: toate trei
           există în antet, două dintre ele LIVE, iar pastila de acolo e chiar comutatorul de rol. --}}
    <div class="fi-welcome__bar">
        <div class="fi-welcome__main">
            <p class="fi-welcome__greeting">{{ $greeting }}, <span>{{ $name }}</span></p>
            @if ($perimeter !== null)
                <p class="fi-welcome__perimeter">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path fill-rule="evenodd" d="M.664 10.59a1.65 1.65 0 010-1.186A10 10 0 0110 3c4.257 0 7.893 2.66 9.336 6.41a1.65 1.65 0 010 1.186A10 10 0 0110 17a10 10 0 01-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                    {{ $perimeter }}
                </p>
            @endif
        </div>

        @if ($today !== null)
            <div class="fi-welcome__today">
                <p class="fi-welcome__today-label">{{ __('panel.widgets.hero.today.label') }}</p>
                @if ($today['count'] > 0)
                    <p class="fi-welcome__today-value">{{ trans_choice('panel.widgets.hero.today.lessons', $today['count'], ['count' => $today['count']]) }}</p>
                    @if ($today['first'] !== null)
                        <p class="fi-welcome__today-detail">{{ $today['first'] }}</p>
                    @endif
                @else
                    <p class="fi-welcome__today-value fi-welcome__today-value--quiet">
                        {{ __($today['closed'] ? 'panel.widgets.hero.today.closed' : 'panel.widgets.hero.today.none') }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- 2. Rigla anului școlar. Segmentele sunt LINKURI doar pentru cine poate configura școala:
           semestrul duce la Semestre, vacanța la Zile libere — acolo se corectează ce vezi pe riglă.
           Pentru ceilalți rămâne un indicator, ca să nu promitem o acțiune care s-ar termina în 403. --}}
    @if ($ruler !== null)
        <div class="fi-ruler">
            <div class="fi-ruler__track" role="img" aria-label="{{ $ruler['yearLabel'] }} — {{ $ruler['caption'] }}">
                @foreach ($ruler['segments'] as $segment)
                    @php
                        $segmentClass = 'fi-ruler__segment fi-ruler__segment--'.$segment['kind'];
                        $segmentUrl = $segment['kind'] === 'term' ? $termsUrl : $holidaysUrl;
                    @endphp
                    @if ($canConfigure)
                        <a class="{{ $segmentClass }}" style="width: {{ $segment['width'] }}%"
                           href="{{ $segmentUrl }}" title="{{ $segment['label'] }}">
                            <span class="fi-ruler__sr">{{ $segment['label'] }}</span>
                        </a>
                    @else
                        <span class="{{ $segmentClass }}" style="width: {{ $segment['width'] }}%"
                              title="{{ $segment['label'] }}"></span>
                    @endif
                @endforeach

                @if ($ruler['todayPercent'] !== null)
                    <span class="fi-ruler__today" style="left: {{ $ruler['todayPercent'] }}%"
                          title="{{ __('panel.widgets.hero.ruler.today') }}" aria-hidden="true"></span>
                @endif
            </div>

            <div class="fi-ruler__legend">
                @foreach ($ruler['segments'] as $segment)
                    <span class="fi-ruler__legend-item" style="width: {{ $segment['width'] }}%">{{ $segment['label'] }}</span>
                @endforeach
            </div>

            <p class="fi-ruler__caption">
                <span class="fi-ruler__year">{{ $ruler['yearLabel'] }}</span>{{ $ruler['caption'] }}
            </p>
        </div>
    @endif

    @if ($missingTeacherProfile)
        {{-- Profesor/diriginte cu rol dar fără fișă Teacher: fără perimetru și fără lecții — îndrumare. --}}
        <div class="fi-welcome-hint" role="status">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                 style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 100 2 1 1 0 000-2zm-1 5a1 1 0 011-1h.01a1 1 0 01.99 1v3a1 1 0 11-2 0v-3z" clip-rule="evenodd" />
            </svg>
            <p>{{ trans('panel.widgets.welcome.missing_teacher') }}</p>
        </div>
    @endif
</div>
</x-filament-widgets::widget>
