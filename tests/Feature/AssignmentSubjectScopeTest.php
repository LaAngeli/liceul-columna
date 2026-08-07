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
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;

beforeEach(function () {
    // Perechea omonimă reală din nomenclator: aceeași denumire, cicluri diferite.
    $this->matePrimar = Subject::factory()->create(['name' => 'Matematică', 'grade_levels' => range(1, 4)]);
    $this->mateGimnaziu = Subject::factory()->create(['name' => 'Matematică', 'grade_levels' => range(5, 12)]);
    // O disciplină fără interval: nomenclator incomplet NU înseamnă „nu se predă".
    $this->faraInterval = Subject::factory()->create(['name' => 'Dezvoltare personală', 'grade_levels' => null]);
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

it('dacă seturile se suprapun, eticheta spune treptele — o listă cu rânduri identice e o ghicitoare', function () {
    // Stare legacy: cele două fișe ajung să acopere amândouă treapta 5 (prin query builder —
    // formularul refuză azi suprapunerea, dar datele istorice o pot purta).
    Subject::query()->whereKey($this->matePrimar->id)->update(['grade_levels' => json_encode(range(1, 6))]);

    $clasa = SchoolClass::factory()->create(['grade_level' => 5]);
    $optiuni = assignmentSubjectOptions($clasa->id);

    expect($optiuni[$this->matePrimar->id])->not->toBe($optiuni[$this->mateGimnaziu->id])
        ->and($optiuni[$this->matePrimar->id])->toContain('I–VI')
        ->and($optiuni[$this->mateGimnaziu->id])->toContain('V–XII');
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

// ─── Restructurarea tabelului (06.08.2026) ──────────────────────────────────────────────
//
// Lista plată era de necitit: mediana e 18 alocări per profesor (max 54), clasa se repeta pe
// fiecare rând, iar coloana de grupă era goală în 89,7% din cazuri — punând întrebarea „ce grupă?"
// unui profesor fără nicio legătură cu engleza.

it('tabelul se grupează pe CLASĂ, iar antetul poartă anul — două clase omonime nu se mai confundă', function () {
    $manager = new TeachingAssignmentsRelationManager;
    $grup = (new ReflectionMethod($manager, 'classGroup'))->getClosure($manager)();

    $anVechi = AcademicYear::factory()->create(['name' => '2025–2026']);
    $anNou = AcademicYear::factory()->create(['name' => '2026–2027']);

    // Aceeași denumire de clasă, ani diferiți — 14 astfel de perechi există în datele reale.
    $vechi = SchoolClass::factory()->for($anVechi)->create(['name' => '1B', 'section' => 'B', 'grade_level' => 1]);
    $noua = SchoolClass::factory()->for($anNou)->create(['name' => '1B', 'section' => 'B', 'grade_level' => 1]);

    $alocarea = fn (SchoolClass $clasa) => TeachingAssignment::factory()->create([
        'school_class_id' => $clasa->id,
        'subject_id' => $this->faraInterval->id,
    ]);

    $titluri = [$grup->getTitle($alocarea($vechi)), $grup->getTitle($alocarea($noua))];
    $descrieri = [$grup->getDescription($alocarea($vechi), $titluri[0]), $grup->getDescription($alocarea($noua), $titluri[1])];

    // Titlurile SUNT identice (aceeași clasă, ca nume) — de aceea anul trebuie să stea în descriere.
    expect($titluri[0])->toBe('1B B')->and($titluri[1])->toBe('1B B')
        ->and($descrieri[0])->toContain('2025–2026')
        ->and($descrieri[1])->toContain('2026–2027')
        ->and($descrieri[0])->not->toBe($descrieri[1])
        // Treapta în cifre romane, ca pe documente.
        ->and($descrieri[0])->toContain('I');
});
