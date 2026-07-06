<?php

use App\Actions\BroadcastAnnouncement;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function lotDUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

// ─── M-8: gating uniform „doar familie" pe cabinet ──────────────────────────────────────

it('personalul e redirecționat de la paginile de cabinet (mesaje/notificări), familia intră', function () {
    $staff = lotDUser(UserRole::AdministratorTehnic->value);

    actingAs($staff)->get('/cabinet/mesaje')->assertRedirect();
    actingAs($staff)->get('/cabinet/notificari')->assertRedirect();
    actingAs($staff)->get('/cabinet/notificari/setari')->assertRedirect();

    $parent = lotDUser(UserRole::Parinte->value);
    actingAs($parent)->get('/cabinet/mesaje')->assertOk();
});

it('vizualizarea profilului unui elev rămâne accesibilă personalului (rută exceptată)', function () {
    $student = Student::factory()->create();
    $teacher = lotDUser(UserRole::Diriginte->value);

    // Nu e redirecționat de middleware-ul „doar familie"; gating-ul propriu (scoped) decide accesul.
    $response = actingAs($teacher)->get("/cabinet/elev/{$student->id}");
    expect($response->getStatusCode())->not->toBe(302);
});

// ─── M-10: cererea de motivare nu acceptă perioade în viitor ─────────────────────────────

it('cererea de motivare cu perioadă în VIITOR e respinsă', function () {
    $student = Student::factory()->create();
    $parent = lotDUser(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    actingAs($parent)
        ->from("/cabinet/elev/{$student->id}")
        ->post("/cabinet/elev/{$student->id}/motivare", [
            'reason' => 'Programare medicală',
            'period_start' => now()->addWeek()->toDateString(),
            'period_end' => now()->addWeek()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors(['period_start', 'period_end']);
});

// ─── M-7: anunțul publicat e blocat la editare/ștergere ─────────────────────────────────

it('anunțul PUBLICAT nu mai poate fi editat/șters; cel nepublicat da', function () {
    actingAs(lotDUser(UserRole::Director->value));

    $draft = Announcement::factory()->create(['published_at' => null]);
    $published = Announcement::factory()->create(['published_at' => now()]);

    expect(AnnouncementResource::canEdit($draft))->toBeTrue()
        ->and(AnnouncementResource::canDelete($draft))->toBeTrue()
        ->and(AnnouncementResource::canEdit($published))->toBeFalse()
        ->and(AnnouncementResource::canDelete($published))->toBeFalse();
});

// ─── S-8: difuzarea anunțului e idempotentă ─────────────────────────────────────────────

it('re-publicarea unui anunț deja difuzat nu retrimite notificarea', function () {
    Notification::fake();

    $family = lotDUser(UserRole::Parinte->value);
    $announcement = Announcement::factory()->create(['published_at' => null]);

    $action = new BroadcastAnnouncement;
    $action->publish($announcement); // prima difuzare
    $action->publish($announcement->fresh()); // a doua — trebuie ignorată

    Notification::assertSentToTimes($family, \App\Notifications\CatalogNotification::class, 1);
});
