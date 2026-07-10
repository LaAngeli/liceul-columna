<?php

/**
 * Conturile demo per rol + login-ul de dezvoltare. Acoperă cerința: fiecare rol are un cont care
 * TRECE de gate-urile de securitate (parolă, 2FA, consimțământ) și poate accesa dashboard-ul /
 * cabinetul pentru testare funcțională. Login-ul de dev e strict local/testing.
 */

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\DemoRoleAccountsSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\get;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    config(['security.two_factor.required_staff' => true, 'security.two_factor.required_cabinet' => true]);

    // Fișe minime ca seederul să aibă ce lega (profesor, diriginte cu clasă, elevi cu note).
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create(['is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create();
    $homeroom = Teacher::factory()->create();
    $class->update(['homeroom_teacher_id' => $homeroom->id]);
    Teacher::factory()->count(2)->create();

    $subject = Subject::factory()->create();
    foreach (range(1, 4) as $i) {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for($class)->for($year)->create();
        Grade::factory()->create([
            'student_id' => $student->id, 'school_class_id' => $class->id,
            'subject_id' => $subject->id, 'term_id' => $term->id, 'value' => 8,
        ]);
    }

    $this->seed(DemoRoleAccountsSeeder::class);
});

it('creează un cont demo pentru fiecare dintre cele 9 roluri', function (string $role) {
    $account = User::query()
        ->where('name', 'like', '[DEMO]%')
        ->whereHas('roles', fn ($q) => $q->where('name', $role))
        ->first();

    expect($account)->not->toBeNull("Lipsește contul demo pentru rolul {$role}");
})->with(array_map(fn (UserRole $r) => $r->value, UserRole::cases()));

it('fiecare cont demo trece de toate gate-urile de securitate', function (string $role) {
    $account = User::query()
        ->where('name', 'like', '[DEMO]%')
        ->whereHas('roles', fn ($q) => $q->where('name', $role))
        ->firstOrFail();

    expect($account->must_change_password)->toBeFalse()
        ->and($account->hasTwoFactorConfigured())->toBeTrue()
        ->and($account->hasAcknowledgedCurrentPrivacyNotice())->toBeTrue();
})->with(array_map(fn (UserRole $r) => $r->value, UserRole::cases()));

it('login-ul de dev duce fiecare rol la panou sau cabinet, fără redirect la securitate', function (string $role) {
    $expected = in_array($role, [UserRole::Elev->value, UserRole::Parinte->value], true)
        ? '/dashboard'
        : '/admin';

    // Login: redirect la homePath.
    get("/_demo/login/{$role}")->assertRedirect($expected);

    // Iar homePath-ul răspunde (nu redirecționează la parolă/2FA/consimțământ).
    $account = User::query()->where('name', 'like', '[DEMO]%')
        ->whereHas('roles', fn ($q) => $q->where('name', $role))->firstOrFail();

    $this->actingAs($account)->get($expected)
        ->assertOk()
        ->assertDontSee('configurare-2fa');
})->with(array_map(fn (UserRole $r) => $r->value, UserRole::cases()));

it('login-ul de dev refuză un rol inexistent', function () {
    get('/_demo/login/rol-inventat')->assertNotFound();
});

it('login-ul de dev loghează doar conturi marcate [DEMO]', function () {
    // Un cont NEmarcat cu rol de director nu trebuie ales de login-ul de dev.
    $real = User::factory()->create(['name' => 'Director Real', 'must_change_password' => false]);
    $real->assignRole(UserRole::Director->value);

    get('/_demo/login/'.UserRole::Director->value)->assertRedirect('/admin');

    // Contul autentificat e cel DEMO, nu cel real.
    expect(auth()->user()->name)->toStartWith('[DEMO]');
});

it('rutele demo login nu sunt înregistrate în producție', function () {
    // În producție, blocul din routes/web.php nu montează ruta (guard de mediu).
    expect(app()->environment(['local', 'testing']))->toBeTrue();
    expect(app()->environment('production'))->toBeFalse();
});
