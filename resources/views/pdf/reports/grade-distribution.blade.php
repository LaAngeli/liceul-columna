@extends('pdf.reports._layout')

@section('title', 'Distribuția notelor')
@section('subtitle', $subjectName)

@section('body')
    <table class="stats">
        <tr>
            <td>Note consemnate<br><b>{{ $total }}</b></td>
            @if ($mean !== null)
                <td>Media notelor<br><b>{{ number_format($mean, 2, ',', '') }}</b></td>
            @endif
        </tr>
    </table>

    @if ($total === 0)
        <p class="muted">Nu există note active la această disciplină în semestrul curent.</p>
    @else
        <div class="section-h">{{ $numeric ? 'Histograma notelor (10 → 1)' : 'Distribuția pe calificative' }}</div>
        <table class="bars">
            @foreach ($buckets as $bucket => $count)
                @include('pdf.reports._bar', [
                    'label' => $numeric ? 'Nota '.$bucket : $bucket,
                    'valueLabel' => $count,
                    'percent' => (int) round($count * 100 / $maxCount),
                    'color' => $numeric && $bucket < 5 ? 'red' : ($numeric && $bucket >= 9 ? 'green' : null),
                ])
            @endforeach
        </table>
        <p class="note">Doar notele ACTIVE (cele anulate nu intră); notele curente, evaluările sumative și tezele deopotrivă.</p>
    @endif
@endsection
