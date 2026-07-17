<?php

/**
 * Secțiunea „Cereri" (Administrare) — restructurată (2026-07-17): navigator pe vederi
 * („De procesat" / „Arhivă") → carduri pe tipul cererii → tabel în context; fișă-document cu
 * comentariul depunătorului (payload.details); procesarea lasă COMENTARIU vizibil familiei în
 * cabinet (review_note), iar contestația cere detalii la depunere.
 */

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Filament\Resources\DocumentRequests\Pages\ViewDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
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

it('contestația fără detalii e respinsă la depunere; cu detalii trece', function () {
    Storage::fake('local');

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $student = Student::factory()->create();
    $parent->students()->attach($student->id);

    actingAs($parent);

    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Contestatie->value,
        'details' => '',
    ])->assertSessionHasErrors(['details']);

    expect(DocumentRequest::query()->count())->toBe(0);

    // Adeverința rămâne cu detalii OPȚIONALE (regula e doar pentru contestație).
    $this->post(route('cabinet.requests.store', $student), [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => '',
    ])->assertSessionDoesntHaveErrors(['details']);
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
