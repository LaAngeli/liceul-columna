<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <style>
        /* Scheletul INSTITUȚIONAL comun al PDF-urilor generate (rapoarte staff + documentele
           familiei): antet cu emblemă + subsol cu numerotare pe FIECARE pagină. Legarea se face
           prin @page header:/footer: — calea fiabilă mpdf (sethtmlpagefooter scăpa subsolul). */
        @page {
            margin: 34mm 16mm 24mm 16mm;
            margin-header: 8mm;
            margin-footer: 9mm;
            header: html_institutionalHeader;
            footer: html_institutionalFooter;
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

        /* ── Blocul de titlu ── */
        .title { font-size: 15pt; font-weight: bold; text-transform: uppercase; color: #0f4d77; margin: 2px 0 0; letter-spacing: 0.4px; }
        .title-rule { width: 52px; height: 3px; background: #9bc31e; margin: 6px 0 10px; }
        .subtitle { font-size: 10.5pt; color: #686867; margin: 0 0 10px; }

        .meta { width: 100%; border-collapse: collapse; background: #f3f6f9; margin: 4px 0 16px; }
        .meta td { border: none; padding: 7px 12px; font-size: 8.5pt; color: #686867; }
        .meta b { font-size: 10pt; color: #1d1d1c; font-weight: bold; }

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

        .section-h { font-size: 11pt; font-weight: bold; color: #0f4d77; margin: 16px 0 4px; }
        .note { font-size: 8.5pt; color: #8a8a89; margin-top: 4px; }

        @yield('doc-styles')
    </style>
</head>
<body>

{{-- Antetul instituțional — repetat pe fiecare pagină de mpdf. --}}
<htmlpageheader name="institutionalHeader">
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

<htmlpagefooter name="institutionalFooter">
    <table class="pf" width="100%">
        <tr>
            <td>@yield('footer-note', 'Document generat electronic din catalogul IPL „Liceul Columna". Reflectă situația din baza de date la momentul generării.')</td>
            <td class="pageno">Pagina {PAGENO} din {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>

<div class="title">@yield('title')</div>
<div class="title-rule"></div>
@hasSection('subtitle')
    <div class="subtitle">@yield('subtitle')</div>
@endif

@yield('meta-row')

@yield('body')

</body>
</html>
