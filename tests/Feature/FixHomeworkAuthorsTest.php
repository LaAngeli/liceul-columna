<?php

use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;

/**
 * `app:fix-homework-authors` — repară temele al căror autor nu putea fi autorul lor.
 *
 * Regula pe care o restaurează e cea a panoului ({@see EnforcesHomeworkScope}):
 * un profesor dă temă doar pe (clasă, disciplină) din alocările lui. Datele demo o încălcaseră, iar
 * familia vedea „predat de X" acolo unde X nu putea preda.
 */

/**
 * An + clasă + disciplină + doi profesori: unul ALOCAT pe pereche, celălalt fără nicio alocare.
 *
 * @return array{class: SchoolClass, subject: Subject, assigned: Teacher, stranger: Teacher}
 */
function fixAuthorsWorld(): array
{
    $year = AcademicYear::factory()->create([
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
    ]);

    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 5, 'section' => 'A']);
    $subject = Subject::factory()->create(['name' => 'Matematică']);

    $assigned = Teacher::factory()->create(['last_name' => 'Alocat', 'first_name' => 'Ion']);
    $stranger = Teacher::factory()->create(['last_name' => 'Străin', 'first_name' => 'Vasile']);

    TeachingAssignment::factory()->create([
        'teacher_id' => $assigned->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);

    return ['class' => $class, 'subject' => $subject, 'assigned' => $assigned, 'stranger' => $stranger];
}

/** O temă pe clasa lumii de test, cu autorul dat. */
function fixAuthorsHomework(array $world, Teacher $author, ?int $subjectId = null): HomeworkAssignment
{
    return HomeworkAssignment::query()->create([
        'subject_id' => $subjectId ?? $world['subject']->id,
        'subject_name' => $world['subject']->name,
        'teacher_id' => $author->id,
        'author_name' => $author->full_name,
        'grade_level' => $world['class']->grade_level,
        'section' => $world['class']->section,
        'assigned_on' => '2026-10-05',
        'topic' => 'Recapitulare',
    ]);
}

it('reatribuie tema profesorului care ARE alocarea pe (clasă, disciplină)', function () {
    $world = fixAuthorsWorld();
    $homework = fixAuthorsHomework($world, $world['stranger']);

    $this->artisan('app:fix-homework-authors', ['--apply' => true])->assertSuccessful();

    $homework->refresh();

    expect($homework->teacher_id)->toBe($world['assigned']->id)
        ->and($homework->author_name)->toBe($world['assigned']->full_name);
});

it('golește autorul când NIMENI nu are alocarea — un câmp gol e adevărat, un nume greșit nu', function () {
    $world = fixAuthorsWorld();
    // Disciplină fără nicio alocare pe clasa asta.
    $orphan = Subject::factory()->create(['name' => 'Astronomie']);
    $homework = fixAuthorsHomework($world, $world['stranger'], $orphan->id);

    $this->artisan('app:fix-homework-authors', ['--apply' => true])->assertSuccessful();

    $homework->refresh();

    expect($homework->teacher_id)->toBeNull()
        ->and($homework->author_name)->toBeNull();
});

it('nu atinge tema al cărei autor e legitim', function () {
    $world = fixAuthorsWorld();
    $homework = fixAuthorsHomework($world, $world['assigned']);

    $this->artisan('app:fix-homework-authors', ['--apply' => true])->assertSuccessful();

    $homework->refresh();

    expect($homework->teacher_id)->toBe($world['assigned']->id)
        ->and($homework->author_name)->toBe($world['assigned']->full_name);
});

it('fără --apply nu scrie NIMIC — raportul e doar raport', function () {
    $world = fixAuthorsWorld();
    $homework = fixAuthorsHomework($world, $world['stranger']);

    $this->artisan('app:fix-homework-authors')->assertSuccessful();

    $homework->refresh();

    expect($homework->teacher_id)->toBe($world['stranger']->id);
});

it('rezolvă clasa în ANUL temei — nu confundă clasele omonime din ani diferiți', function () {
    $world = fixAuthorsWorld();

    // Alt an, aceeași (treaptă, literă) — dar cu ALT profesor alocat.
    $otherYear = AcademicYear::factory()->create([
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-07-31',
        'is_current' => false,
    ]);
    $otherClass = SchoolClass::factory()->for($otherYear)->create(['grade_level' => 5, 'section' => 'A']);
    $otherTeacher = Teacher::factory()->create(['last_name' => 'Anul', 'first_name' => 'Trecut']);
    TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'school_class_id' => $otherClass->id,
        'subject_id' => $world['subject']->id,
    ]);

    // Temă din anul TRECUT → trebuie să primească profesorul anului trecut, nu pe cel de acum.
    $homework = HomeworkAssignment::query()->create([
        'subject_id' => $world['subject']->id,
        'subject_name' => $world['subject']->name,
        'teacher_id' => $world['stranger']->id,
        'author_name' => $world['stranger']->full_name,
        'grade_level' => 5,
        'section' => 'A',
        'assigned_on' => '2025-10-05',
        'topic' => 'Din anul trecut',
    ]);

    $this->artisan('app:fix-homework-authors', ['--apply' => true])->assertSuccessful();

    expect($homework->refresh()->teacher_id)->toBe($otherTeacher->id);
});

it('--demo-only lasă în pace clasele reale', function () {
    $world = fixAuthorsWorld();
    $homework = fixAuthorsHomework($world, $world['stranger']);

    $this->artisan('app:fix-homework-authors', ['--apply' => true, '--demo-only' => true])->assertSuccessful();

    // Clasa din fixtură NU e marcată [DEMO] → rămâne neatinsă.
    expect($homework->refresh()->teacher_id)->toBe($world['stranger']->id);
});
