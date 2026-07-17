@extends('pdf.reports._layout')

@section('title', 'Sinteza școlii')
@section('subtitle', 'Clasele anului școlar curent: efective, media clasei și corigenți — semestrul curent')

@section('body')
    <table class="stats">
        <tr>
            <td>Elevi activi<br><b>{{ $totals['students'] }}</b></td>
            <td>Elevi cu restanțe<br><b>{{ $totals['failing'] }}</b></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:14%">Clasa</th>
                <th class="num" style="width:11%">Elevi</th>
                <th class="num" style="width:15%">Media clasei</th>
                <th style="width:46%">&nbsp;</th>
                <th class="num" style="width:14%">Corigenți</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td><b>{{ $row['class'] }}</b></td>
                    <td class="num">{{ $row['students'] }}</td>
                    <td class="num">{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '') : '—' }}</td>
                    <td style="padding: 5px 9px;">
                        <table width="100%" style="border-collapse: collapse;">
                            <tr>
                                {{-- Fundalul INLINE, nu doar bgcolor: zebra `tr.alt td` altfel l-ar suprascrie pe rândurile alternate. --}}
                                <td width="{{ max(1, min(100, $row['percent'])) }}%" style="background: {{ $row['average'] !== null && $row['average'] < 5 ? '#b3261e' : '#0f4d77' }}; font-size: 2pt; line-height: 8px; padding: 0; border: none;">&nbsp;</td>
                                @if ($row['percent'] < 100)
                                    <td style="background: #eef2f6; font-size: 2pt; line-height: 8px; padding: 0; border: none;">&nbsp;</td>
                                @endif
                            </tr>
                        </table>
                    </td>
                    <td class="num">{!! $row['failing'] > 0 ? '<span class="bad">'.$row['failing'].'</span>' : '<span class="muted">0</span>' !!}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Anul curent nu are clase configurate.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Media clasei = media mediilor semestriale oficiale; „corigenți" = elevi cu cel puțin o medie sub 5 în semestrul curent.</p>
@endsection
