<?php

/**
 * Retenția + arhivarea notificărilor (cerința beneficiarului, 2026-07-21): cele CITITE mai vechi de
 * pragul configurabil trec automat în arhivă (nu se șterg); necititele nu se arhivează NICIODATĂ;
 * ștergerea manuală e eliminată pentru toate rolurile (clopoțelul Filament neutralizat pe server);
 * arhiva e consultabilă (căutare, tip, interval, sortare) în cabinet și în pagina de panou; politica
 * de purge există dar e DORMANTĂ (config null).
 */

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Pages\MyNotifications;
use App\Livewire\PanelNotifications;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/** Inserează o notificare database direct (forma payload-ului CatalogNotification). */
function nrInsert(User $user, array $overrides = [], array $data = []): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\Notifications\CatalogNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(array_merge([
            'type' => NotificationType::NewGrade->value,
            'title' => 'Notă nouă la Matematică',
            'body' => 'Elevul a primit nota 9.',
            'url' => null,
            'icon' => 'heroicon-o-academic-cap',
            'format' => 'filament',
        ], $data), JSON_UNESCAPED_UNICODE),
        'read_at' => now()->subDays(40),
        'archived_at' => null,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
        ...$overrides,
    ]);

    return $id;
}

it('arhivarea automată mută DOAR citite mai vechi de prag; necititele nu se ating, indiferent de vechime', function () {
    $user = User::factory()->create();

    $readOld = nrInsert($user);                                                    // citită, 40 zile → se arhivează
    $unreadOld = nrInsert($user, ['read_at' => null]);                             // necitită, 40 zile → NU
    $readFresh = nrInsert($user, ['read_at' => now(), 'created_at' => now()->subDays(5)]); // citită, 5 zile → NU

    $this->artisan('app:archive-notifications')->assertSuccessful();

    expect(DatabaseNotification::query()->whereKey($readOld)->value('archived_at'))->not->toBeNull()
        ->and(DatabaseNotification::query()->whereKey($unreadOld)->value('archived_at'))->toBeNull()
        ->and(DatabaseNotification::query()->whereKey($readFresh)->value('archived_at'))->toBeNull()
        // Arhivarea NU șterge nimic și nu atinge starea de citire.
        ->and(DatabaseNotification::query()->count())->toBe(3)
        ->and(DatabaseNotification::query()->whereKey($readOld)->value('read_at'))->not->toBeNull();
});

it('pragul de arhivare e configurabil fără cod, iar dry-run nu modifică nimic', function () {
    $user = User::factory()->create();
    $read10 = nrInsert($user, ['read_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);

    // Prag implicit (30): nimic de arhivat la 10 zile.
    $this->artisan('app:archive-notifications')->assertSuccessful();
    expect(DatabaseNotification::query()->whereKey($read10)->value('archived_at'))->toBeNull();

    // Prag coborât la 7 din config: dry-run raportează fără să scrie…
    config(['notifications.archive_after_days' => 7]);
    $this->artisan('app:archive-notifications --dry-run')
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();
    expect(DatabaseNotification::query()->whereKey($read10)->value('archived_at'))->toBeNull();

    // …iar rularea reală arhivează.
    $this->artisan('app:archive-notifications')->assertSuccessful();
    expect(DatabaseNotification::query()->whereKey($read10)->value('archived_at'))->not->toBeNull();
});

it('politica de purge e DORMANTĂ implicit și șterge doar când e activată explicit din config', function () {
    $user = User::factory()->create();
    $ancient = nrInsert($user, ['archived_at' => now()->subYears(5), 'created_at' => now()->subYears(6)]);

    // Config implicit (null) → nimic șters, oricât de veche e arhiva.
    $this->artisan('app:archive-notifications')->assertSuccessful();
    expect(DatabaseNotification::query()->whereKey($ancient)->exists())->toBeTrue();

    // Politică activată explicit (3 ani) → arhiva mai veche se șterge.
    config(['notifications.purge_archived_after_years' => 3]);
    $this->artisan('app:archive-notifications')->assertSuccessful();
    expect(DatabaseNotification::query()->whereKey($ancient)->exists())->toBeFalse();
});

it('clopoțelul din panou nu mai poate șterge nimic: X-ul și „Șterge tot" sunt neutralizate pe server', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);

    $active = nrInsert($staff, ['read_at' => null, 'created_at' => now()]);
    $archived = nrInsert($staff, ['archived_at' => now(), 'created_at' => now()->subDays(60)]);

    $this->actingAs($staff);

    $component = Livewire::test(PanelNotifications::class);

    // Evenimentul care ȘTERGEA în componenta standard nu mai ARE receptor: suprascrierea fără
    // #[On] a deconectat listener-ul (Livewire::test semnalează exact asta prin excepție).
    expect(fn () => Livewire::test(PanelNotifications::class)->dispatch('notificationClosed', id: $active))
        ->toThrow(Exception::class, 'Handler for event notificationClosed does not exist');
    expect(DatabaseNotification::query()->whereKey($active)->exists())->toBeTrue();

    // Apel direct al metodelor (apărare în adâncime) → tot fără efect.
    $component->call('removeNotification', $active);
    $component->call('clearNotifications');
    expect(DatabaseNotification::query()->count())->toBe(2);

    // Clopoțelul arată doar activele (arhiva are pagina ei), iar butonul de ștergere e ascuns.
    $ids = $component->instance()->getNotificationsQuery()->pluck('id');
    expect($ids)->toContain($active)->not->toContain($archived)
        ->and($component->instance()->clearNotificationsAction()->isHidden())->toBeTrue();

    // Marcarea ca citită RĂMÂNE funcțională (singura acțiune păstrată).
    $component->dispatch('markedNotificationAsRead', id: $active);
    expect(DatabaseNotification::query()->whereKey($active)->value('read_at'))->not->toBeNull();
});

it('cabinetul separă filele: Recente arată doar activele, Arhiva e filtrabilă și paginată', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $active = nrInsert($parent, ['read_at' => null, 'created_at' => now()]);
    $archivedGrade = nrInsert($parent, ['archived_at' => now()->subDays(2), 'created_at' => now()->subDays(45)]);
    $archivedMessage = nrInsert(
        $parent,
        ['archived_at' => now()->subDays(2), 'created_at' => now()->subDays(90)],
        ['type' => NotificationType::NewMessage->value, 'title' => 'Mesaj de la diriginte', 'body' => 'Vă rugăm să confirmați.'],
    );

    $this->actingAs($parent);

    // Fila Recente: doar activa; numărătorile filelor prezente.
    $this->get('/cabinet/notificari')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/notifications')
            ->where('tab', 'recente')
            ->has('notifications', 1)
            ->where('notifications.0.id', $active)
            ->where('counts.active', 1)
            ->where('counts.archived', 2));

    // Fila Arhivă: ambele arhivate, cu ștampila arhivării + luna de grupare.
    $this->get('/cabinet/notificari?tab=arhiva')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'arhiva')
            ->has('archive.items', 2)
            ->where('archive.items.0.id', $archivedGrade)
            ->whereNot('archive.items.0.archivedAt', null)
            ->whereNot('archive.items.0.month', null)
            ->has('archiveTypes', 2));

    // Căutare text → doar mesajul; filtru pe tip → doar nota; sortare veche-întâi → inversare.
    $this->get('/cabinet/notificari?tab=arhiva&q=diriginte')
        ->assertInertia(fn (Assert $page) => $page
            ->has('archive.items', 1)
            ->where('archive.items.0.id', $archivedMessage));

    $this->get('/cabinet/notificari?tab=arhiva&tip=new_grade')
        ->assertInertia(fn (Assert $page) => $page
            ->has('archive.items', 1)
            ->where('archive.items.0.id', $archivedGrade));

    $this->get('/cabinet/notificari?tab=arhiva&sort=vechi')
        ->assertInertia(fn (Assert $page) => $page
            ->where('archive.items.0.id', $archivedMessage));

    // Interval care exclude ambele → gol, fără eroare; filtru invalid → ignorat blând.
    $this->get('/cabinet/notificari?tab=arhiva&de_la=2030-01-01')
        ->assertInertia(fn (Assert $page) => $page->has('archive.items', 0));
    $this->get('/cabinet/notificari?tab=arhiva&de_la=nu-e-data&tip=tip-inventat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('archive.items', 2));
});

it('cabinetul nu expune nicio rută de ștergere a notificărilor', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'notificari'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->methods())->not->toContain('DELETE');
    }
});

it('pagina „Notificările mele" din panou listează doar propriile notificări, cu arhiva filtrabilă', function () {
    $diriginte = User::factory()->create();
    $diriginte->assignRole(UserRole::Diriginte->value);
    $altStaff = User::factory()->create();
    $altStaff->assignRole(UserRole::Profesor->value);

    $own = nrInsert($diriginte, ['read_at' => null, 'created_at' => now()]);
    $ownArchived = nrInsert(
        $diriginte,
        ['archived_at' => now(), 'created_at' => now()->subDays(50)],
        ['type' => NotificationType::NewMessage->value, 'title' => 'Mesaj vechi arhivat'],
    );
    $foreign = nrInsert($altStaff, ['read_at' => null, 'created_at' => now()]);

    $this->actingAs($diriginte);

    $component = Livewire::test(MyNotifications::class);

    // Fila Recente: doar propria activă (niciodată ale altora).
    $ids = collect($component->instance()->items()->items())->map(fn ($n) => $n->getKey());
    expect($ids)->toContain($own)->not->toContain($foreign)->not->toContain($ownArchived);

    // Fila Arhivă + căutare.
    $component->set('tab', 'arhiva');
    $ids = collect($component->instance()->items()->items())->map(fn ($n) => $n->getKey());
    expect($ids)->toContain($ownArchived)->not->toContain($own);

    $component->set('q', 'inexistent-in-titlu');
    expect($component->instance()->items()->items())->toBeEmpty();

    // Marchează citită — singura mutare permisă; nimic nu dispare.
    $component->call('markRead', $own);
    expect(DatabaseNotification::query()->whereKey($own)->value('read_at'))->not->toBeNull()
        ->and(DatabaseNotification::query()->count())->toBe(3);

    // Pagina răspunde și pe HTTP (navigație reală).
    $this->get('/admin/notificarile-mele')->assertOk();
});

it('etichetele noii pagini și ale arhivei există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.my_notifications.title', $locale))->toBeTrue("Lipsește panel.my_notifications.title [{$locale}]")
            ->and(Lang::hasForLocale('panel.my_notifications.archive_hint', $locale))->toBeTrue("Lipsește archive_hint [{$locale}]")
            ->and(Lang::hasForLocale('site.cabinet.notif_tab_archive', $locale))->toBeTrue("Lipsește site.cabinet.notif_tab_archive [{$locale}]")
            ->and(Lang::hasForLocale('site.cabinet.notif_archive_hint', $locale))->toBeTrue("Lipsește site.cabinet.notif_archive_hint [{$locale}]");
    }
});
