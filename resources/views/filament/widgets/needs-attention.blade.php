{{-- „Necesită atenție" — listă de triaj în Section nativă (light/dark automat). Rândurile folosesc
     `color-mix(currentColor …)` pentru borduri/fundaluri → se adaptează la tema activă fără override-uri. --}}
<x-filament::section
    :heading="__('panel.widgets.needs_attention.heading')"
    icon="heroicon-o-flag"
    compact
>
    <div class="fi-triage">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" class="fi-triage__row {{ $item['count'] > 0 ? 'is-alert' : '' }}">
                <span class="fi-triage__left">
                    <span class="fi-triage__icon">
                        @svg($item['icon'], '', ['style' => 'width:1.05rem;height:1.05rem'])
                    </span>
                    <span class="fi-triage__label">{{ $item['label'] }}</span>
                </span>
                <span class="fi-triage__right">
                    <span class="fi-triage__count">{{ $item['count'] }}</span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                         style="width:1rem;height:1rem;flex-shrink:0;opacity:0.4;">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            </a>
        @endforeach
    </div>
</x-filament::section>
