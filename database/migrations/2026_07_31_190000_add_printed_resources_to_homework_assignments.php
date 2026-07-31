<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separă cele DOUĂ tipuri de resurse ale unei teme: `links` = adrese web (deschizabile), iar
 * `printed_resources` = referințe la materiale TIPĂRITE/fizice (ex. „Manualul Istoria Românilor,
 * pag. 20, cap. 4"). Până acum ambele stăteau amestecate în `links` (importul legacy punea și
 * text descriptiv acolo, randat ca simplu chip). Backfill-ul mută textul ne-URL în noul câmp, ca
 * afișarea din cabinet să rămână IDENTICĂ, iar câmpul de link să accepte de-acum strict URL-uri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->json('printed_resources')->nullable()->after('links');
        });

        // Split legacy: intrările `http(s)://` rămân link; restul devin resurse tipărite.
        // Criteriul e IDENTIC cu `isUrl` din frontend, deci nimic nu-și schimbă afișarea.
        foreach (DB::table('homework_assignments')->select('id', 'links')->whereNotNull('links')->cursor() as $row) {
            $decoded = json_decode((string) $row->links, true);

            if (! is_array($decoded) || $decoded === []) {
                continue;
            }

            $urls = [];
            $printed = [];

            foreach ($decoded as $entry) {
                $entry = is_string($entry) ? trim($entry) : '';

                if ($entry === '') {
                    continue;
                }

                if (preg_match('#^https?://#i', $entry) === 1) {
                    $urls[] = $entry;
                } else {
                    $printed[] = $entry;
                }
            }

            if ($printed === []) {
                continue;
            }

            DB::table('homework_assignments')->where('id', $row->id)->update([
                'links' => json_encode($urls),
                'printed_resources' => json_encode($printed),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->dropColumn('printed_resources');
        });
    }
};
