<?php

/**
 * ALOCĂRILE oferă disciplinele TREPTEI, nu tot nomenclatorul.
 *
 * Semnalat de beneficiar (06.08.2026): la o clasă a VI-a, „Matematică" apărea de DOUĂ ori în
 * lista de discipline. Nu era o eroare de date — zece denumiri există în două fișe, una de primar
 * și una de gimnaziu/liceu, cu tip de notare diferit („Matematică" cl. 1–4 pe calificativ vs
 * cl. 5–12 numeric). Eroarea era că formularul le arăta pe amândouă, identic etichetate.
 *
 * Miza NU e cosmetică: alocarea fișei de primar unei clase a VI-a ar fi pus acea clasă pe
 * calificative. Același defect a costat deja o dată — 219 din 507 lecții au primit fișa altui
 * ciclu la import; regula exista la Lecții, dar Alocările n-o urmau.
 */

use App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager;
use App\Models\SchoolClass;
use App\Models\Subject;

beforeEach(function () {
    // Perechea omonimă reală din nomenclator: aceeași denumire, cicluri diferite.
    $this->matePrimar = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 1, 'max_grade' => 4]);
    $this->mateGimnaziu = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
    // O disciplină fără interval: nomenclator incomplet NU înseamnă „nu se predă".
    $this->faraInterval = Subject::factory()->create(['name' => 'Dezvoltare personală', 'min_grade' => null, 'max_grade' => null]);
});

/** Opțiunile de disciplină pe care le oferă formularul pentru clasa dată. */
function assignmentSubjectOptions(?int $classId): array
{
    $method = new ReflectionMethod(TeachingAssignmentsRelationManager::class, 'subjectOptions');
    $method->setAccessible(true);

    return $method->invoke(null, $classId);
}

it('clasa de GIMNAZIU primește fișa ei, nu și pe cea de primar', function () {
    $clasa = SchoolClass::factory()->create(['grade_level' => 6]);

    $optiuni = assignmentSubjectOptions($clasa->id);

    expect($optiuni)->toHaveKey($this->mateGimnaziu->id)
        ->and($optiuni)->not->toHaveKey($this->matePrimar->id)
        // „Matematică" apare o SINGURĂ dată — asta a fost sesizarea.
        ->and(array_filter($optiuni, fn (string $l): bool => str_contains($l, 'Matematică')))->toHaveCount(1);
});

it('clasa de PRIMAR primește fișa de primar', function () {
    $clasa = SchoolClass::factory()->create(['grade_level' => 2]);

    $optiuni = assignmentSubjectOptions($clasa->id);

    expect($optiuni)->toHaveKey($this->matePrimar->id)
        ->and($optiuni)->not->toHaveKey($this->mateGimnaziu->id);
});

it('o disciplină fără interval se predă la orice treaptă', function () {
    foreach ([1, 6, 12] as $treapta) {
        $clasa = SchoolClass::factory()->create(['grade_level' => $treapta]);

        expect(assignmentSubjectOptions($clasa->id))->toHaveKey($this->faraInterval->id);
    }
});

it('fără clasă aleasă nu se oferă nicio disciplină — ciclul nu e încă știut', function () {
    expect(assignmentSubjectOptions(null))->toBe([]);
});

it('dacă intervalele se suprapun, eticheta spune treptele — o listă cu rânduri identice e o ghicitoare', function () {
    // Școala editează intervalele și cele două fișe ajung să acopere amândouă treapta 5.
    $this->matePrimar->update(['max_grade' => 6]);

    $clasa = SchoolClass::factory()->create(['grade_level' => 5]);
    $optiuni = assignmentSubjectOptions($clasa->id);

    expect($optiuni[$this->matePrimar->id])->not->toBe($optiuni[$this->mateGimnaziu->id])
        ->and($optiuni[$this->matePrimar->id])->toContain('1–6')
        ->and($optiuni[$this->mateGimnaziu->id])->toContain('5–12');
});

it('scope-ul de model e aceeași regulă ca predicatul per-instanță', function () {
    foreach ([1, 4, 5, 12] as $treapta) {
        $prinScope = Subject::query()->coveringGrade($treapta)->pluck('id')->sort()->values()->all();
        $prinPredicat = Subject::all()
            ->filter(fn (Subject $s): bool => $s->coversGrade($treapta))
            ->pluck('id')->sort()->values()->all();

        expect($prinScope)->toBe($prinPredicat);
    }
});
