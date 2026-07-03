<?php

use App\Actions\SendTwoFactorEmailCode;
use App\Actions\VerifyTwoFactorEmailCode;
use App\Enums\UserRole;
use App\Models\TwoFactorEmailCode;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Spatie\Permission\Models\Role;

/**
 * 2FA pe EMAIL: activare (inclusiv fluxul „adaugă & verifică email" pentru conturile fără
 * adresă — majoritatea celor migrate) + challenge-ul la login pe handshake-ul Fortify.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function emailUser(?string $email = 'parinte2fa@columna.test'): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole(UserRole::Parinte->value);

    return $user;
}

/** Sesiune cu parola confirmată (rutele de activare stau sub password.confirm). */
function confirmedSession(): array
{
    return ['auth.password_confirmed_at' => time()];
}

/** Cod OTP cunoscut („123456") însămânțat direct — nu putem citi codul din emailul trimis. */
function seedEmailCode(User $user, array $overrides = []): TwoFactorEmailCode
{
    return TwoFactorEmailCode::factory()->create(array_merge(['user_id' => $user->id], $overrides));
}

it('trimite codul de activare pe emailul contului', function () {
    Notification::fake();
    $user = emailUser();

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.send'))
        ->assertSessionHas('status', 'two-factor-email-code-sent');

    expect(TwoFactorEmailCode::query()->where('user_id', $user->id)->exists())->toBeTrue();
    Notification::assertSentOnDemand(TwoFactorCodeNotification::class);
});

it('contul fără email primește codul pe adresa NOUĂ (pending), iar confirmarea o comite verificată', function () {
    Notification::fake();
    $user = emailUser(null);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.send'), ['email' => 'adresa-noua@columna.test'])
        ->assertSessionHas('status', 'two-factor-email-code-sent');

    expect(TwoFactorEmailCode::query()->where('user_id', $user->id)->value('pending_email'))
        ->toBe('adresa-noua@columna.test');

    // Codul real e necunoscut (doar hash) — îl înlocuim cu unul cunoscut, păstrând pending-ul.
    TwoFactorEmailCode::query()->where('user_id', $user->id)->update(['code_hash' => hash('sha256', '123456')]);

    $this->post(route('two-factor-email.confirm'), ['code' => '123456'])
        ->assertSessionHas('status', 'two-factor-email-enabled');

    $user->refresh();
    expect($user->email)->toBe('adresa-noua@columna.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->two_factor_email_enabled_at)->not->toBeNull()
        ->and($user->usesEmailTwoFactor())->toBeTrue();
});

it('contul fără email NU poate trimite fără să dea o adresă', function () {
    $user = emailUser(null);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.send'))
        ->assertSessionHasErrors('email');
});

it('adresa nouă trebuie să fie unică între utilizatori', function () {
    emailUser('ocupat@columna.test');
    $user = emailUser(null);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.send'), ['email' => 'ocupat@columna.test'])
        ->assertSessionHasErrors('email');
});

it('retrimiterea în fereastra de cooldown e refuzată', function () {
    Notification::fake();
    $user = emailUser();

    $this->actingAs($user)->withSession(confirmedSession());
    $this->post(route('two-factor-email.send'))->assertSessionHas('status', 'two-factor-email-code-sent');
    $this->post(route('two-factor-email.send'))->assertSessionHasErrors('email');
});

it('activarea cere parola confirmată (password.confirm)', function () {
    $user = emailUser();

    $response = $this->actingAs($user)->post(route('two-factor-email.send'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('confirm-password');
});

it('codul greșit incrementează încercările și nu activează', function () {
    $user = emailUser();
    $row = seedEmailCode($user);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.confirm'), ['code' => '999999'])
        ->assertSessionHasErrors('code');

    expect($row->refresh()->attempts)->toBe(1)
        ->and($user->refresh()->two_factor_email_enabled_at)->toBeNull();
});

it('codul expirat e refuzat', function () {
    $user = emailUser();
    seedEmailCode($user, ['expires_at' => now()->subMinute()]);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

it('după prea multe încercări, chiar și codul corect e refuzat', function () {
    $user = emailUser();
    seedEmailCode($user, ['attempts' => VerifyTwoFactorEmailCode::MAX_ATTEMPTS]);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

it('codul e single-use — rândul dispare la verificare reușită', function () {
    $user = emailUser();
    seedEmailCode($user);

    $this->actingAs($user)->withSession(confirmedSession())
        ->post(route('two-factor-email.confirm'), ['code' => '123456']);

    expect(TwoFactorEmailCode::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('dezactivarea oprește 2FA pe email fără să atingă adresa contului', function () {
    $user = emailUser();
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save();

    $this->actingAs($user)->withSession(confirmedSession())
        ->delete(route('two-factor-email.destroy'))
        ->assertSessionHas('status', 'two-factor-email-disabled');

    $user->refresh();
    expect($user->two_factor_email_enabled_at)->toBeNull()
        ->and($user->email)->toBe('parinte2fa@columna.test');
});

it('utilizatorul cu 2FA pe email e provocat la login (pipeline-ul extins)', function () {
    $user = emailUser();
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save();

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirect('/two-factor-challenge');
    $this->assertGuest('web');
});

it('challenge-ul email: pagina primește metoda + emailul mascat, trimiterea generează cod, codul valid autentifică', function () {
    Notification::fake();
    $user = emailUser();
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    // Pagina de challenge cunoaște metoda (email) + adresa mascată.
    $this->get('/two-factor-challenge')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/two-factor-challenge')
            ->where('method', 'email')
            ->where('maskedEmail', 'pa***@co***.test'));

    // Trimiterea codului către utilizatorul provocat (pre-autentificare).
    $this->post(route('two-factor-email.challenge.send'))
        ->assertSessionHas('status', 'two-factor-email-code-sent');
    Notification::assertSentOnDemand(TwoFactorCodeNotification::class);

    // Verificarea cu un cod cunoscut finalizează autentificarea și redirecționează pe rol.
    TwoFactorEmailCode::query()->where('user_id', $user->id)->update(['code_hash' => hash('sha256', '123456')]);

    $this->post(route('two-factor-email.challenge.verify'), ['code' => '123456'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
});

it('challenge-ul email cu cod greșit NU autentifică', function () {
    $user = emailUser();
    $user->forceFill(['two_factor_email_enabled_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    seedEmailCode($user);

    $this->post(route('two-factor-email.challenge.verify'), ['code' => '654321'])
        ->assertSessionHasErrors('code');
    $this->assertGuest('web');
});

it('endpoint-urile de challenge fără handshake (login.id) redirecționează la login', function () {
    $this->post(route('two-factor-email.challenge.send'))->assertRedirect(route('login'));
    $this->post(route('two-factor-email.challenge.verify'), ['code' => '123456'])->assertRedirect(route('login'));
});

it('TOTP are prioritate când ambele metode sunt active', function () {
    $user = emailUser();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_email_enabled_at' => now()])->save();

    expect($user->refresh()->twoFactorChallengeMethod())->toBe('totp');
});

it('trimiterea sărită de cooldown funcționează prin acțiune (unitar)', function () {
    Notification::fake();
    $user = emailUser();

    $sender = app(SendTwoFactorEmailCode::class);
    expect($sender->execute($user))->toBeTrue()
        ->and($sender->execute($user))->toBeFalse(); // cooldown

    $this->travel(SendTwoFactorEmailCode::COOLDOWN_SECONDS + 1)->seconds();
    expect($sender->execute($user))->toBeTrue();
});
