<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVuSans, sans-serif; font-size: 11pt; color: #1d1d1c; }
        .header { text-align: center; border-bottom: 2px solid #0f4d77; padding-bottom: 8px; margin-bottom: 16px; }
        .header .school { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: #0f4d77; }
        .header .sub { font-size: 9pt; color: #686867; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 10px 0 4px; color: #0f4d77; }
        .subtitle { text-align: center; font-size: 10pt; color: #686867; margin-bottom: 16px; }
        .field { margin-bottom: 4px; font-size: 11pt; }
        .label { color: #686867; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #eef2f6; color: #0f4d77; font-size: 9.5pt; text-align: left; padding: 5px 8px; border: 1px solid #d6dee6; }
        td { font-size: 10pt; padding: 4px 8px; border: 1px solid #e6ebf0; }
        td.num { text-align: center; }
        td.idx { text-align: center; color: #889; width: 7%; }
        .footer { margin-top: 26px; font-size: 8.5pt; color: #889; border-top: 1px solid #e6ebf0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">IPL „LICEUL COLUMNA"</div>
        <div class="sub">Chișinău, Republica Moldova</div>
    </div>

    <div class="title">@yield('title')</div>
    @hasSection('subtitle')
        <div class="subtitle">@yield('subtitle')</div>
    @endif

    <div class="field"><span class="label">Clasa:</span> <strong>{{ $className }}</strong></div>
    <div class="field"><span class="label">Generat la:</span> {{ $date }}</div>

    @yield('body')

    <div class="footer">
        Document generat electronic din catalogul IPL „Liceul Columna" la data de {{ $date }}. Reflectă situația din baza de date la momentul generării.
    </div>
</body>
</html>
