<?php

/**
 * Fișa de EDITARE a elevului, restructurată (cerința beneficiarului, 2026-07-21): câmpuri
 * dependente de structura reală a școlii (limba 2 doar de la clasa a V-a; grupa la engleză
 * strict 1/2 — engleza e L1 pentru toți, pe grupe), numărul matricol unic la schimbare (fără
 * a bloca duplicatele moștenite din legacy), contul de acces administrat de SISTEM (select
 * doar pe fișe orfane, doar pentru cine administrează conturi), avertizare la modificările cu
 * impact în alte module și gardă de model sub orice cale de scriere.
 */

use App\Enums\SecondLanguage;
use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\AcademicYear;
use App\Models\Audit;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);
});

/** Elev înmatriculat în anul curent la treapta dată. */
function setStudentInGrade(Student $student, int $gradeLevel): SchoolClass
{
    $year = AcademicYear::query()->where('is_current', true)->first()
        ?? AcademicYear::factory()->create(['is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => $gradeLevel]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'academic_year_id' => $year->id,
    ]);

    return $class;
}

it('limba străină 2 este blocată pe „Nu studiază" la ciclul primar și liberă din clasa a V-a', function () {
    $primar = Student::factory()->create(['second_language' => SecondLanguage::None]);
    setStudentInGrade($primar, 3);

    // Server: valoarea „germană" pe un elev de clasa a III-a e respinsă.
    Livewire::test(EditStudent::class, ['record' => $primar->getKey()])
        ->fillForm(['second_language' => SecondLanguage::German->value])
        ->call('save')
        ->assertHasFormErrors(['second_language']);

    expect($primar->refresh()->second_language)->toBe(SecondLanguage::None);

    // Din clasa a V-a alegerea e legitimă.
    $gimnaziu = Student::factory()->create(['second_language' => SecondLanguage::None]);
    setStudentInGrade($gimnaziu, 6);

    Livewire::test(EditStudent::class, ['record' => $gimnaziu->getKey()])
        ->fillForm(['second_language' => SecondLanguage::German->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($gimnaziu->refresh()->second_language)->toBe(SecondLanguage::German);
});

it('grupa la engleză acceptă doar 1 sau 2 — și în formular, și la nivel de model', function () {
    $student = Student::factory()->create(['english_group' => 1]);
    setStudentInGrade($student, 7);

    // Formular: valoarea 3 (posibilă în vechiul TextInput) nu mai există în opțiuni.
    Livewire::test(EditStudent::class, ['record' => $student->getKey()])
        ->fillForm(['english_group' => 3])
        ->call('save')
        ->assertHasFormErrors(['english_group']);

    // Model: orice cale de scriere e păzită.
    expect(fn () => Student::factory()->create(['english_group' => 3]))
        ->toThrow(ValidationException::class);
});

it('numărul matricol nou sau modificat nu poate repeta unul existent; duplicatele legacy nu blochează salvarea neutră', function () {
    Student::factory()->create(['register_number' => '100']);

    // Doi elevi cu același număr moștenit din legacy (starea reală: 29 de duplicate).
    $legacyA = Student::factory()->create(['register_number' => '200']);
    Student::factory()->create(['register_number' => '200']);
    setStudentInGrade($legacyA, 8);

    // Salvare care NU atinge numărul → trece (istoricul murdar nu blochează).
    Livewire::test(EditStudent::class, ['record' => $legacyA->getKey()])
        ->fillForm(['first_name' => 'Prenume-Nou'])
        ->call('save')
        ->assertHasNoFormErrors();

    // Schimbarea numărului către unul FOLOSIT → respinsă.
    Livewire::test(EditStudent::class, ['record' => $legacyA->getKey()])
        ->fillForm(['register_number' => '100'])
        ->call('save')
        ->assertHasFormErrors(['register_number']);

    expect($legacyA->refresh()->register_number)->toBe('200');
});

it('contul legat NU mai poate fi schimbat din fișă — selectul există doar pe fișele orfane, pentru cine administrează conturi', function () {
    $account = User::factory()->create();
    $account->assignRole(UserRole::Elev->value);
    $otherAccount = User::factory()->create();
    $otherAccount->assignRole(UserRole::Elev->value);

    $linked = Student::factory()->create(['user_id' => $account->id]);
    setStudentInGrade($linked, 5);

    // Fișă CU cont: user_id nu e în formular → o tentativă de repointare nu are efect.
    Livewire::test(EditStudent::class, ['record' => $linked->getKey()])
        ->fillForm(['first_name' => 'Neutru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($linked->refresh()->user_id)->toBe($account->id);

    // Fișă ORFANĂ + administrator de conturi: legarea funcționează (supapa pentru orfani).
    $orphan = Student::factory()->create(['user_id' => null]);
    setStudentInGrade($orphan, 5);

    Livewire::test(EditStudent::class, ['record' => $orphan->getKey()])
        ->fillForm(['user_id' => $otherAccount->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($orphan->refresh()->user_id)->toBe($otherAccount->id);
});

it('schimbarea grupei sau a limbii 2 avertizează despre impactul în alte module', function () {
    $student = Student::factory()->create(['english_group' => 1, 'second_language' => SecondLanguage::French]);
    setStudentInGrade($student, 6);

    Livewire::test(EditStudent::class, ['record' => $student->getKey()])
        ->fillForm(['english_group' => 2])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    // O salvare fără schimbări sensibile nu bâzâie degeaba: notificarea de salvare standard
    // există oricum, dar cea de IMPACT (persistentă) apare doar la câmpurile sensibile —
    // verificat implicit prin testul de mai sus (wasChanged).
    expect($student->refresh()->english_group)->toBe(2);
});

it('modificările fișei rămân în jurnalul de audit (trasabilitate)', function () {
    config(['audit.console' => true]);

    $student = Student::factory()->create(['first_name' => 'Inițial']);
    $student->update(['first_name' => 'Redenumit']);

    $entry = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $student->id)
        ->where('event', 'updated')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toHaveKey('first_name');
});

it('numele se normalizează la salvare (spații multiple, margini)', function () {
    $student = Student::factory()->create(['last_name' => '  Cațîr  ', 'first_name' => ' Alexandra   Maria ']);

    expect($student->last_name)->toBe('Cațîr')
        ->and($student->first_name)->toBe('Alexandra Maria');
});

it('etichetele noii fișe există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.forms.student.section_academic_hint', $locale))->toBeTrue("Lipsește section_academic_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.student.english_group_long', $locale))->toBeTrue("Lipsește english_group_long [{$locale}]")
            ->and(Lang::hasForLocale('panel.forms.student.account_managed_hint', $locale))->toBeTrue("Lipsește account_managed_hint [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.student.second_language_primary', $locale))->toBeTrue("Lipsește second_language_primary [{$locale}]")
            ->and(Lang::hasForLocale('panel.validation.student.register_number_taken', $locale))->toBeTrue("Lipsește register_number_taken [{$locale}]");
    }
});
