@extends('pdf._institutional')

{{-- Stratul DOCUMENTELOR generate ale elevului (foaia matricolă, situația școlară) peste
     scheletul instituțional: meta-rândul specific (Elev / Clasa / Eliberată la). --}}

@section('footer-note')Document generat electronic din catalogul IPL „Liceul Columna" la data de {{ $date ?? '' }}. Reflectă situația din baza de date la momentul generării.@endsection

@section('meta-row')
    <table class="meta">
        <tr>
            <td>Elev<br><b>{{ $studentName }}</b></td>
            @if (! empty($className))
                <td>Clasa<br><b>{{ $className }}</b></td>
            @endif
            <td>Eliberată la<br><b>{{ $date }}</b></td>
        </tr>
    </table>
@endsection
