<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVuSans, sans-serif; font-size: 11pt; color: #1d1d1c; }
        .header { text-align: center; border-bottom: 2px solid #0f4d77; padding-bottom: 8px; margin-bottom: 18px; }
        .header .school { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: #0f4d77; }
        .header .sub { font-size: 9pt; color: #686867; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 12px 0 18px; color: #0f4d77; }
        .field { margin-bottom: 4px; font-size: 11pt; }
        .label { color: #686867; }
        h3 { color: #0f4d77; font-size: 12pt; margin: 18px 0 6px; border-bottom: 1px solid #9bc31e; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th { background: #eef2f6; color: #0f4d77; font-size: 9.5pt; text-align: left; padding: 5px 8px; border: 1px solid #d6dee6; }
        td { font-size: 10pt; padding: 4px 8px; border: 1px solid #e6ebf0; }
        td.num { text-align: center; }
        .footer { margin-top: 30px; font-size: 8.5pt; color: #889; border-top: 1px solid #e6ebf0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">IPL „LICEUL COLUMNA"</div>
        <div class="sub">Chișinău, Republica Moldova</div>
    </div>

    <div class="title">Foaia matricolă</div>

    <div class="field"><span class="label">Elev:</span> <strong>{{ $studentName }}</strong>@if ($className), clasa {{ $className }}@endif</div>
    <div class="field"><span class="label">Eliberată la:</span> {{ $date }}</div>

    @forelse ($levels as $level)
        <h3>Clasa a {{ $level['grade_level'] }}-a</h3>
        <table>
            <thead>
                <tr>
                    <th style="width:52%">Disciplina</th>
                    <th style="width:16%; text-align:center">Sem. I</th>
                    <th style="width:16%; text-align:center">Sem. II</th>
                    <th style="width:16%; text-align:center">Anuală</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($level['subjects'] as $subject)
                    <tr>
                        <td>{{ $subject['subject'] }}</td>
                        <td class="num">{{ $subject['sem1'] ?? '—' }}</td>
                        <td class="num">{{ $subject['sem2'] ?? '—' }}</td>
                        <td class="num">{{ $subject['annual'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="label">Nu există înregistrări în foaia matricolă.</p>
    @endforelse

    <div class="footer">
        Document generat electronic din catalogul IPL „Liceul Columna" la data de {{ $date }}. Reflectă situația din baza de date la momentul generării.
    </div>
</body>
</html>
