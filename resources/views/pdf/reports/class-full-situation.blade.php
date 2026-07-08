@extends('pdf.reports._layout')

@section('title', 'Situația clasei')
@section('subtitle', 'Media generală și statutul preliminar — semestrul curent')

@section('body')
    <table>
        <thead>
            <tr>
                <th class="idx">Nr.</th>
                <th style="width:40%">Nume și prenume</th>
                <th style="width:16%; text-align:center">Media generală</th>
                <th style="width:16%; text-align:center">Statut</th>
                <th style="width:21%">Restanțe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="idx">{{ $row['index'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '') : '—' }}</td>
                    <td class="num">{{ $row['statusLabel'] ?? '—' }}</td>
                    <td>{{ $row['failing'] !== '' ? $row['failing'] : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="label">Nu există elevi înmatriculați activ în această clasă.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
