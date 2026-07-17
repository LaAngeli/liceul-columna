@extends('pdf.reports._layout')

@section('title', 'Lista de clasă')

@section('body')
    <table class="data">
        <thead>
            <tr>
                <th class="idx">Nr.</th>
                <th style="width:63%">Nume și prenume</th>
                <th style="width:30%">Nr. matricol</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="idx">{{ $student['index'] }}</td>
                    <td>{{ $student['name'] }}</td>
                    <td>{{ $student['registerNumber'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="label">Nu există elevi înmatriculați activ în această clasă.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
