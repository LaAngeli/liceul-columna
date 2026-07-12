<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        /* ── Sistem vizual premium (paleta de brand) — compatibil mpdf (fără flex/grid) ── */
        body { font-family: DejaVuSans, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1d1d1c; }

        /* Copertă / antet de brand */
        .cover { background: #0f4d77; color: #ffffff; padding: 20px 22px 18px; margin-bottom: 22px; }
        .cover .kicker { font-size: 8.5pt; letter-spacing: 3px; text-transform: uppercase; color: #9bc31e; font-weight: bold; margin-bottom: 2px; }
        .cover .badge { font-size: 8.5pt; letter-spacing: 1px; text-transform: uppercase; color: #cfe0ec; margin-bottom: 10px; }
        .cover h1 { font-size: 27pt; font-weight: bold; margin: 0 0 8px; line-height: 1.05; }
        .cover .tagline { font-size: 11pt; color: #e7eff5; line-height: 1.45; margin: 0; }
        .cover .rule { border-top: 3px solid #9bc31e; width: 64px; margin: 12px 0; }
        /* Pastile pe banda navy — culoare SOLIDĂ (mpdf nu randează rgba() fiabil). */
        .chip { background: #1c5c86; color: #ffffff; font-size: 8.5pt; padding: 4px 10px; }
        .chip .lbl { color: #9bc31e; font-weight: bold; }

        /* Secțiuni */
        h2.sec { font-size: 13pt; color: #0f4d77; font-weight: bold; margin: 22px 0 4px; padding-bottom: 4px; }
        h2.sec .num { color: #9bc31e; }
        .sec-rule { border-top: 2px solid #9bc31e; width: 100%; margin: 0 0 12px; }
        .lead { color: #454545; margin: 0 0 6px; }

        /* Carduri / blocuri */
        .card { background: #f4f7fa; padding: 11px 14px; margin-bottom: 6px; }
        .card.accent { border-left: 3px solid #0f4d77; }
        .card p { margin: 0; }
        .muted { color: #686867; }

        /* Liste „poți / nu poți" (două coloane prin tabel) */
        table.two { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        table.two td { vertical-align: top; width: 50%; }
        .panel { padding: 10px 12px; }
        .panel .head { font-weight: bold; font-size: 10.5pt; margin-bottom: 6px; }
        .panel.yes { background: #eef6dd; border-left: 3px solid #7ba017; }
        .panel.yes .head { color: #4f6b0f; }
        .panel.no  { background: #fbeceb; border-left: 3px solid #b3261e; }
        .panel.no  .head { color: #9a231c; }
        .panel ul { margin: 0; padding-left: 16px; }
        .panel li { margin-bottom: 4px; line-height: 1.4; }

        /* Tabele de conținut */
        table.grid { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.grid th { background: #0f4d77; color: #ffffff; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .5px; text-align: left; padding: 6px 8px; }
        table.grid td { font-size: 9.5pt; padding: 6px 8px; border-bottom: 1px solid #e3e9ef; vertical-align: top; }
        table.grid tr.alt td { background: #f4f7fa; }
        td.lvl { text-align: center; font-weight: bold; color: #0f4d77; width: 34px; }
        td.k { font-weight: bold; color: #0f4d77; white-space: nowrap; }

        /* Flow-cards (fluxuri) */
        .flow { background: #f4f7fa; border-left: 3px solid #9bc31e; padding: 10px 13px; margin-bottom: 7px; }
        .flow .name { font-weight: bold; color: #0f4d77; font-size: 10.5pt; }
        .flow .desc { color: #454545; margin: 2px 0 5px; }
        .flow .chain { font-weight: bold; color: #2e2d2c; font-size: 9.5pt; }

        /* Pași */
        .step { margin-bottom: 8px; }
        .step .t { font-weight: bold; color: #0f4d77; }

        /* Componente ecosistem */
        .comp { background: #f4f7fa; border-top: 3px solid #0f4d77; padding: 12px 14px; margin-bottom: 8px; }
        .comp .n { font-size: 12pt; font-weight: bold; color: #0f4d77; }
        .comp .aud { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .5px; color: #7ba017; font-weight: bold; margin: 2px 0 6px; }
        .comp .u { font-size: 8.5pt; color: #686867; }

        .footer-note { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e3e9ef; font-size: 8pt; color: #8a9299; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="cover avoid-break">
        <table style="width:100%"><tr>
            <td style="vertical-align:middle">
                <div class="kicker">Liceul Columna · Ghid de utilizare</div>
                @hasSection('badge')<div class="badge">@yield('badge')</div>@endif
            </td>
            <td style="vertical-align:middle; text-align:right; width:150px">
                <img src="{{ public_path('images/logo/columna-horizontal-white.png') }}" style="width:140px">
            </td>
        </tr></table>
        <div class="rule"></div>
        <h1>@yield('coverTitle')</h1>
        <p class="tagline">@yield('coverTagline')</p>
        @hasSection('coverMeta')<div style="margin-top:12px">@yield('coverMeta')</div>@endif
    </div>

    @yield('body')

    <div class="footer-note">
        IPL „Liceul Columna", Chișinău · Document de prezentare generat din logica reală de permisiuni a platformei.
        Reflectă comportamentul verificat al aplicației la data generării.
    </div>
</body>
</html>
