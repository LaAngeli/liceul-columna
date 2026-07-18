@extends('pdf.documents._layout')

{{-- Dosarul elevului (Faza 5): document COMBINAT de uz intern — situația semestrului curent
     (același corp ca „Situația școlară") + evoluția multi-anuală din foaia matricolă
     (ComputeStudentDynamics: media generală pe trepte, tendință, comparație cu anul trecut). --}}

@section('title', 'Dosarul elevului')
@section('subtitle', $termLabel.' · Situația curentă și evoluția pe ani')

@section('doc-styles')
    table.bars { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.bars td { border: none; padding: 3px 6px 3px 0; font-size: 9.2pt; }
    td.bar-label { width: 30%; color: #1d1d1c; }
    td.bar-track { width: 56%; }
    td.bar-value { width: 14%; text-align: right; color: #0f4d77; font-weight: bold; }
@endsection

@section('body')
    @include('pdf.documents._term-tables')

    <div class="section-h">Evoluția mediei generale pe ani</div>
    @if (count($dynamics['general']) > 0)
        {{-- Grafic cu bare (scala 1–10 → procent din lățime): anii istorici navy, semestrul în
             curs verde, media sub 5 roșie — aceeași tehnică mpdf ca în rapoartele staff. --}}
        <table class="bars">
            @foreach ($dynamics['general'] as $point)
                @include('pdf.reports._bar', [
                    'label' => 'Clasa a '.$point['level'].'-a',
                    'valueLabel' => number_format($point['average'], 2, ',', ''),
                    'percent' => (int) round($point['average'] * 10),
                    'color' => $point['average'] < 5 ? 'red' : null,
                ])
            @endforeach
            @if ($dynamics['current']['average'] !== null)
                @include('pdf.reports._bar', [
                    'label' => $termLabel.' (în curs)',
                    'valueLabel' => number_format($dynamics['current']['average'], 2, ',', ''),
                    'percent' => (int) round($dynamics['current']['average'] * 10),
                    'color' => $dynamics['current']['average'] < 5 ? 'red' : 'green',
                ])
            @endif
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
