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
