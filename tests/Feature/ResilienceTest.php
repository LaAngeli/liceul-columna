<?php

use App\Enums\NotificationType;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Artisan;

/**
 * Reziliență (spec §8 / #41): notificările reîncearcă livrarea, iar backupul e programat în scheduler.
 */
it('notificarea de catalog reîncearcă livrarea eșuată', function () {
    $notification = new CatalogNotification(NotificationType::NewAbsence);

    expect($notification->tries)->toBeGreaterThanOrEqual(3)
        ->and($notification->backoff())->not->toBeEmpty();
});

it('backupul zilnic + curățarea sunt programate în scheduler', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('backup:run')
        ->and($output)->toContain('backup:clean');
});
