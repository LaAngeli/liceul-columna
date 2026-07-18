@extends('pdf.documents._layout')

{{-- Document de circulație internațională → BILINGV RO/EN (Faza 4): etichete duale peste tot,
     numele EN al disciplinei sub cel RO (când traducerea există), notă de precedență a versiunii
     române. Datele (medii, calificative) rămân cele oficiale, netransformate. --}}

@section('title', 'Foaia matricolă · School Transcript')
@section('subtitle')
    Mediile anuale pe trepte de studiu, din arhiva oficială a catalogului<br>
    <span class="en">Annual averages by grade level, from the official catalog archive</span>
@endsection

@section('doc-styles')
    .en { color: #8a8a89; font-size: 9pt; }
    .sub-en { color: #8a8a89; font-size: 8pt; }
@endsection

@section('meta-row')
    <table class="meta">
        <tr>
            <td>Elev · <span class="en">Student</span><br><b>{{ $studentName }}</b></td>
            @if (! empty($className))
                <td>Clasa · <span class="en">Class</span><br><b>{{ $className }}</b></td>
            @endif
            <td>Eliberată la · <span class="en">Issued on</span><br><b>{{ $date }}</b></td>
        </tr>
    </table>
@endsection

@section('footer-note')Document generat electronic din catalogul IPL „Liceul Columna" la data de {{ $date ?? '' }}. Electronically generated from the IPL "Liceul Columna" catalog. Reflectă situația din baza de date la momentul generării.@endsection

@section('body')
    @forelse ($levels as $level)
        <div class="section-h">Clasa a {{ $level['grade_level'] }}-a · <span class="en">Grade {{ $level['grade_level'] }}</span></div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:52%">Disciplina / Subject</th>
                    <th class="num" style="width:16%">Sem. I / 1st sem.</th>
                    <th class="num" style="width:16%">Sem. II / 2nd sem.</th>
                    <th class="num" style="width:16%">Anuală / Annual</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($level['subjects'] as $subject)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td>
                            {{ $subject['subject'] }}
                            @if (! empty($subject['subject_en']))
                                <br><span class="sub-en">{{ $subject['subject_en'] }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $subject['sem1'] ?? '—' }}</td>
                        <td class="num">{{ $subject['sem2'] ?? '—' }}</td>
                        <td class="num"><b>{{ $subject['annual'] ?? '—' }}</b></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">Nu există înregistrări în foaia matricolă. · <span class="en">No records in the transcript.</span></p>
    @endforelse

    <p class="note">Traducerea în limba engleză este informativă; versiunea în limba română prevalează. ·
        The English translation is provided for information purposes; the Romanian version prevails.</p>
@endsection
