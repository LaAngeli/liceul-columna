<?php

/**
 * Coșul de restaurare (cerința beneficiarului 2026-07-23).
 *
 * Problema de la care a pornit: ștergerile erau reversibile în COD (soft delete pe 22 de modele),
 * dar irecuperabile în PANOU — la Profesori acțiunea de restaurare era chiar cod mort (tabelul
 * n-avea filtrul „Șterse", deci un profesor șters nu putea fi listat niciodată). Aici se verifică
 * ce contează: că restaurarea e AJUNGIBILĂ pentru conducere, că refuză stările imposibile în loc
 * să le producă, și că înmatricularea revine ODATĂ cu elevul — altfel elevul s-ar întoarce în
 * afara oricărei clase, fără să i se poată crea alta (slotul (elev, an) e ținut de rândul șters).
 */

use App\Actions\InspectRestoreConflicts;
use App\Actions\RestoreArchivedRecord;
use App\Enums\RestorableType;
use App\Enums\UserRole;
use App\Filament\Pages\RestoreCenter;
use App\Filament\Resources\Users\UserResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5]);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

it('conducerea vede coșul; profesorul nu are ce căuta acolo', function () {
    actingAs($this->director);
    expect(RestoreCenter::canAccess())->toBeTrue();

    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    actingAs($profesor);

    expect(RestoreCenter::canAccess())->toBeFalse();
});

it('un elev șters apare în coș, cu cine l-a șters, și se restaurează din pagină', function () {
    actingAs($this->director);

    $student = Student::factory()->create();
    $student->delete();

    Livewire::test(RestoreCenter::class)
        ->call('openType', RestorableType::Students->value)
        ->assertSet('activeType', RestorableType::Students->value);

    $page = Livewire::test(RestoreCenter::class)->set('activeType', RestorableType::Students->value);
    $rows = $page->instance()->records();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($student->id)
        ->and($rows[0]['blocking'])->toBe([]);

    $page->call('restore', $student->id);

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});

it('elevul restaurat își aduce ÎNAPOI înmatricularea ștearsă odată cu el', function () {
    actingAs($this->director);

    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create([
        'enrolled_on' => '2025-09-01',
        'left_on' => null,
    ]);

    // Ștergerea fișei în panou lasă înmatricularea ștearsă în urmă (fluxul real).
    $enrollment->delete();
    $student->delete();

    $result = app(RestoreArchivedRecord::class)->restore($student->fresh(['enrollments']) ?? $student);

    expect($result['cascaded'])->toBe(1)
        ->and(Enrollment::query()->whereKey($enrollment->id)->exists())->toBeTrue();
});

it('restaurarea unei înmatriculări e BLOCATĂ cât timp elevul sau clasa sunt încă șterse', function () {
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();

    $enrollment->delete();
    $student->delete();

    $conflicts = app(InspectRestoreConflicts::class)->inspect($enrollment->fresh() ?? $enrollment);

    expect($conflicts['blocking'])->not->toBeEmpty();

    app(RestoreArchivedRecord::class)->restore($enrollment->fresh() ?? $enrollment);
})->throws(ValidationException::class);

it('o clasă dintr-un an ÎNCHIS nu se restaurează — registrul acelui an e înghețat', function () {
    // Clasa se creează cât anul e deschis (garda de model refuză altfel), apoi anul se închide —
    // exact secvența reală: clasa a existat, a fost ștearsă, iar între timp anul s-a încheiat.
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 6]);
    $class->delete();
    $year->forceFill(['closed_at' => now()])->save();

    $conflicts = app(InspectRestoreConflicts::class)->inspect($class->fresh() ?? $class);

    expect($conflicts['blocking'])->not->toBeEmpty();
});

it('elevul al cărui cont a fost luat de altă fișă e blocat, nu duplicat tăcut', function () {
    $account = User::factory()->create();
    $account->assignRole(UserRole::Elev->value);

    $vechi = Student::factory()->create(['user_id' => $account->id]);
    $vechi->delete();

    // Între timp, contul a fost legat de o fișă nouă.
    Student::factory()->create(['user_id' => $account->id]);

    $conflicts = app(InspectRestoreConflicts::class)->inspect($vechi->fresh() ?? $vechi);

    expect($conflicts['blocking'])->not->toBeEmpty();
});

it('disciplina restaurată cu poziția ocupată în foaia matricolă se mută la coadă, nu dublează ordinea', function () {
    actingAs($this->director);

    $veche = Subject::factory()->create(['name' => 'Astronomie', 'report_order' => 3]);
    $veche->delete();

    // Poziția 3 a fost preluată de altă disciplină cât timp aceasta era ștearsă.
    Subject::factory()->create(['name' => 'Robotică', 'report_order' => 3]);

    $result = app(RestoreArchivedRecord::class)->restore($veche->fresh() ?? $veche);

    expect($result['repaired'])->not->toBeEmpty()
        ->and(Subject::query()->whereKey($veche->id)->value('report_order'))->not->toBe(3)
        ->and(Subject::query()->where('report_order', 3)->count())->toBe(1);
});

it('disciplina al cărei nume a fost reluat de alta e blocată (numele e unic prin regulă)', function () {
    $veche = Subject::factory()->create(['name' => 'Logică']);
    $veche->delete();

    Subject::factory()->create(['name' => 'Logică']);

    $conflicts = app(InspectRestoreConflicts::class)->inspect($veche->fresh() ?? $veche);

    expect($conflicts['blocking'])->not->toBeEmpty();
});

it('profesorul șters ajunge în coș — regresia care făcea restaurarea inaccesibilă', function () {
    actingAs($this->director);

    $teacher = Teacher::factory()->create();
    $teacher->delete();

    $page = Livewire::test(RestoreCenter::class)->set('activeType', RestorableType::Teachers->value);

    expect($page->instance()->records())->toHaveCount(1);

    $page->call('restore', $teacher->id);

    expect(Teacher::query()->whereKey($teacher->id)->exists())->toBeTrue();
});

it('ștergerea definitivă e doar a super-adminului; directorul n-o poate face nici prin apel direct', function () {
    actingAs($this->director);

    $student = Student::factory()->create();
    $student->delete();

    $page = Livewire::test(RestoreCenter::class)->set('activeType', RestorableType::Students->value);

    expect($page->instance()->canPurge())->toBeFalse();

    $page->call('purge', $student->id);

    expect(Student::withTrashed()->whereKey($student->id)->exists())->toBeTrue();

    $super = User::factory()->create();
    $super->assignRole(UserRole::Admin->value);
    actingAs($super);

    Livewire::test(RestoreCenter::class)
        ->set('activeType', RestorableType::Students->value)
        ->call('purge', $student->id);

    expect(Student::withTrashed()->whereKey($student->id)->exists())->toBeFalse();
});

it('conturile NU se mai șterg din panou — nici măcar de super-admin', function () {
    $super = User::factory()->create();
    $super->assignRole(UserRole::Admin->value);
    actingAs($super);

    $parinte = User::factory()->create();
    $parinte->assignRole(UserRole::Parinte->value);

    expect(UserResource::canDelete($parinte))->toBeFalse()
        ->and(UserResource::canDeleteAny())->toBeFalse();
});
