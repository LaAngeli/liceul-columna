<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        /* Marginile lasă loc antetului/subsolului repetate pe FIECARE pagină; legarea lor se
           face AICI (header/footer în @page — calea fiabilă mpdf; sethtmlpagefooter scăpa
           subsolul nerandat). */
        @page {
            margin: 34mm 16mm 24mm 16mm;
            margin-header: 8mm;
            margin-footer: 9mm;
            header: html_reportHeader;
            footer: html_reportFooter;
        }

        body { font-family: DejaVuSans, sans-serif; font-size: 10.5pt; color: #1d1d1c; }

        /* ── Antetul instituțional (pe fiecare pagină) ── */
        .ph { border-bottom: 2px solid #0f4d77; padding-bottom: 6px; }
        .ph td { vertical-align: middle; border: none; padding: 0; }
        .ph .school { font-size: 13pt; font-weight: bold; letter-spacing: 0.6px; color: #0f4d77; }
        .ph .sub { font-size: 8pt; color: #686867; letter-spacing: 0.3px; }
        .ph .origin { font-size: 8pt; color: #686867; text-align: right; }
        .ph .origin b { color: #0f4d77; }

        /* ── Subsolul (pe fiecare pagină) ── */
        .pf { border-top: 1px solid #d6dee6; padding-top: 5px; font-size: 7.5pt; color: #8a8a89; }
        .pf td { border: none; padding: 0; }
        .pf .pageno { text-align: right; white-space: nowrap; }

        /* ── Blocul de titlu + meta ── */
        .title { font-size: 15pt; font-weight: bold; text-transform: uppercase; color: #0f4d77; margin: 2px 0 0; letter-spacing: 0.4px; }
        .title-rule { width: 52px; height: 3px; background: #9bc31e; margin: 6px 0 10px; }
        .subtitle { font-size: 10.5pt; color: #686867; margin: 0 0 10px; }

        .meta { width: 100%; border-collapse: collapse; background: #f3f6f9; margin: 4px 0 16px; }
        .meta td { border: none; padding: 7px 12px; font-size: 8.5pt; color: #686867; }
        .meta b { display: block; font-size: 10pt; color: #1d1d1c; margin-top: 1px; font-weight: bold; }

        /* ── Tabele de conținut ── */
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #0f4d77; color: #ffffff; font-size: 8.8pt; text-align: left; padding: 6px 9px; border: 1px solid #0f4d77; font-weight: bold; }
        table.data td { font-size: 9.6pt; padding: 5px 9px; border: 1px solid #e2e8ee; }
        table.data tr.alt td { background: #f7f9fb; }
        td.num, th.num { text-align: center; }
        td.idx { text-align: center; color: #8a8a89; width: 7%; }
        .muted { color: #8a8a89; }
        .good { color: #5a8a00; font-weight: bold; }
        .bad { color: #b3261e; font-weight: bold; }

        /* ── Cutii de sinteză (statistici-cheie) ── */
        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 2px 0 12px; }
        table.stats td { border: 1px solid #e2e8ee; background: #f7f9fb; padding: 8px 10px; font-size: 8.5pt; color: #686867; width: 25%; }
        table.stats b { display: block; font-size: 15pt; color: #0f4d77; margin-top: 2px; }

        /* ── Bare orizontale (grafice simple, lizibile la tipar) ── */
        table.bars { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.bars td { border: none; padding: 3px 6px 3px 0; font-size: 9.2pt; }
        td.bar-label { width: 30%; color: #1d1d1c; }
        td.bar-track { width: 56%; }
        td.bar-value { width: 14%; text-align: right; color: #0f4d77; font-weight: bold; }
        .bar-outer { width: 100%; background: #eef2f6; }
        .bar-fill { background: #0f4d77; height: 9px; }
        .bar-fill.green { background: #9bc31e; }
        .bar-fill.red { background: #b3261e; }
        .bar-fill.amber { background: #d97706; }

        .section-h { font-size: 11pt; font-weight: bold; color: #0f4d77; margin: 16px 0 4px; }
        .note { font-size: 8.5pt; color: #8a8a89; margin-top: 4px; }
    </style>
</head>
<body>

{{-- Antetul instituțional — repetat pe fiecare pagină de mpdf. --}}
<htmlpageheader name="reportHeader">
    <table class="ph" width="100%">
        <tr>
            <td style="width: 44px;">
                <img src="{{ public_path('images/logo/columna-crest-color.png') }}" style="width: 38px;" alt="">
            </td>
            <td style="padding-left: 8px;">
                <div class="school">IPL „LICEUL COLUMNA"</div>
                <div class="sub">Chișinău, Republica Moldova · Succesul copilului începe aici.</div>
            </td>
            <td class="origin">
                Catalogul electronic<br><b>columna.md</b>
            </td>
        </tr>
    </table>
</htmlpageheader>

<htmlpagefooter name="reportFooter">
    <table class="pf" width="100%">
        <tr>
            <td>Document generat electronic din catalogul IPL „Liceul Columna" la {{ $generatedAt ?? '' }}, de {{ $generatedBy ?? '—' }}. Reflectă situația din baza de date la momentul generării.</td>
            <td class="pageno">Pagina {PAGENO} din {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>

<div class="title">@yield('title')</div>
<div class="title-rule"></div>
@hasSection('subtitle')
    <div class="subtitle">@yield('subtitle')</div>
@endif

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

@yield('body')

</body>
</html>
