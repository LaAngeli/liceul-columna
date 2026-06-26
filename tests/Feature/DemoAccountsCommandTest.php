<?php

use App\Console\Commands\DemoAccounts;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

it('listează doar conturile marcate [DEMO]', function () {
    User::factory()->create(['name' => DemoAccounts::MARKER.' Elev Test', 'email' => 'demo@x.test']);
    User::factory()->create(['name' => 'Utilizator Real', 'email' => 'real@x.test']);

    $this->artisan('app:demo-accounts')
        ->expectsOutputToContain('demo@x.test')
        ->assertSuccessful();

    expect(User::where('email', 'real@x.test')->exists())->toBeTrue();
});

it('--remove șterge conturile demo dar păstrează fișele de elev/profesor (dezlegate)', function () {
    $student = Student::factory()->create();
    $elev = User::factory()->create(['name' => DemoAccounts::MARKER.' Elev', 'email' => 'elev@x.test']);
    $student->update(['user_id' => $elev->id]);

    $teacher = Teacher::factory()->create();
    $prof = User::factory()->create(['name' => DemoAccounts::MARKER.' Prof', 'email' => 'prof@x.test']);
    $teacher->update(['user_id' => $prof->id]);

    $real = User::factory()->create(['name' => 'Admin Real', 'email' => 'real@x.test']);

    $this->artisan('app:demo-accounts --remove')->assertSuccessful();

    expect(User::where('email', 'elev@x.test')->exists())->toBeFalse()
        ->and(User::where('email', 'prof@x.test')->exists())->toBeFalse()
        ->and(User::whereKey($real->id)->exists())->toBeTrue()
        ->and(Student::whereKey($student->id)->exists())->toBeTrue()
        ->and($student->fresh()->user_id)->toBeNull()
        ->and(Teacher::whereKey($teacher->id)->exists())->toBeTrue()
        ->and($teacher->fresh()->user_id)->toBeNull();
});

it('marchează conturile demo distinct de cele reale', function () {
    $demo = User::factory()->create(['name' => DemoAccounts::MARKER.' Cineva']);
    $real = User::factory()->create(['name' => 'Cineva Real']);

    expect(str_starts_with($demo->name, DemoAccounts::MARKER))->toBeTrue()
        ->and(str_starts_with($real->name, DemoAccounts::MARKER))->toBeFalse();
});
