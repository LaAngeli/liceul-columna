<?php

namespace Database\Seeders;

use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Documente DEMO pentru biblioteca „Documente utile" — ca să vedem cum se afișează (categorii ×
 * niveluri de acces × roluri). Fiecare document primește un PDF valid, generat pe loc (branded).
 *
 * Marcate `[DEMO]` în titlu → curățabile. Idempotent: la re-rulare șterge întâi documentele demo
 * (și fișierele lor), apoi le recreează.
 *
 *   php artisan db:seed --class=DemoDocumentsSeeder            # creează
 *   php artisan db:seed --class=DemoDocumentsSeeder --  (--remove nu e suportat; vezi mai jos)
 *
 * Curățare la deploy: Document::where('title','like','[DEMO]%')->get() → șterge fișierul + rândul.
 */
class DemoDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $this->purgeExisting();

        $uploader = User::query()->role(UserRole::Admin->value)->first();

        foreach ($this->documents() as $doc) {
            [$path, $size] = $this->makePdf($doc['title'], $doc['description']);

            Document::create([
                'title' => '[DEMO] '.$doc['title'],
                'description' => $doc['description'],
                'category' => $doc['category'],
                'access_level' => $doc['roles'] === [] ? DocumentAccessLevel::Public : DocumentAccessLevel::RoleSpecific,
                'visible_roles' => $doc['roles'] === [] ? null : $doc['roles'],
                'source' => DocumentSource::Static,
                'file_path' => $path,
                'file_name' => Str::slug($doc['title']).'.pdf',
                'file_size' => $size,
                'mime_type' => 'application/pdf',
                'version' => $doc['version'] ?? null,
                'is_published' => $doc['published'] ?? true,
                'uploaded_by_user_id' => $uploader?->id,
            ]);
        }

        $this->command->info('Documente demo create: '.count($this->documents()).' (marcate [DEMO]).');
    }

    /**
     * Definiția documentelor demo — acoperă categoriile și rolurile din spec §2.
     *
     * @return list<array{title: string, description: string, category: DocumentCategory, roles: list<string>, version?: string, published?: bool}>
     */
    private function documents(): array
    {
        $conducere = [UserRole::Director->value, UserRole::PrimVicedirector->value, UserRole::AdministratorOperational->value];
        $pedagogic = [UserRole::Profesor->value, UserRole::Diriginte->value];
        $familie = [UserRole::Elev->value, UserRole::Parinte->value];

        return [
            // Comune — publice (toate rolurile).
            ['title' => 'Regulament de ordine interioară (ROI)', 'description' => 'Regulamentul intern al liceului — drepturi, obligații, disciplină.', 'category' => DocumentCategory::Useful, 'roles' => [], 'version' => 'ed. 2025'],
            ['title' => 'Calendarul anului școlar 2025–2026', 'description' => 'Structura anului: semestre, vacanțe, sesiuni.', 'category' => DocumentCategory::Useful, 'roles' => []],
            ['title' => 'Orarul sunetelor', 'description' => 'Programul orelor și al pauzelor.', 'category' => DocumentCategory::Useful, 'roles' => []],
            ['title' => 'Meniul cantinei — săptămâna curentă', 'description' => 'Meniul propus de cantina liceului.', 'category' => DocumentCategory::Useful, 'roles' => []],
            ['title' => 'Ghid de utilizare a catalogului electronic', 'description' => 'Pași de bază pentru elevi, părinți și personal.', 'category' => DocumentCategory::Useful, 'roles' => []],

            // Elev / Părinte.
            ['title' => 'Drepturi și obligații ale elevului', 'description' => 'Extras pe scurt, pentru elevi și familii.', 'category' => DocumentCategory::Useful, 'roles' => $familie],
            ['title' => 'Cerere de învoire — formular', 'description' => 'Formular de învoire/absență planificată.', 'category' => DocumentCategory::Requests, 'roles' => $familie],

            // Profesor / Diriginte.
            ['title' => 'Metodologia de evaluare (integrală)', 'description' => 'Documentul metodologic complet pentru cadre didactice.', 'category' => DocumentCategory::Useful, 'roles' => $pedagogic, 'version' => 'ed. 2026'],
            ['title' => 'Borderou de note — model', 'description' => 'Șablon de borderou pentru evaluări.', 'category' => DocumentCategory::Forms, 'roles' => $pedagogic],
            ['title' => 'Proces-verbal de evaluare — șablon', 'description' => 'Model de proces-verbal la evaluări.', 'category' => DocumentCategory::Forms, 'roles' => $pedagogic],

            // Diriginte.
            ['title' => 'Fișă de caracterizare a elevului — model', 'description' => 'Șablon de fișă de caracterizare.', 'category' => DocumentCategory::Forms, 'roles' => [UserRole::Diriginte->value]],
            ['title' => 'Proces-verbal ședință cu părinții — șablon', 'description' => 'Model de proces-verbal pentru ședințe.', 'category' => DocumentCategory::Forms, 'roles' => [UserRole::Diriginte->value]],

            // Conducere.
            ['title' => 'Regulament de evaluare — extras', 'description' => 'Extras pentru conducerea academică.', 'category' => DocumentCategory::Useful, 'roles' => $conducere],
            ['title' => 'Situație promovabilitate — model raport', 'description' => 'Model de raport agregat (draft, nepublicat).', 'category' => DocumentCategory::Reports, 'roles' => $conducere, 'published' => false],

            // Administrator tehnic.
            ['title' => 'Procedură de backup și securitate', 'description' => 'Documentație tehnică — fără conținut pedagogic.', 'category' => DocumentCategory::Useful, 'roles' => [UserRole::AdministratorTehnic->value]],

            // Înștiințare (comună).
            ['title' => 'Model de înștiințare a părinților', 'description' => 'Șablon de înștiințare (statut/comportament).', 'category' => DocumentCategory::Notices, 'roles' => [UserRole::Diriginte->value, UserRole::Director->value]],
        ];
    }

    /**
     * Generează un PDF valid, branded, pentru un document demo și îl stochează privat.
     *
     * @return array{0: string, 1: int} [cale, dimensiune bytes]
     */
    private function makePdf(string $title, string $description): array
    {
        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $html = <<<HTML
            <div style="font-family: sans-serif; color: #1d1d1c;">
                <div style="border-bottom: 3px solid #0f4d77; padding-bottom: 8px; margin-bottom: 24px;">
                    <span style="color:#0f4d77; font-size: 22px; font-weight: bold;">IPL Liceul Columna</span>
                    <span style="color:#9bc31e; font-size: 12px; float: right;">DOCUMENT DEMO</span>
                </div>
                <h1 style="color:#0f4d77; font-size: 20px;">{$title}</h1>
                <p style="font-size: 13px; color:#686867;">{$description}</p>
                <p style="margin-top: 32px; font-size: 12px; color:#686867;">
                    Acesta este un document de DEMONSTRAȚIE, generat automat pentru a ilustra afișarea
                    bibliotecii „Documente utile". Va fi înlocuit cu documentul real la punerea în funcțiune.
                </p>
            </div>
            HTML;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'margin_top' => 20,
            'margin_left' => 22,
            'margin_right' => 22,
        ]);
        $mpdf->WriteHTML($html);
        $content = (string) $mpdf->Output('', Destination::STRING_RETURN);

        $path = 'documents/demo-'.Str::slug($title).'.pdf';
        Storage::disk('local')->put($path, $content);

        return [$path, strlen($content)];
    }

    /** Șterge documentele demo existente (rând + fișier), pentru idempotență. */
    private function purgeExisting(): void
    {
        Document::withTrashed()->where('title', 'like', '[DEMO]%')->get()->each(function (Document $document): void {
            if ($document->file_path !== null && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
            $document->forceDelete();
        });
    }
}
