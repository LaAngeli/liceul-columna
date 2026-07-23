<?php

/**
 * Contul de acces AL UNEI FIȘE existente (cerința beneficiarului 2026-07-24).
 *
 * Situația reală: importul legacy a adus fișe fără cont (18 profesori, 3 elevi). Fișa lor oferea
 * un singur lucru — „leagă un cont existent" — inutil exact când nu există ce lega, și nefiltrat
 * la profesor (se putea lega un cont de părinte sau unul deja folosit de altă fișă).
 *
 * Regula nouă, verificată aici: contul se CREEAZĂ din fișă, identitatea vine din registru (nu se
 * re-tastează, deci nu poate diverge), iar legarea unui cont orfan rămâne doar ca supapă.
 */

use App\Actions\CreateAccountForFiche;
use App\Enums\UserRole;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->operational = User::factory()->create();
    $this->operational->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->operational);
});

it('fișa de profesor fără cont primește un cont creat DIN ea, cu identitatea din registru', function () {
    $fiche = Teacher::factory()->create(['last_name' => 'Iorga', 'first_name' => 'Alida', 'user_id' => null]);

    $user = app(CreateAccountForFiche::class)->create($fiche, [
        'username' => 'iorga.alida',
        'email' => 'iorga@columna.test',
        'password' => 'Parola-Temp-1',
        'role' => UserRole::Profesor->value,
    ]);

    expect($fiche->fresh()->user_id)->toBe($user->id)
        // Numele NU se tastează: vine din fișă, deci contul și fișa nu pot diverge.
        ->and($user->name)->toBe($fiche->full_name)
        ->and($user->getRoleNames()->all())->toBe([UserRole::Profesor->value])
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('Parola-Temp-1', $user->password))->toBeTrue();
});

it('fișa de elev determină singură rolul contului — nu se poate alege altul', function () {
    $fiche = Student::factory()->create(['user_id' => null]);

    $user = app(CreateAccountForFiche::class)->create($fiche, [
        'username' => 'elev.nou',
        'password' => 'Parola-Temp-2',
        // Rol strecurat: fișa de elev îl ignoră.
        'role' => UserRole::Director->value,
    ]);

    expect($user->getRoleNames()->all())->toBe([UserRole::Elev->value])
        ->and($fiche->fresh()->user_id)->toBe($user->id);
});

it('o fișă care are DEJA cont nu primește un al doilea', function () {
    $existing = User::factory()->create();
    $fiche = Teacher::factory()->create(['user_id' => $existing->id]);

    app(CreateAccountForFiche::class)->create($fiche, [
        'username' => 'al.doilea',
        'password' => 'Parola-Temp-3',
        'role' => UserRole::Profesor->value,
    ]);
})->throws(ValidationException::class);

it('nu se poate atribui un rol din afara ierarhiei celui care creează contul', function () {
    // Administratorul operațional NU administrează conturi de director.
    $fiche = Teacher::factory()->create(['user_id' => null]);

    app(CreateAccountForFiche::class)->create($fiche, [
        'username' => 'rol.interzis',
        'password' => 'Parola-Temp-4',
        'role' => UserRole::Director->value,
    ]);
})->throws(ValidationException::class);

it('utilizatorul propus se derivă din numele fișei și rămâne UNIC', function () {
    $fiche = Teacher::factory()->create(['last_name' => 'Iorga', 'first_name' => 'Alida']);

    expect(CreateAccountForFiche::suggestUsername($fiche))->toBe('iorga.alida');

    User::factory()->create(['username' => 'iorga.alida']);

    expect(CreateAccountForFiche::suggestUsername($fiche))->toBe('iorga.alida2');
});

it('credențialele pleacă pe e-mail doar dacă operatorul a cerut asta', function () {
    Notification::fake();

    $fiche = Teacher::factory()->create(['user_id' => null]);

    $user = app(CreateAccountForFiche::class)->create($fiche, [
        'username' => 'cu.credentiale',
        'email' => 'prof@columna.test',
        'password' => 'Parola-Temp-5',
        'role' => UserRole::Profesor->value,
        'send_credentials' => true,
    ]);

    Notification::assertSentTo($user, TemporaryCredentials::class);
});

it('acțiunea din fișa profesorului creează și leagă contul, cu doar câmpurile de acces', function () {
    $fiche = Teacher::factory()->create(['last_name' => 'Munteanu', 'first_name' => 'Radu', 'user_id' => null]);

    // Acțiunea trăiește într-o componentă de SCHEMA (secțiunea „Cont de acces"), nu în antetul
    // paginii → se adresează prin TestAction::schemaComponent, altfel Filament n-o găsește.
    Livewire::test(EditTeacher::class, ['record' => $fiche->getRouteKey()])
        ->callAction(TestAction::make('createFicheAccount')->schemaComponent('ficheAccountActions'), [
            'role' => UserRole::Profesor->value,
            'username' => 'munteanu.radu',
            'password' => 'Parola-Temp-6',
        ]);

    $user = User::query()->where('username', 'munteanu.radu')->sole();

    expect($fiche->fresh()->user_id)->toBe($user->id)
        ->and($user->name)->toBe('Munteanu Radu');
});

it('la CREARE cu fișă existentă, identitatea nu se mai cere: numele contului vine din fișă', function () {
    $fiche = Teacher::factory()->create(['last_name' => 'Balan', 'first_name' => 'Vera', 'user_id' => null]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => UserForm::FICHE_LINK,
            'teacher_id' => $fiche->id,
            'username' => 'balan.vera',
            'password' => 'Parola-Temp-7',
        ])
        // Fără nume completat manual — formularul nici nu-l mai cere.
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'balan.vera')->sole();

    expect($user->name)->toBe('Balan Vera')
        ->and($fiche->fresh()->user_id)->toBe($user->id);
});

it('rolurile FĂRĂ fișă cer în continuare numele (nu există registru din care să vină)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'role' => UserRole::Parinte->value,
            'username' => 'parinte.fara.nume',
            'password' => 'Parola-Temp-8',
        ])
        ->call('create')
        ->assertHasFormErrors(['last_name', 'first_name']);
});

it('secțiunea de identitate dispare DOAR când fișa existentă e chiar aleasă', function () {
    $page = Livewire::test(CreateUser::class)
        ->fillForm([
            'role' => UserRole::Profesor->value,
            'teacher_fiche_mode' => UserForm::FICHE_LINK,
        ]);

    // Mod „fișă existentă", dar fără fișă aleasă → numele rămâne cerut (altfel n-ar exista sursă).
    expect($page->instance()->form->getRawState()['teacher_id'] ?? null)->toBeNull();

    $page->fillForm(['teacher_id' => Teacher::factory()->create()->id]);

    $page->call('create')->assertHasNoFormErrors(['last_name', 'first_name']);
});
