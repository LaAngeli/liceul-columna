<?php

/**
 * Meniul mobil al panoului (audit responsiv 2026-07-21): sidebar-ul deschis acoperă tot ecranul,
 * iar închiderea stă în ACEEAȘI bară cu logo-ul (render hook SIDEBAR_LOGO_AFTER) — X-ul implicit
 * din topbar rămâne acoperit de sidebar-ul full-width. Testul fixează prezența butonului (cu
 * legătura Alpine către $store.sidebar.close) și cheile de limbă.
 */

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('sidebar-ul panoului conține butonul mobil de închidere, legat de store-ul Alpine', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($admin);

    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('fi-sidebar-mobile-close-btn')
        ->and($html)->toContain('$store.sidebar.close()')
        ->and($html)->toContain(__('panel.nav.close_menu'));
});

it('eticheta de închidere există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        expect(__('panel.nav.close_menu'))->not->toBe('panel.nav.close_menu', "Cheia lipsește pe {$locale}");
    }

    app()->setLocale('ro');
});
