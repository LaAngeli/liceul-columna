<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Igiena alocărilor (consolidarea Profesori→Utilizatori, 2026-07-31): grupa există DOAR la
     * limba engleză — singura disciplină împărțită pe grupe. Importul legacy copia `engl_gr`
     * NECONDIȚIONAT de disciplină, iar formularul o accepta oriunde; migrarea curăță defensiv
     * orice grupă rătăcită pe alte discipline (pe baza locală: 0 rânduri — dar alte medii pot
     * diferi). Garda din TeachingAssignmentObserver împiedică reapariția.
     */
    public function up(): void
    {
        $englishSubjectIds = DB::table('subjects')
            ->where('name', 'like', '%nglez%')
            ->pluck('id')
            ->all();

        DB::table('teaching_assignments')
            ->whereNotNull('english_group')
            ->when(
                $englishSubjectIds !== [],
                fn ($query) => $query->whereNotIn('subject_id', $englishSubjectIds),
            )
            ->update(['english_group' => null, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Curățare de date — nu există stare anterioară de restaurat.
    }
};
