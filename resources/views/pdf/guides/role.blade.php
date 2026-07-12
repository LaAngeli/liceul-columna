@extends('pdf.guides._layout')

@section('badge', $role['badge'])
@section('coverTitle', $role['title'])
@section('coverTagline', $role['tagline'])
@section('coverMeta')
    <span class="chip"><span class="lbl">Nivelul</span> {{ $role['level'] }} din 9</span>
    &nbsp;
    <span class="chip"><span class="lbl">Lucrezi în:</span> {{ $role['where'] }}</span>
@endsection

@section('body')
    <h2 class="sec">Cine ești</h2>
    <div class="sec-rule"></div>
    <div class="card accent avoid-break"><p>{{ $role['identity'] }}</p></div>

    <h2 class="sec">Ecranele tale</h2>
    <div class="sec-rule"></div>
    <p class="lead">Ce vezi când te autentifici — secțiunile care îți apar (verificate în aplicație):</p>
    <table class="grid">
        @foreach ($role['screens'] as $i => $s)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="k" style="width:34%">{{ $s['name'] }}</td>
                <td>{{ $s['desc'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2 class="sec">Ce poți face &nbsp;·&nbsp; Ce NU poți face</h2>
    <div class="sec-rule"></div>
    <table class="two"><tr>
        <td>
            <div class="panel yes">
                <div class="head">✓ Îți este permis</div>
                <ul>@foreach ($role['canDo'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
        </td>
        <td>
            <div class="panel no">
                <div class="head">✕ Nu îți este permis</div>
                <ul>@foreach ($role['cannotDo'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
        </td>
    </tr></table>

    <h2 class="sec">Fluxurile în care intri</h2>
    <div class="sec-rule"></div>
    @foreach ($role['flows'] as $f)
        <div class="flow avoid-break">
            <span class="name">{{ $f['name'] }}</span> &nbsp;<span class="muted">— {{ $f['role'] }}</span>
        </div>
    @endforeach

    <h2 class="sec">Cu cine interacționezi</h2>
    <div class="sec-rule"></div>
    <table class="grid">
        @foreach ($role['interactions'] as $i => $it)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="k" style="width:38%">{{ $it['who'] }}</td>
                <td class="muted">{{ $it['how'] }}</td>
            </tr>
        @endforeach
    </table>

    @if (! empty($role['steps']))
        <h2 class="sec">Pași uzuali</h2>
        <div class="sec-rule"></div>
        @foreach ($role['steps'] as $s)
            <div class="step avoid-break"><span class="t">{{ $s['title'] }}.</span> {{ $s['desc'] }}</div>
        @endforeach
    @endif
@endsection
