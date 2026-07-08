@extends('pdf.reports._layout')

@section('title', 'Situația clasei la disciplină')
@section('subtitle', $subjectName)

@section('body')
    <table>
        <thead>
            <tr>
                <th class="idx">Nr.</th>
                <th style="width:63%">Nume și prenume</th>
                <th style="width:30%; text-align:center">Media semestrială</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="idx">{{ $row['index'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="label">Nu există elevi înmatriculați activ în această clasă.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
