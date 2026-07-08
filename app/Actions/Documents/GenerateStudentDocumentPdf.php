<?php

namespace App\Actions\Documents;

use App\Enums\GeneratedDocumentType;

/**
 * Randează PDF-ul unui document GENERAT per-elev (foaie matricolă, situația școlară) din datele deja
 * pregătite de apelant. NU stochează nimic — produs la cerere, mereu actualizat (§3). Datele + gardurile
 * de acces sunt responsabilitatea apelantului (CabinetController). Delegă randarea mpdf la {@see RenderPdf}.
 */
class GenerateStudentDocumentPdf
{
    public function __construct(private readonly RenderPdf $renderer) {}

    /**
     * @param  array<string, mixed>  $data
     * @return string conținutul binar al PDF-ului
     */
    public function render(GeneratedDocumentType $type, array $data): string
    {
        return $this->renderer->fromView($type->blade(), $data);
    }
}
