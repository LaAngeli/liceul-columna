@extends('pdf._institutional')

{{-- Stratul RAPOARTELOR de staff peste scheletul instituțional: meta-rândul specific
     (Perioada / Clasa / Generat la / Generat de) + stilurile barelor orizontale. --}}

@section('doc-styles')
        /* ── Bare orizontale (grafice simple, lizibile la tipar) ── */
        table.bars { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.bars td { border: none; padding: 3px 6px 3px 0; font-size: 9.2pt; }
        td.bar-label { width: 30%; color: #1d1d1c; }
        td.bar-track { width: 56%; }
        td.bar-value { width: 14%; text-align: right; color: #0f4d77; font-weight: bold; }
@endsection

@section('footer-note')Document generat electronic din catalogul IPL „Liceul Columna" la {{ $generatedAt ?? '' }}, de {{ $generatedBy ?? '—' }}. Reflectă situația din baza de date la momentul generării.@endsection

@section('meta-row')
    <table class="meta">
        <tr>
            <td>Perioada<br><b>{{ $periodLabel ?? '—' }}</b></td>
            @isset($className)
                <td>Clasa<br><b>{{ $className }}</b></td>
            @endisset
            <td>Generat la<br><b>{{ $generatedAt ?? '—' }}</b></td>
            <td>Generat de<br><b>{{ $generatedBy ?? '—' }}</b></td>
        </tr>
    </table>
@endsection
