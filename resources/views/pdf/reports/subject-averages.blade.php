@extends('pdf.reports._layout')

@section('title', 'Situația disciplinelor')
@section('subtitle', 'Media clasei la fiecare disciplină — semestrul curent, descrescător')

@section('body')
    @if ($rows === [])
        <p class="muted">Nu există medii semestriale calculate pentru această clasă.</p>
    @else
        <table class="bars">
            @foreach ($rows as $row)
                @include('pdf.reports._bar', [
                    'label' => $row['subject'],
                    'valueLabel' => number_format($row['average'], 2, ',', ''),
                    'percent' => $row['percent'],
                    'color' => $row['average'] < 5 ? 'red' : ($row['average'] >= 9 ? 'green' : null),
                ])
            @endforeach
        </table>
        <p class="note">Bara reprezintă media clasei raportată la nota maximă 10; roșu = medie sub 5, verde = 9 și peste.</p>
    @endif
@endsection
