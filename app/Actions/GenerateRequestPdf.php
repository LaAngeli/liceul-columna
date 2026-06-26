<?php

namespace App\Actions;

use App\Models\DocumentRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Generează PDF-ul unei cereri tipice (mpdf, pur PHP) și îl stochează pe disk PRIVAT (`local`) —
 * conține PII de minor, deci NU public. Întoarce calea relativă a fișierului.
 */
class GenerateRequestPdf
{
    public function generate(DocumentRequest $request): string
    {
        $request->loadMissing(['student', 'requestedBy']);
        $student = $request->student;
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;

        /** @var array<string, mixed> $payload */
        $payload = $request->payload;
        $start = isset($payload['period_start']) ? (string) $payload['period_start'] : null;
        $end = isset($payload['period_end']) ? (string) $payload['period_end'] : null;

        $html = view('pdf.document-request', [
            'typeLabel' => $request->type->label(),
            'studentName' => $student->full_name,
            'className' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
            'parentName' => $request->requestedBy?->name,
            'details' => isset($payload['details']) ? (string) $payload['details'] : '',
            'period' => $this->formatPeriod($start, $end),
            'date' => now()->format('d.m.Y'),
        ])->render();

        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 22,
            'margin_right' => 22,
        ]);
        $mpdf->WriteHTML($html);

        $path = "cereri/cerere-{$request->id}.pdf";
        Storage::disk('local')->put($path, (string) $mpdf->Output('', Destination::STRING_RETURN));

        $request->update(['pdf_path' => $path]);

        return $path;
    }

    private function formatPeriod(?string $start, ?string $end): ?string
    {
        if ($start === null) {
            return null;
        }

        $from = Carbon::parse($start)->format('d.m.Y');

        if ($end === null || $end === $start) {
            return $from;
        }

        return $from.' – '.Carbon::parse($end)->format('d.m.Y');
    }
}
