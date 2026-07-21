{{--
    Suprascriere a componentei `x-filament::tabs` (audit responsiv 2026-07-21).

    Bara de taburi se derulează orizontal când nu încap toate (fișa elevului are șase: Note,
    Absențe, Foaie matricolă, Înmatriculări, Tutori, Jurnal) — pe mobil asta e legitim, dar
    vendorul lăsa doar bara nativă de scroll: urâtă și, pe touch, invizibilă până începi să
    tragi. Aici bara e ascunsă și înlocuită cu SĂGEȚI care apar DOAR când chiar mai e conținut
    în direcția lor, iar tabul activ e adus în vizor la încărcare.

    Se aplică tuturor barelor de taburi din panou (relation managers + navigatoare), fără să
    atingem fiecare blade. Varianta VERTICALĂ rămâne exact ca în vendor — nu derulează orizontal.
--}}
@props([
    'contained' => false,
    'label' => null,
    'vertical' => false,
])

@if ($vertical)
    <nav
        {{
            $attributes
                ->merge([
                    'aria-label' => $label,
                    'role' => 'tablist',
                ])
                ->class([
                    'fi-tabs',
                    'fi-contained' => $contained,
                    'fi-vertical',
                ])
        }}
    >
        {{ $slot }}
    </nav>
@else
    <div
        class="fi-tabs-scroller"
        x-data="{
            overflowing: false,
            atStart: true,
            atEnd: false,
            sync() {
                const el = this.$refs.tabs;

                if (! el) {
                    return;
                }

                this.overflowing = el.scrollWidth > el.clientWidth + 1;
                this.atStart = el.scrollLeft <= 1;
                this.atEnd = Math.ceil(el.scrollLeft + el.clientWidth) >= el.scrollWidth - 1;
            },
            nudge(direction) {
                const el = this.$refs.tabs;

                el.scrollBy({
                    left: direction * Math.max(140, el.clientWidth * 0.7),
                    behavior: 'smooth',
                });
            },
            init() {
                this.$nextTick(() => {
                    // Tabul activ poate fi al șaselea — fără asta ar rămâne în afara ecranului.
                    this.$refs.tabs?.querySelector('.fi-active')?.scrollIntoView({
                        inline: 'center',
                        block: 'nearest',
                    });

                    this.sync();
                });
            },
        }"
        x-on:resize.window.debounce.150ms="sync()"
    >
        <button
            type="button"
            class="fi-tabs-scroll-btn fi-tabs-scroll-btn-start"
            x-cloak
            x-show="overflowing && ! atStart"
            x-transition.opacity
            x-on:click="nudge(-1)"
            aria-label="{{ __('panel.nav.tabs_scroll_start') }}"
            title="{{ __('panel.nav.tabs_scroll_start') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="16" height="16" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>

        <nav
            x-ref="tabs"
            x-on:scroll.passive.debounce.50ms="sync()"
            {{
                $attributes
                    ->merge([
                        'aria-label' => $label,
                        'role' => 'tablist',
                    ])
                    ->class([
                        'fi-tabs',
                        'fi-contained' => $contained,
                    ])
            }}
        >
            {{ $slot }}
        </nav>

        <button
            type="button"
            class="fi-tabs-scroll-btn fi-tabs-scroll-btn-end"
            x-cloak
            x-show="overflowing && ! atEnd"
            x-transition.opacity
            x-on:click="nudge(1)"
            aria-label="{{ __('panel.nav.tabs_scroll_end') }}"
            title="{{ __('panel.nav.tabs_scroll_end') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="16" height="16" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
@endif
