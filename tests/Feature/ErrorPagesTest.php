<?php

/**
 * PAGINILE DE EROARE (cerința beneficiarului, 01.08.2026): personalizate peste tot, cu DOUĂ
 * direcții de reluare a drumului — „Panoul meu" (dashboard-ul potrivit rolului) și „Pagina
 * principală website".
 *
 * Două randări, un singur limbaj vizual:
 *  - SITE PUBLIC → pagina Inertia `public/error` (bogată, cu carduri de explorare);
 *  - ZONELE AUTENTIFICATE + orice rută ne-Inertia → Blade-ul standalone `errors/columna`.
 *
 * Garda-cheie: `Route::fallback()` face 404-ul să treacă prin middleware-ul `web`, deci
 * SESIUNEA e pornită și pagina știe cine e utilizatorul (altfel un director autentificat
 * primea „Autentificare" în loc de „Panoul meu").
 */

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->withoutVite();
});

/** Cont de staff, trecut de lanțul de onboarding (parolă/consimțământ nu sunt subiectul aici). */
function errorPageStaff(UserRole $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole($role->value);

    if (in_array($role, [UserRole::Profesor, UserRole::Diriginte], true)) {
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    return $user;
}

// ─── Zonele autentificate: Blade-ul brand-uit ───────────────────────────────────────────

it('404 în panou: pagina brand-uită, nu cea implicită Laravel', function () {
    $this->actingAs(errorPageStaff(UserRole::Director))
        ->get('/admin/ruta-care-nu-exista')
        ->assertNotFound()
        ->assertSee(__('site.error_page.status.404.title'))
        // Amprenta paginii proprii: emblema de brand + numeralul.
        ->assertSee('columna-horizontal-white.png', escape: false)
        ->assertSee('class="numeral"', escape: false);
});

it('404 în cabinet: aceeași pagină brand-uită', function () {
    $parent = User::factory()->create(['must_change_password' => false]);
    $parent->assignRole(UserRole::Parinte->value);

    $this->actingAs($parent)
        ->get('/cabinet/ruta-care-nu-exista')
        ->assertNotFound()
        ->assertSee(__('site.error_page.status.404.title'));
});

// ─── Cele DOUĂ direcții ─────────────────────────────────────────────────────────────────

it('staff-ul primește „Panoul meu" spre /admin + „Pagina principală website"', function () {
    $this->actingAs(errorPageStaff(UserRole::Profesor))
        ->get('/admin/ruta-care-nu-exista')
        ->assertNotFound()
        ->assertSee(__('site.error_page.dashboard'))
        ->assertSee('href="/admin"', escape: false)
        ->assertSee(__('site.error_page.website'));
});

it('familia primește „Panoul meu" spre cabinet, nu spre panoul staff', function () {
    $student = User::factory()->create(['must_change_password' => false]);
    $student->assignRole(UserRole::Elev->value);
    Student::factory()->create(['user_id' => $student->id]);

    $response = $this->actingAs($student)->get('/admin/ruta-care-nu-exista');

    $response->assertNotFound()
        ->assertSee(__('site.error_page.dashboard'))
        ->assertSee(route('dashboard'), escape: false);

    expect($response->getContent())->not->toContain('href="/admin"');
});

it('vizitatorul anonim primește „Autentificare" în locul panoului', function () {
    $this->get('/admin/ruta-care-nu-exista')
        ->assertNotFound()
        ->assertSee(__('site.error_page.login'))
        ->assertSee(route('login'), escape: false)
        ->assertDontSee(__('site.error_page.dashboard'));
});

// ─── Fallback-ul: 404-ul trece prin sesiune, dar RĂMÂNE 404 ─────────────────────────────

it('fallback-ul nu schimbă codul de răspuns și nu inventează rute', function () {
    $this->get('/o/ruta/adanca/inexistenta')->assertNotFound();
    $this->get('/admin/adanc/inexistent')->assertNotFound();
});

// ─── Site public: pagina Inertia, cu aceleași două direcții ─────────────────────────────

it('404 public randează pagina Inertia dedicată (nu Blade-ul de panou)', function () {
    $this->get('/pagina-publica-inexistenta')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/error')
            ->where('status', 404));
});

// ─── Limba urmează utilizatorul, chiar fără middleware de rută ──────────────────────────

it('pagina de panou vorbește limba contului (middleware-ul de grup nu rulează pe 404)', function () {
    $user = errorPageStaff(UserRole::Director);
    $user->forceFill(['locale' => 'ru'])->save();

    $this->actingAs($user)
        ->get('/admin/ruta-care-nu-exista')
        ->assertNotFound()
        ->assertSee(trans('site.error_page.status.404.title', [], 'ru'))
        ->assertSee('lang="ru"', escape: false);
});

// ─── Celelalte coduri folosesc aceeași pagină ───────────────────────────────────────────

it('403 în zonele autentificate folosește aceeași pagină brand-uită', function () {
    // Administratorul tehnic nu are acces la datele academice → 403 pe o resursă de catalog.
    $this->actingAs(errorPageStaff(UserRole::AdministratorTehnic))
        ->get('/admin/students')
        ->assertForbidden()
        ->assertSee(__('site.error_page.status.403.title'))
        ->assertSee(__('site.error_page.website'));
});
