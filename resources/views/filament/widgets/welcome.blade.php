{{-- Card-EROU dashboard staff (hybrid V-D). Stilurile sunt în theme.css (widget Livewire cu un
     singur root — un `<style>` frate ar fi eliminat la morphing). --}}
<div class="fi-welcome">
    <div class="fi-welcome__bar">
        <div class="fi-welcome__intro">
            <span class="fi-welcome__avatar" aria-hidden="true">{{ $initials }}</span>
            <div class="fi-welcome__main">
                <p class="fi-welcome__greeting">{{ $greeting }}, <span>{{ $name }}</span></p>
                @if ($roleLabel !== null)
                    <span class="fi-welcome__role">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                             style="width: 0.85rem; height: 0.85rem; flex-shrink: 0;">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                        {{ $roleLabel }}
                    </span>
                @endif
                <p class="fi-welcome__date">{{ \Illuminate\Support\Str::ucfirst($date) }}</p>
            </div>
        </div>

        @if ($primaryValue !== null)
            <div class="fi-hero__metric">
                <p class="fi-hero__metric-label">{{ $primaryLabel }}</p>
                <p class="fi-hero__metric-value">{{ number_format((int) $primaryValue, 0, ',', '.') }}</p>
                @if ($secondaryLine !== null)
                    <p class="fi-hero__metric-secondary">{{ $secondaryLine }}</p>
                @endif
                @if ($sparkPoints !== null)
                    <svg class="fi-hero__spark" viewBox="0 0 260 34" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="{{ $sparkPoints }}" fill="none" stroke="currentColor" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                    </svg>
                @endif
            </div>
        @endif
    </div>

    @if ($missingTeacherProfile)
        {{-- Profesor/diriginte cu rol dar fără fișă Teacher: metrica lipsește — îndrumare. --}}
        <div class="fi-welcome-hint" role="status">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                 style="width: 1.125rem; height: 1.125rem; flex-shrink: 0;">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 100 2 1 1 0 000-2zm-1 5a1 1 0 011-1h.01a1 1 0 01.99 1v3a1 1 0 11-2 0v-3z" clip-rule="evenodd" />
            </svg>
            <p>{{ trans('panel.widgets.welcome.missing_teacher') }}</p>
        </div>
    @endif
</div>
