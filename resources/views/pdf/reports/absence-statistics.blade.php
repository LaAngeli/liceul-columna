@extends('pdf.reports._layout')

@section('title', 'Statistica absențelor')
@section('subtitle', 'Frecvența clasei în semestrul curent — pe elevi și pe luni')

@section('body')
    <table class="stats">
        <tr>
            <td>Total absențe<br><b>{{ $totals['total'] }}</b></td>
            <td>Motivate<br><b>{{ $totals['motivated'] }}</b></td>
            <td>Nemotivate<br><b>{{ $totals['unmotivated'] }}</b></td>
        </tr>
    </table>

    @if ($monthly !== [])
        <div class="section-h">Absențe pe luni</div>
        <table class="bars">
            @foreach ($monthly as $month => $count)
                @include('pdf.reports._bar', [
                    'label' => ucfirst($month),
                    'valueLabel' => $count,
                    'percent' => (int) round($count * 100 / $monthlyMax),
                    'color' => null,
                ])
            @endforeach
        </table>
    @endif

    <div class="section-h">Pe elevi</div>
    <table class="data">
        <thead>
            <tr>
                <th class="idx">Nr.</th>
                <th style="width:47%">Nume și prenume</th>
                <th class="num" style="width:14%">Total</th>
                <th class="num" style="width:14%">Motivate</th>
                <th class="num" style="width:18%">Nemotivate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="idx">{{ $row['index'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['total'] }}</td>
                    <td class="num">{{ $row['motivated'] }}</td>
                    <td class="num">{!! $row['unmotivated'] > 0 ? '<span class="bad">'.$row['unmotivated'].'</span>' : 0 !!}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Nu există elevi înmatriculați activ în această clasă.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
