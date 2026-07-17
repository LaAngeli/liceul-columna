@extends('pdf.documents._layout')

@section('title', 'Situația școlară')
@section('subtitle', $termLabel)

@section('body')
    <table class="stats">
        <tr>
            <td>Media generală<br><b>{{ $average !== null ? number_format($average, 2, ',', '') : '—' }}</b></td>
            <td>Total absențe<br><b>{{ $absencesTotal }}</b></td>
            @if ($statusLabel)
                <td>Statut{{ $statusOfficial ? ' (oficial)' : ' (preliminar)' }}<br><b style="font-size: 12pt;">{{ $statusLabel }}</b></td>
            @endif
        </tr>
    </table>

    <div class="section-h">Medii pe discipline</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width:70%">Disciplina</th>
                <th class="num" style="width:30%">Media semestrială</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $subject['subject'] }}</td>
                    <td class="num">{!! $subject['average'] !== null
                        ? ($subject['average'] < 5 ? '<span class="bad">'.number_format($subject['average'], 2, ',', '').'</span>' : number_format($subject['average'], 2, ',', ''))
                        : '—' !!}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">Nu există note în semestrul curent.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($absences) > 0)
        <div class="section-h">Absențe pe discipline</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:70%">Disciplina</th>
                    <th class="num" style="width:30%">Număr</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($absences as $absence)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td>{{ $absence['subject'] }}</td>
                        <td class="num">{{ $absence['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
