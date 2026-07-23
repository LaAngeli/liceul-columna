<?php

namespace App\Http\Controllers;

use App\Actions\ComputeDeferralRisk;
use App\Actions\ComputeStudentDynamics;
use App\Actions\DetermineStudentStatus;
use App\Actions\Documents\GenerateStudentDocumentPdf;
use App\Actions\GenerateRequestPdf;
use App\Actions\LogStudentAccess;
use App\Enums\AcademicRecordPeriod;
use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\GeneratedDocumentType;
use App\Enums\RequestStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\Message;
use App\Models\SemesterValidation;
use App\Models\StatusAcknowledgement;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\ContentTranslator;
use App\Support\Grades;
use App\Support\SchoolCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetController extends Controller
{
    use Concerns\BuildsStudentCatalogData;

    /**
     * Cabinetul personal — COCKPIT: bandă de alerte cross-copil + carduri-copil îmbogățite
     * (status, medie+tendință, ultima notă, absențe recente). Pentru elevul însuși: cockpit personal.
     *
     * Optimizat vs. versiunea veche (N+1 fix): un singur query bulk pentru term_averages și pentru
     * absențele recente, un singur Term::current. Vezi audit § N+1 — fix-uri pe summary()/index().
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user('web');

        // Personalul folosește exclusiv panoul Filament — niciodată cabinetul Inertia.
        if ($user->hasAnyRole(UserRole::panelRoleValues())) {
            return redirect()->to($user->homePath());
        }

        $currentTermId = Term::query()->where('is_current', true)->value('id');

        $self = Student::query()->where('user_id', $user->id)->first();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Student> $children */
        $children = $user->students()->get();

        // Un elev legat ȘI prin user_id (self) ȘI prin pivotul guardian_student ar apărea de două ori
        // (două carduri identice) și ar fi numărat dublu la „cu risc". Excludem self din lista de
        // copii (analog Student::notifiableUsers, care face unique('id') pentru același motiv). (#37)
        if ($self !== null) {
            $children = $children->reject(fn (Student $child): bool => $child->id === $self->id)->values();
        }

        // EloquentCollection (NU Support\Collection) ca să avem loadMissing() pentru eager-load bulk.
        /** @var \Illuminate\Database\Eloquent\Collection<int, Student> $allStudents */
        $allStudents = new \Illuminate\Database\Eloquent\Collection;
        if ($self !== null) {
            $allStudents->push($self);
        }
        foreach ($children as $child) {
            $allStudents->push($child);
        }

        $studentIds = $allStudents->pluck('id')->all();

        // Bulk: mediile semestriale pentru TOȚI elevii într-un singur query (înlocuiește N+1 din summary()).
        /** @var Collection<int, Collection<int, TermAverage>> $termAveragesByStudent */
        $termAveragesByStudent = $currentTermId !== null && count($studentIds) > 0
            ? TermAverage::query()
                ->whereIn('student_id', $studentIds)
                ->where('term_id', $currentTermId)
                ->get()
                ->groupBy('student_id')
            : collect();

        // Bulk: absențele recente nemotivate (ultimele 7 zile) numărate pe elev (1 query).
        /** @var Collection<int|string, int> $recentAbsenceCounts */
        $recentAbsenceCounts = count($studentIds) > 0
            ? Absence::query()
                ->whereIn('student_id', $studentIds)
                ->where('occurred_on', '>=', now()->subDays(7)->toDateString())
                ->where('is_motivated', false)
                ->selectRaw('student_id, count(*) as cnt')
                ->groupBy('student_id')
                ->pluck('cnt', 'student_id')
            : collect();

        // Bulk: motivări în așteptare (status pending) numărate pe elev (1 query). Afișate PER-COPIL pe
        // card (nu agregat în banda de alerte): contorul agregat cross-copil nu corespundea unui singur
        // profil → confuz. Per-copil, numărul se potrivește mereu cu ce vezi când deschizi copilul.
        /** @var Collection<int|string, int> $pendingMotivationCounts */
        $pendingMotivationCounts = count($studentIds) > 0
            ? AbsenceMotivation::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', RequestStatus::Pending)
                ->selectRaw('student_id, count(*) as cnt')
                ->groupBy('student_id')
                ->pluck('cnt', 'student_id')
            : collect();

        // Eager-load înmatriculările (cu clasa) UNA SINGURĂ DATĂ pentru TOȚI copiii (1 query bulk, nu N).
        $allStudents->loadMissing(['enrollments.schoolClass']);

        // Status + tendință calculate O SINGURĂ DATĂ pe copil (audit § cockpit perf #1). Refolosite atât
        // de cockpitCard cât și de cockpitAlerts → eliminăm calculul dublu de status și apelul costisitor
        // ComputeStudentDynamics::for() folosit doar pentru `current.trend`.
        $dynamicsAction = app(ComputeStudentDynamics::class);
        /** @var Collection<int|string, array<string, mixed>> $statusByStudent */
        $statusByStudent = $allStudents->mapWithKeys(
            fn (Student $s): array => [$s->id => $this->currentStatus($s)]
        );
        /** @var Collection<int|string, string|null> $trendByStudent */
        $trendByStudent = $allStudents->mapWithKeys(
            fn (Student $s): array => [$s->id => $dynamicsAction->trendFor($s, $currentTermId !== null ? (int) $currentTermId : null)]
        );

        // Bulk: ultima notă activă pe elev (1 query — selectează doar id-urile maxime per copil, apoi
        // hidratează cu subject). Înlocuiește N+1 din cockpitCard.
        /** @var Collection<int, Grade> $lastGradeByStudent */
        $lastGradeByStudent = count($studentIds) > 0
            ? Grade::query()
                ->whereIn('student_id', $studentIds)
                ->whereNull('annulled_at')
                ->whereIn('id', function ($sub) use ($studentIds): void {
                    // Subquery-ul brut NU primește global scope-ul SoftDeletes → filtrăm explicit
                    // deleted_at (altfel MAX(id) putea alege o notă ștearsă, iar query-ul exterior —
                    // cu scope — n-o găsea → cardul arăta „—" deși existau note active). (#37)
                    $sub->selectRaw('MAX(id)')
                        ->from('grades')
                        ->whereIn('student_id', $studentIds)
                        ->whereNull('annulled_at')
                        ->whereNull('deleted_at')
                        ->groupBy('student_id');
                })
                ->with('subject')
                ->get()
                ->keyBy('student_id')
            : collect();

        // Helper inline care construiește datele complete pentru un card (extragem din colecții bulk
        // valori scalare/obiect simple → cockpitCard primește doar tipuri fără generic, mulțumită PHPStan).
        $buildCard = function (Student $s) use (
            $termAveragesByStudent, $recentAbsenceCounts, $pendingMotivationCounts,
            $statusByStudent, $trendByStudent, $lastGradeByStudent
        ): array {
            /** @var \Illuminate\Database\Eloquent\Collection<int, TermAverage> $avgs */
            $avgs = $termAveragesByStudent->get($s->id, new \Illuminate\Database\Eloquent\Collection);
            $overall = $avgs->isNotEmpty()
                ? Grades::truncate2((float) $avgs->avg(fn (TermAverage $a): float => (float) $a->value))
                : null;

            return $this->cockpitCard(
                $s,
                $overall,
                (int) ($recentAbsenceCounts->get($s->id, 0)),
                (int) ($pendingMotivationCounts->get($s->id, 0)),
                $statusByStudent->get($s->id),
                $trendByStudent->get($s->id),
                $lastGradeByStudent->get($s->id),
            );
        };

        return Inertia::render('dashboard', [
            'cabinet' => [
                'children' => $children->map($buildCard)->all(),
                'self' => $self !== null ? $buildCard($self) : null,
                'alerts' => $this->cockpitAlerts($user, $allStudents, $statusByStudent),
            ],
        ]);
    }

    /**
     * Card de cockpit pentru un elev: identitate + medie + tendință + status + „ce-i nou".
     *
     * IMPORTANT: primește TOATE valorile pre-calculate din `index()` (audit § perf cockpit #1). Nu mai
     * recalculează status/trend/lastGrade/medie (cost ~6 query-uri/copil înainte). Înmatricularea e citită
     * din relația eager-loaded (`$student->enrollments->...`), nu re-cerută.
     *
     * @param  array<string, mixed>|null  $status  shape: status, label, failingSubjects, official, orderReference (vezi currentStatus())
     * @return array<string, mixed>
     */
    private function cockpitCard(Student $student, ?float $overallAverage, int $recentAbsences, int $pendingMotivations, ?array $status, ?string $trend, ?Grade $lastGrade): array
    {
        // Enrollment-urile sunt eager-loaded în index(): citire în memorie, fără query suplimentar.
        // Sortăm pe academic_year_id (definiția canonică din Student::currentSchoolClass), NU pe id:
        // o înmatriculare completată RETROACTIV pentru un an trecut are id mai mare dar an mai vechi —
        // pe id, cardul ar arăta clasa istorică, divergent de header/orar (#37).
        $class = $student->enrollments->sortByDesc('academic_year_id')->first()?->schoolClass;

        $statusValue = $status['status'] ?? null;

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'class' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
            'average' => $overallAverage,
            'trend' => $trend,
            'statusValue' => $statusValue,
            'isAtRisk' => is_string($statusValue) && (StudentStatus::tryFrom($statusValue)?->isAtRisk() ?? false),
            'lastGrade' => $lastGrade !== null ? [
                'value' => $lastGrade->value !== null
                    ? (string) (int) $lastGrade->value
                    : ($lastGrade->calificativ ?? '—'),
                'subject' => ContentTranslator::subject((string) $lastGrade->subject->name),
                'date' => $lastGrade->graded_on->format('d.m.Y'),
            ] : null,
            'recentAbsences' => $recentAbsences,
            'pendingMotivations' => $pendingMotivations,
        ];
    }

    /**
     * Agregat de alerte cross-copil pentru banda cockpit: lucruri care îți cer ATENȚIA și care nu
     * aparțin unui singur copil — mesaje + notificări necitite, nr. copii cu risc corigent/amânat.
     * (Motivările în așteptare NU sunt aici: sunt status per-copil, afișat pe cardul fiecărui copil —
     * vezi `cockpitCard`. Un agregat cross-copil nu corespundea unui singur profil → confuz.)
     * Toate query-urile sunt bulk — fără N+1.
     *
     * Statusul fiecărui copil e PRE-CALCULAT în `index()` și pasat aici (audit § perf cockpit #1) — nu mai
     * reapelăm `currentStatus()` pentru aceiași copii deja procesați de cockpitCard.
     *
     * `at_risk_student_id` = id-ul PRIMULUI copil cu risc, pentru link direct la profilul lui (tab Prezentare,
     * unde apare confirmarea „am luat cunoștință").
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Student>  $allStudents
     * @param  Collection<int|string, array<string, mixed>>  $statusByStudent
     * @return array{unread_messages: int, unread_notifications: int, at_risk: int, at_risk_student_id: int|null}
     */
    private function cockpitAlerts(User $user, \Illuminate\Database\Eloquent\Collection $allStudents, Collection $statusByStudent): array
    {
        $unreadMessages = Message::query()->forRecipient($user->id)->unread()->count();
        $unreadNotifications = $user->unreadNotifications()->count();

        $atRisk = 0;
        $atRiskStudentId = null;
        foreach ($allStudents as $student) {
            $statusValue = $statusByStudent->get($student->id)['status'] ?? null;
            $isAtRisk = is_string($statusValue) && (StudentStatus::tryFrom($statusValue)?->isAtRisk() ?? false);
            if ($isAtRisk) {
                $atRisk++;
                $atRiskStudentId ??= $student->id;
            }
        }

        return [
            'unread_messages' => $unreadMessages,
            'unread_notifications' => $unreadNotifications,
            'at_risk' => $atRisk,
            'at_risk_student_id' => $atRiskStudentId,
        ];
    }

    /**
     * Profilul personal — DOAR vizualizare (cabinetul elev/părinte nu permite editarea sau ștergerea
     * contului; conturile sunt administrate de personal, după ierarhie). Afișează datele contului plus
     * un rezumat al situației: pentru elev — propria fișă; pentru părinte — copiii asociați.
     */
    public function profile(Request $request): Response|RedirectResponse
    {
        $user = $request->user('web');

        // Personalul folosește panoul Filament — niciodată cabinetul Inertia.
        if ($user->hasAnyRole(UserRole::panelRoleValues())) {
            return redirect()->to($user->homePath());
        }

        $self = Student::query()->where('user_id', $user->id)->first();

        return Inertia::render('cabinet/profile', [
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                // `translatedFormat` folosește tokeni PHP `date()` (F = lună întreagă, Y = an 4
                // cifre), NU tokeni ICU (MMMM/yyyy). Cu tokeni ICU luna se dublează pe fiecare
                // literă suplimentară (`MMMM` → 4×lună) și `y` = an 2 cifre × 4 → „26262626".
                // Pentru sintaxă ICU folosește `isoFormat('D MMMM YYYY')`.
                'memberSince' => $user->created_at?->translatedFormat('d F Y'),
                'locale' => $user->locale,
            ],
            // Secțiunea „Securitate" (2FA) — singura zonă self-service a contului; restul datelor
            // rămân gestionate de staff (cabinet read-only). Două metode: TOTP sau cod pe email.
            'twoFactor' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
                'email' => [
                    'enabled' => $user->usesEmailTwoFactor(),
                    'address' => $user->email,
                ],
            ],
            // Flash-ul pașilor 2FA pe email („cod trimis" etc.) — nu există un share global de flash.
            'status' => $request->session()->get('status'),
            'self' => $self !== null ? $this->profileCard($self) : null,
            'children' => $user->students()->get()
                ->map(fn (Student $student): array => $this->profileCard($student))
                ->all(),
        ]);
    }

    /**
     * Card de profil (rezumat + situație curentă) pentru un elev, afișat în pagina de profil a cabinetului.
     *
     * @return array<string, mixed>
     */
    private function profileCard(Student $student): array
    {
        $status = $this->currentStatus($student);

        return [
            ...$this->summary($student),
            'statusValue' => $status['status'],
            'statusLabel' => $status['label'],
        ];
    }

    /**
     * Profilul unui elev: note, absențe, foaie matricolă și teme. Acces via StudentPolicy.
     *
     * Folosește `Inertia::defer()` pentru blocurile grele (taburi) → prima încărcare aduce DOAR identitatea
     * + statusul (care pictează headerul + banda de alerte). Restul (note/absențe/orar/teme/dinamică/cereri)
     * vine progresiv într-un al 2-lea request automat. Frontend-ul afișează skeleton pe taburi până sosesc.
     * Vezi audit § eager-loading în `student()` — fix prin defer.
     */
    public function student(Student $student, LogStudentAccess $accessLog): Response
    {
        Gate::authorize('view', $student);

        $student->load(['grades.subject', 'grades.term', 'grades.schoolClass', 'absences.subject', 'academicRecords.subject']);

        $viewer = auth('web')->user();

        // Jurnalizarea accesului (L133 §7): personalul care vizualizează dosarul unui elev care NU
        // e copilul lui. Familia care-și vede propriul copil nu intră în jurnal (e dreptul ei).
        // NU logăm pe reload-ul PARȚIAL (props deferate → un al 2-lea request automat cu header
        // X-Inertia-Partial-Data): altfel o singură deschidere reală = 2 intrări „viewed" în jurnalul
        // reținut 12 ani, diluând valoarea probatorie (#37).
        if ($viewer instanceof User
            && ! $this->isFamilyOf($viewer, $student)
            && ! request()->hasHeader('X-Inertia-Partial-Data')) {
            $accessLog->record($student, 'viewed', 'Vizualizare profil elev în cabinet');
        }

        $class = $student->currentSchoolClass();
        $status = $this->currentStatus($student);
        $isFamily = $viewer instanceof User && $this->isFamilyOf($viewer, $student);
        $canRequestMotivation = $isFamily;

        // Datele SENSIBILE ale situației (motivele motivărilor — potențial PII medical de minor — +
        // lichidarea corigenței) merg doar la familie, administrație sau DIRIGINTELE elevului. Un
        // profesor de disciplină (care vede profilul prin StudentPolicy::view) NU are nevoie de ele
        // (minim necesar L133) — acolo ar afla diagnoze din motivele cererilor + primea link 403.
        $canSeeSensitive = $isFamily
            || ($viewer instanceof User && $viewer->isAdministrator())
            || ($viewer instanceof User && $student->homeroomUser()?->is($viewer) === true);

        // Audit § profil #6 — comutator copil din interiorul profilului (părinte cu mai mulți copii).
        // Lista frați se trimite DOAR familiei (nu personalului). Profesorul vede `siblings = []`.
        /** @var array<int, array{id: int, name: string}> $siblings */
        $siblings = $viewer instanceof User && $this->isFamilyOf($viewer, $student)
            ? $viewer->students()->orderBy('first_name')->get()
                ->map(fn (Student $s): array => ['id' => $s->id, 'name' => $s->full_name])
                ->all()
            : [];

        return Inertia::render('cabinet/student-profile', [
            // === Eager (vin la prima încărcare — pictează identitatea + alerte) ===
            'student' => $this->summary($student),
            'status' => $status,
            'statusAck' => $this->statusAcknowledgement($student, $viewer, $status),
            'absencesTotal' => $student->absences->count(),
            'absencesMotivated' => $student->absences->where('is_motivated', true)->count(),
            'absencesUnmotivated' => $student->absences->where('is_motivated', false)->count(),
            'requestTypes' => DocumentRequestType::options(),
            // Doar familia (tutore/elev) poate depune cereri de motivare/tipice — personalul vede
            // pagina, dar nu și formularele (ar primi 403 la trimitere).
            'canRequestMotivation' => $canRequestMotivation,
            'siblings' => $siblings,

            // Fereastra de depunere a motivării (anul școlar curent → azi) — mică, vine eager
            // ca formularul să aibă min/max din prima randare.
            'motivationWindow' => $canRequestMotivation ? $this->motivationWindow() : null,

            // === Defer (vin progresiv într-un al 2-lea request după mount) ===
            // Tab „Situație" — note + absențe + motivări
            // Același catalog ca modulul „Note" din meniu (o singură implementare, un singur design).
            'gradebook' => Inertia::defer(fn (): array => $this->gradeBook($student)),
            'absenceRegister' => Inertia::defer(fn (): array => $this->absenceRegister($student)),
            'deferralRisk' => Inertia::defer(fn (): array => app(ComputeDeferralRisk::class)->for($student)),
            'motivations' => Inertia::defer(fn (): array => $canSeeSensitive ? $this->motivations($student) : []),

            // Tab „Orar & teme" — o singură formă normalizată (publicat/structurat), vezi WeeklySchedule.
            'weekly' => Inertia::defer(fn (): ?array => $this->weeklySchedule($class)),
            'homework' => Inertia::defer(fn (): array => $this->homeworkForStudent($student)),

            // Tab „Istoric" — dinamică multi-an + foaia matricolă
            'dynamics' => Inertia::defer(fn (): array => app(ComputeStudentDynamics::class)->for($student)),
            'transcript' => Inertia::defer(fn (): array => $this->transcript($student)),

            // Tab „Cereri" — cereri tipice + lichidare corigență. Cererile sunt o chestiune
            // familie↔secretariat (L133, minim necesar): profesorul/dirigintele NU le vede —
            // altfel afla că familia depune transfer/contestație și primea link PDF garantat 403.
            'documentRequests' => Inertia::defer(fn (): array => ($viewer instanceof User
                && ($this->isFamilyOf($viewer, $student) || $viewer->isAdministrator()))
                ? $this->documentRequests($student, $this->isFamilyOf($viewer, $student))
                : []),
            // Totalul REAL al cererilor — lista de mai sus e plafonată la 15, deci indicatorul de
            // trunchiere nu se poate calcula din ea (paritate cu pagina Documente, deferred #37).
            'documentRequestsTotal' => Inertia::defer(fn (): int => ($viewer instanceof User
                && ($this->isFamilyOf($viewer, $student) || $viewer->isAdministrator()))
                ? DocumentRequest::query()->where('student_id', $student->id)->count()
                : 0),
            // Notele contestabile pentru formularul de contestație (doar familia depune) — cererea
            // se depune CU nota vizată, nu cu o descriere liberă din care secretariatul ghicește.
            'contestableGrades' => Inertia::defer(fn (): array => $canRequestMotivation
                ? $this->contestableGrades($student)
                : []),
            // Lichidarea corigenței = planificare familie↔administrație (tabul „Cereri"); profesorul
            // de disciplină o vede în panou, nu în cabinet (minim necesar, coerent cu motivations).
            'corigentaExams' => Inertia::defer(fn (): array => $canSeeSensitive ? $this->corigentaExams($student) : []),
        ]);
    }

    /**
     * Cerere de motivare a absențelor depusă de familie (§2.1). Doar familia (tutore/elev)
     * poate depune; dirigintele validează ulterior din panou.
     */
    public function requestMotivation(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User && $this->isFamilyOf($user, $student), 403);

        // Se motivează absențe DEJA petrecute → perioada nu poate fi în viitor (audit M-10, aliniat cu
        // garda de dată-viitoare de la consemnarea absenței/notei). Limita de JOS = începutul anului
        // școlar curent (aceeași fereastră pe care o afișează și formularul) — o perioadă din anul
        // trecut nu mai are ce motiva în catalogul activ.
        $window = $this->motivationWindow();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'period_start' => array_filter([
                'required', 'date', 'before_or_equal:today',
                $window !== null ? 'after_or_equal:'.$window['min'] : null,
            ]),
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Trebuie să existe cel puțin o absență NEMOTIVATĂ în perioadă — altfel aprobarea nu ar marca
        // nimic (motivare „oarbă": familia greșește luna, dirigintele o aprobă degeaba). (#37)
        // whereDate (nu whereBetween): occurred_on e datetime, iar o margine dată-doar ar exclude
        // absența chiar pe ziua de FINAL (timpul 00:00:00 > '…' lexicografic în comparația de string).
        $hasUnmotivated = Absence::query()
            ->where('student_id', $student->id)
            ->whereDate('occurred_on', '>=', $data['period_start'])
            ->whereDate('occurred_on', '<=', $data['period_end'])
            ->where('is_motivated', false)
            ->exists();

        if (! $hasUnmotivated) {
            throw ValidationException::withMessages([
                'period_start' => __('cabinet_flash.motivation_no_absences'),
            ]);
        }

        // Anti-duplicat: nicio altă cerere PENDING care se suprapune cu perioada (altfel N cereri
        // identice inundă coada dirigintelui + storage-ul privat). Suprapunere = start ≤ celălalt.end
        // ȘI end ≥ celălalt.start. (#37 — simetric cu anti-duplicatul cererilor tipice.)
        $overlapPending = AbsenceMotivation::query()
            ->where('student_id', $student->id)
            ->where('status', RequestStatus::Pending)
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])
            ->exists();

        if ($overlapPending) {
            throw ValidationException::withMessages([
                'period_start' => __('cabinet_flash.motivation_duplicate_pending'),
            ]);
        }

        // Justificativul (adeverință etc.) e PII de minor → stocare PRIVATĂ, niciodată public.
        // Un eșec de scriere (disc plin/permisiuni) NU se înghite tăcut (motivare fără document +
        // toast de succes) — familia primește eroare și reîncearcă (#37).
        $documentPath = null;
        $file = $request->file('document');
        if ($file instanceof UploadedFile) {
            $stored = $file->store('motivations', 'local');
            if ($stored === false) {
                throw ValidationException::withMessages([
                    'document' => __('cabinet_flash.motivation_upload_failed'),
                ]);
            }
            $documentPath = $stored;
        }

        // Excepție = cererea acoperă absențe deja consolidate sau cu termenul de depunere (5 zile
        // lucrătoare) depășit. O astfel de cerere se aprobă de vicedirectorul pe educație, nu de diriginte.
        $isException = Absence::query()
            ->where('student_id', $student->id)
            ->whereDate('occurred_on', '>=', $data['period_start'])
            ->whereDate('occurred_on', '<=', $data['period_end'])
            ->where('is_motivated', false)
            ->where(function (Builder $query): void {
                $query->whereNotNull('motivation_locked_at')
                    ->orWhereDate('motivation_deadline', '<', today());
            })
            ->exists();

        AbsenceMotivation::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $user->id,
            'reason' => $data['reason'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'document_path' => $documentPath,
            'is_exception' => $isException,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($isException ? 'cabinet_flash.motivation_sent_exception' : 'cabinet_flash.motivation_sent'),
        ]);

        return back();
    }

    /**
     * Confirmarea electronică a părintelui că a luat cunoștință de statutul corigent/amânat al
     * elevului (spec pct. 108–109 — echivalentul „contra-semnăturii"). Doar familia; idempotentă;
     * rămâne urmă (cine/când) inclusiv în jurnalul de audit (modelul e Auditable).
     */
    public function acknowledgeStatus(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User && $this->isFamilyOf($user, $student), 403);

        $status = $this->currentStatus($student);
        abort_unless(
            in_array($status['status'], [StudentStatus::Corigent->value, StudentStatus::Amanat->value], true),
            422,
        );

        $termId = Term::query()->where('is_current', true)->value('id');
        abort_if($termId === null, 422);

        StatusAcknowledgement::updateOrCreate(
            ['student_id' => $student->id, 'term_id' => (int) $termId],
            [
                'acknowledged_by_user_id' => $user->id,
                'status' => $status['status'],
                'acknowledged_at' => now(),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('cabinet_flash.status_acknowledged'),
        ]);

        return back();
    }

    /**
     * Depunerea unei cereri tipice (§4.3): se generează PDF (stocat PRIVAT) și se transmite
     * secretariatului. Doar familia poate depune.
     */
    public function requestDocument(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User && $this->isFamilyOf($user, $student), 403);

        // Cererile cu interval (învoirea) CER perioada (DocumentRequestType::needsPeriod) — fără
        // ea, secretariatul primea o „cerere de învoire" care nu spune CÂND (neprocesabilă).
        $type = DocumentRequestType::tryFrom((string) $request->input('type'));
        $needsPeriod = $type?->needsPeriod() ?? false;

        // Contestația FĂRĂ context e neprocesabilă: cere NOTA contestată (disciplina, valoarea,
        // data, profesorul vin din notă — nu re-tastate). Detaliile sunt OBLIGATORII la TOATE
        // tipurile: o cerere fără motiv/destinație/temă e neprocesabilă și ar produce doar un
        // ping-pong (secretariatul respinge cerând detalii → familia redepune). Placeholderele
        // din formular ghidează CE se scrie la fiecare tip.
        $needsGrade = $type === DocumentRequestType::Contestatie;

        $data = $request->validate([
            'type' => ['required', new Enum(DocumentRequestType::class)],
            'grade_id' => [$needsGrade ? 'required' : 'nullable', 'integer'],
            'details' => ['required', 'string', 'max:1500'],
            // Învoirea e PROSPECTIVĂ (se cere înainte de absență); pentru absențe deja petrecute
            // există fluxul de motivare (§2.1) — simetric cu regula lui „doar trecut".
            'period_start' => [$needsPeriod ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            // Justificativ opțional (bilet medical, cererea școlii noi, foto lucrării) — PII de
            // minor, stocat PRIVAT, aceleași limite ca justificativul motivărilor.
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'details.required' => $type === DocumentRequestType::Contestatie
                ? __('cabinet_flash.contestation_details_required')
                : __('cabinet_flash.request_details_required'),
            'grade_id.required' => __('cabinet_flash.contestation_grade_required'),
            'period_start.after_or_equal' => __('cabinet_flash.invoire_past_period'),
        ]);

        // Anti-duplicat: o cerere PENDING de același tip pentru același elev nu se redepune —
        // fiecare submit costă un render mpdf sincron + notificări către secretariat.
        $pendingSameType = DocumentRequest::query()
            ->where('student_id', $student->id)
            ->where('type', $data['type'])
            ->where('status', RequestStatus::Pending)
            ->exists();

        if ($pendingSameType) {
            throw ValidationException::withMessages([
                'type' => __('cabinet_flash.request_duplicate_pending'),
            ]);
        }

        $payload = ['details' => $data['details'] ?? ''];
        if (! empty($data['period_start'])) {
            $payload['period_start'] = $data['period_start'];
            $payload['period_end'] = $data['period_end'] ?? $data['period_start'];
        }

        // Nota contestată se validează pe SERVER (a elevului, activă, fără corecție în așteptare)
        // și se ÎNGHEAȚĂ în payload ca snapshot: procesatorul analizează contextul, nu îl
        // reconstruiește; iar dacă nota se schimbă între timp, cererea păstrează ce s-a contestat.
        if ($needsGrade) {
            $contestedGrade = $this->resolveContestedGrade($student, (int) $data['grade_id']);

            $payload['grade_id'] = $contestedGrade->id;
            $payload['grade'] = [
                'subject' => (string) $contestedGrade->subject->name,
                'value' => $contestedGrade->value !== null ? (string) (float) $contestedGrade->value : null,
                'calificativ' => $contestedGrade->calificativ,
                'graded_on' => $contestedGrade->graded_on->format('d.m.Y'),
                'teacher' => $contestedGrade->teacher?->full_name,
            ];
        }

        // Justificativul (PII de minor) → stocare PRIVATĂ; un eșec de scriere NU se înghite tăcut
        // (cerere fără document + toast de succes) — același contract ca la motivări.
        $attachmentPath = null;
        $file = $request->file('attachment');
        if ($file instanceof UploadedFile) {
            $stored = $file->store('cereri/justificative', 'local');
            if ($stored === false) {
                throw ValidationException::withMessages([
                    'attachment' => __('cabinet_flash.attachment_upload_failed'),
                ]);
            }
            $attachmentPath = $stored;
        }

        // Cererea + PDF-ul într-o TRANZACȚIE: dacă randarea mpdf pică (tempDir, memorie), rândul se
        // dă înapoi → fără cerere PENDING orfană (fără PDF, dar care blochează redepunerea și a
        // notificat deja secretariatul). Notificarea observer-ului rulează afterCommit → nu anunță
        // o cerere anulată. (#37)
        DB::transaction(function () use ($data, $student, $user, $payload, $attachmentPath): void {
            $documentRequest = DocumentRequest::create([
                'type' => $data['type'],
                'student_id' => $student->id,
                'requested_by_user_id' => $user->id,
                'payload' => $payload,
                'attachment_path' => $attachmentPath,
            ]);

            app(GenerateRequestPdf::class)->generate($documentRequest);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('cabinet_flash.request_generated'),
        ]);

        return back();
    }

    /**
     * Descărcarea PDF-ului unei cereri — fișier PRIVAT (PII de minor): doar familia elevului sau
     * administrația. Niciodată URL public.
     */
    public function downloadRequest(Request $request, DocumentRequest $documentRequest, LogStudentAccess $accessLog): StreamedResponse
    {
        $user = $request->user('web');
        // Administrația ÎNTÂI (scurt-circuit) + gardă de null pe elev: fără ele, cererea unui elev
        // arhivat crăpa cu TypeError (isFamilyOf cere Student ne-nullable) pentru ORICINE — exact pe
        // cazul în care administrația trebuie să poată închide cererea unui elev plecat. Familia se
        // verifică INCLUSIV pe elevul arhivat — istoricul rămâne descărcabil după plecare.
        $student = $documentRequest->student;
        abort_unless(
            $user instanceof User
                && ($user->isAdministrator() || ($student !== null && $this->isFamilyOfIncludingArchived($user, $student))),
            403,
        );
        abort_unless(
            $documentRequest->pdf_path !== null && Storage::disk('local')->exists($documentRequest->pdf_path),
            404,
        );

        // Export de PII de minor (PDF) → jurnalizat indiferent de cine (L133 §7).
        if ($student !== null) {
            $accessLog->record($student, 'exported', 'Descărcare PDF cerere tipică');
        }

        return Storage::disk('local')->download(
            $documentRequest->pdf_path,
            'cerere-'.$documentRequest->type->value.'.pdf',
        );
    }

    /**
     * Descărcarea justificativului atașat unei cereri tipice — fișier PRIVAT (PII de minor):
     * familia elevului sau administrația (care procesează). Accesul se jurnalizează.
     */
    public function downloadRequestAttachment(Request $request, DocumentRequest $documentRequest, LogStudentAccess $accessLog): StreamedResponse
    {
        $user = $request->user('web');
        $student = $documentRequest->student;
        abort_unless(
            $user instanceof User
                && ($user->isAdministrator() || ($student !== null && $this->isFamilyOfIncludingArchived($user, $student))),
            403,
        );
        abort_unless(
            $documentRequest->attachment_path !== null && Storage::disk('local')->exists($documentRequest->attachment_path),
            404,
        );

        if ($student !== null) {
            $accessLog->record($student, 'exported', 'Descărcare justificativ cerere tipică');
        }

        return Storage::disk('local')->download(
            $documentRequest->attachment_path,
            'justificativ-'.$documentRequest->id.'.'.pathinfo($documentRequest->attachment_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * Familia își RETRAGE o cerere încă neprocesată (depusă greșit / rămasă fără obiect): soft
     * delete — rândul rămâne restaurabil, PDF-ul pe disc (igiena forceDeleted îl curăță doar la
     * ștergerea definitivă), iar anti-duplicatul se deblochează pentru o depunere corectă.
     * După decizie retragerea nu mai e posibilă (răspunsul secretariatului rămâne în istoric).
     */
    public function withdrawRequest(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        $user = $request->user('web');
        $student = $documentRequest->student;
        abort_unless(
            $user instanceof User && $student !== null && $this->isFamilyOf($user, $student),
            403,
        );
        abort_unless($documentRequest->status === RequestStatus::Pending, 422);

        $documentRequest->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('cabinet_flash.request_withdrawn'),
        ]);

        return back();
    }

    /**
     * Justificativul unei cereri de motivare — fișier PRIVAT (PII de minor): familia elevului,
     * dirigintele clasei sau administrația (validatorul excepțiilor — vicedirectorul pe educație —
     * e mereu un rol de administrație, {@see UserRole::audienceDomainHolderValues}). Niciodată URL
     * public. Accesul se jurnalizează. `?inline=1` servește fișierul inline (previzualizarea de pe
     * fișa cererii din panou).
     */
    public function downloadMotivationDocument(Request $request, AbsenceMotivation $absenceMotivation, LogStudentAccess $accessLog): StreamedResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 403);

        // Administrația întâi + gardă de null (elev hard-deleted) — aceeași apărare ca la cereri.
        // Familia inclusiv pe elevul arhivat: justificativele vechi rămân ale familiei.
        $student = $absenceMotivation->student;
        abort_unless(
            $user->isAdministrator()
                || ($student !== null && ($this->isFamilyOfIncludingArchived($user, $student)
                    || ($student->homeroomUser()?->is($user) ?? false))),
            403,
        );
        abort_unless(
            $absenceMotivation->document_path !== null && Storage::disk('local')->exists($absenceMotivation->document_path),
            404,
        );

        $inline = $request->boolean('inline');
        $accessLog->record($student, 'exported', $inline
            ? 'Previzualizare justificativ motivare'
            : 'Descărcare justificativ motivare');

        return $inline
            ? Storage::disk('local')->response($absenceMotivation->document_path)
            : Storage::disk('local')->download($absenceMotivation->document_path);
    }

    /**
     * Descarcă un document GENERAT per-elev (foaie matricolă, situația școlară) — produs LA CERERE din
     * datele catalogului, mereu actualizat (§3), NU stocat. Accesul e re-confirmat pe server: familia
     * (copilul propriu), administrația academică sau dirigintele clasei. Randare oficială în RO.
     */
    public function downloadGeneratedDocument(
        Request $request,
        Student $student,
        string $type,
        GenerateStudentDocumentPdf $pdf,
        LogStudentAccess $accessLog,
    ): StreamedResponse {
        $user = $request->user('web');
        abort_unless(
            $user instanceof User
                && ($this->isFamilyOf($user, $student)
                    || $user->isAdministrator()
                    || ($student->homeroomUser()?->is($user) ?? false)),
            403,
        );

        $documentType = GeneratedDocumentType::tryFrom($type);
        abort_if($documentType === null, 404);

        // Document oficial → randare consecventă în RO (antete + denumiri de discipline).
        app()->setLocale('ro');

        $content = $pdf->render($documentType, $this->generatedDocumentData($documentType, $student));

        $accessLog->record($student, 'exported', 'Descărcare document generat: '.$documentType->value);

        $fileName = $documentType->fileBase().'-'.Str::slug($student->full_name).'.pdf';

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $fileName,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Datele pentru un document generat, reutilizând helper-ele de date ale cabinetului (o singură sursă).
     *
     * @return array<string, mixed>
     */
    private function generatedDocumentData(GeneratedDocumentType $type, Student $student): array
    {
        $class = $student->currentSchoolClass();
        $className = $class !== null ? trim($class->name.' '.($class->section ?? '')) : null;

        if ($type === GeneratedDocumentType::Transcript) {
            $student->loadMissing('academicRecords.subject');

            // Foaia matricolă e documentul de circulație internațională → BILINGV RO/EN (Faza 4):
            // fiecare disciplină primește și numele englezesc (dicționarul `subjects`, fallback RO);
            // șablonul îl afișează sub numele RO doar când traducerea există și diferă.
            $levels = $this->transcript($student);

            foreach ($levels as &$level) {
                foreach ($level['subjects'] as &$subject) {
                    $en = ContentTranslator::subject((string) $subject['subject_ro'], 'en');
                    $subject['subject_en'] = $en !== $subject['subject_ro'] ? $en : null;
                }
                unset($subject);
            }
            unset($level);

            return [
                'studentName' => $student->full_name,
                'className' => $className,
                'levels' => $levels,
                'date' => SchoolCalendar::localNow()->format('d.m.Y'),
            ];
        }

        // Dosarul elevului (Faza 5) = situația semestrului curent + evoluția multi-anuală
        // (ComputeStudentDynamics — aceeași sursă ca dinamica din cabinet) într-un singur document.
        if ($type === GeneratedDocumentType::StudentFile) {
            return [
                ...$this->termSituationData($student, $className),
                'dynamics' => app(ComputeStudentDynamics::class)->for($student),
            ];
        }

        if ($type === GeneratedDocumentType::AbsenceReport) {
            return $this->absenceReportData($student, $className);
        }

        return $this->termSituationData($student, $className);
    }

    /**
     * Datele „Raportului absențelor": TOATE absențele anului școlar curent (anul semestrului
     * `is_current`), detaliate pe DATE și grupate pe semestre — completează situația școlară,
     * care agregă doar pe discipline. Fără semestru curent (vacanță), raportul iese gol — onest.
     *
     * @return array<string, mixed>
     */
    private function absenceReportData(Student $student, ?string $className): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first(['id', 'academic_year_id']);

        $terms = $currentTerm === null
            ? collect()
            : Term::query()
                ->where('academic_year_id', $currentTerm->academic_year_id)
                ->orderBy('number')
                ->get(['id', 'number']);

        $yearName = $currentTerm === null
            ? null
            : AcademicYear::query()->whereKey($currentTerm->academic_year_id)->value('name');

        $absences = $terms->isEmpty()
            ? collect()
            : $student->absences()
                ->whereIn('term_id', $terms->pluck('id'))
                ->with('subject')
                ->orderBy('occurred_on')
                ->get();

        $byTerm = $absences->groupBy('term_id');
        $wholeDayLabel = (string) __('site.cabinet.whole_day_absence');

        $sections = [];
        foreach ($terms as $term) {
            /** @var Collection<int, Absence> $rows */
            $rows = $byTerm->get($term->id, collect());

            $sections[] = [
                'label' => 'Semestrul '.((int) $term->number === 1 ? 'I' : 'II'),
                'rows' => $rows->map(fn (Absence $absence): array => [
                    'date' => $absence->occurred_on->format('d.m.Y'),
                    'subject' => $absence->subject !== null
                        ? ContentTranslator::subject((string) $absence->subject->name)
                        : $wholeDayLabel,
                    'motivated' => (bool) $absence->is_motivated,
                ])->values()->all(),
                'motivated' => $rows->where('is_motivated', true)->count(),
                'unmotivated' => $rows->where('is_motivated', false)->count(),
            ];
        }

        return [
            'studentName' => $student->full_name,
            'className' => $className,
            'yearLabel' => $yearName,
            'sections' => $sections,
            'total' => $absences->count(),
            'totalMotivated' => $absences->where('is_motivated', true)->count(),
            'totalUnmotivated' => $absences->where('is_motivated', false)->count(),
            'date' => SchoolCalendar::localNow()->format('d.m.Y'),
        ];
    }

    /**
     * Datele „Situației școlare" — SEMESTRUL CURENT: titlul promite un semestru, deci notele și
     * absențele se SCOPEAZĂ pe el (audit Documente: documentul oficial agrega toate
     * semestrele/anii, deși media și statusul de mai jos erau deja pe semestrul curent).
     * Relațiile se încarcă CONSTRÂNS — helper-ele (gradesBySubject/absencesBySubject)
     * iterează exact ce e încărcat. Fără semestru curent (vacanță), secțiunile ies goale —
     * onest și consecvent cu media/statusul. Refolosite și de „Dosarul elevului".
     *
     * @return array<string, mixed>
     */
    private function termSituationData(Student $student, ?string $className): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first(['id', 'number']);
        $currentTermId = $currentTerm === null ? 0 : (int) $currentTerm->id;

        $student->load([
            'grades' => fn ($query) => $query->where('term_id', $currentTermId),
            'grades.subject',
            'grades.term',
            'grades.schoolClass',
            'absences' => fn ($query) => $query->where('term_id', $currentTermId),
            'absences.subject',
        ]);
        $status = $this->currentStatus($student);

        return [
            'studentName' => $student->full_name,
            'className' => $className,
            'termLabel' => $currentTerm !== null
                ? 'Semestrul '.((int) $currentTerm->number === 1 ? 'I' : 'II')
                : 'Semestrul curent',
            'subjects' => $this->gradesBySubject($student),
            'absences' => $this->absencesBySubject($student),
            'absencesTotal' => $student->absences->count(),
            'average' => $this->summary($student)['average'],
            'statusLabel' => $status['label'],
            'statusOfficial' => $status['official'],
            'date' => SchoolCalendar::localNow()->format('d.m.Y'),
        ];
    }

    /**
     * Doar familia (tutorele atribuit sau elevul însuși) poate depune/vedea formularul de motivare.
     */
    private function isFamilyOf(User $user, Student $student): bool
    {
        return $user->students()->whereKey($student->id)->exists() || $student->user_id === $user->id;
    }

    /**
     * Familia, INCLUSIV pentru un elev ARHIVAT (plecat din liceu) — folosită DOAR la descărcarea
     * documentelor ISTORICE (PDF cerere, justificative): istoricul familiei rămâne al familiei și
     * după plecare. Relația tutore→elev trece prin global scope-ul SoftDeletes al elevului, deci
     * fără `withTrashed` părintele primea 403 pe actele vechi, în timp ce CONTUL elevului (legat
     * direct prin user_id) le putea descărca — asimetrie fără sens (decizie de produs 2026-07-18).
     * Depunerile/acțiunile active rămân pe {@see isFamilyOf} (doar elevi activi).
     */
    private function isFamilyOfIncludingArchived(User $user, Student $student): bool
    {
        return $user->students()->withTrashed()->whereKey($student->id)->exists()
            || $student->user_id === $user->id;
    }

    /**
     * Cererile tipice ale elevului (cele mai recente), pentru afișare + descărcare PDF în cabinet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentRequests(Student $student, bool $familyViewer = false): array
    {
        return DocumentRequest::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'type' => $request->type->label(),
                'date' => $request->created_at?->format('d.m.Y'),
                'status' => $request->status->value,
                'statusLabel' => $request->status->label(),
                'pdfUrl' => $request->pdf_path !== null ? route('cabinet.requests.pdf', $request) : null,
                // Justificativul atașat la depunere — descărcabil de familie (și administrație).
                'attachmentUrl' => $request->attachment_path !== null
                    ? route('cabinet.requests.attachment', $request)
                    : null,
                // Comentariul secretariatului la procesare/respingere — familia vede DE CE.
                'note' => $request->review_note,
                // Nota contestată (snapshot din depunere) — familia își recunoaște cererea.
                'grade' => $this->contestedGradeLabelFor($request),
                // Familia își poate retrage cererea cât e încă neprocesată.
                'canWithdraw' => $familyViewer && $request->status === RequestStatus::Pending,
            ])
            ->all();
    }

    /** Eticheta notei contestate pentru cabinet — disciplina TRADUSĂ în limba interfeței. */
    private function contestedGradeLabelFor(DocumentRequest $request): ?string
    {
        $snapshot = $request->contestedGradeSnapshot();

        if ($snapshot === null) {
            return null;
        }

        $snapshot['subject'] = ContentTranslator::subject($snapshot['subject']);

        return DocumentRequest::composeGradeLabel($snapshot);
    }

    /**
     * Notele pe care familia le poate contesta: active (neanulate), fără o corecție deja în
     * așteptare — aceleași reguli ca validarea de la depunere (resolveContestedGrade), ca UI-ul
     * și serverul să nu divergă. Eticheta identifică UNIC nota: disciplină — valoare (dată) · profesor.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function contestableGrades(Student $student): array
    {
        return $student->grades()
            ->active()
            ->whereDoesntHave('corrections', fn (Builder $query) => $query->where('status', CorrectionStatus::Pending))
            ->with(['subject', 'teacher'])
            ->orderByDesc('graded_on')
            ->get()
            ->map(fn (Grade $grade): array => [
                'id' => $grade->id,
                'label' => DocumentRequest::composeGradeLabel([
                    'subject' => ContentTranslator::subject((string) $grade->subject->name),
                    'value' => $grade->value !== null ? (string) (float) $grade->value : null,
                    'calificativ' => $grade->calificativ,
                    'graded_on' => $grade->graded_on->format('d.m.Y'),
                    'teacher' => $grade->teacher?->full_name,
                ]),
            ])
            ->all();
    }

    /**
     * Nota vizată de o contestație, validată pe server: a elevului, activă și fără o corecție
     * deja în așteptare. Erorile cad pe câmpul grade_id al formularului.
     */
    private function resolveContestedGrade(Student $student, int $gradeId): Grade
    {
        $grade = Grade::query()
            ->whereKey($gradeId)
            ->where('student_id', $student->id)
            ->active()
            ->with(['subject', 'teacher'])
            ->first();

        if ($grade === null) {
            throw ValidationException::withMessages([
                'grade_id' => __('cabinet_flash.contestation_grade_invalid'),
            ]);
        }

        $hasPendingCorrection = $grade->corrections()
            ->where('status', CorrectionStatus::Pending)
            ->exists();

        if ($hasPendingCorrection) {
            throw ValidationException::withMessages([
                'grade_id' => __('cabinet_flash.contestation_grade_pending'),
            ]);
        }

        return $grade;
    }

    /**
     * Statutul preliminar al elevului în semestrul curent (§2.5), din mediile calculate.
     *
     * @return array{status: string|null, label: string|null, failingSubjects: array<int, string>, official: bool, orderReference: string|null}
     */
    private function currentStatus(Student $student): array
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return ['status' => null, 'label' => null, 'failingSubjects' => [], 'official' => false, 'orderReference' => null];
        }

        $result = app(DetermineStudentStatus::class)->forTerm($student->id, (int) $currentTermId);
        $failing = array_map(
            fn (string $subject): string => ContentTranslator::subject($subject),
            $result['failingSubjects'],
        );

        // Statutul OFICIAL validat de conducere (Consiliul prof. + ordin) primează (spec §2.5 / #33).
        $validated = SemesterValidation::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->first();

        if ($validated !== null) {
            return [
                'status' => $validated->status->value,
                'label' => $validated->status->label(),
                'failingSubjects' => $failing,
                'official' => true,
                'orderReference' => $validated->order_reference,
            ];
        }

        return [
            'status' => $result['status']?->value,
            'label' => $result['status']?->label(),
            'failingSubjects' => $failing,
            'official' => false,
            'orderReference' => null,
        ];
    }

    /**
     * Starea confirmării de „luare la cunoștință" a statutului corigent/amânat (spec pct. 108–109):
     * dacă e necesară, dacă a fost deja confirmată (cu data) și dacă familia o poate confirma acum.
     *
     * @param  array<string, mixed>  $status
     * @return array{needed: bool, acknowledged: bool, acknowledgedAt: string|null, acknowledgedBy: string|null, canAcknowledge: bool}
     */
    private function statusAcknowledgement(Student $student, ?User $viewer, array $status): array
    {
        $needed = in_array($status['status'], [StudentStatus::Corigent->value, StudentStatus::Amanat->value], true);

        if (! $needed) {
            return ['needed' => false, 'acknowledged' => false, 'acknowledgedAt' => null, 'acknowledgedBy' => null, 'canAcknowledge' => false];
        }

        $termId = Term::query()->where('is_current', true)->value('id');
        $ack = $termId !== null
            ? StatusAcknowledgement::query()->with('acknowledgedBy')->where('student_id', $student->id)->where('term_id', $termId)->first()
            : null;

        $acknowledged = $ack !== null && $ack->status->value === $status['status'];

        $acknowledgedAt = null;
        $acknowledgedBy = null;
        if ($ack !== null && $acknowledged) {
            $acknowledgedAt = SchoolCalendar::local($ack->acknowledged_at)?->format('d.m.Y H:i');
            // CINE a confirmat — părintele nu poate distinge altfel confirmarea proprie de cea făcută
            // de elevul (minor) însuși (isFamilyOf include contul elevului). Transparență (#37).
            $acknowledgedBy = $ack->acknowledgedBy?->name;
        }

        $isFamily = $viewer instanceof User && $this->isFamilyOf($viewer, $student);

        return [
            'needed' => true,
            'acknowledged' => $acknowledged,
            'acknowledgedAt' => $acknowledgedAt,
            'acknowledgedBy' => $acknowledgedBy,
            'canAcknowledge' => $isFamily && ! $acknowledged,
        ];
    }

    /**
     * Foaia matricolă: pe fiecare treaptă (descrescător), media pe disciplină
     * la semestrul I, semestrul II și anuală.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transcript(Student $student): array
    {
        $levels = [];
        foreach ($student->academicRecords->groupBy('grade_level') as $gradeLevel => $records) {
            $subjects = [];
            // Pe subject_id (nu pe nume) — două discipline omonime pe aceeași treaptă și-ar
            // suprascrie reciproc perioadele (Sem I/II/anuală) în rândul contopit.
            foreach ($records->groupBy('subject_id') as $items) {
                $byPeriod = [];
                foreach ($items as $record) {
                    $byPeriod[$record->period->value] = $record->value !== null
                        ? (string) Grades::truncate2((float) $record->value)
                        : ($record->calificativ ?: null);
                }
                $subjects[] = [
                    'subject' => ContentTranslator::subject((string) $items->first()->subject->name),
                    // Numele RO canonic (netradus) — cheia dicționarelor; PDF-ul bilingv derivă EN din el.
                    'subject_ro' => (string) $items->first()->subject->name,
                    'sem1' => $byPeriod[AcademicRecordPeriod::SemesterI->value] ?? null,
                    'sem2' => $byPeriod[AcademicRecordPeriod::SemesterII->value] ?? null,
                    'annual' => $byPeriod[AcademicRecordPeriod::Annual->value] ?? null,
                ];
            }
            $levels[] = ['grade_level' => (int) $gradeLevel, 'subjects' => $subjects];
        }
        usort($levels, fn (array $a, array $b): int => $b['grade_level'] <=> $a['grade_level']);

        return $levels;
    }

    /**
     * Rezumat afișabil pentru un elev (card).
     *
     * @return array<string, mixed>
     */
    private function summary(Student $student): array
    {
        // Definiția canonică a clasei curente (academic_year_id, nu id) — vezi nota din cockpitCard.
        $class = $student->currentSchoolClass();

        // Media generală = media mediilor semestriale calculate (nu a notelor brute).
        $averages = $this->semesterAverages($student);
        $overall = $averages->isNotEmpty()
            ? Grades::truncate2((float) $averages->avg(fn (TermAverage $ta): float => (float) $ta->value))
            : null;

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'class' => $class ? trim($class->name.' '.($class->section ?? '')) : null,
            'grades_count' => $student->grades()->count(),
            'absences_count' => $student->absences()->count(),
            'average' => $overall,
            // Data plecării (dacă a plecat) — cabinetul semnalează „elev plecat", coerent cu
            // calendarul/rapoartele care îl taie la left_on (#37).
            'departedOn' => $student->departedOn()?->format('d.m.Y'),
        ];
    }

    /**
     * Calendarul de lichidare a corigenței al elevului (spec §2.5 / #33): disciplina restantă,
     * sezonul, data și comisia (când sunt programate). Vizibil familiei și dirigintelui.
     *
     * @return array<int, array<string, mixed>>
     */
    private function corigentaExams(Student $student): array
    {
        return CorigentaExam::query()
            ->where('student_id', $student->id)
            ->with(['subject', 'commission', 'session'])
            ->orderByDesc('id')
            ->get()
            ->map(function (CorigentaExam $exam): array {
                // Sesiunea trece prin draft → aprobată prin ordin → PUBLICATĂ, iar publicarea e
                // exact pasul care o face vizibilă familiilor. Până atunci data și comisia sunt
                // propuneri de lucru: arătate devreme, familia își face planuri pe un calendar
                // care se poate încă schimba (sau care n-a fost aprobat deloc). Disciplina
                // restantă rămâne vizibilă — vine din propriile medii ale elevului.
                $scheduled = $exam->session?->isPublished() ?? false;

                return [
                    'id' => $exam->id,
                    'subject' => ContentTranslator::subject((string) $exam->subject->name),
                    'season' => $exam->season->label(),
                    'scheduledOn' => $scheduled ? $exam->scheduled_on?->format('d.m.Y') : null,
                    'commission' => $scheduled ? $exam->commission?->name : null,
                    'sessionType' => $scheduled ? $exam->session->type->label() : null,
                    'mark' => $exam->mark,
                    'passed' => $exam->isPassed(),
                ];
            })
            ->all();
    }
}
