<?php

namespace Database\Seeders;

use App\Actions\BroadcastAnnouncement;
use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Announcement;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\DocumentRequest;
use App\Models\ExamCommission;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\Lesson;
use App\Models\Message;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Date de TEST (random) pentru secțiunile noi/goale ale catalogului: corecții de notă și
 * cereri de motivare. Construite peste date reale (note/elevi importați), legate de conturile
 * demo, ca să se poată testa atât cabinetul, cât și panourile.
 *
 * Toate sunt marcate „[DEMO]" în motiv → ștergere curată:
 *   GradeCorrection::where('reason','like','[DEMO]%')->delete();
 *   AbsenceMotivation::where('reason','like','[DEMO]%')->delete();
 *
 * Idempotent: șterge întâi orice [DEMO] existent, apoi regenerează.
 * Necesită: `app:import-legacy` (date) + `db:seed --class=DemoAccountsSeeder` (conturi).
 */
class DemoTestDataSeeder extends Seeder
{
    private const MARKER = '[DEMO]';

    /** Ordinul sesiunii-suport pentru comisiile demo (distinct de sesiunea din CalendarDemoSeeder). */
    private const COMMISSION_SESSION = '[DEMO] Sesiune comisii';

    public function run(): void
    {
        if (Student::query()->count() === 0) {
            $this->command->warn('Nu există date de catalog — rulează `app:import-legacy` întâi.');

            return;
        }

        GradeCorrection::query()->where('reason', 'like', self::MARKER.'%')->delete();
        AbsenceMotivation::query()->where('reason', 'like', self::MARKER.'%')->delete();
        Message::query()->where('body', 'like', self::MARKER.'%')->forceDelete();
        // Anunțurile demo + notificările DIFUZATE pentru ele (titlul/corpul sunt COPIATE în
        // payload — fără ștergerea notificărilor, re-seedarea ar dubla anunțul în inboxuri).
        $demoAnnouncementIds = Announcement::withTrashed()
            ->where('title', 'like', self::MARKER.'%')
            ->pluck('id');
        DatabaseNotification::query()->whereIn('data->announcement_id', $demoAnnouncementIds)->delete();
        Announcement::withTrashed()->whereKey($demoAnnouncementIds)->forceDelete();
        // Corecțiile de teme ÎNAINTEA temelor (aparțin temelor prin FK); temele [DEMO] definitiv —
        // au SoftDeletes, iar un delete simplu le-ar lăsa marcate în tabel la fiecare re-seedare.
        HomeworkCorrection::query()->where('reason', 'like', self::MARKER.'%')->delete();
        HomeworkAssignment::withTrashed()->where('topic', 'like', self::MARKER.'%')->forceDelete();
        // forceDelete PE MODEL (nu pe query): observer-ul curăță PDF/justificativ dacă există.
        DocumentRequest::withTrashed()
            ->where('payload->details', 'like', self::MARKER.'%')
            ->get()
            ->each(fn (DocumentRequest $request) => $request->forceDelete());

        // Comisiile + sesiunea-suport demo: examenele ÎNTÂI (referă sesiunea și comisiile prin
        // FK nullOnDelete — șterse după, ar rămâne orfane cu comisia null și s-ar dubla la
        // re-seedare), apoi sesiunea proprie și comisiile [DEMO].
        $demoCommissionIds = ExamCommission::query()->where('name', 'like', self::MARKER.'%')->pluck('id');
        $demoSessionIds = CorigentaSession::query()->where('order_reference', self::COMMISSION_SESSION)->pluck('id');
        CorigentaExam::query()
            ->where(function ($query) use ($demoCommissionIds, $demoSessionIds): void {
                $query->whereIn('exam_commission_id', $demoCommissionIds)
                    ->orWhereIn('corigenta_session_id', $demoSessionIds);
            })
            ->delete();
        CorigentaSession::query()->whereKey($demoSessionIds)->delete();
        ExamCommission::query()->whereKey($demoCommissionIds)->delete();

        $this->seedRoleAccounts();
        $this->seedCorrections();
        $this->seedMotivations();
        $this->seedMessages();
        $this->seedDocumentRequests();
        $this->seedLessons();
        $this->seedAnnouncements();
        $this->seedHomeworkCorrections();
        $this->seedExamCommissions();
    }

    /**
     * Comisii de examen DEMO, pe toate stările care contează operațional (spec §2.5):
     *  - COMPLETĂ și FOLOSITĂ (președinte + 2 membri, cu examene de corigență alocate și programate);
     *  - COMPLETĂ dar fără examene (pregătită din timp);
     *  - INCOMPLETĂ (fără președinte, un singur membru) — starea care cere atenție;
     *  - plus o disciplină CU examene dar FĂRĂ comisie — golul de acoperire pe care secțiunea
     *    trebuie să-l scoată la suprafață.
     *
     * Examenele-suport stau într-o sesiune proprie ([DEMO] în ordin), pe elevii demo — nu ating
     * corigențele reale. Componența = fișe REALE de profesori (ca formularele și listele să arate
     * nume adevărate).
     */
    private function seedExamCommissions(): void
    {
        $term = Term::query()->where('is_current', true)->first();
        $yearId = $term?->academic_year_id;

        $students = Student::query()
            ->whereIn('user_id', User::query()->whereIn('email', ['elev@columna.test', 'elev2@columna.test'])->pluck('id'))
            ->orderBy('id')
            ->get();

        $teachers = Teacher::query()
            ->where('last_name', 'not like', self::MARKER.'%')
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($term === null || $yearId === null || $students->count() < 2 || $teachers->count() < 5) {
            $this->command->warn('Fără semestru curent / elevi demo / fișe de profesori — sar peste comisiile demo.');

            return;
        }

        $subjectByName = fn (string $name): ?Subject => Subject::query()
            ->where('name', $name)
            ->orderBy('id')
            ->first();

        $math = $subjectByName('Matematică');
        $romanian = $subjectByName('Limba și literatura română');
        $physics = $subjectByName('Fizică');
        $uncovered = Subject::query()
            ->whereNotIn('id', collect([$math?->id, $romanian?->id, $physics?->id])->filter()->all())
            ->orderBy('id')
            ->first();

        if ($math === null || $romanian === null || $physics === null || $uncovered === null) {
            $this->command->warn('Nomenclatorul de discipline e incomplet — sar peste comisiile demo.');

            return;
        }

        // Sesiunea-suport (publicată — examenele ei sunt „în lucru", nu ipotetice).
        $session = CorigentaSession::query()->create([
            'academic_year_id' => $yearId,
            'season' => CorigentaSeason::cases()[0]->value,
            'type' => CorigentaSessionType::cases()[0]->value,
            'starts_on' => Carbon::now()->addDays(10)->toDateString(),
            'ends_on' => Carbon::now()->addDays(17)->toDateString(),
            'status' => CorigentaSessionStatus::Published->value,
            'order_reference' => self::COMMISSION_SESSION,
        ]);

        [$first, $second] = [$students[0], $students[1]];
        $season = $session->season->value;

        $makeExam = fn (Student $student, Subject $subject, ?int $commissionId, ?string $scheduledOn) => CorigentaExam::query()->updateOrCreate(
            ['student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $term->id],
            [
                'season' => $season,
                'corigenta_session_id' => $session->id,
                'exam_commission_id' => $commissionId,
                'scheduled_on' => $scheduledOn,
            ],
        );

        // 1) Matematică: comisie COMPLETĂ + 2 examene alocate ei, programate.
        $mathCommission = ExamCommission::query()->create([
            'academic_year_id' => $yearId,
            'subject_id' => $math->id,
            'name' => self::MARKER.' Comisia de matematică',
            'president_teacher_id' => $teachers[0]->id,
        ]);
        $mathCommission->members()->sync([$teachers[1]->id, $teachers[2]->id]);
        $makeExam($first, $math, $mathCommission->id, $session->starts_on->toDateString());
        $makeExam($second, $math, $mathCommission->id, $session->starts_on->toDateString());

        // 2) Limba română: comisie COMPLETĂ, fără examene alocate (pregătită din timp).
        $romanianCommission = ExamCommission::query()->create([
            'academic_year_id' => $yearId,
            'subject_id' => $romanian->id,
            'name' => self::MARKER.' Comisia de limba și literatura română',
            'president_teacher_id' => $teachers[3]->id,
        ]);
        $romanianCommission->members()->sync([$teachers[4]->id, $teachers[1]->id]);

        // 3) Fizică: comisie INCOMPLETĂ (fără președinte, un singur membru) + un examen încă
        //    NEatribuit vreunei comisii.
        $physicsCommission = ExamCommission::query()->create([
            'academic_year_id' => $yearId,
            'subject_id' => $physics->id,
            'name' => self::MARKER.' Comisia de fizică',
            'president_teacher_id' => null,
        ]);
        $physicsCommission->members()->sync([$teachers[2]->id]);
        $makeExam($first, $physics, null, null);

        // 4) Disciplină cu examen dar FĂRĂ comisie → golul de acoperire.
        $makeExam($second, $uncovered, null, null);

        $this->command->info(
            'Comisii demo: completă+folosită (matematică), completă (lb. română), incompletă (fizică) '
            ."+ examen fără comisie ({$uncovered->name})."
        );
    }

    /**
     * Corecții de TEME demo, pe toate stările judecății: una ÎN AȘTEPTARE (alimentează badge-ul
     * aprobatorilor), una APROBATĂ prin fluxul real ({@see HomeworkCorrection::approve()} — tema
     * chiar se modifică, iar arhiva păstrează vechi → nou) și una RESPINSĂ cu motiv consemnat.
     *
     * Temele-suport sunt create tot aici, marcate [DEMO] în subiect și semnate de profesorul demo:
     * corecțiile pe teme REALE ar fi alterat, la aprobarea de test, conținutul văzut de familii.
     */
    private function seedHomeworkCorrections(): void
    {
        $requester = User::query()->where('email', 'profesor@columna.test')->first();
        $reviewer = User::query()->where('email', 'vicedirector@columna.test')->first()
            ?? User::query()->where('email', 'admin@liceul-columna.test')->first();
        $teacher = $requester?->teacher;

        if ($requester === null || $reviewer === null || $teacher === null) {
            $this->command->warn('Fără profesor/aprobator demo — sar peste corecțiile de teme.');

            return;
        }

        $assignment = $teacher->teachingAssignments()->with(['schoolClass', 'subject'])->first();
        $class = $assignment?->schoolClass;
        $subject = $assignment?->subject;

        if ($class === null || $subject === null) {
            $this->command->warn('Profesorul demo nu are alocări — sar peste corecțiile de teme.');

            return;
        }

        $makeHomework = fn (string $topic, string $task): HomeworkAssignment => HomeworkAssignment::query()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'subject_name' => $subject->name,
            'author_name' => $teacher->full_name,
            'grade_level' => $class->grade_level,
            'section' => $class->section,
            'assigned_on' => Carbon::now()->subDays(3)->toDateString(),
            'due_on' => Carbon::now()->addDays(4)->toDateString(),
            'topic' => $topic,
            'required_task' => $task,
        ]);

        // 1) ÎN AȘTEPTARE — exercițiile propuse diferă; aprobatorii au ce judeca.
        $pendingHomework = $makeHomework(self::MARKER.' Fracții ordinare — recapitulare', 'Ex. 1–4, pag. 52');
        HomeworkCorrection::query()->create([
            'homework_assignment_id' => $pendingHomework->id,
            'requested_by_user_id' => $requester->id,
            'old_required_task' => $pendingHomework->required_task,
            'new_required_task' => 'Ex. 1–6, pag. 52 (am omis două exerciții la dictare)',
            'reason' => self::MARKER.' Am transcris greșit lista de exerciții din planificare.',
        ]);

        // 2) APROBATĂ — prin fluxul real: tema se modifică, arhiva reține vechi → nou.
        $approvedHomework = $makeHomework(self::MARKER.' Ecuații de gradul I', 'Fișa de lucru nr. 3');
        $approved = HomeworkCorrection::query()->create([
            'homework_assignment_id' => $approvedHomework->id,
            'requested_by_user_id' => $requester->id,
            'old_topic' => $approvedHomework->topic,
            'new_topic' => self::MARKER.' Ecuații de gradul I cu o necunoscută',
            'reason' => self::MARKER.' Titlul trunchiat deruta elevii la căutarea lecției.',
        ]);
        $approved->approve($reviewer->id, self::MARKER.' Corect — titlul complet e cel din manual.');

        // 3) RESPINSĂ — tema rămâne neatinsă, motivul respingerii e consemnat.
        $rejectedHomework = $makeHomework(self::MARKER.' Probleme cu procente', 'Problemele 7–9, pag. 60');
        $rejected = HomeworkCorrection::query()->create([
            'homework_assignment_id' => $rejectedHomework->id,
            'requested_by_user_id' => $requester->id,
            'old_required_task' => $rejectedHomework->required_task,
            'new_required_task' => 'Problemele 7–12, pag. 60',
            'reason' => self::MARKER.' Aș vrea să extind tema cu încă trei probleme.',
        ]);
        $rejected->reject($reviewer->id, self::MARKER.' Extinderea după publicare ar surprinde familiile — depune o temă nouă.');

        $this->command->info('Corecții de teme demo: 1 în așteptare + 1 aprobată + 1 respinsă (pe teme [DEMO] proprii).');
    }

    /**
     * Anunțuri DEMO pe toate stările fluxului: două PUBLICATE (difuzate REAL, prin
     * {@see BroadcastAnnouncement} — notificările ajung în inboxurile familiilor, deci confirmarea
     * de citire e testabilă, nu doar afișabilă) și unul NEPUBLICAT (pe el se vede că publicarea e
     * un pas separat, iar editarea/ștergerea sunt permise doar înainte de difuzare).
     *
     * Autor = contul demo al administratorului operațional (canPublishContent). Titlurile poartă
     * marcajul [DEMO] → intră în plasa `app:purge-demo-data`, care șterge și notificările difuzate.
     */
    private function seedAnnouncements(): void
    {
        $author = User::query()->where('email', 'operational@columna.test')->first()
            ?? User::query()->where('email', 'admin@liceul-columna.test')->first();

        if ($author === null) {
            return;
        }

        $broadcaster = app(BroadcastAnnouncement::class);

        foreach ([
            [
                'title' => self::MARKER.' Ședința generală a părinților — 5 septembrie',
                'body' => 'Vineri, 5 septembrie, ora 18:00, în sala festivă. Prezența unui părinte per familie este binevenită; ordinea de zi include organizarea noului an școlar.',
            ],
            [
                'title' => self::MARKER.' Program scurtat în ajunul sărbătorilor',
                'body' => 'În ultima zi de școală dinaintea vacanței, lecțiile se scurtează la 30 de minute, iar programul prelungit se încheie la ora 15:00.',
            ],
        ] as $data) {
            $broadcaster->publish(Announcement::query()->create($data + ['author_user_id' => $author->id]));
        }

        // Ciornă: rămâne needitată/nedifuzată — de pe ea se testează publicarea din panou.
        Announcement::query()->create([
            'title' => self::MARKER.' Colecta de rechizite (în pregătire — nepublicat)',
            'body' => 'Ciornă de anunț pentru testarea pasului de publicare: familiile NU o văd până la difuzare.',
            'author_user_id' => $author->id,
        ]);

        $this->command->info('Anunțuri demo: 2 publicate (difuzate real) + 1 ciornă.');
    }

    /**
     * Cereri tipice DEMO pe toate cele 5 tipuri, cu statusuri mixte (în așteptare / aprobată cu
     * comentariu / respinsă cu motiv), pe copiii conturilor demo — ca fiecare compartiment al
     * secțiunii „Cereri" să aibă ce procesa/afișa. Contestațiile acoperă AMBELE forme: cu nota
     * purtată din depunere (fluxul curent) și legacy (fără notă — selectul rămâne la procesare).
     */
    private function seedDocumentRequests(): void
    {
        $parent = User::query()->where('email', 'parinte@columna.test')->first();
        $studentUser = User::query()->where('email', 'elev@columna.test')->first();
        $reviewer = User::query()->where('email', 'operational@columna.test')->first()
            ?? User::query()->where('email', 'admin@liceul-columna.test')->first();

        $children = $parent?->students()->orderBy('id')->get() ?? new Collection;
        $ownFiche = $studentUser !== null
            ? Student::query()->where('user_id', $studentUser->id)->first()
            : null;

        if ($parent === null || $children->isEmpty()) {
            $this->command->warn('Fără copii legați de părintele demo — sar peste cererile tipice.');

            return;
        }

        $first = $children->first();
        $second = $children->skip(1)->first() ?? $first;

        $make = function (Student $student, User $requester, DocumentRequestType $type, array $payload): DocumentRequest {
            return DocumentRequest::create([
                'type' => $type,
                'student_id' => $student->id,
                'requested_by_user_id' => $requester->id,
                'payload' => $payload,
            ]);
        };

        // Copilul 1: învoire ÎN AȘTEPTARE (cu perioadă viitoare), adeverință APROBATĂ cu
        // comentariu, transfer RESPINS cu motiv.
        $make($first, $parent, DocumentRequestType::Invoire, [
            'details' => self::MARKER.' Participare la concursul republican de matematică.',
            'period_start' => now()->addDays(7)->toDateString(),
            'period_end' => now()->addDays(9)->toDateString(),
        ]);

        $adeverinta = $make($first, $parent, DocumentRequestType::Adeverinta, [
            'details' => self::MARKER.' Necesară pentru dosarul de bursă al elevului.',
        ]);
        $reviewer !== null && $adeverinta->markProcessed($reviewer->id, self::MARKER.' Adeverința e gata — se ridică de la secretariat.');

        $transfer = $make($first, $parent, DocumentRequestType::Transfer, [
            'details' => self::MARKER.' Transfer la LT „Ion Creangă" — schimbarea domiciliului.',
        ]);
        $reviewer !== null && $transfer->markRejected($reviewer->id, self::MARKER.' Lipsesc actele școlii de destinație — reveniți cu acordul lor.');

        // Copilul 2: ședință ÎN AȘTEPTARE + contestație NOUĂ (cu nota purtată din depunere).
        $make($second, $parent, DocumentRequestType::Sedinta, [
            'details' => self::MARKER.' Doresc o întâlnire cu dirigintele despre adaptarea copilului.',
        ]);

        $contestable = Grade::query()
            ->where('student_id', $second->id)
            ->whereNull('annulled_at')
            ->whereDoesntHave('corrections', fn ($q) => $q->where('status', CorrectionStatus::Pending))
            ->with(['subject', 'teacher'])
            ->orderByDesc('graded_on')
            ->first();

        if ($contestable !== null) {
            $make($second, $parent, DocumentRequestType::Contestatie, [
                'details' => self::MARKER.' Considerăm că lucrarea a fost punctată greșit la ultimul subiect.',
                'grade_id' => $contestable->id,
                'grade' => [
                    'subject' => (string) $contestable->subject->name,
                    'value' => $contestable->value !== null ? (string) (float) $contestable->value : null,
                    'calificativ' => $contestable->calificativ,
                    'graded_on' => $contestable->graded_on->format('d.m.Y'),
                    'teacher' => $contestable->teacher?->full_name,
                ],
            ]);
        }

        // Elevul demo (cont propriu): adeverință în așteptare + contestație LEGACY (fără notă).
        // ($ownFiche non-null implică $studentUser non-null — fișa se caută doar cu cont existent.)
        if ($ownFiche !== null) {
            $make($ownFiche, $studentUser, DocumentRequestType::Adeverinta, [
                'details' => self::MARKER.' Adeverință pentru legitimația de transport.',
            ]);
            $make($ownFiche, $studentUser, DocumentRequestType::Contestatie, [
                'details' => self::MARKER.' Consider că lucrarea de la ultima evaluare a fost punctată greșit.',
            ]);
        }

        $this->command->info('Cereri tipice demo: toate cele 5 tipuri, statusuri mixte (+ contestație nouă și legacy).');
    }

    /**
     * Orar structurat demo pentru clasa dirigintelui demo, ca grila din cabinet + alerta de amânare
     * să aibă ce afișa. Idempotent: regenerează orarul clasei.
     */
    private function seedLessons(): void
    {
        $profUser = User::query()->where('email', 'profesor@columna.test')->first();

        if ($profUser === null) {
            return;
        }

        $class = $profUser->teacher?->homeroomClasses()->first();

        if ($class === null) {
            return;
        }

        Lesson::query()->where('school_class_id', $class->id)->forceDelete();

        $subjectIds = Subject::query()->inRandomOrder()->limit(6)->pluck('id')->all();

        if ($subjectIds === []) {
            return;
        }

        $teacherId = $profUser->teacher->id;

        $created = 0;
        foreach (range(1, 5) as $day) { // luni–vineri
            foreach (range(1, 4 + ($day % 2)) as $number) { // 4–5 lecții/zi
                Lesson::create([
                    'academic_year_id' => $class->academic_year_id,
                    'school_class_id' => $class->id,
                    'subject_id' => $subjectIds[($day + $number) % count($subjectIds)],
                    'teacher_id' => $teacherId,
                    'day_of_week' => $day,
                    'lesson_number' => $number,
                    'room' => (string) (10 + $number),
                ]);
                $created++;
            }
        }

        $this->command->info("Orar structurat demo: {$created} lecții pentru clasa dirigintelui.");
    }

    /**
     * Conturi demo pentru rolurile noi (#28), ca să se poată testa panoul + rutarea audienței.
     * Marcate „[DEMO]" → curățabile cu `app:demo-accounts --remove`. Parola: `password`.
     */
    private function seedRoleAccounts(): void
    {
        $accounts = [
            'vicedirector@columna.test' => [UserRole::PrimVicedirector, 'Prim-vicedirector'],
            'operational@columna.test' => [UserRole::AdministratorOperational, 'Administrator operațional'],
            'tehnic@columna.test' => [UserRole::AdministratorTehnic, 'Administrator tehnic'],
        ];

        foreach ($accounts as $email => [$role, $label]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => self::MARKER.' '.$label, 'password' => 'password'],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);

            // Prim-vicedirectorul demo poartă domeniul „educație" (§4.2): fără el, EXCEPȚIILE
            // de motivare și audiențele pe educație nu ar avea validator/destinatar de testat.
            if ($role === UserRole::PrimVicedirector) {
                $user->forceFill(['audience_domains' => [AudienceDomain::Educatie->value]])->save();
            }
        }

        $this->command->info('Conturi de rol demo: vicedirector@/operational@/tehnic@columna.test / password.');
    }

    /**
     * Conversații demo: familie ↔ diriginte (cu răspuns) + o solicitare de audiență spre conducere.
     */
    private function seedMessages(): void
    {
        $parent = User::query()->where('email', 'parinte@columna.test')->first();
        $profUser = User::query()->where('email', 'profesor@columna.test')->first();

        if ($parent === null || $profUser === null) {
            return;
        }

        $send = app(SendMessage::class);

        // Leagă părintele demo de un elev din clasa dirigintelui demo, ca să poată comunica direct.
        $homeroom = $profUser->teacher?->homeroomClasses()->first();
        if ($homeroom !== null) {
            $classStudent = Student::query()
                ->whereHas('enrollments', fn ($q) => $q->where('school_class_id', $homeroom->id))
                ->first();

            if ($classStudent !== null) {
                $parent->students()->syncWithoutDetaching([$classStudent->id]);

                $thread = $send->direct(
                    $parent,
                    $profUser,
                    self::MARKER.' Bună ziua, aș dori detalii despre evaluarea de săptămâna viitoare.',
                    'Evaluare',
                    $classStudent,
                );
                $send->reply($profUser, $thread, self::MARKER.' Bună ziua! Evaluarea acoperă capitolele 3–4. Cu stimă.');
            }
        }

        // Solicitare de audiență pe domeniu (instruire) → rutată către responsabilul de domeniu,
        // cu fallback pe director dacă niciun cont nu gestionează domeniul.
        $child = $parent->students()->first();
        if ($child !== null) {
            $send->audience(
                $parent,
                $child,
                self::MARKER.' Solicitare audiență',
                self::MARKER.' Aș dori o întâlnire pentru a discuta progresul copilului.',
                AudienceDomain::Instruire,
            );
        }

        $this->command->info('Conversații demo: mesaj direct (cu răspuns) + solicitare audiență.');
    }

    /**
     * Corecții de notă peste note reale, solicitate de profesorul demo; câteva aprobate/respinse
     * de admin, restul în așteptare (ca administrația să testeze fluxul de aprobare).
     */
    private function seedCorrections(): void
    {
        $requester = User::query()->where('email', 'profesor@columna.test')->first();
        $reviewer = User::query()->where('email', 'admin@liceul-columna.test')->first();

        if ($requester === null) {
            $this->command->warn('Fără cont de profesor demo — sar peste corecții (rulează DemoAccountsSeeder).');

            return;
        }

        $grades = Grade::query()
            ->whereNotNull('value')
            ->whereNull('annulled_at')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        $i = 0;
        foreach ($grades as $grade) {
            $i++;
            $newValue = max(1, min(10, (int) $grade->value + ($i % 2 === 0 ? 1 : -1)));

            $correction = GradeCorrection::create([
                'grade_id' => $grade->id,
                'requested_by_user_id' => $requester->id,
                'old_value' => $grade->value,
                'new_value' => $newValue,
                'reason' => self::MARKER.' Eroare de transcriere a notei.',
                'status' => CorrectionStatus::Pending,
            ]);

            if ($reviewer !== null && $i <= 2) {
                $correction->approve($reviewer->id, self::MARKER.' Corect, aprobat.');
            } elseif ($reviewer !== null && $i <= 4) {
                $correction->reject($reviewer->id, self::MARKER.' Nejustificat.');
            }
        }

        $this->command->info("Corecții note demo: {$grades->count()} (2 aprobate, 2 respinse, restul în așteptare).");
    }

    /**
     * Cereri de motivare pentru copiii contului-părinte demo, elevul demo și elevii clasei
     * dirigintelui demo (ca panoul „Motivări absențe" să aibă ce valida).
     */
    private function seedMotivations(): void
    {
        $parent = User::query()->where('email', 'parinte@columna.test')->first();
        $studentUser = User::query()->where('email', 'elev@columna.test')->first();
        $profUser = User::query()->where('email', 'profesor@columna.test')->first();

        /** @var Collection<int, Student> $targets */
        $targets = collect();

        if ($parent !== null) {
            $targets = $targets->merge($parent->students()->get());
        }

        if ($studentUser !== null) {
            $own = Student::query()->where('user_id', $studentUser->id)->first();
            if ($own !== null) {
                $targets->push($own);
            }
        }

        $homeroomClass = $profUser?->teacher?->homeroomClasses()->first();
        if ($homeroomClass !== null) {
            $classStudents = Student::query()
                ->whereHas('enrollments', fn ($q) => $q->where('school_class_id', $homeroomClass->id))
                ->limit(6)
                ->get();
            $targets = $targets->merge($classStudents);
        }

        $targets = $targets->unique('id')->values();

        if ($targets->isEmpty()) {
            $this->command->warn('Fără elevi-țintă pentru motivări demo (rulează DemoAccountsSeeder).');

            return;
        }

        $requester = $parent ?? $studentUser ?? User::query()->first();
        $reviewer = $profUser ?? User::query()->where('email', 'admin@liceul-columna.test')->first();

        if ($requester === null) {
            return;
        }

        // Cele 4 povești acoperă TOATE stările fișei (2026-07-20): aprobată; în așteptare CU
        // justificativ atașat (previzualizabil pe fișă); respinsă cu motiv COERENT (chiar nu are
        // document); EXCEPȚIE tardivă în așteptare (coada vicedirectorului pe educație).
        $reasons = [
            'Consultație medicală programată.',
            'Stare gripală, certificat anexat.',
            'Participare la concurs/olimpiadă.',
            'Certificat medical obținut după termen.',
        ];

        $documentPath = $this->storeDemoJustificativ();

        $i = 0;
        foreach ($targets as $student) {
            $pending = in_array($i % 4, [1, 3], true);

            // Cererile care RĂMÂN în așteptare se ancorează pe o absență REALĂ a elevului, ca
            // fișa să arate impact adevărat („N absențe vor fi motivate"). Cele judecate de
            // seeder păstrează perioade de vară FĂRĂ absențe: approve() de la seeding nu are
            // voie să motiveze absențe reale (datele de catalog nu se ating).
            $anchor = $pending
                ? Absence::query()
                    ->where('student_id', $student->id)
                    ->where('is_motivated', false)
                    ->latest('occurred_on')
                    ->value('occurred_on')
                : null;

            $start = $anchor !== null
                ? Carbon::parse((string) $anchor)->subDay()
                : Carbon::now()->subDays(($i * 5) % 60 + 3);
            $end = $start->copy()->addDays($anchor !== null ? 2 : $i % 3);

            $motivation = AbsenceMotivation::create([
                'student_id' => $student->id,
                'requested_by_user_id' => $requester->id,
                'reason' => self::MARKER.' '.$reasons[$i % count($reasons)],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => RequestStatus::Pending,
                'document_path' => $i % 4 === 1 ? $documentPath : null,
                'is_exception' => $i % 4 === 3,
            ]);

            if ($reviewer !== null && $i % 4 === 0) {
                $motivation->approve($reviewer->id, self::MARKER.' Justificat.');
            } elseif ($reviewer !== null && $i % 4 === 2) {
                $motivation->reject($reviewer->id, self::MARKER.' Lipsă document — diploma nu a fost anexată.');
            }

            $i++;
        }

        $this->command->info("Cereri de motivare demo: {$targets->count()} (statusuri mixte + justificativ + excepție).");
    }

    /**
     * Un justificativ-imagine DEMO în stocarea PRIVATĂ (aceeași cale ca upload-urile reale ale
     * familiei), ca previzualizarea/descărcarea de pe fișă să fie testabilă. Un singur fișier,
     * refolosit de toate cererile demo; `app:purge-demo-data` îl șterge odată cu rândurile.
     * Text ASCII (fonturile GD built-in nu au diacritice); fără GD → cereri fără document.
     */
    private function storeDemoJustificativ(): ?string
    {
        $path = 'motivations/demo-justificativ.png';

        if (Storage::disk('local')->exists($path)) {
            return $path;
        }

        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor(640, 400);
        $white = (int) imagecolorallocate($image, 255, 255, 252);
        $navy = (int) imagecolorallocate($image, 15, 77, 119);
        $gray = (int) imagecolorallocate($image, 104, 104, 103);
        imagefill($image, 0, 0, $white);
        imagerectangle($image, 12, 12, 627, 387, $navy);
        imagestring($image, 5, 40, 60, '[DEMO] ADEVERINTA MEDICALA', $navy);
        imagestring($image, 3, 40, 110, 'Document generat pentru TESTAREA platformei.', $gray);
        imagestring($image, 3, 40, 140, 'Nu este un act medical real.', $gray);
        imagestring($image, 3, 40, 200, 'Se recomanda scutirea de frecventa', $navy);
        imagestring($image, 3, 40, 230, 'pe perioada indicata in cerere.', $navy);
        imagestring($image, 2, 40, 340, 'Liceul Columna - mediu de test', $gray);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
