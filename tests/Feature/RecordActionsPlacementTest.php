<?php

/**
 * Acțiunile care operează pe ÎNREGISTRARE (Ștergere / Ștergere definitivă / Restaurare) stau pe
 * rândul butoanelor formularului, lângă „Salvare"/„Anulare" — nu singure sub titlul paginii
 * (cerința userului 2026-07-22, {@see PlacesRecordActionsWithForm}).
 * Testul fixează atât funcționarea din noua poziție, cât și disciplina: nicio pagină de editare
 * nu are voie să le strecoare înapoi în antet.
 */

use App\Enums\UserRole;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
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
});

it('ștergerea fișei se face din rândul butoanelor formularului', function () {
    $student = Student::factory()->create();

    Livewire::test(EditStudent::class, ['record' => $student->getKey()])
        ->assertActionExists(TestAction::make('delete')->schemaComponent('form-actions', schema: 'content'))
        ->callAction(TestAction::make('delete')->schemaComponent('form-actions', schema: 'content'));

    expect($student->refresh()->trashed())->toBeTrue();
});

it('nicio pagină de editare nu mai ține acțiunile de înregistrare în antet', function () {
    $recordActions = ['DeleteAction::make()', 'ForceDeleteAction::make()', 'RestoreAction::make()'];

    $offenders = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        if (! str_starts_with($file->getFilename(), 'Edit')) {
            continue;
        }

        $source = $file->getContents();

        if (preg_match('/function getHeaderActions\(\): array\s*\{(.*?)\n    \}/s', $source, $matches) !== 1) {
            continue;
        }

        foreach ($recordActions as $action) {
            if (str_contains($matches[1], $action)) {
                $offenders[] = $file->getRelativePathname().' → '.$action;
            }
        }
    }

    expect($offenders)->toBe([]);
});
