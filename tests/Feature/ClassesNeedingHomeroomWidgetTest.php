<?php

use App\Enums\UserRole;
use App\Filament\Widgets\ClassesNeedingHomeroom;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function homeroomTestUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * Clasă fără diriginte, dar CU un elev înmatriculat (deci „activă").
 */
function activeClassWithoutHomeroom(AcademicYear $year): SchoolClass
{
    $class = SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => null]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    return $class;
}

it('e ascuns când toate clasele au diriginte', function () {
    $year = AcademicYear::factory()->create();
    $teacher = Teacher::factory()->create();
    SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs(homeroomTestUser(UserRole::Admin));

    expect(ClassesNeedingHomeroom::canView())->toBeFalse();
});

it('e ascuns când clasa fără diriginte e goală (fără elevi)', function () {
    $year = AcademicYear::factory()->create();
    SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => null]);

    $this->actingAs(homeroomTestUser(UserRole::Admin));

    expect(ClassesNeedingHomeroom::canView())->toBeFalse();
});

it('apare pentru admin și conducere când există clase ACTIVE fără diriginte', function () {
    $year = AcademicYear::factory()->create();
    activeClassWithoutHomeroom($year);

    $this->actingAs(homeroomTestUser(UserRole::Admin));
    expect(ClassesNeedingHomeroom::canView())->toBeTrue();

    $this->actingAs(homeroomTestUser(UserRole::Director));
    expect(ClassesNeedingHomeroom::canView())->toBeTrue();

    $this->actingAs(homeroomTestUser(UserRole::PrimVicedirector));
    expect(ClassesNeedingHomeroom::canView())->toBeTrue();

    $this->actingAs(homeroomTestUser(UserRole::AdministratorOperational));
    expect(ClassesNeedingHomeroom::canView())->toBeTrue();
});

it('NU apare pentru profesor sau diriginte', function () {
    $year = AcademicYear::factory()->create();
    activeClassWithoutHomeroom($year);

    $this->actingAs(homeroomTestUser(UserRole::Profesor));
    expect(ClassesNeedingHomeroom::canView())->toBeFalse();

    $this->actingAs(homeroomTestUser(UserRole::Diriginte));
    expect(ClassesNeedingHomeroom::canView())->toBeFalse();
});

it('numirea unui diriginte scoate clasa din lista celor active fără diriginte', function () {
    $year = AcademicYear::factory()->create();
    $class = activeClassWithoutHomeroom($year);
    $teacher = Teacher::factory()->create();

    expect(SchoolClass::query()->whereNull('homeroom_teacher_id')->has('enrollments')->count())->toBe(1);

    $class->update(['homeroom_teacher_id' => $teacher->id]);

    expect(SchoolClass::query()->whereNull('homeroom_teacher_id')->has('enrollments')->count())->toBe(0);
});
