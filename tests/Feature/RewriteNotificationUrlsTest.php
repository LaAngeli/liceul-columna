<?php

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migrarea de date a țintelor vechi de notificare (`app:rewrite-notification-urls`).
 * Comanda se rulează pe baza LIVE, deci fiecare garanție e pinuită aici: dry-run care nu scrie,
 * remapare corectă per tip, alinierea acțiunii Filament, idempotență și „nu atinge ce nu e al ei".
 */

/** Inserează o notificare cu payload-ul VECHI (ținta = profilul general). */
function legacyNotification(User $user, NotificationType $type, int $studentId, bool $withAction = true): string
{
    $id = (string) Str::uuid();
    $url = "/cabinet/elev/{$studentId}";

    $data = [
        'type' => $type->value,
        'title' => 'Titlu',
        'body' => 'Corp',
        'url' => $url,
        'format' => 'filament',
    ];

    if ($withAction) {
        $data['actions'] = [['name' => 'open', 'label' => 'Deschide', 'url' => $url, 'shouldMarkAsRead' => true]];
    }

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\CatalogNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/** Payload-ul decodat al unei notificări. */
function payload(string $id): array
{
    return json_decode((string) DB::table('notifications')->where('id', $id)->value('data'), true);
}

it('dry-run raportează, dar NU scrie nimic', function () {
    $user = User::factory()->create();
    $id = legacyNotification($user, NotificationType::AbsenceMotivationDecided, 7);

    $this->artisan('app:rewrite-notification-urls')->assertSuccessful();

    expect(payload($id)['url'])->toBe('/cabinet/elev/7');
});

it('remapează fiecare tip la secțiunea lui, inclusiv acțiunea „Deschide" din payload-ul Filament', function () {
    $user = User::factory()->create();

    $cases = [
        [NotificationType::AbsenceMotivationDecided, '/cabinet/absente?copil=7&sectiune=motivari'],
        [NotificationType::NewAbsence, '/cabinet/absente?copil=7&sectiune=registru'],
        [NotificationType::NewGrade, '/cabinet/note?copil=7'],
        [NotificationType::GradeAnnulled, '/cabinet/note?copil=7'],
        [NotificationType::GradeCorrected, '/cabinet/note?copil=7'],
        [NotificationType::ContestationRejected, '/cabinet/elev/7?tab=requests'],
        [NotificationType::CorigentaResult, '/cabinet/elev/7?tab=requests'],
    ];

    $ids = [];
    foreach ($cases as [$type, $expected]) {
        $ids[$type->value] = [legacyNotification($user, $type, 7), $expected];
    }

    $this->artisan('app:rewrite-notification-urls --apply')->assertSuccessful();

    foreach ($ids as [$id, $expected]) {
        $data = payload($id);

        expect($data['url'])->toBe($expected)
            // Ținta duplicată în acțiunea clopoțelului trebuie să urmeze, altfel butonul
            // „Deschide" din panou ar rămâne pe vechea destinație.
            ->and($data['actions'][0]['url'])->toBe($expected);
    }
});

it('NU atinge tipurile a căror destinație a rămas fișa elevului (status_change)', function () {
    $user = User::factory()->create();
    $id = legacyNotification($user, NotificationType::StatusChange, 7);

    $this->artisan('app:rewrite-notification-urls --apply')->assertSuccessful();

    expect(payload($id)['url'])->toBe('/cabinet/elev/7');
});

it('e idempotentă: a doua rulare nu mai găsește nimic', function () {
    $user = User::factory()->create();
    $id = legacyNotification($user, NotificationType::NewGrade, 7);

    $this->artisan('app:rewrite-notification-urls --apply')->assertSuccessful();
    expect(payload($id)['url'])->toBe('/cabinet/note?copil=7');

    // A doua oară: URL-ul nu mai e în forma veche → rămâne neschimbat.
    $this->artisan('app:rewrite-notification-urls --apply')
        ->expectsOutputToContain('Nicio notificare de remapat')
        ->assertSuccessful();

    expect(payload($id)['url'])->toBe('/cabinet/note?copil=7');
});

it('nu atinge notificările fără țintă sau cu altă formă de URL', function () {
    $user = User::factory()->create();

    $noUrl = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $noUrl,
        'type' => 'App\\Notifications\\CatalogNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['type' => NotificationType::NewGrade->value, 'url' => null]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $thread = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $thread,
        'type' => 'App\\Notifications\\CatalogNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['type' => NotificationType::NewMessage->value, 'url' => '/cabinet/mesaje?fir=5']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('app:rewrite-notification-urls --apply')->assertSuccessful();

    expect(payload($noUrl)['url'])->toBeNull()
        ->and(payload($thread)['url'])->toBe('/cabinet/mesaje?fir=5');
});
