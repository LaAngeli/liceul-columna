<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate(UserRole::Parinte->value, 'web');
});

it('un părinte poate avea mai mulți copii și îi accesează din cont', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $child1 = Student::factory()->create();
    $child2 = Student::factory()->create();
    $parent->students()->attach([$child1->id, $child2->id]);

    expect($parent->students)->toHaveCount(2)
        ->and($parent->students->pluck('id'))->toContain($child1->id, $child2->id);
});

it('un elev poate avea mai mulți tutori', function () {
    $student = Student::factory()->create();
    $g1 = User::factory()->create();
    $g2 = User::factory()->create();

    $student->guardians()->attach([$g1->id, $g2->id]);

    expect($student->guardians)->toHaveCount(2);
});
