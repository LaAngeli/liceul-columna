<?php

use App\Models\User;

/**
 * Curățarea setărilor de notificări de canalele dezafectate (messenger/whatsapp) după scoaterea lor
 * din NotificationChannel — contactele și matricea de preferințe rămân doar cu canale valide.
 */
it('elimină canalele dezafectate din contacte și preferințe', function () {
    $user = User::factory()->create([
        'notification_contacts' => ['telegram' => 'tg', 'viber' => 'vb', 'messenger' => 'm', 'whatsapp' => 'w'],
        'notification_preferences' => [
            'new_grade' => ['cabinet', 'messenger'],
            'new_absence' => ['whatsapp', 'email'],
            'new_homework' => ['whatsapp'],
        ],
    ]);

    $this->artisan('app:prune-notification-channels')->assertSuccessful();

    $user->refresh();

    expect($user->notification_contacts)->toBe(['telegram' => 'tg', 'viber' => 'vb'])
        ->and($user->notification_preferences)->toBe([
            'new_grade' => ['cabinet'],
            'new_absence' => ['email'],
            'new_homework' => [],
        ]);
});

it('nu atinge un utilizator deja curat (idempotent)', function () {
    $user = User::factory()->create([
        'notification_contacts' => ['telegram' => 'tg'],
        'notification_preferences' => ['new_grade' => ['cabinet', 'email']],
    ]);

    $this->artisan('app:prune-notification-channels')->assertSuccessful();

    $user->refresh();

    expect($user->notification_contacts)->toBe(['telegram' => 'tg'])
        ->and($user->notification_preferences)->toBe(['new_grade' => ['cabinet', 'email']]);
});
