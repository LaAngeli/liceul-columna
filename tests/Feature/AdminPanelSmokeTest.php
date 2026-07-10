<?php

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('adminul poate deschide paginile principale ale panoului', function (string $url) {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    $this->actingAs($admin)->get($url)->assertOk();
})->with([
    '/admin',
    '/admin/academic-years',
    '/admin/terms',
    '/admin/students',
    '/admin/teachers',
    '/admin/subjects',
    '/admin/school-classes',
    '/admin/enrollments',
    '/admin/grades',
    '/admin/absences',
    '/admin/academic-records',
    '/admin/homework-assignments',
    '/admin/grade-corrections',
    '/admin/absence-motivations',
    '/admin/mesaje',
    '/admin/schedules',
    '/admin/document-requests',
    '/admin/users',
]);

it('un elev nu poate accesa panoul de gestiune', function () {
    $elev = User::factory()->create();
    $elev->assignRole(UserRole::Elev->value);

    $this->actingAs($elev)->get('/admin')->assertForbidden();
});
