{{-- Fișa anunțului: conținutul INTEGRAL (recitit înainte de „Publică" — butonul stă în header,
     doar pe ciorne) + pentru publicate pâlnia difuzării (programate → livrate → citite),
     bara de progres și defalcarea pe elevi/părinți.

     Cele două panouri sunt celule ale ACELEIAȘI grile (nu carduri în secțiuni separate, fiecare
     dimensionată de conținutul ei) → se întind egal și marginile de jos rămân aliniate, indiferent
     care are mai mult text. Subsolul repetă aceeași grilă, ca nota ciornei să cadă sub conținut
     și întoarcerea la listă sub panoul de difuzare. --}}
<x-filament-panels::page>
    <div class="flex flex-col gap-3">
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Conținutul --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.forms.announcement.body') }}"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                    @if ($this->record->isPublished())
                        <x-filament::badge color="success" size="sm">
                            {{ __('panel.forms.announcement.published_at') }}
                        </x-filament::badge>
                        <span class="tabular-nums">{{ \App\Support\SchoolCalendar::local($this->record->published_at)?->translatedFormat('d.m.Y H:i') }}</span>
                    @else
                        <x-filament::badge color="warning" size="sm">
                            {{ __('panel.forms.announcement.draft') }}
                        </x-filament::badge>
                        <span class="tabular-nums">{{ \App\Support\SchoolCalendar::local($this->record->created_at)?->translatedFormat('d.m.Y H:i') }}</span>
                    @endif

                    @if ($this->record->author !== null)
                        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                        <span>{{ __('panel.announcements.by_author', ['name' => $this->record->author->name]) }}</span>
                    @endif
                </div>

                {{-- Audiența aleasă — cine primește (sau a primit) anunțul. --}}
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="font-semibold text-gray-500 dark:text-gray-400">{{ __('panel.announcements.audience_label') }}:</span>
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 font-medium text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
                        {{ $this->audienceDescription() }}
                    </span>
                </div>

                <div class="mt-4 whitespace-pre-line text-sm leading-relaxed text-gray-950 dark:text-white">{{ $this->record->body }}</div>
            </section>

            {{-- Difuzarea & citirea --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.announcements.broadcast') }}"
            >
                @php($funnel = $this->broadcastFunnel())

                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.announcements.broadcast') }}
                </h2>

                @if ($funnel === null)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ trans_choice('panel.announcements.broadcast_draft', $this->audienceCount(), ['count' => $this->audienceCount()]) }}
                    </p>
                @else
                    {{-- Pâlnia: programate → livrate → citite. --}}
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.announcements.recipients') }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $funnel['recipients'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.announcements.delivered') }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $funnel['delivered'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.announcements.read') }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">
                                {{ $funnel['read'] }}
                                @if ($funnel['percent'] !== null)
                                    <span class="font-normal text-gray-400 dark:text-gray-500">({{ $funnel['percent'] }}%)</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $funnel['percent'] ?? 0 }}"
                        aria-label="{{ __('panel.announcements.read') }}"
                    >
                        <div
                            class="h-full rounded-full bg-primary-600 dark:bg-primary-500"
                            style="width: {{ min(100, $funnel['percent'] ?? 0) }}%"
                        ></div>
                    </div>

                    @if ($funnel['delivering'])
                        <p class="mt-3 flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-clock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-info-500" />
                            {{ __('panel.announcements.delivering_hint') }}
                        </p>
                    @endif

                    @if ($funnel['breakdown'] !== [])
                        <h3 class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.announcements.breakdown') }}
                        </h3>

                        <div class="mt-2 space-y-3">
                            @foreach ($funnel['breakdown'] as $role => $row)
                                @php($rolePercent = $row['delivered'] > 0 ? (int) round($row['read'] / $row['delivered'] * 100) : 0)
                                <div>
                                    <div class="flex items-baseline justify-between gap-3 text-xs">
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ __('panel.announcements.role_'.$role) }}
                                        </span>
                                        <span class="tabular-nums text-gray-950 dark:text-white">
                                            {{ $row['read'] }} / {{ $row['delivered'] }}
                                        </span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div
                                            class="h-full rounded-full bg-primary-600/70 dark:bg-primary-500/70"
                                            style="width: {{ min(100, $rolePercent) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>
        </div>

        {{-- Subsol, pe aceleași coloane: starea ciornei sub conținut, întoarcerea sub difuzare. --}}
        <div class="grid gap-x-6 gap-y-3 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if (! $this->record->isPublished())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('panel.announcements.draft_hint') }}
                    </p>
                @endif
            </div>

            <div>
                <a
                    href="{{ \App\Filament\Resources\Announcements\AnnouncementResource::getUrl() }}"
                    class="inline-flex min-h-9 items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
                >
                    <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                    {{ __('panel.announcements.back') }}
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
