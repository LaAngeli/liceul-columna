@extends('pdf.reports._layout')

@section('title', 'Clasamentul elevilor')
@section('subtitle', 'După media generală a semestrului curent — elevii fără medie încheiată apar la final, nepoziționați')

@section('body')
    <table class="data">
        <thead>
            <tr>
                <th class="num" style="width:9%">Loc</th>
                <th style="width:47%">Nume și prenume</th>
                <th class="num" style="width:18%">Media generală</th>
                <th style="width:26%">Restanțe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="num">{!! $row['rank'] !== null ? '<b>'.$row['rank'].'</b>' : '<span class="muted">—</span>' !!}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '') : '—' }}</td>
                    <td>{{ $row['failing'] !== '' ? $row['failing'] : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nu există elevi înmatriculați activ în această clasă.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Clasamentul folosește mediile semestriale oficiale (fără rotunjire, cu trunchiere la sutimi).</p>
@endsection
