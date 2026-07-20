{{-- Previzualizarea unui orar publicabil: EXACT tabelul pe care îl văd familiile pe site
     (headers + rows, fără nicio coloană internă). Lat prin natura lui → derulare orizontală
     în interiorul modalului, nu pe pagină. --}}
<div class="overflow-x-auto">
    @php($rows = collect((array) $schedule->rows)->map(fn ($row): array => array_values((array) $row)))

    @if ($rows->isEmpty())
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.forms.schedule.preview_empty') }}
        </p>
    @else
        <table class="min-w-full text-sm">
            @if (filled($schedule->headers))
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/10">
                        @foreach ((array) $schedule->headers as $header)
                            <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td @class([
                                'px-3 py-2 align-top text-gray-700 dark:text-gray-200',
                                'whitespace-nowrap font-medium text-gray-950 dark:text-white' => $loop->first,
                            ])>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
