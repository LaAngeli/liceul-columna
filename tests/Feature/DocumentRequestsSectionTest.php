<?php

/**
 * Secțiunea „Cereri" (Administrare) — restructurată (2026-07-17): navigator pe vederi
 * („De procesat" / „Arhivă") → carduri pe tipul cererii → tabel în context; fișă-document cu
 * comentariul depunătorului (payload.details); procesarea lasă COMENTARIU vizibil familiei în
 * cabinet (review_note), iar contestația cere detalii la depunere.
 */

use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Filament\Resources\DocumentRequests\Pages\ViewDocumentRequest;
use App\Models\AcademicYear;
use App\Models\Audit;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

// ─── Navigatorul: vederi → carduri pe tip → context ─────────────────────────────────────

it('aterizarea arată cardurile celor 5 tipuri, cu numărători și semnale', function () {
    DocumentRequest::factory()->count(2)->create(); // adeverințe (default) în așteptare
    DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create();
    DocumentRequest::factory()->create(['status' => RequestStatus::Approved]); // arhivată

    $page = Livewire::test(ListDocumentRequests::class)->instance();
    $cards = collect($page->typeCards());

    expect($cards->pluck('id')->all())->toBe(array_map(
        fn (DocumentRequestType $type): string => $type->value,
        DocumentRequestType::cases(),
    ));

    $adeverinte = $cards->firstWhere('id', DocumentRequestType::Adeverinta->value);
    expect($adeverinte['badge'])->toBe('2')
        ->and($adeverinte['stats'][0])->toContain('2');

    $pills = collect($page->requestsViewPills());
    expect($pills->firstWhere('key', 'queue')['count'])->toBe(3)
        ->and($pills->firstWhere('key', 'archive')['count'])->toBe(1);
});

it('contextul unui tip arată doar cererile lui în așteptare; arhiva pe cele închise', function () {
    $pendingAdeverinta = DocumentRequest::factory()->create();
    $pendingContestatie = DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create();
    $approvedAdeverinta = DocumentRequest::factory()->create(['status' => RequestStatus::Approved]);

    Livewire::withQueryParams(['tip' => 'adeverinta'])
        ->test(ListDocumentRequests::class)
        ->assertCanSeeTableRecords([$pendingAdeverinta])
        ->assertCanNotSeeTableRecords([$pendingContestatie, $approvedAdeverinta]);

    Livewire::withQueryParams(['arhiva' => '1', 'tip' => 'adeverinta'])
        ->test(ListDocumentRequests::class)
        ->assertCanSeeTableRecords([$approvedAdeverinta])
        ->assertCanNotSeeTableRecords([$pendingAdeverinta]);

    expect(Livewire::withQueryParams(['tip' => 'tip-inventat'])->test(ListDocumentRequests::class)->instance()->activeType())
        ->toBeNull();
});

it('cererile elevilor ARHIVAȚI nu blochează coada, dar rămân în arhivă', function () {
    $archivedStudent = Student::factory()->create();
    $request = DocumentRequest::factory()->create(['student_id' => $archivedStudent->id]);
    $archivedStudent->delete();

    $page = Livewire::test(ListDocumentRequests::class)->instance();

    expect(collect($page->requestsViewPills())->firstWhere('key', 'queue')['count'])->toBe(0)
        ->and($page->queueIsEmpty())->toBeTrue();

    // În coadă nu apare (nu blochează procesarea) — fișa ei rămâne accesibilă direct prin URL.
    Livewire::withQueryParams(['tip' => 'adeverinta'])
        ->test(ListDocumentRequests::class)
        ->assertCanNotSeeTableRecords([$request]);
});

// ─── Comentariile: depunător → fișă; procesator → familie ───────────────────────────────

it('procesarea cu comentariu îl salvează și familia îl vede în cabinet', function () {
    $request = DocumentRequest::factory()->create();

    Livewire::test(ViewDocumentRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('process', data: ['review_note' => 'Adeverința e gata la secretariat.'])
        ->assertHasNoActionErrors();

    $request->refresh();

    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->reviewed_by_user_id)->toBe($this->director->id)
        ->and($request->review_note)->toBe('Adeverința e gata la secretariat.');
});

it('procesarea merge și FĂRĂ comentariu (opțional), respingerea poartă motivul', function () {
    $processed = DocumentRequest::factory()->create();
    $rejected = DocumentRequest::factory()->create([
        'type' => DocumentRequestType::Sedinta,
        'student_id' => $processed->student_id,
    ]);

    Livewire::withQueryParams(['tip' => 'adeverinta'])
        ->test(ListDocumentRequests::class)
        ->callTableAction('process', $processed)
        ->assertHasNoTableActionErrors();

    expect($processed->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($processed->fresh()->review_note)->toBeNull();

    Livewire::test(ViewDocumentRequest::class, ['record' => $rejected->getRouteKey()])
        ->callAction('reject', data: ['review_note' => 'Programul de ședințe e complet luna aceasta.'])
        ->assertHasNoActionErrors();

    expect($rejected->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($rejected->fresh()->review_note)->toBe('Programul de ședințe e complet luna aceasta.');
});

it('comentariul secretariatului ajunge în răspunsul cabinetului (nota pe cerere)', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $student = Student::factory()->create();
    $parent->students()->attach($student->id);

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);
    $request->markProcessed($this->director->id, 'Gata la secretariat.');

    // Cererile sunt deferred — le cerem explicit prin reîncărcarea parțială Inertia.
    $data = actingAs($parent)
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'documentRequests'))
        ->assertOk()
        ->json('props.documentRequests');

    expect($data)->toHaveCount(1)
        ->and($data[0]['note'])->toBe('Gata la secretariat.');
});

// ─── Contestația poartă NOTA din depunere (robustețea logicii, 2026-07-17) ──────────────

/**
 * Elev cu o notă activă (7, disciplină numerică) și părinte-tutore — fundația fluxului de
 * contestație în care nota vizată se alege LA DEPUNERE, nu la procesare.
 *
 * @return array{grade: Grade, student: Student, parent: User}
 */
function contestationGradeFixture(): array
{
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-20', 'is_current' => true,
    ]);
    $class = SchoolClass::factory()->for($year)->create();
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    $student = Student::factory()->create();

    $grade = Grade::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 7,
        'calificativ' => null,
        'graded_on' => '2026-10-05',
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return ['grade' => $grade, 'student' => $student, 'parent' => $parent];
}

it('detaliile sunt OBLIGATORII la toate tipurile — o cerere fără conținut e neprocesabilă', function () {
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);

    // Contestație fără motiv → mesajul dedicat contestației.
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Contestatie->value,
        'grade_id' => $grade->id,
        'details' => '',
    ])->assertSessionHasErrors(['details']);

    // Adeverință fără detalii → respinsă și ea (destinația/scopul sunt esența cererii).
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => '',
    ])->assertSessionHasErrors(['details']);

    expect(DocumentRequest::query()->count())->toBe(0);
});

it('învoirea nu se poate cere pentru trecut — pentru absențe petrecute există motivarea (§2.1)', function () {
    ['student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Invoire->value,
        'details' => 'Plecare în familie.',
        'period_start' => now()->subDays(3)->toDateString(),
        'period_end' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['period_start']);

    expect(DocumentRequest::query()->count())->toBe(0);
});

it('justificativul se atașează la depunere, se descarcă doar de familie/administrație și dispare la ștergerea definitivă', function () {
    Storage::fake('local');
    ['student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Invoire->value,
        'details' => 'Participare la concurs — invitația anexată.',
        'period_start' => now()->addDay()->toDateString(),
        'period_end' => now()->addDays(2)->toDateString(),
        'attachment' => UploadedFile::fake()->create('invitatie.pdf', 120, 'application/pdf'),
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $request = DocumentRequest::query()->firstOrFail();

    expect($request->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($request->attachment_path);

    // Familia și administrația îl descarcă; un părinte STRĂIN — nu (403).
    $this->get(route('cabinet.requests.attachment', $request))->assertOk();
    actingAs($this->director)->get(route('cabinet.requests.attachment', $request))->assertOk();

    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Parinte->value);
    actingAs($stranger)->get(route('cabinet.requests.attachment', $request))->assertForbidden();

    // Igiena L133: ștergerea DEFINITIVĂ curăță și justificativul, nu doar PDF-ul.
    $attachmentPath = $request->attachment_path;
    $pdfPath = $request->pdf_path;
    $request->forceDelete();
    Storage::disk('local')->assertMissing($attachmentPath);
    Storage::disk('local')->assertMissing($pdfPath);

    // Tipurile de fișier neacceptate sunt respinse la validare.
    actingAs($parent)->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'Pentru dosar.',
        'attachment' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors(['attachment']);
});

it('familia își RETRAGE cererea în așteptare (anti-duplicatul se deblochează); după decizie nu se mai poate', function () {
    Storage::fake('local');
    ['student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'Pentru dosarul de bursă.',
    ])->assertRedirect();

    $request = DocumentRequest::query()->firstOrFail();

    // Cât e în așteptare, redepunerea aceluiași tip e blocată (anti-duplicat).
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'A doua, identică.',
    ])->assertSessionHasErrors(['type']);

    // Un străin nu poate retrage cererea altei familii.
    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Parinte->value);
    actingAs($stranger)->post(route('cabinet.requests.withdraw', $request))->assertForbidden();

    // Familia retrage → soft delete (restaurabil) + anti-duplicatul se deblochează.
    actingAs($parent)->post(route('cabinet.requests.withdraw', $request))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect(DocumentRequest::query()->find($request->id))->toBeNull()
        ->and(DocumentRequest::withTrashed()->findOrFail($request->id)->trashed())->toBeTrue();

    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'Varianta corectată.',
    ])->assertSessionDoesntHaveErrors();

    // Cererea DECISĂ rămâne în istoric — retragerea e refuzată (422).
    $second = DocumentRequest::query()->where('status', RequestStatus::Pending->value)->firstOrFail();
    $second->markProcessed($this->director->id);
    actingAs($parent)->post(route('cabinet.requests.withdraw', $second))->assertStatus(422);
});

it('depunerea și decizia lasă urmă în jurnalul de audit (L133 §7)', function () {
    config(['audit.console' => true]);

    $request = DocumentRequest::factory()->create();
    $request->markProcessed($this->director->id, 'Gata la secretariat.');

    $updated = Audit::query()
        ->where('auditable_type', DocumentRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect(Audit::query()->where('auditable_type', DocumentRequest::class)->where('event', 'created')->exists())->toBeTrue()
        ->and($updated)->not->toBeNull()
        ->and($updated->new_values['status'] ?? null)->toBe(RequestStatus::Approved->value)
        ->and((int) $updated->user_id)->toBe($this->director->id);
});

it('închiderea cererii anunță familia cu tipul DEDICAT (link pe tabul Cereri); depunerea numește elevul către secretariat', function () {
    Notification::fake();

    ['student' => $student, 'parent' => $parent] = contestationGradeFixture();

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);

    // Depunerea: secretariatul află TIPUL și ELEVUL, cu link direct în coada tipului.
    Notification::assertSentTo(
        $ao,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::DocumentRequestSubmitted
            && ($n->params['student'] ?? '') === $student->full_name
            && str_contains((string) $n->url, 'tip='),
    );

    $request->markProcessed($ao->id, 'Gata.');

    // Închiderea: tip DEDICAT (nu StatusChange — acela e al statutului academic), link pe tabul Cereri.
    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::DocumentRequestClosed
            && ($n->params['doc_type'] ?? '') !== ''
            && str_contains((string) $n->url, 'tab=requests'),
    );
    Notification::assertNotSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
    );
});

it('contestația se depune CU nota: lipsa ei, nota altui elev sau una cu corecție în așteptare sunt respinse', function () {
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);
    $submit = fn (array $extra) => $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Contestatie->value,
        'details' => 'Lucrarea a fost punctată greșit.',
        ...$extra,
    ]);

    // Fără notă → câmpul e obligatoriu.
    $submit([])->assertSessionHasErrors(['grade_id']);

    // Nota ALTUI elev → nu aparține copilului, respinsă pe server (nu doar în UI).
    $foreignGrade = Grade::factory()->create();
    $submit(['grade_id' => $foreignGrade->id])->assertSessionHasErrors(['grade_id']);

    // Nota cu o corecție DEJA în așteptare → conducerea o judecă oricum; contestația nouă e respinsă.
    GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'old_value' => 7,
        'new_value' => 8,
        'status' => CorrectionStatus::Pending,
    ]);
    $submit(['grade_id' => $grade->id])->assertSessionHasErrors(['grade_id']);

    expect(DocumentRequest::query()->count())->toBe(0);
});

it('contestația validă ÎNGHEAȚĂ snapshot-ul notei în payload (disciplină, valoare, dată, profesor)', function () {
    Storage::fake('local');
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    actingAs($parent);
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Contestatie->value,
        'grade_id' => $grade->id,
        'details' => 'Lucrarea a fost punctată greșit la subiectul II.',
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $request = DocumentRequest::query()->firstOrFail();

    expect($request->contestedGradeId())->toBe($grade->id)
        ->and($request->payload['grade']['subject'])->toBe($grade->subject->name)
        ->and($request->payload['grade']['value'])->toBe('7')
        ->and($request->payload['grade']['graded_on'])->toBe('05.10.2026')
        ->and($request->payload['grade']['teacher'])->toBe($grade->teacher->full_name)
        ->and($request->contestedGradeLabel())->toContain($grade->subject->name.' — 7 (05.10.2026)')
        ->and($request->pdf_path)->not->toBeNull();
});

it('notele contestabile ajung DOAR la familie; nota cu corecție în așteptare iese din listă', function () {
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    $props = actingAs($parent)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'contestableGrades'))
        ->assertOk()
        ->json('props.contestableGrades');

    expect($props)->toHaveCount(1)
        ->and($props[0]['id'])->toBe($grade->id)
        ->and($props[0]['label'])->toContain(' — 7 (05.10.2026)');

    // O corecție în așteptare pe notă → dispare din opțiuni (regula selectului = regula validării).
    GradeCorrection::factory()->create([
        'grade_id' => $grade->id, 'old_value' => 7, 'new_value' => 8, 'status' => CorrectionStatus::Pending,
    ]);

    expect(actingAs($parent)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'contestableGrades'))
        ->json('props.contestableGrades'))->toBe([]);

    // Personalul vede pagina, dar nu depune → lista e goală pentru el.
    expect(actingAs($this->director)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'contestableGrades'))
        ->json('props.contestableGrades'))->toBe([]);
});

it('la contestațiile NOI corecția se leagă de nota din depunere (fără selecție), iar motivul NU se pre-copiază', function () {
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    $request = DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
        'payload' => [
            'details' => 'Punctaj greșit la subiectul II.',
            'grade_id' => $grade->id,
            'grade' => [
                'subject' => $grade->subject->name,
                'value' => '7',
                'calificativ' => null,
                'graded_on' => '05.10.2026',
                'teacher' => $grade->teacher->full_name,
            ],
        ],
    ]);

    // Fără motiv PROPRIU → acțiunea pică. (Înainte, textul familiei se pre-copia în câmp și
    // acțiunea trecea cu un motiv care nu era al procesatorului — fix-ul e chiar această eroare.)
    Livewire::test(ViewDocumentRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('openCorrection', data: ['new_value' => 9])
        ->assertHasActionErrors(['reason']);

    expect(GradeCorrection::query()->count())->toBe(0);

    // Cu motiv propriu → corecția se leagă de nota din depunere, fără vreun grade_id în formular.
    Livewire::test(ViewDocumentRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('openCorrection', data: [
            'new_value' => 9,
            'reason' => 'Punctajul recalculat al lucrării dă nota 9.',
        ])
        ->assertHasNoActionErrors();

    $correction = GradeCorrection::query()->firstOrFail();

    expect($correction->grade_id)->toBe($grade->id)
        ->and($correction->reason)->toBe('Punctajul recalculat al lucrării dă nota 9.')
        ->and((float) $correction->old_value)->toBe(7.0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Approved);
});

it('fișa contestației noi afișează nota contestată din snapshot', function () {
    ['grade' => $grade, 'student' => $student, 'parent' => $parent] = contestationGradeFixture();

    $request = DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
        'payload' => [
            'details' => 'Punctaj greșit.',
            'grade_id' => $grade->id,
            'grade' => [
                'subject' => $grade->subject->name,
                'value' => '7',
                'calificativ' => null,
                'graded_on' => '05.10.2026',
                'teacher' => $grade->teacher->full_name,
            ],
        ],
    ]);

    Livewire::test(ViewDocumentRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk()
        ->assertSee($grade->subject->name.' — 7 (05.10.2026)');
});

// ─── Fișa cererii ────────────────────────────────────────────────────────────────────────

it('fișa cererii se deschide și poartă comentariul depunătorului', function () {
    $request = DocumentRequest::factory()->create([
        'payload' => ['details' => 'Rugăm eliberarea unei adeverințe pentru medicul de familie.'],
    ]);

    Livewire::test(ViewDocumentRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk()
        ->assertSee('Rugăm eliberarea unei adeverințe pentru medicul de familie.');
});
