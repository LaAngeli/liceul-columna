<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVuSans, sans-serif; font-size: 12pt; color: #1a2233; line-height: 1.5; }
        /* Format de SCRISOARE (cerere către direcțiune) — deliberat diferit de rapoartele
           instituționale; doar antetul poartă aceeași emblemă și culorile brandului. */
        .header { border-bottom: 2px solid #0f4d77; padding-bottom: 8px; margin-bottom: 24px; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .header .school { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: #0f4d77; }
        .header .sub { font-size: 9pt; color: #667; }
        .to { text-align: right; margin-bottom: 28px; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 18px 0 24px; }
        .field { margin-bottom: 6px; }
        .label { color: #667; }
        .body { margin: 20px 0; text-align: justify; }
        .signature { margin-top: 48px; }
        .signature .row { display: block; margin-top: 24px; }
        .muted { color: #889; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td style="width: 44px;">
                <img src="{{ public_path('images/logo/columna-crest-color.png') }}" style="width: 38px;" alt="">
            </td>
            <td style="padding-left: 8px;">
                <div class="school">IPL „LICEUL COLUMNA"</div>
                <div class="sub">Chișinău, Republica Moldova</div>
            </td>
        </tr>
    </table>

    <div class="to">
        Către Direcțiunea<br>
        IPL „Liceul Columna"
    </div>

    <div class="title">{{ $typeLabel }}</div>

    <div class="field"><span class="label">Elev:</span> <strong>{{ $studentName }}</strong>@if ($className), clasa {{ $className }}@endif</div>
    @if ($parentName)
        <div class="field"><span class="label">Depusă de:</span> {{ $parentName }}</div>
    @endif
    @if ($period)
        <div class="field"><span class="label">Perioada:</span> {{ $period }}</div>
    @endif
    @if ($contestedGrade)
        <div class="field"><span class="label">Nota contestată:</span> <strong>{{ $contestedGrade }}</strong></div>
    @endif
    <div class="field"><span class="label">Data:</span> {{ $date }}</div>

    <div class="body">
        @if ($details)
            {{ $details }}
        @else
            <span class="muted">Vă rog să examinați prezenta cerere conform procedurii aplicabile.</span>
        @endif
    </div>

    <div class="signature">
        <div class="row">Semnătura solicitantului: ______________________</div>
        <div class="row muted">Document generat electronic prin catalogul Liceului Columna.</div>
    </div>
</body>
</html>
