<?php

/**
 * Fidelitatea importului de conturi (audit legacy 2026-07-19): omonimii primesc fiecare FIȘA lui
 * (coadă de fișe per nume, nu „prima potrivire câștigă" — care lega ambele conturi de aceeași fișă
 * și lăsa un cont orfan + o fișă fără cont); un cont duplicat în sursă (două conturi, o singură
 * fișă) nu se mai creează deloc.
 */

use App\Actions\ImportLegacyUsers;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Conexiune „legacy" de test: aceeași bază SQLite ca a suitei (altfel :memory: separat ar
    // dispărea între conexiuni) — creăm doar tabelul bdn_users în ea.
    config()->set('database.connections.legacy', config('database.connections.'.config('database.default')));
    DB::purge('legacy');

    Schema::connection('legacy')->create('bdn_users', function ($table): void {
        $table->increments('id');
        $table->string('login');
        $table->string('password');
        $table->string('name_1');
        $table->string('name_2');
        $table->string('sex')->default('m');
        $table->string('status')->default('1');
        $table->string('func');
    });
});

it('omonimii primesc fiecare propria fișă; duplicatul de cont din sursă e sărit, nu legat peste alții', function () {
    // Două fișe „Munteanu Cristian" (clase diferite) + o fișă unică „Iurco Marc".
    $fisa1 = Student::factory()->create(['last_name' => 'Munteanu', 'first_name' => 'Cristian']);
    $fisa2 = Student::factory()->create(['last_name' => 'Munteanu', 'first_name' => 'Cristian']);
    $fisaIurco = Student::factory()->create(['last_name' => 'Iurco', 'first_name' => 'Marc']);

    DB::connection('legacy')->table('bdn_users')->insert([
        ['login' => 'cmunteanu', 'password' => 'p1', 'name_1' => 'Munteanu', 'name_2' => 'Cristian', 'func' => '1'],
        ['login' => 'crmunteanu', 'password' => 'p2', 'name_1' => 'Munteanu', 'name_2' => 'Cristian', 'func' => '1'],
        // Duplicat REAL din sursă: două conturi pentru același copil (o singură fișă).
        ['login' => 'miurco', 'password' => 'p3', 'name_1' => 'Iurco', 'name_2' => 'Marc', 'func' => '1'],
        ['login' => 'miurco', 'password' => 'p4', 'name_1' => 'Iurco', 'name_2' => 'Marc', 'func' => '1'],
    ]);

    $stats = app(ImportLegacyUsers::class)->execute();

    // Omonimii: fiecare cont și-a consumat fișa lui — nicio fișă orfană, niciun cont orfan.
    $u1 = User::query()->where('username', 'cmunteanu')->firstOrFail();
    $u2 = User::query()->where('username', 'crmunteanu')->firstOrFail();
    expect($fisa1->refresh()->user_id)->toBe($u1->id)
        ->and($fisa2->refresh()->user_id)->toBe($u2->id)
        ->and($u1->hasRole(UserRole::Elev->value))->toBeTrue();

    // Duplicatul: primul cont ia fișa; al doilea NU se creează (coada goală → fără fișă → sărit).
    $iurco = User::query()->where('username', 'miurco')->firstOrFail();
    expect($fisaIurco->refresh()->user_id)->toBe($iurco->id)
        ->and(User::query()->where('username', 'miurco2')->exists())->toBeFalse()
        ->and($stats['created'])->toBe(3)
        ->and($stats['skippedNoMatch'])->toBe(1);
});
