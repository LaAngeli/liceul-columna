<?php

use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Models\AcademicYear;
use App\Models\Absence;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $year = AcademicYear::factory()->create();
    $this->semI = Term::factory()->for($year)->create([
        'number' => 1, 'name' => 'Semestrul I',
        'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => true,
    ]);
    $this->semII = Term::factory()->for($year)->create([
        'number' => 2, 'name' => 'Semestrul II',
        'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => false,
    ]);

    // Super-admin → canAdministerCatalog() true → sar peste scoping, testez doar regulile universale.
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin);
});

/**
 * Expune metoda protejată enforceAbsenceScope printr-o clasă anonimă care folosește trait-ul.
 */
function absenceScope(): object
{
    return new class
    {
        use EnforcesAbsenceScope;

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function run(array $data, ?int $ignoreId = null): array
        {
            return $this->enforceAbsenceScope($data, $ignoreId);
        }
    };
}

it('Term::forDate mapează data la semestrul care o conține (null în vacanță)', function () {
    expect(Term::forDate(Carbon::parse('2025-10-15'))?->id)->toBe($this->semI->id)
        ->and(Term::forDate(Carbon::parse('2026-03-10'))?->id)->toBe($this->semII->id)
        ->and(Term::forDate(Carbon::parse('2026-07-20')))->toBeNull();
});

it('derivă semestrul din data absenței, nu dintr-o alegere manuală', function () {
    $student = Student::factory()->create();

    expect(absenceScope()->run(['occurred_on' => '2025-10-15', 'student_id' => $student->id])['term_id'])
        ->toBe($this->semI->id)
        ->and(absenceScope()->run(['occurred_on' => '2026-03-10', 'student_id' => $student->id])['term_id'])
        ->toBe($this->semII->id);
});

it('data în afara semestrelor (vacanță trecută) cade pe semestrul curent', function () {
    $student = Student::factory()->create();

    // 15.08.2025 — vara dinainte de Sem. I, în trecut → forDate null → fallback la is_current.
    expect(absenceScope()->run(['occurred_on' => '2025-08-15', 'student_id' => $student->id])['term_id'])
        ->toBe($this->semI->id);
});

it('respinge o absență cu data în viitor', function () {
    $student = Student::factory()->create();

    absenceScope()->run([
        'occurred_on' => today()->addDay()->toDateString(),
        'student_id' => $student->id,
    ]);
})->throws(ValidationException::class);

it('respinge o absență duplicat (elev + zi + disciplină)', function () {
    $student = Student::factory()->create();
    Absence::factory()->create([
        'student_id' => $student->id, 'occurred_on' => '2025-10-15', 'subject_id' => null,
    ]);

    absenceScope()->run(['occurred_on' => '2025-10-15', 'student_id' => $student->id]);
})->throws(ValidationException::class);

it('editarea aceleiași absențe nu se auto-respinge ca duplicat (exclude id-ul propriu)', function () {
    $student = Student::factory()->create();
    $absence = Absence::factory()->create([
        'student_id' => $student->id, 'occurred_on' => '2025-10-15', 'subject_id' => null,
    ]);

    $data = absenceScope()->run(
        ['occurred_on' => '2025-10-15', 'student_id' => $student->id],
        (int) $absence->id,
    );

    expect($data['term_id'])->toBe($this->semI->id);
});
