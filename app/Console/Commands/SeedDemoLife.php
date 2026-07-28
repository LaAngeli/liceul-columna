<?php

namespace App\Console\Commands;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\DocumentRequestType;
use App\Enums\EvaluationType;
use App\Enums\RequestStatus;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * VIAȚA zonei demo: fluxuri DUSE PÂNĂ LA CAPĂT, nu rânduri de umplutură.
 *
 * Complement pentru `app:seed-demo-zone`, care construiește STRUCTURA (clase, elevi, profesori,
 * conturi). Comanda de față populează ACTIVITATEA, la cererea beneficiarului (2026-07-27):
 * datele demo trebuie tratate ca instrument de validare, nu ca valori care umplu ecrane.
 *
 * DE CE UN STRAT SEPARAT: `seed-demo-zone` rămâne al sesiunii paralele. Comanda asta nu-i atinge
 * nici fișierul, nici entitățile de structură — doar activitatea, cu manifest propriu. Ambele
 * rămân rulabile independent.
 *
 * PRINCIPIUL: fiecare scenariu trece prin MODELE, nu prin query builder. Observerii aplicației
 * emit atunci notificările reale, cu destinatarii și linkurile corecte — deci „o entitate emite,
 * alta procesează" nu se simulează, chiar se întâmplă. (Activitatea generată de `seed-demo-zone`
 * folosea `DB::table()->insert()`, deci nu declanșa nimic: de aici cererile fără notificare și
 * notificările orfane care duceau în 403.)
 *
 * CE REPARĂ, în plus, la fiecare rulare:
 * - numele profesorilor demo („Diriginte 1"…„Diriginte 12") — apăreau ca SUBTITLU pe cardurile de
 *   clasă și se citeau ca un marcaj de dirigenție, nu ca nume (raportat de beneficiar cu captură);
 * - notificările orfane: cele emise când conturile demo erau legate de ALT elev duc, la click, în
 *   403 — garda de cabinet lucrează corect, ținta e cea greșită.
 */
class SeedDemoLife extends Command
{
    protected $signature = 'app:seed-demo-life
        {--remove : Șterge activitatea generată de această comandă}';

    protected $description = 'Populează zona demo cu fluxuri complete (note, absențe, motivări, teme, mesaje, cereri) + notificările lor';

    private const MARK = '[DEMO]';

    /** @var array<string, list<int>> */
    private array $manifest = [];

    private string $manifestPath;

    /**
     * Nume pentru profesorii demo — realiste, DELIBERAT interne: `fake()` vine din require-dev și
     * lipsește în producție (`composer install --no-dev`), unde comanda chiar trebuie să ruleze.
     *
     * @var list<string>
     */
    private const TEACHER_NAMES = [
        'Cebotari Mihaela', 'Ursu Valentin', 'Popescu Angela', 'Ciobanu Grigore',
        'Damian Svetlana', 'Lungu Anatolie', 'Mocanu Viorica', 'Croitoru Ștefan',
        'Bejan Natalia', 'Rusu Gheorghe', 'Vieru Cristina', 'Ganea Dumitru',
        'Postica Ludmila', 'Melnic Andrei',
    ];

    public function handle(SendMessage $messenger): int
    {
        $this->manifestPath = storage_path('app/demo/life.json');

        // Simetric cu garda din `app:link-demo-accounts`: activitatea demo are sens DOAR peste
        // structura demo. Fără zonă, comanda ar scrie pe elevi REALI — exact ce trebuie evitat
        // până la go-live.
        if (! File::exists(storage_path('app/demo/zone.json'))) {
            $this->error('Nu există zonă demo (storage/app/demo/zone.json).');
            $this->line('Rulează întâi: php artisan app:seed-demo-zone');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove();
        }

        if (File::exists($this->manifestPath)) {
            $this->warn('Activitatea demo există deja. O înlocuiesc.');
            $this->remove();
        }

        return $this->seed($messenger);
    }

    private function seed(SendMessage $messenger): int
    {
        $context = $this->resolveContext();

        if ($context === null) {
            return self::FAILURE;
        }

        $this->repairTeacherNames();
        // După reparare, ca numele contului să urmeze fișa corectată — și la rulările următoare,
        // când numele profesorilor sunt deja bune și repararea iese devreme.
        $this->syncAccountNamesToFiches();
        $this->purgeOrphanNotifications($context['familyUsers']);

        try {
            DB::transaction(function () use ($context, $messenger): void {
                $this->seedGrades($context);
                $this->seedGradeCorrections($context);
                $this->seedAbsencesAndMotivations($context);
                $this->seedHomework($context);
                $this->seedMessages($context, $messenger);
                $this->seedDocumentRequests($context);
            });
        } catch (Throwable $e) {
            $this->error('Generare eșuată: '.$e->getMessage());

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, (string) json_encode($this->manifest, JSON_PRETTY_PRINT));

        $this->report();

        return self::SUCCESS;
    }

    /**
     * Actorii scenariilor — conturile demo și entitățile lor. Fiecare flux are nevoie de un
     * emitent ȘI de un procesator, altfel nu se poate urmări cap-coadă.
     *
     * @return array{term: Term, prof: User, dirig: User, parent: User, student: User, pvd: User, ao: User, classes: array<int, SchoolClass>, familyUsers: list<int>}|null
     */
    private function resolveContext(): ?array
    {
        $term = Term::query()->where('is_current', true)->first()
            ?? Term::query()->latest('id')->first();

        $accounts = [];

        foreach ([
            'prof' => 'profesor@columna.test',
            'dirig' => 'diriginte@columna.test',
            'parent' => 'parinte@columna.test',
            'student' => 'elev@columna.test',
            'pvd' => 'vicedirector@columna.test',
            'ao' => 'operational@columna.test',
        ] as $key => $email) {
            $user = User::query()->where('email', $email)->first();

            if (! $user instanceof User) {
                $this->error("Cont demo lipsă: {$email}. Rulează `php artisan db:seed --class=DemoRoleAccountsSeeder`.");

                return null;
            }

            $accounts[$key] = $user;
        }

        if (! $term instanceof Term) {
            $this->error('Niciun semestru configurat.');

            return null;
        }

        // Terenul de test = TOATE clasele văzute de conturile demo pedagogice: cele predate de
        // „profesor" și de „diriginte", plus dirigențiile. Altfel un cont aterizează pe carduri
        // goale — exact ce reclama beneficiarul: date care nu susțin validarea.
        $classIds = array_values(array_unique([
            ...($accounts['prof']->teacher?->visibleSchoolClassIds() ?? []),
            ...($accounts['dirig']->teacher?->visibleSchoolClassIds() ?? []),
        ]));

        $found = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->with('homeroomTeacher')
            ->get();

        if ($found->isEmpty()) {
            $this->error('Conturile demo pedagogice nu văd nicio clasă — zona demo pare incompletă.');

            return null;
        }

        // `values()` reindexează: fără el rămâne un array cu chei din colecție, iar contractul
        // declarat cere o listă.
        $classes = $found->values()->all();

        return [
            'term' => $term,
            'prof' => $accounts['prof'],
            'dirig' => $accounts['dirig'],
            'parent' => $accounts['parent'],
            'student' => $accounts['student'],
            'pvd' => $accounts['pvd'],
            'ao' => $accounts['ao'],
            'classes' => $classes,
            'familyUsers' => [
                (int) $accounts['parent']->getKey(),
                (int) $accounts['student']->getKey(),
            ],
        ];
    }

    /**
     * „Diriginte 1"…„Diriginte 12" nu sunt nume, sunt etichete — iar pe cardurile de clasă apar ca
     * subtitlu, unde se citesc ca „ești diriginte aici". Elevii demo au deja nume realiste;
     * profesorii primesc acum la fel. Idempotentă: numele deja realiste nu se ating.
     */
    private function repairTeacherNames(): void
    {
        $teachers = Teacher::query()
            ->where('last_name', 'like', self::MARK.'%')
            ->where(function ($query): void {
                $query->where('first_name', 'like', 'Diriginte%')
                    ->orWhere('last_name', 'like', '%Diriginte %');
            })
            ->orderBy('id')
            ->get();

        if ($teachers->isEmpty()) {
            return;
        }

        foreach ($teachers as $index => $teacher) {
            $parts = explode(' ', self::TEACHER_NAMES[$index % count(self::TEACHER_NAMES)], 2);

            $teacher->forceFill([
                'last_name' => self::MARK.' '.$parts[0],
                'first_name' => $parts[1] ?? '',
            ])->save();
        }

        $this->line("Nume reparate pentru {$teachers->count()} profesori demo (erau „Diriginte N”).");
    }

    /**
     * Numele contului urmează FIȘA — identitatea stă în registru, nu pe cont (principiul din
     * secțiunea Utilizatori). După re-legările succesive ale conturilor demo, contul „profesor"
     * afișa în bara panoului un nume, iar pe carduri și în alocări apărea altul: două identități
     * pentru aceeași persoană, exact genul de incoerență care face testarea nesigură.
     */
    private function syncAccountNamesToFiches(): void
    {
        $fixed = 0;

        $accounts = User::query()
            ->where('name', 'like', self::MARK.'%')
            ->with('teacher', 'student')
            ->get();

        foreach ($accounts as $account) {
            $fiche = $account->teacher ?? $account->student;
            $ficheName = $fiche instanceof Teacher || $fiche instanceof Student ? $fiche->full_name : null;

            if ($ficheName === null || $ficheName === $account->name) {
                continue;
            }

            $account->forceFill(['name' => $ficheName])->save();
            $fixed++;
        }

        if ($fixed > 0) {
            $this->line("Sincronizate {$fixed} nume de cont cu fișa din registru.");
        }
    }

    /**
     * Notificările emise când conturile demo erau legate de ALT elev poartă în payload id-ul de
     * atunci; la click duc în fișa altui copil → 403 (garda e corectă, ținta e greșită).
     * Ele nu mai pot fi reparate — se șterg, iar scenariile de mai jos emit altele, valide.
     *
     * @param  list<int>  $familyUserIds
     */
    private function purgeOrphanNotifications(array $familyUserIds): void
    {
        $deleted = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $familyUserIds)
            ->delete();

        if ($deleted > 0) {
            $this->line("Șterse {$deleted} notificări orfane (duceau în 403 — vizau alt elev).");
        }
    }

    // ── Scenariile ──────────────────────────────────────────────────────────────────────────

    /**
     * NOTE coerente: pe alocările REALE ale claselor (disciplina se predă chiar acolo), eșalonate
     * în timp, cu teze. Elevul contului demo primește un set BOGAT — cabinetul lui e prima
     * fereastră prin care se validează platforma.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedGrades(array $context): void
    {
        /** @var Term $term */
        $term = $context['term'];

        foreach ($context['classes'] as $class) {
            $pairs = DB::table('teaching_assignments')
                ->where('school_class_id', $class->getKey())
                ->whereNull('deleted_at')
                ->get(['subject_id', 'teacher_id']);

            $studentIds = DB::table('enrollments')
                ->where('school_class_id', $class->getKey())
                ->whereNull('deleted_at')
                ->pluck('student_id');

            if ($pairs->isEmpty() || $studentIds->isEmpty()) {
                continue;
            }

            foreach ($studentIds as $studentId) {
                foreach ($pairs as $pair) {
                    // 2–4 note pe disciplină: destule cât media semestrială să aibă sens.
                    foreach (range(1, random_int(2, 4)) as $step) {
                        $grade = new Grade;
                        $grade->forceFill([
                            'student_id' => $studentId,
                            'subject_id' => $pair->subject_id,
                            'school_class_id' => $class->getKey(),
                            'term_id' => $term->getKey(),
                            'teacher_id' => $pair->teacher_id,
                            'graded_on' => Carbon::now()->subDays(random_int(5, 120))->toDateString(),
                            'type' => 1,
                            'evaluation_type' => $step === 1 && random_int(1, 8) === 1
                                ? EvaluationType::Teza->value
                                : EvaluationType::Curenta->value,
                            'value' => random_int(5, 10),
                        ])->save();

                        $this->manifest['grades'][] = (int) $grade->getKey();
                    }
                }
            }
        }
    }

    /**
     * CORECȚIE DE NOTĂ, ambele deznodăminte: una aprobată (nota chiar se schimbă, familia vede
     * diferența) și una respinsă (profesorul primește verdictul cu motiv). Plus una în așteptare,
     * ca prim-vicedirectorul să aibă ce judeca la testare.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedGradeCorrections(array $context): void
    {
        $grades = Grade::query()
            ->whereIn('id', array_slice($this->manifest['grades'] ?? [], 0, 200))
            ->where('value', '<', 9)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        if ($grades->count() < 3) {
            return;
        }

        $scenarios = [
            ['status' => 'approved', 'reason' => 'Am transcris greșit nota din catalogul de clasă — valoarea corectă e cea de la lucrarea scrisă.'],
            ['status' => 'rejected', 'reason' => 'Solicit majorarea notei pentru participare la olimpiadă.'],
            ['status' => 'pending', 'reason' => 'Elevul a recuperat lucrarea în termen; nota inițială nu reflectă rezultatul final.'],
        ];

        foreach ($grades as $index => $grade) {
            $scenario = $scenarios[$index];

            $correction = new GradeCorrection;
            $correction->forceFill([
                'grade_id' => $grade->getKey(),
                'requested_by_user_id' => $context['prof']->getKey(),
                'old_value' => $grade->value,
                'new_value' => min(10, (int) $grade->value + 1),
                'reason' => self::MARK.' '.$scenario['reason'],
                'status' => RequestStatus::Pending->value,
                'created_at' => Carbon::now()->subDays(random_int(2, 12)),
            ])->save();

            $this->manifest['grade_corrections'][] = (int) $correction->getKey();

            if ($scenario['status'] === 'approved') {
                $correction->approve((int) $context['pvd']->getKey(), self::MARK.' Verificat cu lucrarea scrisă. Corecția se aplică.');
            }

            if ($scenario['status'] === 'rejected') {
                $correction->reject((int) $context['pvd']->getKey(), self::MARK.' Participarea la olimpiadă se recunoaște prin notă separată, nu prin majorarea celei existente.');
            }
        }
    }

    /**
     * ABSENȚE → MOTIVARE → VERDICT, fluxul complet al familiei. Motivarea acoperă absențe
     * NEMOTIVATE REALE ale copilului, pe perioada lor: la aprobare, absențele chiar devin
     * motivate, deci efectul se vede în cabinet, nu doar în lista de cereri.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedAbsencesAndMotivations(array $context): void
    {
        /** @var Term $term */
        $term = $context['term'];
        /** @var User $parent */
        $parent = $context['parent'];

        $children = $parent->students()->pluck('students.id')->all();
        $ownStudent = $context['student']->student?->getKey();

        if ($ownStudent !== null) {
            $children[] = $ownStudent;
        }

        $children = array_values(array_unique(array_map('intval', $children)));

        foreach ($children as $position => $studentId) {
            $enrollment = DB::table('enrollments')
                ->where('student_id', $studentId)
                ->whereNull('deleted_at')
                ->first();

            if ($enrollment === null) {
                continue;
            }

            $pair = DB::table('teaching_assignments')
                ->where('school_class_id', $enrollment->school_class_id)
                ->whereNull('deleted_at')
                ->first();

            if ($pair === null) {
                continue;
            }

            // Trei zile consecutive de absențe nemotivate — o „răceală" credibilă, care dă obiect
            // motivării de mai jos.
            $start = Carbon::now()->subDays(12 + $position * 4)->startOfDay();

            foreach (range(0, 2) as $day) {
                $absence = new Absence;
                $absence->forceFill([
                    'student_id' => $studentId,
                    'subject_id' => $pair->subject_id,
                    'school_class_id' => $enrollment->school_class_id,
                    'term_id' => $term->getKey(),
                    'teacher_id' => $pair->teacher_id,
                    'occurred_on' => $start->copy()->addDays($day)->toDateString(),
                    'is_motivated' => false,
                ])->save();

                $this->manifest['absences'][] = (int) $absence->getKey();
            }

            $motivation = new AbsenceMotivation;
            $motivation->forceFill([
                'student_id' => $studentId,
                'requested_by_user_id' => $parent->getKey(),
                'reason' => self::MARK.' Copilul a fost bolnav (viroză respiratorie). Anexez certificatul medical eliberat de medicul de familie.',
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->addDays(2)->toDateString(),
                'status' => RequestStatus::Pending->value,
                'is_exception' => false,
                'created_at' => $start->copy()->addDays(3),
            ])->save();

            $this->manifest['absence_motivations'][] = (int) $motivation->getKey();

            // Prima cerere se aprobă (absențele devin motivate — efect vizibil în cabinet);
            // a doua rămâne în așteptare, ca dirigintele să aibă ce valida la testare.
            if ($position === 0) {
                $motivation->approve((int) $context['dirig']->getKey(), self::MARK.' Certificat medical verificat. Absențele se motivează.');
            }
        }

        // EXCEPȚIE (motivare tardivă): dreptul e al vicedirectorului pe educație, nu al
        // dirigintelui — el vede cererea, dar fără butoane. Scenariu greu de testat altfel.
        // Poartă absențe REALE în perioada ei: o cerere „fără obiect" n-ar arăta impactul.
        if ($children !== []) {
            $lateStart = Carbon::now()->subDays(60)->startOfDay();
            $enrollment = DB::table('enrollments')
                ->where('student_id', $children[0])
                ->whereNull('deleted_at')
                ->first();

            $pair = $enrollment === null ? null : DB::table('teaching_assignments')
                ->where('school_class_id', $enrollment->school_class_id)
                ->whereNull('deleted_at')
                ->first();

            if ($pair !== null) {
                foreach (range(0, 2) as $day) {
                    $absence = new Absence;
                    $absence->forceFill([
                        'student_id' => $children[0],
                        'subject_id' => $pair->subject_id,
                        'school_class_id' => $enrollment->school_class_id,
                        'term_id' => $term->getKey(),
                        'teacher_id' => $pair->teacher_id,
                        'occurred_on' => $lateStart->copy()->addDays($day)->toDateString(),
                        'is_motivated' => false,
                    ])->save();

                    $this->manifest['absences'][] = (int) $absence->getKey();
                }
            }

            $late = new AbsenceMotivation;
            $late->forceFill([
                'student_id' => $children[0],
                'requested_by_user_id' => $parent->getKey(),
                'reason' => self::MARK.' Solicit motivarea, deși termenul a expirat: certificatul a fost eliberat cu întârziere de policlinică.',
                'period_start' => $lateStart->toDateString(),
                'period_end' => $lateStart->copy()->addDays(2)->toDateString(),
                'status' => RequestStatus::Pending->value,
                'is_exception' => true,
                'created_at' => Carbon::now()->subDays(3),
            ])->save();

            $this->manifest['absence_motivations'][] = (int) $late->getKey();
        }
    }

    /**
     * TEME + CORECȚIE DE TEMĂ. Temele se leagă de clasă prin perechea (grade_level, section) —
     * moștenire legacy — deci trebuie scrise cu valorile clasei, altfel nu apar în cabinet.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedHomework(array $context): void
    {
        $topics = [
            ['Exerciții recapitulative', 'Rezolvați problemele 1–8 din culegere.', 'Problema 9 (pentru nota 10).'],
            ['Lectură și analiză', 'Citiți capitolul indicat și notați ideile principale.', 'Redactați un rezumat de o pagină.'],
            ['Lucrare practică', 'Completați fișa de observație distribuită la oră.', null],
        ];

        foreach ($context['classes'] as $index => $class) {
            $pair = DB::table('teaching_assignments')
                ->where('school_class_id', $class->getKey())
                ->whereNull('deleted_at')
                ->first();

            if ($pair === null) {
                continue;
            }

            $teacher = Teacher::query()->whereKey($pair->teacher_id)->first();
            $subject = DB::table('subjects')->where('id', $pair->subject_id)->first();
            $topic = $topics[$index % count($topics)];

            $homework = new HomeworkAssignment;
            $homework->forceFill([
                'subject_id' => $pair->subject_id,
                'teacher_id' => $pair->teacher_id,
                'subject_name' => $subject->name ?? '—',
                'author_name' => $teacher instanceof Teacher ? $teacher->full_name : '—',
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'assigned_on' => Carbon::now()->subDays(3)->toDateString(),
                'due_on' => Carbon::now()->addDays(4)->toDateString(),
                'topic' => self::MARK.' '.$topic[0],
                'required_task' => $topic[1],
                'optional_task' => $topic[2],
            ])->save();

            $this->manifest['homework'][] = (int) $homework->getKey();

            // O singură corecție de temă, pe prima clasă: fluxul are alt aprobator decât notele
            // (include administratorul operațional), deci merită testat explicit.
            if ($index === 0) {
                $correction = new HomeworkCorrection;
                $correction->forceFill([
                    'homework_assignment_id' => $homework->getKey(),
                    'requested_by_user_id' => $context['prof']->getKey(),
                    'old_topic' => $homework->topic,
                    'new_topic' => self::MARK.' '.$topic[0].' (revizuit)',
                    'old_required_task' => $homework->required_task,
                    'new_required_task' => $topic[1].' Termenul se prelungește cu trei zile.',
                    'reason' => self::MARK.' Termenul coincide cu teza la altă disciplină; solicit amânarea și reformularea cerinței.',
                    'status' => RequestStatus::Pending->value,
                ])->save();

                $this->manifest['homework_corrections'][] = (int) $correction->getKey();
            }
        }
    }

    /**
     * MESAJE pe cele trei căi ale spec-ului §4: familia scrie direct dirigintelui; profesorul
     * semnalează comportamental prin prim-vicedirector (familia NU e destinatar direct); familia
     * cere audiență, rutată pe domeniu. Fiecare cu răspuns, ca firul să fie navigabil.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedMessages(array $context, SendMessage $messenger): void
    {
        /** @var User $parent */
        $parent = $context['parent'];
        $child = $parent->students()->first();

        if (! $child instanceof Student) {
            return;
        }

        try {
            $direct = $messenger->direct(
                $parent,
                $context['dirig'],
                'Bună ziua! Aș dori să știu cum stă copilul cu notele la matematică înainte de încheierea semestrului. Vă mulțumesc!',
                self::MARK.' Întrebare despre situația la învățătură',
                $child,
            );
            $this->manifest['messages'][] = (int) $direct->getKey();

            $reply = $messenger->reply(
                $context['dirig'],
                $direct,
                'Bună ziua! Situația e bună — media curentă e peste 8. Recomand exerciții suplimentare la geometrie. Vă aștept la ședința de vineri.',
            );
            $this->manifest['messages'][] = (int) $reply->getKey();

            $behavioral = $messenger->behavioralReport(
                $context['prof'],
                $child,
                self::MARK.' Elevul a lipsit nemotivat de la trei ore consecutive de la disciplina mea. Solicit intervenția dirigintelui.',
            );
            $this->manifest['messages'][] = (int) $behavioral->getKey();

            $audience = $messenger->audience(
                $parent,
                $child,
                self::MARK.' Solicitare audiență — situația la matematică',
                'Solicit o întrevedere pentru a discuta rezultatele copilului și posibilitățile de recuperare.',
                AudienceDomain::Instruire,
            );
            $this->manifest['messages'][] = (int) $audience->getKey();
        } catch (Throwable $e) {
            $this->warn('Mesaje: '.$e->getMessage());
        }
    }

    /**
     * CERERI TIPICE: una închisă (familia vede verdictul + documentul) și una în așteptare, ca
     * secretariatul să aibă ce procesa. PDF-ul NU se generează aici — cere mpdf și scrie în
     * stocarea privată; se produce la procesarea reală, din panou.
     *
     * @param  array<string, mixed>  $context
     */
    private function seedDocumentRequests(array $context): void
    {
        /** @var User $parent */
        $parent = $context['parent'];
        $child = $parent->students()->first();

        if (! $child instanceof Student) {
            return;
        }

        $requests = [
            [
                'type' => DocumentRequestType::Adeverinta,
                'payload' => ['scop' => self::MARK.' Prezentare la secția de înot'],
                'close' => true,
            ],
            [
                'type' => DocumentRequestType::Invoire,
                'payload' => ['motiv' => self::MARK.' Consultație medicală programată', 'perioada' => Carbon::now()->addDays(5)->format('d.m.Y')],
                'close' => false,
            ],
        ];

        foreach ($requests as $spec) {
            $request = new DocumentRequest;
            $request->forceFill([
                'type' => $spec['type']->value,
                'student_id' => $child->getKey(),
                'requested_by_user_id' => $parent->getKey(),
                'payload' => $spec['payload'],
                'status' => RequestStatus::Pending->value,
                'created_at' => Carbon::now()->subDays(random_int(2, 8)),
            ])->save();

            $this->manifest['document_requests'][] = (int) $request->getKey();

            if ($spec['close']) {
                $request->forceFill([
                    'status' => RequestStatus::Approved->value,
                    'reviewed_by_user_id' => $context['ao']->getKey(),
                    'reviewed_at' => Carbon::now()->subDay(),
                    'review_note' => self::MARK.' Adeverința a fost eliberată și poate fi descărcată din cabinet.',
                ])->save();
            }
        }
    }

    // ── Curățare + raport ───────────────────────────────────────────────────────────────────

    private function remove(): int
    {
        if (! File::exists($this->manifestPath)) {
            $this->warn('Fără manifest — nimic de curățat.');

            return self::SUCCESS;
        }

        /** @var array<string, list<int>> $manifest */
        $manifest = json_decode((string) File::get($this->manifestPath), true) ?: [];

        // Ordinea CONTEAZĂ: ce depinde de altceva se șterge întâi (corecțiile înaintea notelor),
        // altfel FK-urile cascadează și raportul arată zero acolo unde chiar au existat rânduri.
        $order = [
            'grade_corrections' => 'grade_corrections',
            'homework_corrections' => 'homework_corrections',
            'absence_motivations' => 'absence_motivations',
            'document_requests' => 'document_requests',
            'messages' => 'messages',
            'homework' => 'homework_assignments',
            'absences' => 'absences',
            'grades' => 'grades',
        ];

        $rows = [];

        foreach ($order as $key => $table) {
            $ids = $manifest[$key] ?? [];

            if ($ids === []) {
                continue;
            }

            $rows[] = [$table, DB::table($table)->whereIn('id', $ids)->delete()];
        }

        // Notificările produse de scenarii — RESTRÂNSE la conturile demo. Un filtru doar pe
        // marcajul „[DEMO]" în payload ar prinde și anunțurile demo difuzate către utilizatori
        // reali (7.294 de rânduri la prima încercare): efect colateral pe date care nu-mi aparțin.
        $demoUserIds = User::query()
            ->where('name', 'like', self::MARK.'%')
            ->pluck('id')
            ->all();

        $rows[] = ['notifications', DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $demoUserIds)
            ->where('data', 'like', '%'.self::MARK.'%')
            ->delete()];

        File::delete($this->manifestPath);

        $this->table(['Tabel', 'Șterse'], $rows);
        $this->info('Activitatea demo a fost curățată.');

        return self::SUCCESS;
    }

    private function report(): void
    {
        $rows = [];

        foreach ($this->manifest as $key => $ids) {
            $rows[] = [$key, count($ids)];
        }

        $this->table(['Ce s-a creat', 'Rânduri'], $rows);
        $this->info('Zona demo are acum fluxuri complete.');
        $this->line('Notificările pleacă pe coadă: php artisan queue:work --stop-when-empty');
        $this->line('Mediile se recalculează cu: php artisan app:compute-averages');
    }
}
