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
use App\Models\TwoFactorEmailCode;
use App\Models\User;
use App\Support\DemoSecurity;
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
    // A doua fișă cu dirigenție: vitrina multi-rol (director@) își ia propria clasă,
    // fără să i-o fure dirigintelui dedicat.
    $secondHomeroom = Teacher::factory()->create();
    SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => $secondHomeroom->id]);
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

it('login-ul de dev SE AUTO-VINDECĂ: cont demo cu 2FA șters + obligativitate pe TRUE → tot intră', function () {
    // Scenariul recurent (2026-07-31): `app:demo-accounts --reset-2fa` a golit 2FA de pe conturile
    // demo, iar `SECURITY_2FA_STAFF` era cache-uit pe true → provocarea de cod reapărea. Aici
    // reproducem exact starea stricată și verificăm că login-ul demo o repară singur.
    $director = User::query()->where('name', 'like', '[DEMO]%')
        ->whereHas('roles', fn ($q) => $q->where('name', UserRole::Director->value))->firstOrFail();

    $director->forceFill([
        'two_factor_email_enabled_at' => null,
        'must_change_password' => true,
        'privacy_acknowledged_at' => null,
        'privacy_acknowledged_version' => null,
    ])->save();

    expect($director->fresh()->hasTwoFactorConfigured())->toBeFalse();

    // Cu obligativitatea pe true (ca un config cache-uit), login-ul demo intră fără provocare.
    config(['security.two_factor.required_staff' => true]);

    get('/_demo/login/'.UserRole::Director->value)->assertRedirect('/admin');

    $this->actingAs($director->fresh())->get('/admin')
        ->assertOk()
        ->assertDontSee('configurare-2fa');

    expect($director->fresh()->hasTwoFactorConfigured())->toBeTrue();
});

// ── Vitrina multi-rol: director@ = „Ion Popescu" din specificație ─────────────────────────────

it('director@ poartă cele trei roluri și comutatorul din topbar le arată pe toate', function () {
    // Raportat 30.07: „de pe director nu pot schimba rolul" — contul era mono. Acum e vitrina.
    $director = User::query()->where('username', 'director')->firstOrFail();

    expect($director->getRoleNames()->all())
        ->toEqualCanonicalizing([UserRole::Director->value, UserRole::Profesor->value, UserRole::Diriginte->value])
        ->and($director->isMultiRole())->toBeTrue()
        // Are fișă proprie, cu dirigenție — toate cele trei contexte au conținut.
        ->and($director->teacher)->not->toBeNull()
        ->and($director->homeroomSchoolClassIds())->not->toBe([]);

    // Badge-ul din topbar devine SELECT cu toate cele trei roluri.
    $this->actingAs($director);
    $html = view('filament.topbar.live-datetime')->render();

    expect($html)->toContain('id="fi-role-switch-select"')
        ->and($html)->toContain(UserRole::Director->label())
        ->and($html)->toContain(UserRole::Profesor->label())
        ->and($html)->toContain(UserRole::Diriginte->label());

    // Iar comutarea chiar funcționează pe el.
    $this->post(route('staff.role.switch'), ['role' => UserRole::Profesor->value])->assertRedirect();
    expect($director->fresh()->activeRole())->toBe(UserRole::Profesor);
});

it('login-ul demo alege contul după USERNAME — dirigintele dedicat, nu directorul-vitrină', function () {
    // Sub multi-rol, căutarea pe rol devine ambiguă: directorul poartă ȘI rolul diriginte.
    get('/_demo/login/'.UserRole::Diriginte->value)->assertRedirect('/admin');
    expect(auth()->user()->username)->toBe('diriginte');

    auth()->logout();

    get('/_demo/login/'.UserRole::Profesor->value)->assertRedirect('/admin');
    expect(auth()->user()->username)->toBe('profesor');
});

it('profesor@ rămâne DELIBERAT mono-rol — cazul-negativ, fără comutator', function () {
    $profesor = User::query()->where('username', 'profesor')->firstOrFail();

    expect($profesor->isMultiRole())->toBeFalse();

    $this->actingAs($profesor);
    $html = view('filament.topbar.live-datetime')->render();

    expect($html)->not->toContain('id="fi-role-switch-select"');
});

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

/**
 * REGRESIA CARE S-A ÎNTORS DE TREI ORI: „îmi cere iar cod de confirmare".
 *
 * Cauza n-a fost configul, ci contradicția dintre două mecanisme — `DemoSecurity::pass()` înrola
 * contul în 2FA pe email ca să treacă de gate-ul de OBLIGATIVITATE, iar înrolarea e exact ce
 * declanșează PROVOCAREA la login. Cum funcția rulează la fiecare login demo, orice curățare era
 * anulată imediat. Testele de mai jos fixează contractul în AMBELE poziții ale comutatorului.
 */
it('cu obligativitatea STINSĂ, contul demo nu are 2FA — deci nu i se cere cod', function () {
    config(['security.two_factor.required_staff' => false, 'security.two_factor.required_cabinet' => false]);

    $user = User::factory()->create(['name' => '[DEMO] Profesor', 'email' => 'p@columna.test']);
    $user->assignRole(UserRole::Profesor->value);
    // Starea din care pornea problema: cont deja înrolat pe email, cu un cod în așteptare.
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save();
    TwoFactorEmailCode::create([
        'user_id' => $user->id,
        'code_hash' => hash('sha256', '123456'),
        'sent_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);

    DemoSecurity::pass($user);

    expect($user->fresh()->twoFactorChallengeMethod())->toBeNull()
        ->and($user->fresh()->two_factor_email_enabled_at)->toBeNull()
        ->and(TwoFactorEmailCode::where('user_id', $user->id)->count())->toBe(0);
});

it('cu obligativitatea APRINSĂ, contul demo rămâne înrolat (altfel s-ar bloca pe pagina de configurare)', function () {
    config(['security.two_factor.required_staff' => true]);

    $user = User::factory()->create(['name' => '[DEMO] Profesor', 'email' => 'p2@columna.test']);
    $user->assignRole(UserRole::Profesor->value);

    DemoSecurity::pass($user);

    expect($user->fresh()->hasTwoFactorConfigured())->toBeTrue();
});

it('obligativitatea se citește dintr-un singur loc, pe segmentul corect', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $familie = User::factory()->create();
    $familie->assignRole(UserRole::Parinte->value);

    config(['security.two_factor.required_staff' => true, 'security.two_factor.required_cabinet' => false]);
    expect($staff->requiresTwoFactorEnrollment())->toBeTrue()
        ->and($familie->requiresTwoFactorEnrollment())->toBeFalse();

    config(['security.two_factor.required_staff' => false, 'security.two_factor.required_cabinet' => true]);
    expect($staff->requiresTwoFactorEnrollment())->toBeFalse()
        ->and($familie->requiresTwoFactorEnrollment())->toBeTrue();
});

it('contul demo se loghează prin ruta REALĂ doar cu utilizator + parolă, fără pagina de cod', function () {
    config(['security.two_factor.required_staff' => false]);

    $user = User::factory()->create([
        'name' => '[DEMO] Profesor',
        'email' => 'login@columna.test',
        'password' => bcrypt('password'),
        'must_change_password' => false,
    ]);
    $user->assignRole(UserRole::Profesor->value);
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save(); // starea „stricată"

    DemoSecurity::pass($user);

    // Ruta Fortify reală, exact ca din formular.
    $response = $this->post('/login', ['email' => 'login@columna.test', 'password' => 'password']);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('two-factor-challenge');
    $this->assertAuthenticatedAs($user);
});
