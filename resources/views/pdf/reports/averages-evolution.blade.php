@extends('pdf.reports._layout')

@section('title', 'Evoluția mediilor')
@section('subtitle', 'Media clasei pe discipline, între semestrele anului școlar curent')

@section('body')
    <table class="data">
        <thead>
            <tr>
                <th style="width:40%">Disciplina</th>
                <th class="num" style="width:18%">{{ $termNames['first'] }}</th>
                <th class="num" style="width:18%">{{ $termNames['second'] }}</th>
                <th class="num" style="width:24%">Evoluția</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $row['subject'] }}</td>
                    <td class="num">{{ $row['first'] !== null ? number_format($row['first'], 2, ',', '') : '—' }}</td>
                    <td class="num">{{ $row['second'] !== null ? number_format($row['second'], 2, ',', '') : '—' }}</td>
                    <td class="num">
                        @if ($row['delta'] === null)
                            <span class="muted">—</span>
                        @elseif ($row['delta'] > 0)
                            <span class="good">▲ +{{ number_format($row['delta'], 2, ',', '') }}</span>
                        @elseif ($row['delta'] < 0)
                            <span class="bad">▼ {{ number_format($row['delta'], 2, ',', '') }}</span>
                        @else
                            <span class="muted">= 0,00</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nu există medii semestriale calculate pentru această clasă în anul curent.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Evoluția compară mediile semestriale oficiale ale clasei; „—" = semestrul nu are încă medii încheiate.</p>
@endsection
