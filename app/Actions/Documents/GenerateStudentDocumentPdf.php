<?php

namespace App\Actions\Documents;

use App\Enums\GeneratedDocumentType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Randează PDF-ul unui document GENERAT per-elev (foaie matricolă, situația școlară) din datele deja
 * pregătite de apelant (mpdf, pur PHP). NU stochează nimic — produs la cerere, mereu actualizat (§3).
 * Datele + gardurile de acces sunt responsabilitatea apelantului (CabinetController).
 */
class GenerateStudentDocumentPdf
{
    /**
     * @param  array<string, mixed>  $data
     * @return string conținutul binar al PDF-ului
     */
    public function render(GeneratedDocumentType $type, array $data): string
    {
        $html = View::make($type->blade(), $data)->render();

        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'margin_left' => 18,
            'margin_right' => 18,
        ]);
        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', Destination::STRING_RETURN);
    }
}
