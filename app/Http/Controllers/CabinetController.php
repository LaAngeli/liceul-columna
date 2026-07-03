<?php

namespace App\Http\Controllers;

use App\Actions\ComputeDeferralRisk;
use App\Actions\ComputeStudentDynamics;
use App\Actions\DetermineStudentStatus;
use App\Actions\GenerateRequestPdf;
use App\Actions\LogStudentAccess;
use App\Enums\AcademicRecordPeriod;
use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Enums\SchoolCycle;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicRecord;
use App\Models\CorigentaExam;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\SemesterValidation;
use App\Models\StatusAcknowledgement;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\ContentTranslator;
use App\Support\Grades;
use App\Support\Timetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetController extends Controller
{
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
                    $sub->selectRaw('MAX(id)')
                        ->from('grades')
                        ->whereIn('student_id', $studentIds)
                        ->whereNull('annulled_at')
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
                ? round($avgs->avg(fn (TermAverage $a): float => (float) $a->value), 2)
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
        $class = $student->enrollments->sortByDesc('id')->first()?->schoolClass;

        $statusValue = $status['status'] ?? null;

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'class' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
            'average' => $overallAverage,
            'trend' => $trend,
            'statusValue' => $statusValue,
            'isAtRisk' => in_array(
                $statusValue,
                [StudentStatus::Corigent->value, StudentStatus::Amanat->value],
                true,
            ),
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
            $isAtRisk = in_array(
                $statusValue,
                [StudentStatus::Corigent->value, StudentStatus::Amanat->value],
                true,
            );
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
        if ($viewer instanceof User && ! $this->isFamilyOf($viewer, $student)) {
            $accessLog->record($student, 'viewed', 'Vizualizare profil elev în cabinet');
        }

        $class = $student->currentSchoolClass();
        $status = $this->currentStatus($student);
        $canRequestMotivation = $viewer instanceof User && $this->isFamilyOf($viewer, $student);

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

            // === Defer (vin progresiv într-un al 2-lea request după mount) ===
            // Tab „Situație" — note + absențe + motivări
            'subjects' => Inertia::defer(fn (): array => $this->gradesBySubject($student)),
            'absencesBySubject' => Inertia::defer(fn (): array => $this->absencesBySubject($student)),
            'deferralRisk' => Inertia::defer(fn (): array => app(ComputeDeferralRisk::class)->for($student)),
            'motivations' => Inertia::defer(fn (): array => $this->motivations($student)),

            // Tab „Orar & teme"
            'timetable' => Inertia::defer(fn (): ?array => $class !== null
                ? app(Timetable::class)->forClass($class)
                : null
            ),
            'lessonsSchedule' => Inertia::defer(fn (): ?array => $this->lessonsSchedule($class)),
            'homework' => Inertia::defer(fn (): array => $this->homeworkForStudent($student)),

            // Tab „Istoric" — dinamică multi-an + foaia matricolă
            'dynamics' => Inertia::defer(fn (): array => app(ComputeStudentDynamics::class)->for($student)),
            'transcript' => Inertia::defer(fn (): array => $this->transcript($student)),

            // Tab „Cereri" — cereri tipice + lichidare corigență
            'documentRequests' => Inertia::defer(fn (): array => $this->documentRequests($student)),
            'corigentaExams' => Inertia::defer(fn (): array => $this->corigentaExams($student)),
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

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Justificativul (adeverință etc.) e PII de minor → stocare PRIVATĂ, niciodată public.
        $documentPath = null;
        $file = $request->file('document');
        if ($file instanceof UploadedFile) {
            $documentPath = $file->store('motivations', 'local') ?: null;
        }

        // Excepție = cererea acoperă absențe deja consolidate sau cu termenul de depunere (5 zile
        // lucrătoare) depășit. O astfel de cerere se aprobă de vicedirectorul pe educație, nu de diriginte.
        $isException = Absence::query()
            ->where('student_id', $student->id)
            ->whereBetween('occurred_on', [$data['period_start'], $data['period_end']])
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

        $data = $request->validate([
            'type' => ['required', new Enum(DocumentRequestType::class)],
            'details' => ['nullable', 'string', 'max:1500'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $payload = ['details' => $data['details'] ?? ''];
        if (! empty($data['period_start'])) {
            $payload['period_start'] = $data['period_start'];
            $payload['period_end'] = $data['period_end'] ?? $data['period_start'];
        }

        $documentRequest = DocumentRequest::create([
            'type' => $data['type'],
            'student_id' => $student->id,
            'requested_by_user_id' => $user->id,
            'payload' => $payload,
        ]);

        app(GenerateRequestPdf::class)->generate($documentRequest);

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
        abort_unless(
            $user instanceof User
                && ($this->isFamilyOf($user, $documentRequest->student) || $user->isAdministrator()),
            403,
        );
        abort_unless(
            $documentRequest->pdf_path !== null && Storage::disk('local')->exists($documentRequest->pdf_path),
            404,
        );

        // Export de PII de minor (PDF) → jurnalizat indiferent de cine (L133 §7).
        $accessLog->record($documentRequest->student, 'exported', 'Descărcare PDF cerere tipică');

        return Storage::disk('local')->download(
            $documentRequest->pdf_path,
            'cerere-'.$documentRequest->type->value.'.pdf',
        );
    }

    /**
     * Descărcarea justificativului unei cereri de motivare — fișier PRIVAT (PII de minor): familia
     * elevului, dirigintele clasei sau administrația. Niciodată URL public. Accesul se jurnalizează.
     */
    public function downloadMotivationDocument(Request $request, AbsenceMotivation $absenceMotivation, LogStudentAccess $accessLog): StreamedResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 403);

        $student = $absenceMotivation->student;
        abort_unless(
            $this->isFamilyOf($user, $student)
                || $user->isAdministrator()
                || ($student->homeroomUser()?->is($user) ?? false),
            403,
        );
        abort_unless(
            $absenceMotivation->document_path !== null && Storage::disk('local')->exists($absenceMotivation->document_path),
            404,
        );

        $accessLog->record($student, 'exported', 'Descărcare justificativ motivare');

        return Storage::disk('local')->download($absenceMotivation->document_path);
    }

    /**
     * Doar familia (tutorele atribuit sau elevul însuși) poate depune/vedea formularul de motivare.
     */
    private function isFamilyOf(User $user, Student $student): bool
    {
        return $user->students()->whereKey($student->id)->exists() || $student->user_id === $user->id;
    }

    /**
     * Orarul „lecții" PUBLIC al clasei (legat prin canonizare), în forma {label, headers, rows} —
     * orarul bogat (cu ore) afișat în cabinet, complementar orarului structurat. Date la nivel de
     * clasă (fără PII); doar dacă e publicat.
     *
     * @return array{label: string, headers: list<string>, rows: list<list<string>>}|null
     */
    private function lessonsSchedule(?SchoolClass $class): ?array
    {
        $schedule = $class?->lessonsSchedule;

        if ($schedule === null || ! $schedule->is_public) {
            return null;
        }

        return [
            'label' => $schedule->label,
            'headers' => array_values($schedule->headers),
            'rows' => array_values(array_map(
                static fn (array $row): array => array_values($row),
                $schedule->rows,
            )),
        ];
    }

    /**
     * Cererile de motivare ale elevului (cele mai recente), pentru afișare în cabinet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function motivations(Student $student): array
    {
        return AbsenceMotivation::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AbsenceMotivation $motivation): array => [
                'id' => $motivation->id,
                'reason' => $motivation->reason,
                'period' => $motivation->period_start->format('d.m.Y').' – '.$motivation->period_end->format('d.m.Y'),
                'status' => $motivation->status->value,
                'statusLabel' => $motivation->status->label(),
                'isException' => $motivation->is_exception,
                'documentUrl' => $motivation->document_path !== null
                    ? route('cabinet.motivation.document', ['absenceMotivation' => $motivation->id], false)
                    : null,
            ])
            ->all();
    }

    /**
     * Cererile tipice ale elevului (cele mai recente), pentru afișare + descărcare PDF în cabinet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentRequests(Student $student): array
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
            ])
            ->all();
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
     * @return array{needed: bool, acknowledged: bool, acknowledgedAt: string|null, canAcknowledge: bool}
     */
    private function statusAcknowledgement(Student $student, ?User $viewer, array $status): array
    {
        $needed = in_array($status['status'], [StudentStatus::Corigent->value, StudentStatus::Amanat->value], true);

        if (! $needed) {
            return ['needed' => false, 'acknowledged' => false, 'acknowledgedAt' => null, 'canAcknowledge' => false];
        }

        $termId = Term::query()->where('is_current', true)->value('id');
        $ack = $termId !== null
            ? StatusAcknowledgement::query()->where('student_id', $student->id)->where('term_id', $termId)->first()
            : null;

        $acknowledged = $ack !== null && $ack->status->value === $status['status'];

        $acknowledgedAt = null;
        if ($ack !== null && $acknowledged) {
            $acknowledgedAt = $ack->acknowledged_at->format('d.m.Y H:i');
        }

        $isFamily = $viewer instanceof User && $this->isFamilyOf($viewer, $student);

        return [
            'needed' => true,
            'acknowledged' => $acknowledged,
            'acknowledgedAt' => $acknowledgedAt,
            'canAcknowledge' => $isFamily && ! $acknowledged,
        ];
    }

    /**
     * Notele grupate pe disciplină, cu media SEMESTRIALĂ calculată oficial (term_averages),
     * nu o medie aritmetică brută — vezi §2.4 din specificație.
     *
     * @return array<int, array<string, mixed>>
     */
    private function gradesBySubject(Student $student): array
    {
        $averages = $this->semesterAverages($student);

        // Notele anulate (§1) nu apar în cabinet.
        $activeGrades = $student->grades->whereNull('annulled_at');

        $subjects = [];
        foreach ($activeGrades->groupBy(fn (Grade $grade): string => $grade->subject->name) as $name => $items) {
            $subjectId = (int) $items->first()->subject_id;
            $ms = $averages->get($subjectId);
            $subjects[] = [
                'subject' => ContentTranslator::subject((string) $name),
                'average' => $ms !== null && $ms->value !== null ? (float) $ms->value : null,
                // Componentele MS, pentru transparență (§1.3): media curentelor + sumativa semestrială.
                'mc' => $ms !== null && $ms->mc_value !== null ? (float) $ms->mc_value : null,
                'summative' => $ms !== null && $ms->summative_value !== null ? (float) $ms->summative_value : null,
                'items' => $items->map(fn (Grade $grade): array => [
                    'value' => $grade->value,
                    'calificativ' => $grade->calificativ,
                    'date' => $grade->graded_on->format('d.m.Y'),
                    'term' => $grade->term->number,
                    // Tipul notei cu etichetă pe ciclu (ESS/teză) + dacă e sumativa ponderată — badge distinct.
                    'type' => $grade->evaluation_type->value,
                    'typeLabel' => $grade->evaluation_type->labelForCycle(
                        SchoolCycle::fromGradeLevel((int) $grade->schoolClass->grade_level)
                    ),
                    'isSummative' => $grade->evaluation_type->isWeighted(),
                ])->all(),
            ];
        }

        return $subjects;
    }

    /**
     * Mediile semestriale (cache term_averages) pentru semestrul curent, indexate pe subject_id.
     * Modelele COMPLETE (nu doar valoarea) — ca să putem expune și componentele MC/sumativă.
     *
     * @return Collection<int, TermAverage>
     */
    private function semesterAverages(Student $student): Collection
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return collect();
        }

        return TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->get()
            ->keyBy('subject_id');
    }

    /**
     * Absențele numărate pe disciplină (descrescător).
     *
     * @return array<int, array{subject: string, count: int}>
     */
    private function absencesBySubject(Student $student): array
    {
        $absences = [];
        foreach ($student->absences->groupBy(fn (Absence $absence): string => $absence->subject->name) as $name => $items) {
            $absences[] = ['subject' => ContentTranslator::subject((string) $name), 'count' => $items->count()];
        }
        usort($absences, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $absences;
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
            foreach ($records->groupBy(fn (AcademicRecord $record): string => $record->subject->name) as $name => $items) {
                $byPeriod = [];
                foreach ($items as $record) {
                    $byPeriod[$record->period->value] = $record->value !== null
                        ? (string) round((float) $record->value, 2)
                        : ($record->calificativ ?: null);
                }
                $subjects[] = [
                    'subject' => ContentTranslator::subject((string) $name),
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
     * Temele clasei curente a elevului (după treaptă + literă), cele mai recente întâi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function homeworkForStudent(Student $student): array
    {
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;

        if (! $class) {
            return [];
        }

        return HomeworkAssignment::query()
            ->where('grade_level', $class->grade_level)
            ->where(function (Builder $query) use ($class): void {
                $query->where('section', $class->section)->orWhereNull('section');
            })
            ->orderByDesc('assigned_on')
            ->limit(25)
            ->get()
            ->map(fn (HomeworkAssignment $homework): array => [
                'id' => $homework->id,
                'date' => $homework->assigned_on->format('d.m.Y'),
                'subject' => ContentTranslator::subject((string) $homework->subject_name),
                'topic' => $homework->topic,
                'required' => $homework->required_task,
                'optional' => $homework->optional_task,
                'links' => $homework->links ?? [],
            ])
            ->all();
    }

    /**
     * Rezumat afișabil pentru un elev (card).
     *
     * @return array<string, mixed>
     */
    private function summary(Student $student): array
    {
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;

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
            ->map(fn (CorigentaExam $exam): array => [
                'id' => $exam->id,
                'subject' => ContentTranslator::subject((string) $exam->subject->name),
                'season' => $exam->season->label(),
                'scheduledOn' => $exam->scheduled_on?->format('d.m.Y'),
                'commission' => $exam->commission?->name,
                'sessionType' => $exam->session?->type->label(),
                'mark' => $exam->mark,
                'passed' => $exam->isPassed(),
            ])
            ->all();
    }
}
