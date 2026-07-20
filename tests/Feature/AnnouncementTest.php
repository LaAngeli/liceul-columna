<?php

use App\Actions\BroadcastAnnouncement;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('resursa Anunțuri e accesibilă conducerii, dar nu profesorului', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $this->actingAs($director)->get('/admin/announcements')->assertOk();
    $this->actingAs($profesor)->get('/admin/announcements')->assertForbidden();
});

it('publicarea unui anunț îl trimite tuturor familiilor, cu announcement_id în payload', function () {
    Notification::fake();

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $announcement = Announcement::factory()->create(['title' => 'Ședință', 'body' => 'Vineri la 18:00']);

    app(BroadcastAnnouncement::class)->publish($announcement);

    expect($announcement->refresh()->published_at)->not->toBeNull()
        ->and($announcement->recipients_count)->toBe(2);

    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::Announcement
            && ($n->meta['announcement_id'] ?? null) === $announcement->id,
    );
    Notification::assertSentTo($elev, CatalogNotification::class);
    Notification::assertNotSentTo($profesor, CatalogNotification::class);
});

it('purge-ul demo șterge anunțurile [DEMO] ȘI notificările lor din inboxurile utilizatorilor REALI', function () {
    // FĂRĂ Notification::fake — testul are nevoie de rândurile reale din `notifications`:
    // broadcast-ul copiază titlul în payload, deci ștergerea anunțului NU curăță inboxurile.
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $demo = Announcement::factory()->create(['title' => '[DEMO] Anunț de test', 'body' => 'corp']);
    $real = Announcement::factory()->create(['title' => 'Anunț real al conducerii', 'body' => 'corp']);

    app(BroadcastAnnouncement::class)->publish($demo);
    app(BroadcastAnnouncement::class)->publish($real);

    expect($parent->notifications()->count())->toBe(2);

    $this->artisan('app:purge-demo-data')->assertSuccessful();

    // Anunțul demo + notificarea lui au dispărut; cel real și notificarea lui au rămas neatinse.
    expect(Announcement::withTrashed()->whereKey($demo->id)->exists())->toBeFalse()
        ->and(Announcement::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and($parent->notifications()->count())->toBe(1)
        ->and($parent->notifications()->first()->data['announcement_id'] ?? null)->toBe($real->id);
});

it('matricea de acces la resursă: conducerea intră, restul rolurilor nu', function (string $role, bool $allowed) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)->get('/admin/announcements');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'super-admin' => [UserRole::Admin->value, true],
    'director' => [UserRole::Director->value, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector->value, true],
    'administrator operațional' => [UserRole::AdministratorOperational->value, true],
    // Tehnicul n-are date academice; dirigintele/profesorul comunică prin mesaje, nu prin broadcast;
    // familia primește anunțurile în cabinet, nu în panou.
    'administrator tehnic' => [UserRole::AdministratorTehnic->value, false],
    'diriginte' => [UserRole::Diriginte->value, false],
    'profesor' => [UserRole::Profesor->value, false],
    'elev' => [UserRole::Elev->value, false],
    'părinte' => [UserRole::Parinte->value, false],
]);
