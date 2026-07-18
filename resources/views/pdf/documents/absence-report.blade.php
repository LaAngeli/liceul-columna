@extends('pdf.documents._layout')

{{-- Raportul absențelor (Faza 5): TOATE absențele anului școlar curent, pe DATE — completează
     „Situația școlară" (care agregă doar pe discipline). Fiecare absență: data, disciplina
     (sau „Zi întreagă"), statutul motivării; totaluri per semestru și pe an. --}}

@section('title', 'Raportul absențelor')
@section('subtitle', $yearLabel !== null ? 'Anul școlar '.$yearLabel : 'Anul școlar curent')

@section('body')
    <table class="stats">
        <tr>
            <td>Total absențe<br><b>{{ $total }}</b></td>
            <td>Motivate<br><b class="good" style="font-size: 15pt;">{{ $totalMotivated }}</b></td>
            <td>Nemotivate<br><b class="{{ $totalUnmotivated > 0 ? 'bad' : '' }}" style="font-size: 15pt;">{{ $totalUnmotivated }}</b></td>
        </tr>
    </table>

    @forelse ($sections as $section)
        <div class="section-h">{{ $section['label'] }} — {{ count($section['rows']) }} {{ count($section['rows']) === 1 ? 'absență' : 'absențe' }}
            @if (count($section['rows']) > 0)
                ({{ $section['motivated'] }} motivate · {{ $section['unmotivated'] }} nemotivate)
            @endif
        </div>
        @if (count($section['rows']) > 0)
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:22%">Data</th>
                        <th style="width:56%">Disciplina</th>
                        <th class="num" style="width:22%">Motivată</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr class="{{ $loop->even ? 'alt' : '' }}">
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['subject'] }}</td>
                            <td class="num">{!! $row['motivated'] ? '<span class="good">Da</span>' : '<span class="bad">Nu</span>' !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">Nicio absență în acest semestru.</p>
        @endif
    @empty
        <p class="muted">Nu există un an școlar activ — raportul se generează în timpul anului școlar.</p>
    @endforelse

    <p class="note">Absențele motivate au la bază cereri de motivare aprobate de diriginte. Pentru
        sinteza pe discipline vezi „Situația școlară".</p>
@endsection
