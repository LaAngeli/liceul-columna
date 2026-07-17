@extends('pdf.documents._layout')

@section('title', 'Foaia matricolă')
@section('subtitle', 'Mediile anuale pe trepte de studiu, din arhiva oficială a catalogului')

@section('body')
    @forelse ($levels as $level)
        <div class="section-h">Clasa a {{ $level['grade_level'] }}-a</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:52%">Disciplina</th>
                    <th class="num" style="width:16%">Sem. I</th>
                    <th class="num" style="width:16%">Sem. II</th>
                    <th class="num" style="width:16%">Anuală</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($level['subjects'] as $subject)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td>{{ $subject['subject'] }}</td>
                        <td class="num">{{ $subject['sem1'] ?? '—' }}</td>
                        <td class="num">{{ $subject['sem2'] ?? '—' }}</td>
                        <td class="num"><b>{{ $subject['annual'] ?? '—' }}</b></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">Nu există înregistrări în foaia matricolă.</p>
    @endforelse
@endsection
