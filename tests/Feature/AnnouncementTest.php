<?php

use App\Actions\BroadcastAnnouncement;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
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

it('fluxul: publicatele cu bară de citire, ciornele în coada lor', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $published = Announcement::factory()->create(['title' => 'Orar modificat luni', 'body' => 'Detalii în cabinet.']);
    app(BroadcastAnnouncement::class)->publish($published);
    $parent->notifications()->update(['read_at' => now()]);

    $draft = Announcement::factory()->create(['title' => 'Colectă de rechizite', 'body' => 'În pregătire.']);

    $this->actingAs($director);

    // Coada implicită: publicatele, cu progresul citirii; ciorna NU e aici.
    Livewire::test(ListAnnouncements::class)
        ->assertSee('Orar modificat luni')
        ->assertSee(__('panel.announcements.read_progress', ['read' => 1, 'total' => 1]))
        ->assertSee('100%')
        ->assertDontSee('Colectă de rechizite');

    // Coada ciornelor: doar ciorna, cu marcaj și scurtătură de editare.
    Livewire::test(ListAnnouncements::class, ['stateParam' => 'ciorne'])
        ->assertSee('Colectă de rechizite')
        ->assertSee(__('panel.forms.announcement.draft'))
        ->assertSee("announcements/{$draft->id}/edit")
        ->assertDontSee('Orar modificat luni');
});

it('fișa arată pâlnia difuzării cu defalcarea pe roluri; publicatul nu mai are Publică/Editează', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);

    $announcement = Announcement::factory()->create(['title' => 'Ședință generală', 'body' => 'Joi, 18:00, în aulă.']);
    app(BroadcastAnnouncement::class)->publish($announcement);

    // Părintele citește, elevul nu — defalcarea trebuie să le despartă.
    $parent->notifications()->update(['read_at' => now()]);

    expect($announcement->refresh()->deliveredCount())->toBe(2)
        ->and($announcement->readCount())->toBe(1)
        ->and($announcement->readPercent())->toBe(50)
        ->and($announcement->readBreakdown())->toBe([
            UserRole::Elev->value => ['delivered' => 1, 'read' => 0],
            UserRole::Parinte->value => ['delivered' => 1, 'read' => 1],
        ]);

    $this->actingAs($director);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertSee('Joi, 18:00, în aulă.')
        ->assertSee(__('panel.announcements.broadcast'))
        ->assertSee(__('panel.announcements.role_parinte'))
        ->assertDontSee(__('panel.forms.announcement.publish.heading'));
});

it('ciorna se publică din fișă, cu numărul real de destinatari în confirmare', function () {
    Notification::fake();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $draft = Announcement::factory()->create(['title' => 'Program scurtat', 'body' => 'Vineri, 4 ore.']);

    $this->actingAs($director);

    Livewire::test(ViewAnnouncement::class, ['record' => $draft->id])
        ->assertSee(__('panel.forms.announcement.publish.label'))
        ->assertSee(__('panel.announcements.draft_hint'))
        ->callAction('publish')
        ->assertNotified();

    expect($draft->refresh()->isPublished())->toBeTrue()
        ->and($draft->recipients_count)->toBe(1);

    Notification::assertSentTo($parent, CatalogNotification::class);
});

it('după creare, autorul aterizează pe FIȘĂ — recitește, apoi publică', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);

    $component = Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'title' => 'Anunț nou de probă',
            'body' => 'Conținutul anunțului.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Announcement::query()->where('title', 'Anunț nou de probă')->firstOrFail();

    $component->assertRedirect(AnnouncementResource::getUrl('view', ['record' => $created]));

    expect($created->isPublished())->toBeFalse()
        ->and($created->author_user_id)->toBe($director->id);
});

it('fișa anunțului e interzisă rolurilor fără drept de publicare', function () {
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $announcement = Announcement::factory()->create(['title' => 'Intern', 'body' => 'corp']);

    $this->actingAs($profesor)
        ->get("/admin/announcements/{$announcement->id}")
        ->assertForbidden();
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
