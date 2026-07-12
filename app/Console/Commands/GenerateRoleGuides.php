<?php

namespace App\Console\Commands;

use App\Support\RoleGuides;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Generează ghidurile de rol ca PDF-uri premium (prezentare pentru client). Sursa de conținut =
 * {@see RoleGuides}; layout-ul = resources/views/pdf/guides/. Un document de ansamblu (00) +
 * câte unul per rol (01–09). Portabil: `--out` alege folderul de ieșire (implicit storage/app).
 *
 * Randare consecventă în RO (document oficial), indiferent de limba de interfață.
 */
class GenerateRoleGuides extends Command
{
    protected $signature = 'app:role-guides {--out= : Folderul de ieșire (implicit storage/app/role-guides)}';

    protected $description = 'Generează ghidurile de rol (PDF premium): ansamblu + 9 roluri.';

    public function handle(): int
    {
        $out = $this->option('out') ?: storage_path('app/role-guides');
        File::ensureDirectoryExists($out);

        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $original = app()->getLocale();
        app()->setLocale('ro');

        try {
            // Documentul de ansamblu.
            $this->write($out, '00-harta-de-ansamblu', View::make('pdf.guides.overview', [
                'components' => RoleGuides::components(),
                'rolesTable' => RoleGuides::rolesTable(),
                'flows' => RoleGuides::flows(),
                'accountCreation' => RoleGuides::accountCreation(),
                'principles' => RoleGuides::principles(),
            ])->render(), $tempDir);
            $this->info('✓ 00-harta-de-ansamblu.pdf');

            // Câte un ghid per rol.
            foreach (RoleGuides::roles() as $role) {
                $html = View::make('pdf.guides.role', ['role' => $role])->render();
                $this->write($out, $role['file'], $html, $tempDir);
                $this->info("✓ {$role['file']}.pdf");
            }
        } finally {
            app()->setLocale($original);
        }

        $this->newLine();
        $this->info("Ghiduri generate în: {$out}");

        return self::SUCCESS;
    }

    private function write(string $out, string $name, string $html, string $tempDir): void
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_top' => 15,
            'margin_bottom' => 18,
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_footer' => 8,
        ]);

        $mpdf->SetHTMLFooter(
            '<table width="100%" style="border-top:0.5px solid #dfe5ea; font-size:7.5pt; color:#9aa3ab; padding-top:3px">'
            .'<tr><td>Liceul Columna · Ghid de utilizare pe roluri</td>'
            .'<td align="right">{PAGENO} / {nbpg}</td></tr></table>'
        );

        $mpdf->WriteHTML($html);

        File::put("{$out}/{$name}.pdf", (string) $mpdf->Output('', Destination::STRING_RETURN));
    }
}
