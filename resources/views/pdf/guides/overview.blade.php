@extends('pdf.guides._layout')

@section('badge', 'Imaginea de ansamblu')
@section('coverTitle', 'Platforma Liceul Columna')
@section('coverTagline', 'Cum funcționează întregul ecosistem — site public, cabinet personal și panou de gestiune — cine este cine și cum circulă informația între roluri.')
@section('coverMeta')
    <span class="chip"><span class="lbl">Public țintă:</span> prezentare pentru client</span>
    &nbsp;
    <span class="chip"><span class="lbl">Limbă:</span> RO (platforma e RO / RU / EN)</span>
@endsection

@section('body')
    <h2 class="sec"><span class="num">01.</span> Cele trei componente ale platformei</h2>
    <div class="sec-rule"></div>
    <p class="lead">Platforma nu e un singur ecran, ci trei zone care lucrează împreună peste aceeași bază de date: o vitrină
        publică, un cabinet privat pentru familii și un panou de lucru pentru personal.</p>
    @foreach ($components as $c)
        <div class="comp avoid-break">
            <div class="n">{{ $c['name'] }}</div>
            <div class="aud">{{ $c['audience'] }} · {{ $c['url'] }}</div>
            <div>{{ $c['desc'] }}</div>
        </div>
    @endforeach

    <h2 class="sec"><span class="num">02.</span> Cele nouă roluri (lanțul de încredere)</h2>
    <div class="sec-rule"></div>
    <p class="lead">Rolurile sunt ordonate de la cea mai mare autoritate spre beneficiar. Fiecare nivel are mai multă
        autoritate decât cel de sub el. Personalul lucrează în panou; elevii și părinții în cabinetul personal.</p>
    <table class="grid">
        <tr><th class="lvl" style="color:#fff">Niv.</th><th>Rol</th><th>Pe scurt</th><th>Unde</th></tr>
        @foreach ($rolesTable as $i => $r)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="lvl">{{ $r['level'] }}</td>
                <td class="k">{{ $r['role'] }}</td>
                <td>{{ $r['gist'] }}</td>
                <td class="muted">{{ $r['where'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2 class="sec"><span class="num">03.</span> Cele patru fluxuri care leagă rolurile</h2>
    <div class="sec-rule"></div>
    <p class="lead">Interacțiunile importante dintre utilizatori nu sunt libere, ci canalizate — ca informația să ajungă
        la persoana potrivită și să rămână urmă.</p>
    @foreach ($flows as $f)
        <div class="flow avoid-break">
            <span class="name">{{ $f['name'] }}</span>
            <div class="desc">{{ $f['desc'] }}</div>
            <div class="chain">{{ $f['chain'] }}</div>
        </div>
    @endforeach

    <h2 class="sec"><span class="num">04.</span> Cine creează conturile cui</h2>
    <div class="sec-rule"></div>
    <table class="grid">
        @foreach ($accountCreation as $i => $a)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="k" style="width:38%">{{ $a['who'] }}</td>
                <td>{{ $a['can'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2 class="sec"><span class="num">05.</span> Două principii care se aplică peste tot</h2>
    <div class="sec-rule"></div>
    @foreach ($principles as $p)
        <div class="card accent avoid-break">
            <p><strong style="color:#0f4d77">{{ $p['title'] }}.</strong> {{ $p['desc'] }}</p>
        </div>
    @endforeach

    <h2 class="sec"><span class="num">06.</span> Cum se citește un ghid de rol</h2>
    <div class="sec-rule"></div>
    <p class="lead">Fiecare rol are propriul ghid, cu aceeași structură, ca să poată fi comparate ușor:</p>
    <div class="card avoid-break">
        <p><strong>Cine ești</strong> — locul rolului în școală. &nbsp;·&nbsp;
           <strong>Ecranele tale</strong> — ce vezi când te autentifici. &nbsp;·&nbsp;
           <strong style="color:#4f6b0f">Ce poți face</strong> / <strong style="color:#9a231c">Ce NU poți face</strong> — capabilități și limite, impuse pe server. &nbsp;·&nbsp;
           <strong>Fluxurile în care intri</strong> și <strong>cu cine interacționezi</strong> — legăturile cu ceilalți.
           La elev și părinte se adaugă <strong>pași uzuali</strong> (cum se face pas cu pas).</p>
    </div>
@endsection
