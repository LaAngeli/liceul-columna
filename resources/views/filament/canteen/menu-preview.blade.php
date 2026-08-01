{{-- Previzualizarea unei zile de meniu: aceleași rubrici pe care le vede familia în cabinet.
     Rubricile goale se sar (în meniul real unele zile n-au garnitură/salată). --}}
@php
    $sections = [
        ['title' => __('panel.forms.canteen.breakfast'), 'fields' => \App\Models\CanteenMenu::breakfastFields()],
        ['title' => __('panel.forms.canteen.lunch'), 'fields' => \App\Models\CanteenMenu::lunchFields()],
    ];
@endphp

<div class="space-y-6">
    @foreach ($sections as $section)
        @php($filled = collect($section['fields'])->filter(fn (string $field): bool => filled($menu->{$field})))

        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h3>

            @if ($filled->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.forms.canteen.section_empty') }}</p>
            @else
                <dl class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($filled as $field)
                        <div class="flex items-baseline justify-between gap-4 py-2">
                            <dt class="shrink-0 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('panel.forms.canteen.'.$field) }}
                            </dt>
                            <dd class="text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $menu->{$field} }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    @endforeach

    @if (filled($menu->notes))
        <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
            {{ $menu->notes }}
        </p>
    @endif
</div>
