<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validation messages (EN) — only what the framework does NOT ship
|--------------------------------------------------------------------------
|
| Laravel already provides the standard English rule messages, and the loader
| MERGES this file over them (paths: framework lang → app lang). So we keep
| here only the project's own keys. Without them they fell back to Romanian,
| producing mixed output for English users — e.g. „The adresa de email field
| is required." (English template, Romanian attribute name).
|
*/

return [

    // Clear message for the „date in the future" guards (grades/absences/justifications):
    // avoids showing the time with seconds coming from `maxDate(now())`.
    'not_future_date' => 'The date cannot be in the future.',

    'custom' => [
        'email' => [
            'unique' => 'This email address is already used by another account.',
        ],
        'username' => [
            'unique' => 'This username is already taken.',
        ],
        'password' => [
            'confirmed' => 'The password confirmation does not match the password.',
        ],
    ],

    'attributes' => [
        'name' => 'name',
        'username' => 'username',
        'email' => 'email address',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'current_password' => 'current password',
        'remember' => 'remember me',
        'first_name' => 'first name',
        'last_name' => 'last name',
        'value' => 'grade',
        'calificativ' => 'qualifier',
        'student_id' => 'student',
        'subject_id' => 'subject',
        'school_class_id' => 'class',
        'term_id' => 'term',
        'grade_level' => 'class (grade level)',
        'occurred_on' => 'date',
        'graded_on' => 'date',
        'assigned_on' => 'date',
        'period_start' => 'start date',
        'period_end' => 'end date',
        'type' => 'request type',
    ],

];
