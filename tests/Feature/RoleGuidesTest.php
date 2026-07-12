<?php

/**
 * Ghidurile de rol (PDF-uri de prezentare): integritatea sursei de conținut, randarea template-urilor
 * și comanda de generare end-to-end.
 */

use App\Support\RoleGuides;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

it('acoperă exact cele 9 roluri, cu nivele 1–9 unice și fișiere unice', function () {
    $roles = RoleGuides::roles();

    expect($roles)->toHaveCount(9)
        ->and(collect($roles)->pluck('level')->sort()->values()->all())->toBe(range(1, 9))
        ->and(collect($roles)->pluck('key')->unique())->toHaveCount(9)
        ->and(collect($roles)->pluck('file')->unique())->toHaveCount(9);
});

it('fiecare rol are câmpurile obligatorii completate', function () {
    foreach (RoleGuides::roles() as $role) {
        expect($role['title'])->not->toBe('')
            ->and($role['identity'])->not->toBe('')
            ->and($role['screens'])->not->toBeEmpty()
            ->and($role['canDo'])->not->toBeEmpty()
            ->and($role['cannotDo'])->not->toBeEmpty()
            ->and($role['interactions'])->not->toBeEmpty();
    }
});

it('template-ul de rol randează fără eroare și conține titlul rolului', function () {
    foreach (RoleGuides::roles() as $role) {
        $html = View::make('pdf.guides.role', ['role' => $role])->render();

        expect($html)->toContain($role['title'])
            ->and($html)->toContain('Ce poți face')
            ->and($html)->toContain('Ecranele tale');
    }
});

it('template-ul de ansamblu randează cu toate cele 9 roluri în tabel', function () {
    $html = View::make('pdf.guides.overview', [
        'components' => RoleGuides::components(),
        'rolesTable' => RoleGuides::rolesTable(),
        'flows' => RoleGuides::flows(),
        'accountCreation' => RoleGuides::accountCreation(),
        'principles' => RoleGuides::principles(),
    ])->render();

    expect($html)->toContain('Platforma Liceul Columna')
        ->and($html)->toContain('Super Administrator')
        ->and($html)->toContain('Părinte')
        ->and($html)->toContain('Cele patru fluxuri');
});

it('comanda generează cele 10 PDF-uri în folderul indicat', function () {
    $out = storage_path('app/test-role-guides');
    File::deleteDirectory($out);

    $this->artisan('app:role-guides', ['--out' => $out])->assertExitCode(0);

    $files = glob("{$out}/*.pdf");
    expect($files)->toHaveCount(10);

    // PDF valid + neconsumabil (semnătura %PDF la început).
    foreach ($files as $file) {
        expect(File::size($file))->toBeGreaterThan(1000)
            ->and(substr((string) File::get($file), 0, 4))->toBe('%PDF');
    }

    File::deleteDirectory($out);
});
