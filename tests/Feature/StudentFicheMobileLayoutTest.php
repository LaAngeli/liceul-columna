<?php

/**
 * Fișa elevului pe mobil (audit responsiv 2026-07-21):
 *  - tabelele relation manager-elor NU mai repetă coloana proprietarului („Elev" pe fișa lui),
 *    care ocupa 122 din cele 343 de puncte disponibile și împingea tabelul în scroll orizontal;
 *  - bara de taburi (șase pe fișa elevului) primește săgeți de derulare — vendorul lăsa doar
 *    scrollbar-ul nativ, invizibil pe touch.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Students\RelationManagers\AcademicRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);

    $this->student = Student::factory()->create();
});

it('tabelele fișei nu mai repetă coloana „Elev" — e chiar elevul fișei', function (string $relationManager) {
    $columns = Livewire::test($relationManager, [
        'ownerRecord' => $this->student,
        'pageClass' => ViewStudent::class,
    ])->instance()->getTable()->getColumns();

    expect(array_keys($columns))->not->toContain('student.full_name');
})->with([
    GradesRelationManager::class,
    AbsencesRelationManager::class,
    AcademicRecordsRelationManager::class,
]);

it('disciplina se poate înfășura, ca tabelul să încapă pe lățimea ecranului', function () {
    $columns = Livewire::test(GradesRelationManager::class, [
        'ownerRecord' => $this->student,
        'pageClass' => ViewStudent::class,
    ])->instance()->getTable()->getColumns();

    expect($columns['subject.name']->canWrap())->toBeTrue();
});

it('bara de taburi primește săgeți de derulare, cu etichete în toate cele trei limbi', function () {
    $html = $this->get(ViewStudent::getUrl(['record' => $this->student]))->assertOk()->getContent();

    expect($html)->toContain('fi-tabs-scroller')
        ->and($html)->toContain('fi-tabs-scroll-btn-start')
        ->and($html)->toContain('fi-tabs-scroll-btn-end');

    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (['panel.nav.tabs_scroll_start', 'panel.nav.tabs_scroll_end'] as $key) {
            expect(__($key))->not->toBe($key, "Cheia {$key} lipsește pe {$locale}");
        }
    }

    app()->setLocale('ro');
});
