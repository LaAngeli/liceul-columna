@extends('pdf.reports._layout')

@section('title', 'Promovabilitatea clasei')
@section('subtitle', 'Statutul preliminar al elevilor în semestrul curent (înainte de validarea Consiliului profesoral)')

@section('body')
    <table class="stats">
        <tr>
            <td>Elevi în clasă<br><b>{{ $studentsTotal }}</b></td>
            <td>Cu statut determinat<br><b>{{ $evaluated }}</b></td>
            <td>Rata de promovare<br><b>{{ $promotionPercent !== null ? $promotionPercent.'%' : '—' }}</b></td>
        </tr>
    </table>

    <div class="section-h">Structura statutului</div>
    <table class="bars">
        @foreach ($statusCounts as $status => $count)
            @include('pdf.reports._bar', [
                'label' => trans('enums.student_status.'.$status),
                'valueLabel' => $count,
                'percent' => $evaluated > 0 ? (int) round($count * 100 / $evaluated) : 0,
                'color' => $status === 'promovat' ? 'green' : ($status === 'corigent' || $status === 'repetent' ? 'red' : 'amber'),
            ])
        @endforeach
    </table>

    @if ($failingSubjects !== [])
        <div class="section-h">Disciplinele cu restanțe (nr. de elevi corigenți)</div>
        <table class="bars">
            @foreach ($failingSubjects as $subject => $count)
                @include('pdf.reports._bar', [
                    'label' => $subject,
                    'valueLabel' => $count,
                    'percent' => (int) round($count * 100 / $failingMax),
                    'color' => 'red',
                ])
            @endforeach
        </table>
    @endif

    <p class="note">Statutul e cel PRELIMINAR, calculat din mediile semestriale (corigent = cel puțin o medie sub 5); statutul oficial se stabilește prin validarea Consiliului profesoral.</p>
@endsection
