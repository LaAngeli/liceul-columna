<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVuSans, sans-serif; font-size: 11pt; color: #1d1d1c; }
        .header { text-align: center; border-bottom: 2px solid #0f4d77; padding-bottom: 8px; margin-bottom: 18px; }
        .header .school { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: #0f4d77; }
        .header .sub { font-size: 9pt; color: #686867; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 12px 0 6px; color: #0f4d77; }
        .subtitle { text-align: center; font-size: 10pt; color: #686867; margin-bottom: 18px; }
        .field { margin-bottom: 4px; font-size: 11pt; }
        .label { color: #686867; }
        h3 { color: #0f4d77; font-size: 12pt; margin: 18px 0 6px; border-bottom: 1px solid #9bc31e; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th { background: #eef2f6; color: #0f4d77; font-size: 9.5pt; text-align: left; padding: 5px 8px; border: 1px solid #d6dee6; }
        td { font-size: 10pt; padding: 4px 8px; border: 1px solid #e6ebf0; }
        td.num { text-align: center; }
        .summary { margin: 8px 0 4px; }
        .summary .box { display: inline-block; margin-right: 20px; }
        .summary .big { font-size: 16pt; font-weight: bold; color: #0f4d77; }
        .status { padding: 3px 8px; border-radius: 4px; font-size: 10pt; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 8.5pt; color: #889; border-top: 1px solid #e6ebf0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">IPL „LICEUL COLUMNA"</div>
        <div class="sub">Chișinău, Republica Moldova</div>
    </div>

    <div class="title">Situația școlară</div>
    <div class="subtitle">{{ $termLabel }}</div>

    <div class="field"><span class="label">Elev:</span> <strong>{{ $studentName }}</strong>@if ($className), clasa {{ $className }}@endif</div>
    <div class="field"><span class="label">Eliberată la:</span> {{ $date }}</div>

    <div class="summary" style="margin-top:14px;">
        <div class="box">
            <span class="label">Media generală</span><br>
            <span class="big">{{ $average !== null ? number_format($average, 2, ',', '') : '—' }}</span>
        </div>
        <div class="box">
            <span class="label">Total absențe</span><br>
            <span class="big">{{ $absencesTotal }}</span>
        </div>
        @if ($statusLabel)
            <div class="box">
                <span class="label">Statut{{ $statusOfficial ? ' (oficial)' : ' (preliminar)' }}</span><br>
                <span class="big" style="font-size:13pt;">{{ $statusLabel }}</span>
            </div>
        @endif
    </div>

    <h3>Medii pe discipline</h3>
    <table>
        <thead>
            <tr>
                <th style="width:70%">Disciplina</th>
                <th style="width:30%; text-align:center">Media semestrială</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject['subject'] }}</td>
                    <td class="num">{{ $subject['average'] !== null ? number_format($subject['average'], 2, ',', '') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="label">Nu există note în semestrul curent.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($absences) > 0)
        <h3>Absențe pe discipline</h3>
        <table>
            <thead>
                <tr>
                    <th style="width:70%">Disciplina</th>
                    <th style="width:30%; text-align:center">Număr</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($absences as $absence)
                    <tr>
                        <td>{{ $absence['subject'] }}</td>
                        <td class="num">{{ $absence['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Document generat electronic din catalogul IPL „Liceul Columna" la data de {{ $date }}. Reflectă situația din baza de date la momentul generării.
    </div>
</body>
</html>
