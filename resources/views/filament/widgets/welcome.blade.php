{{-- Banner de bun-venit (dashboard staff).
     ⚠️ Stilurile sunt în `resources/css/filament/admin/theme.css` (NU inline): widget-ul e o
     componentă Livewire cu UN SINGUR element rădăcină (`.fi-welcome`). Un bloc `<style>` frate al
     rădăcinii era eliminat la morphing-ul Livewire → cardul apărea complet nestilizat. --}}
<div class="fi-welcome">
    <div class="fi-welcome__bar">
        <div class="fi-welcome__intro">
            {{-- Avatar cu inițiale (oglindește avatarul din topbar). Decorativ → aria-hidden;
                 numele complet e deja anunțat în salut. --}}
            <span class="fi-welcome__avatar" aria-hidden="true">{{ $initials }}</span>

            <div class="fi-welcome__main">
                <p class="fi-welcome__greeting">{{ $greeting }}, <span>{{ $name }}</span></p>
                <p class="fi-welcome__date">{{ \Illuminate\Support\Str::ucfirst($date) }}</p>
            </div>
        </div>

        @if ($roleLabel !== null)
            <span class="fi-welcome__role">
                {{-- width/height + style inline: Filament v4 aplică un reset `svg { width: 100% }`;
                     fără dimensiuni explicite, iconița devine gigantică. --}}
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                     style="width: 1rem; height: 1rem; flex-shrink: 0;">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                {{ $roleLabel }}
            </span>
        @endif
    </div>

    @if ($missingTeacherProfile)
        {{-- Profesor/diriginte cu rol dar fără fișă Teacher: dashboard-ul „de rol" se ascunde — îl
             îndrumăm să contacteze administrația ca să-i atribuie o fișă. --}}
        <div class="fi-welcome-hint" role="status">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                 style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 100 2 1 1 0 000-2zm-1 5a1 1 0 011-1h.01a1 1 0 01.99 1v3a1 1 0 11-2 0v-3z" clip-rule="evenodd" />
            </svg>
            <p>{{ trans('panel.widgets.welcome.missing_teacher') }}</p>
        </div>
    @endif
</div>
