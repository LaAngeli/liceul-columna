<?php

/**
 * Secțiunea Utilizatori — reorganizată (2026-07-16): navigator pe ROLURI (carduri cu numărători
 * și semnale) → lista contextului; formular pe secțiuni cu parolă TEMPORARĂ generată, asocierea
 * fișei potrivite rolului (profesor/elev/copiii părintelui), starea contului (activ/suspendat) și
 * trimiterea credențialelor pe e-mail. Fluxul complet e verificat aici: creare pe fiecare rol,
 * asocieri, autentificare cu parola temporară, suspendare (login + sesiuni), resetare de parolă.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

// ─── Navigatorul pe roluri ───────────────────────────────────────────────────────────────

it('aterizarea arată TOATE rolurile drept carduri, cu numărători și semnale', function () {
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $suspendat = User::factory()->create(['suspended_at' => now()]);
    $suspendat->assignRole(UserRole::Profesor->value);

    User::factory()->create(); // cont rătăcit, fără rol

    $page = Livewire::test(ListUsers::class)->instance();
    $cards = collect($page->roleCards());

    // Toate cele 9 roluri + bucket-ul „Fără rol" (există un cont rătăcit).
    expect($cards->pluck('id')->all())->toBe([...UserRole::values(), 'fara-rol']);

    $profCard = $cards->firstWhere('id', UserRole::Profesor->value);
    expect($profCard['stats'][0])->toContain('2')
        ->and($profCard['badge'])->toContain('1');
});

it('contextul unui rol arată doar conturile lui; un rol inexistent nu deschide context', function () {
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);

    $component = Livewire::test(ListUsers::class)->call('openRole', UserRole::Elev->value);

    $component->assertCanSeeTableRecords([$elev])
        ->assertCanNotSeeTableRecords([$this->director]);

    expect(Livewire::withQueryParams(['rol' => 'rol-inventat'])->test(ListUsers::class)->instance()->activeRole())
        ->toBeNull();
});

// ─── Crearea conturilor: fiecare rol manageabil, parolă temporară, autentificare ─────────

it('directorul creează conturi pentru fiecare rol pe care îl poate atribui', function () {
    foreach ($this->director->manageableRoleValues() as $index => $role) {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Cont '.$role,
                'username' => 'cont-'.$role,
                'role' => $role,
                'password' => 'Parola-Temp-'.$index,
                'account_status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('username', 'cont-'.$role)->sole();

        expect($user->getRoleNames()->all())->toBe([$role])
            ->and($user->must_change_password)->toBeTrue()
            ->and(Hash::check('Parola-Temp-'.$index, $user->password))->toBeTrue()
            ->and($user->isSuspended())->toBeFalse();
    }
});

it('contul nou se autentifică cu parola temporară și e dus la schimbarea parolei', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Elev Onboarding',
            'username' => 'elev.onboarding',
            'role' => UserRole::Elev->value,
            'password' => 'Temp-Parola-9',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    auth('web')->logout();

    // Autentificare cu utilizator + parola temporară → sesiune deschisă…
    post('/login', ['email' => 'elev.onboarding', 'password' => 'Temp-Parola-9']);
    $this->assertAuthenticated();

    // …și orice pagină protejată redirecționează spre schimbarea OBLIGATORIE a parolei.
    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
});

it('rolul din contextul navigatorului pre-completează formularul, iar un rol neatribuibil nu', function () {
    Livewire::withQueryParams(['rol' => UserRole::Profesor->value])
        ->test(CreateUser::class)
        ->assertFormSet(['role' => UserRole::Profesor->value]);

    // Directorul nu poate atribui super-admin → default-ul se ignoră.
    Livewire::withQueryParams(['rol' => UserRole::Admin->value])
        ->test(CreateUser::class)
        ->assertFormSet(['role' => null]);
});

// ─── Asocierile per rol ──────────────────────────────────────────────────────────────────

it('contul de elev se leagă de fișa lui, iar re-legarea eliberează fișa veche', function () {
    $ficheA = Student::factory()->create();
    $ficheB = Student::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Elev Legat',
            'username' => 'elev.legat',
            'role' => UserRole::Elev->value,
            'student_id' => $ficheA->id,
            'password' => 'Temp-Parola-1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'elev.legat')->sole();
    expect($ficheA->fresh()->user_id)->toBe($user->id);

    // Re-legarea pe fișa B eliberează fișa A.
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['student_id' => $ficheB->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ficheA->fresh()->user_id)->toBeNull()
        ->and($ficheB->fresh()->user_id)->toBe($user->id);
});

it('contul de profesor se leagă de fișa de profesor, iar schimbarea rolului o eliberează', function () {
    $fiche = Teacher::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Prof Legat',
            'username' => 'prof.legat',
            'role' => UserRole::Profesor->value,
            'teacher_id' => $fiche->id,
            'password' => 'Temp-Parola-2',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'prof.legat')->sole();
    expect($fiche->fresh()->user_id)->toBe($user->id);

    // Rolul devine administrativ → fișa pedagogică se dezleagă (perimetrul nu mai are sens).
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['role' => UserRole::PrimVicedirector->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fiche->fresh()->user_id)->toBeNull();
});

it('părintele primește copiii selectați (pivotul guardian_student)', function () {
    $copil1 = Student::factory()->create();
    $copil2 = Student::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Părinte Nou',
            'username' => 'parinte.nou',
            'role' => UserRole::Parinte->value,
            'guardian_student_ids' => [$copil1->id, $copil2->id],
            'password' => 'Temp-Parola-3',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'parinte.nou')->sole();

    expect($user->students()->pluck('students.id')->sort()->values()->all())
        ->toBe(collect([$copil1->id, $copil2->id])->sort()->values()->all());
});

// ─── Credențiale pe e-mail ───────────────────────────────────────────────────────────────

it('trimite credențialele pe e-mail când opțiunea e bifată, cu parola temporară în mesaj', function () {
    Notification::fake();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Cont Cu Email',
            'username' => 'cont.cu.email',
            'email' => 'cont@test.columna',
            'role' => UserRole::Profesor->value,
            'password' => 'Temp-Parola-4',
            'send_credentials' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'cont.cu.email')->sole();

    Notification::assertSentTo($user, TemporaryCredentials::class, function (TemporaryCredentials $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return str_contains(implode(' ', array_map(strval(...), $mail->introLines)), 'Temp-Parola-4');
    });
});

it('opțiunea de trimitere fără e-mail completat e respinsă', function () {
    Notification::fake();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Cont Fara Email',
            'username' => 'cont.fara.email',
            'email' => null,
            'role' => UserRole::Elev->value,
            'password' => 'Temp-Parola-5',
            'send_credentials' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);

    Notification::assertNothingSent();
});

// ─── Starea contului: suspendare ─────────────────────────────────────────────────────────

it('contul suspendat nu se poate autentifica și primește mesajul dedicat', function () {
    $user = User::factory()->create([
        'username' => 'suspendat.login',
        'password' => 'Parola-Activa-1',
        'suspended_at' => now(),
    ]);
    $user->assignRole(UserRole::Profesor->value);

    auth('web')->logout();

    post('/login', ['email' => 'suspendat.login', 'password' => 'Parola-Activa-1'])
        ->assertSessionHasErrors(['email' => __('auth.suspended')]);

    $this->assertGuest();
});

it('sesiunea existentă a unui cont suspendat e închisă la următoarea cerere', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);

    actingAs($user);

    // Contul e suspendat ÎN TIMPUL sesiunii → următoarea cerere îl deconectează.
    $user->forceFill(['suspended_at' => now()])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('suspendarea și reactivarea se fac din listă, dar nu pe propriul cont', function () {
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $component = Livewire::test(ListUsers::class)->call('openRole', UserRole::Profesor->value);

    $component->callTableAction('toggleSuspension', $profesor);
    expect($profesor->fresh()->isSuspended())->toBeTrue();

    $component->callTableAction('toggleSuspension', $profesor);
    expect($profesor->fresh()->isSuspended())->toBeFalse();

    // Propriul cont: acțiunea nu există.
    Livewire::test(ListUsers::class)->call('openRole', UserRole::Director->value)
        ->assertTableActionHidden('toggleSuspension', $this->director);
});

it('suspendarea din formular e refuzată pe propriul cont (garda de server)', function () {
    Livewire::test(EditUser::class, ['record' => $this->director->getRouteKey()])
        ->fillForm(['account_status' => 'suspended'])
        ->call('save')
        ->assertHasFormErrors();

    expect($this->director->fresh()->isSuspended())->toBeFalse();
});

// ─── Parolă nouă din listă + ierarhie ────────────────────────────────────────────────────

it('parola temporară nouă se setează din listă și forțează schimbarea la login', function () {
    $elev = User::factory()->create(['must_change_password' => false]);
    $elev->assignRole(UserRole::Elev->value);

    Livewire::test(ListUsers::class)->call('openRole', UserRole::Elev->value)
        ->callTableAction('newPassword', $elev, ['password' => 'Temp-Resetata-7'])
        ->assertHasNoTableActionErrors();

    $elev->refresh();
    expect(Hash::check('Temp-Resetata-7', $elev->password))->toBeTrue()
        ->and($elev->must_change_password)->toBeTrue();
});

it('operațiunile de cont respectă ierarhia: AO nu operează pe un director', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);

    $altDirector = User::factory()->create();
    $altDirector->assignRole(UserRole::Director->value);

    actingAs($ao);

    Livewire::test(ListUsers::class)->call('openRole', UserRole::Director->value)
        ->assertTableActionHidden('newPassword', $altDirector)
        ->assertTableActionHidden('toggleSuspension', $altDirector);
});
