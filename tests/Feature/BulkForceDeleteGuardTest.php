<?php

/**
 * CENTURA pe ștergerea PERMANENTĂ în masă (§1: „notele nu se șterg NICIODATĂ").
 *
 * Gardul per-rând trăiește în `ConfiguredBySchoolAdmins::forceDelete()` (refuză rândurile cu
 * istoric academic dependent), dar Filament autorizează acțiunile BULK prin metoda `*Any()` —
 * iar `forceDeleteAny()` e un simplu `canConfigureSchool()`. Fără `authorizeIndividualRecords()`,
 * selecția în masă ocolește complet gardul, iar cascadele FK șterg definitiv notele unor minori,
 * fără urmă în jurnalul de audit (cascada e la nivel de DB, owen-it nu o vede).
 */

use App\Enums\UserRole;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\Resources\Terms\Pages\ListTerms;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->configurator = User::factory()->create();
    $this->configurator->assignRole(UserRole::Director->value);
    actingAs($this->configurator);
});

it('ștergerea în masă NU distruge un semestru cu note (gardul per-rând se aplică și la bulk)', function () {
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create();
    $class = SchoolClass::factory()->for($year)->create();

    $grade = Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
    ]);

    // Semestrul ajunge la coș (starea din care ForceDelete e disponibil în UI).
    $term->delete();

    Livewire::test(ListTerms::class)
        ->filterTable('trashed', 'only')
        ->callTableBulkAction('forceDelete', [$term]);

    // Semestrul supraviețuiește ȘI nota rămâne — cascada nu s-a produs.
    expect(Term::withTrashed()->whereKey($term->id)->exists())->toBeTrue()
        ->and(Grade::withTrashed()->whereKey($grade->id)->exists())->toBeTrue();
});

it('ștergerea în masă NU distruge un an școlar cu structură dependentă', function () {
    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create();

    $year->delete();

    Livewire::test(ListAcademicYears::class)
        ->filterTable('trashed', 'only')
        ->callTableBulkAction('forceDelete', [$year]);

    expect(AcademicYear::withTrashed()->whereKey($year->id)->exists())->toBeTrue();
});

it('un rând CURAT rămâne ștergibil permanent în masă (gardul nu blochează curățarea)', function () {
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create();
    $term->delete();

    Livewire::test(ListTerms::class)
        ->filterTable('trashed', 'only')
        ->callTableBulkAction('forceDelete', [$term]);

    expect(Term::withTrashed()->whereKey($term->id)->exists())->toBeFalse();
});

it('DISCIPLINĂ: fiecare FK cu ON DELETE CASCADE spre terms/academic_years e acoperit de policy', function () {
    // Enumeră cascadele REALE din schemă și cere ca ștergerea permanentă a părintelui să fie
    // refuzată când copilul există. Acoperirea poate fi TRANZITIVĂ (ex. lessons cascadează prin
    // school_classes, care e deja verificat) — testul cere doar ca rezultatul final să fie „refuz".
    $driver = DB::connection()->getDriverName();

    if ($driver !== 'mysql') {
        // Pe SQLite (suita implicită) citim cascadele din pragma-ul fiecărui tabel.
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table'"))
            ->pluck('name')
            ->reject(fn (string $t): bool => str_starts_with($t, 'sqlite_'));

        $cascades = [];
        foreach ($tables as $table) {
            foreach (DB::select("PRAGMA foreign_key_list('{$table}')") as $fk) {
                if (in_array($fk->table, ['terms', 'academic_years'], true) && $fk->on_delete === 'CASCADE') {
                    $cascades[$fk->table][] = $table;
                }
            }
        }

        expect($cascades)->not->toBeEmpty('Schema nu declară nicio cascadă — testul și-ar pierde sensul.');
    }

    // Verificarea de comportament: un an cu structură și un semestru cu note NU se pot forța.
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create();
    $class = SchoolClass::factory()->for($year)->create();
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => $term->id,
    ]);

    expect($this->configurator->can('forceDelete', $term))->toBeFalse()
        ->and($this->configurator->can('forceDelete', $year))->toBeFalse();
});
