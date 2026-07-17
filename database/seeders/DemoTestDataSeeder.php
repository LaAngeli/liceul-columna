<?php

namespace Database\Seeders;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AbsenceMotivation;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Lesson;
use App\Models\Message;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function run(): void
    {
        if (Student::query()->count() === 0) {
            $this->command->warn('Nu există date de catalog — rulează `app:import-legacy` întâi.');

            return;
        }

        GradeCorrection::query()->where('reason', 'like', self::MARKER.'%')->delete();
        AbsenceMotivation::query()->where('reason', 'like', self::MARKER.'%')->delete();
        Message::query()->where('body', 'like', self::MARKER.'%')->forceDelete();
        // forceDelete PE MODEL (nu pe query): observer-ul curăță PDF/justificativ dacă există.
        DocumentRequest::withTrashed()
            ->where('payload->details', 'like', self::MARKER.'%')
            ->get()
            ->each(fn (DocumentRequest $request) => $request->forceDelete());

        $this->seedRoleAccounts();
        $this->seedCorrections();
        $this->seedMotivations();
        $this->seedMessages();
        $this->seedDocumentRequests();
        $this->seedLessons();
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

        $reasons = [
            'Consultație medicală programată.',
            'Stare gripală, certificat anexat.',
            'Participare la concurs/olimpiadă.',
            'Probleme de familie.',
        ];

        $i = 0;
        foreach ($targets as $student) {
            $start = Carbon::now()->subDays(($i * 5) % 60 + 3);
            $end = $start->copy()->addDays($i % 3);

            $motivation = AbsenceMotivation::create([
                'student_id' => $student->id,
                'requested_by_user_id' => $requester->id,
                'reason' => self::MARKER.' '.$reasons[$i % count($reasons)],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => RequestStatus::Pending,
            ]);

            if ($reviewer !== null && $i % 4 === 0) {
                $motivation->approve($reviewer->id, self::MARKER.' Justificat.');
            } elseif ($reviewer !== null && $i % 4 === 1) {
                $motivation->reject($reviewer->id, self::MARKER.' Lipsă document.');
            }

            $i++;
        }

        $this->command->info("Cereri de motivare demo: {$targets->count()} (statusuri mixte).");
    }
}
