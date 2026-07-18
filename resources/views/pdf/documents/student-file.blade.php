@extends('pdf.documents._layout')

{{-- Dosarul elevului (Faza 5): document COMBINAT de uz intern — situația semestrului curent
     (același corp ca „Situația școlară") + evoluția multi-anuală din foaia matricolă
     (ComputeStudentDynamics: media generală pe trepte, tendință, comparație cu anul trecut). --}}

@section('title', 'Dosarul elevului')
@section('subtitle', $termLabel.' · Situația curentă și evoluția pe ani')

@section('body')
    @include('pdf.documents._term-tables')

    <div class="section-h">Evoluția mediei generale pe ani</div>
    @if (count($dynamics['general']) > 0)
        <table class="data">
            <thead>
                <tr>
                    <th style="width:70%">Treapta de studiu</th>
                    <th class="num" style="width:30%">Media generală anuală</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dynamics['general'] as $point)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td>Clasa a {{ $point['level'] }}-a</td>
                        <td class="num"><b>{{ number_format($point['average'], 2, ',', '') }}</b></td>
                    </tr>
                @endforeach
                @if ($dynamics['current']['average'] !== null)
                    <tr>
                        <td>{{ $termLabel }} (în curs)</td>
                        <td class="num"><b>{{ number_format($dynamics['current']['average'], 2, ',', '') }}</b></td>
                    </tr>
                @endif
            </tbody>
        </table>

        @php
            $trendLabel = match ($dynamics['current']['trend']) {
                'up' => 'în creștere',
                'down' => 'în scădere',
                'stable' => 'stabilă',
                default => null,
            };
        @endphp
        @if ($trendLabel !== null || $dynamics['current']['previousYearSameTerm'] !== null)
            <p class="note">
                @if ($trendLabel !== null)
                    Tendința față de ultima medie anuală: <b>{{ $trendLabel }}</b>.
                @endif
                @if ($dynamics['current']['previousYearSameTerm'] !== null)
                    Același semestru, anul trecut: <b>{{ number_format($dynamics['current']['previousYearSameTerm'], 2, ',', '') }}</b>.
                @endif
                @if ($dynamics['current']['alert'])
                    <span class="bad">Atenție: media curentă e semnificativ sub istoricul propriu.</span>
                @endif
            </p>
        @endif
    @else
        <p class="muted">Nu există încă medii anuale arhivate în foaia matricolă.</p>
    @endif

    <p class="note">Document de uz intern — sinteză generată din catalogul electronic. Pentru istoricul
        complet pe discipline vezi „Foaia matricolă"; pentru detaliul semestrului vezi „Situația școlară".</p>
@endsection
