@extends('pdf.reports._layout')

@section('title', 'Activitatea profesorilor')
@section('subtitle', 'Consemnările din semestrul curent, pe fiecare cadru didactic')

@section('body')
    <table class="data">
        <thead>
            <tr>
                <th class="idx">Nr.</th>
                <th style="width:30%">Profesor</th>
                <th class="num" style="width:12%">Alocări</th>
                <th class="num" style="width:14%">Note puse</th>
                <th class="num" style="width:16%">Absențe consemnate</th>
                <th style="width:21%">Diriginte al</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="idx">{{ $row['index'] }}</td>
                    <td>
                        {{ $row['name'] }}
                        @if ($row['position'] !== null && $row['position'] !== '')
                            <br><span class="muted" style="font-size:8pt">{{ $row['position'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $row['assignments'] }}</td>
                    <td class="num">{!! $row['grades'] === 0 ? '<span class="muted">0</span>' : $row['grades'] !!}</td>
                    <td class="num">{!! $row['absences'] === 0 ? '<span class="muted">0</span>' : $row['absences'] !!}</td>
                    <td>{{ $row['homeroom'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nu există fișe de profesor în registru.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">„Alocări" = perechile clasă × disciplină din registrul alocărilor; notele anulate nu se numără.</p>
@endsection
