<?php

namespace App\Actions\Documents;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Randează un șablon Blade într-un PDF (mpdf, pur PHP) și întoarce conținutul binar. Sursă UNICĂ de
 * configurare mpdf pentru toate documentele generate (familie + staff) — nu stochează nimic.
 */
class RenderPdf
{
    /**
     * @param  array<string, mixed>  $data
     * @return string conținutul binar al PDF-ului
     */
    public function fromView(string $view, array $data): string
    {
        $html = View::make($view, $data)->render();

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
