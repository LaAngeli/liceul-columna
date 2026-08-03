<?php

namespace App\Console\Commands;

use App\Actions\Enrollments\GraduateClasses;
use App\Enums\DocumentRequestType;
use App\Enums\GeneratedDocumentType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * SIMULAREA absolvirii, pe date demo izolate — verifică fluxul complet fără să atingă niciun elev real.
 *
 * De ce un AN ȘCOLAR propriu: {@see GraduateClasses} operează pe an, iar anul curent conține și
 * clasa a XII-a REALĂ. O simulare rulată acolo ar fi consemnat absolvirea a 22 de elevi adevărați —
 * o operațiune de registru, nu un test. Anul demo conține DOAR date marcate `[DEMO]`.
 *
 * Ce verifică (afișat ca tabel înainte/după):
 *   • clasa terminală iese din registru cu motivul `absolvire`, clasa a XI-a rămâne neatinsă;
 *   • ceasul de retenție (L133 §7) pornește — dosarul devine eligibil de ștergere după termen;
 *   • absolventul păstrează accesul read-only (adeverințe + arhivă), pierde fluxurile operaționale;
 *   • părintele cu un copil absolvent ȘI unul activ rămâne în circuitul operațional.
 *
 * REVERSIBIL 100%: ce se creează intră într-un manifest (`storage/app/demo/graduation.json`), iar
 * `--remove` șterge exact acele rânduri. Notele/absențele se scriu prin QUERY BUILDER — observerii
 * ar fi trimis notificări reale familiilor demo și ar fi recalculat medii la fiecare inserare.
 */
class SimulateGraduation extends Command
{
    protected $signature = 'app:simulate-graduation
        {--remove : Șterge datele simulării (an, clase, elevi, conturi)}
        {--setup-only : Creează datele, fără să ruleze absolvirea (ca să vezi starea „înainte")}';

    protected $description = 'Simulează absolvirea unei promoții pe date demo izolate și verifică fluxul';

    private const MARK = '[DEMO]';

    /**
     * ⚠️ Anul NU poartă marcajul `[DEMO]`: modelul impune denumirea canonică „YYYY–YYYY" (doi ani
     * consecutivi), iar garda e corectă — n-o ocolim pentru o simulare. Anul e identificabil prin
     * MANIFEST, iar tot ce conține (clase, elevi) e marcat; un an istoric gol de date reale se
     * citește oricum ca ce este.
     */
    private const YEAR_NAME = '2019–2020';

    private const PASSWORD = 'password';

    private string $manifestPath = '';

    public function handle(): int
    {
        abort_unless(app()->environment(['local', 'testing']), 403);

        $this->manifestPath = storage_path('app/demo/graduation.json');

        if ($this->option('remove')) {
            return $this->remove();
        }

        if (File::exists($this->manifestPath)) {
            $this->warn('Simularea există deja. Rulează întâi `php artisan app:simulate-graduation --remove`.');

            return self::FAILURE;
        }

        $ids = $this->seed();

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, (string) json_encode($ids, JSON_PRETTY_PRINT));

        $year = AcademicYear::query()->whereKey((int) $ids['year'])->firstOrFail();

        $this->newLine();
        $this->info('── ÎNAINTE de absolvire ──────────────────────────────');
        $this->report($ids);

        if ($this->option('setup-only')) {
            $this->comment('`--setup-only`: absolvirea NU a fost rulată.');

            return self::SUCCESS;
        }

        $result = app(GraduateClasses::class)->handle($year);

        $this->newLine();
        $this->info("── Absolvire executată: {$result['graduated']} elevi, {$result['classes']} clasă/clase ──");
        $this->newLine();
        $this->info('── DUPĂ absolvire ───────────────────────────────────');
        $this->report($ids);

        $this->newLine();
        $this->line('Cont de elev absolvent: <options=bold>'.$ids['alumnus_username'].'</> / '.self::PASSWORD);
        $this->line('Cont de părinte (un copil absolvent + unul activ): <options=bold>'.$ids['parent_username'].'</> / '.self::PASSWORD);
        $this->line('Curățare: <options=bold>php artisan app:simulate-graduation --remove</>');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function seed(): array
    {
        return DB::transaction(function (): array {
            $year = AcademicYear::query()->create([
                'name' => self::YEAR_NAME,
                'starts_on' => '2019-09-01',
                'ends_on' => '2020-07-31',
                'is_current' => false,
            ]);

            $terms = [];
            foreach ([[1, '2019-09-01', '2019-12-31'], [2, '2020-01-01', '2020-07-31']] as [$number, $from, $to]) {
                $terms[] = Term::query()->create([
                    'academic_year_id' => $year->id,
                    'number' => $number,
                    'name' => Term::canonicalName($number),
                    'starts_on' => $from,
                    'ends_on' => $to,
                    'is_current' => false,
                ])->id;
            }

            // Clasa TERMINALĂ (absolvă) + una de control, o treaptă mai jos (NU trebuie atinsă).
            $twelfth = SchoolClass::query()->create([
                'academic_year_id' => $year->id, 'grade_level' => 12, 'name' => self::MARK.' XII', 'section' => 'S',
            ]);
            $eleventh = SchoolClass::query()->create([
                'academic_year_id' => $year->id, 'grade_level' => 11, 'name' => self::MARK.' XI', 'section' => 'S',
            ]);

            $graduating = [];
            foreach (['Andrei', 'Maria', 'Victor', 'Elena'] as $name) {
                $graduating[] = $this->makeStudent($name, $twelfth, $year, $terms[0]);
            }

            $continuing = [];
            foreach (['Cristina', 'Radu'] as $name) {
                $continuing[] = $this->makeStudent($name, $eleventh, $year, $terms[0]);
            }

            // Contul elevului absolvent — ca accesul read-only să fie verificabil prin login real.
            $alumnusAccount = $this->makeAccount('Absolvent Simulare', 'sim-absolvent', UserRole::Elev);
            Student::query()->whereKey($graduating[0])->update(['user_id' => $alumnusAccount->id]);

            // Părinte cu DOI copii: unul absolvă, unul rămâne — cazul care dovedește că gating-ul
            // stă pe elev, nu pe cont.
            $parentAccount = $this->makeAccount('Părinte Simulare', 'sim-parinte', UserRole::Parinte);
            $parentAccount->students()->attach([$graduating[1], $continuing[0]]);

            return [
                'year' => $year->id,
                'terms' => $terms,
                'classes' => [$twelfth->id, $eleventh->id],
                'twelfth' => $twelfth->id,
                'eleventh' => $eleventh->id,
                'graduating' => $graduating,
                'continuing' => $continuing,
                'accounts' => [$alumnusAccount->id, $parentAccount->id],
                'alumnus_username' => 'sim-absolvent',
                'parent_username' => 'sim-parinte',
            ];
        });
    }

    /** Elev demo + înmatriculare activă + puțin istoric academic (ca arhiva să nu fie goală). */
    private function makeStudent(string $firstName, SchoolClass $class, AcademicYear $year, int $termId): int
    {
        $student = Student::query()->create([
            'last_name' => self::MARK.' Simulare',
            'first_name' => $firstName,
            'sex' => 'm',
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year->id,
            'enrolled_on' => '2019-09-01',
            'left_on' => null,
        ]);

        // Query builder: GradeObserver ar fi notificat familiile și ar fi recalculat mediile la
        // fiecare rând — efecte reale pentru date de simulare.
        $subjectId = DB::table('subjects')->value('id');

        if ($subjectId !== null) {
            foreach ([9, 8, 10] as $value) {
                DB::table('grades')->insert([
                    'student_id' => $student->id, 'subject_id' => $subjectId,
                    'school_class_id' => $class->id, 'term_id' => $termId,
                    'value' => $value, 'evaluation_type' => 'curenta',
                    'graded_on' => '2019-10-15', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return (int) $student->id;
    }

    private function makeAccount(string $name, string $username, UserRole $role): User
    {
        $user = User::query()->create([
            'name' => self::MARK.' '.$name,
            'username' => $username,
            'email' => $username.'@columna.test',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    private function report(array $ids): void
    {
        /** @var list<int> $graduating */
        $graduating = $ids['graduating'];
        /** @var list<int> $continuing */
        $continuing = $ids['continuing'];

        $year = AcademicYear::query()->whereKey((int) $ids['year'])->firstOrFail();
        $alumnus = Student::query()->whereKey($graduating[0])->firstOrFail();
        $sibling = Student::query()->whereKey($continuing[0])->firstOrFail();
        $parent = User::query()->whereKey((int) $ids['accounts'][1])->firstOrFail();

        $rows = [
            ['Elevi de absolvit (pendingCount)', (string) app(GraduateClasses::class)->pendingCount($year)],
            ['Clasa a XII-a — înmatriculări active', (string) $this->activeIn($ids['twelfth'])],
            ['Clasa a XI-a — înmatriculări active (control)', (string) $this->activeIn($ids['eleventh'])],
            ['Motivul plecării (primul elev)', $alumnus->departureReason()?->label() ?? '—'],
            ['Este absolvent (acces la arhivă)', $this->yn($alumnus->isAlumnus())],
            ['Elevul de a XI-a e absolvent', $this->yn($sibling->isAlumnus())],
            ['Ceasul de retenție pornit (L133 §7)', $this->yn($this->retentionStarted($alumnus))],
            ['Tipuri de cereri disponibile', implode(', ', $alumnus->isAlumnus()
                ? DocumentRequestType::alumniOptions()
                : DocumentRequestType::options())],
            ['Documente generate disponibile', implode(', ', $this->documentsFor($alumnus))],
            ['Părintele mai are un copil în școală', $this->yn($parent->hasAnyActiveStudent())],
            ['Părintele a rămas doar cu absolvenți', $this->yn($parent->hasOnlyDepartedStudents())],
            ['Elevi ai școlii (currentlyEnrolled)', (string) Student::query()->currentlyEnrolled()->count()],
        ];

        $this->table(['Verificare', 'Valoare'], $rows);
    }

    private function activeIn(int $classId): int
    {
        return Enrollment::query()->where('school_class_id', $classId)->whereNull('left_on')->count();
    }

    /** Predicatul lui `app:purge-expired-students`: dosarul e închis, deci termenul curge. */
    private function retentionStarted(Student $student): bool
    {
        return Student::query()
            ->whereKey($student->getKey())
            ->whereDoesntHave('enrollments', fn ($q) => $q->whereNull('left_on'))
            ->whereHas('enrollments', fn ($q) => $q->whereNotNull('left_on'))
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function documentsFor(Student $student): array
    {
        $types = array_filter(
            GeneratedDocumentType::cases(),
            fn (GeneratedDocumentType $type): bool => ! $student->isAlumnus() || $type->availableToAlumni(),
        );

        return array_values(array_map(fn (GeneratedDocumentType $type): string => $type->getLabel(), $types));
    }

    private function yn(bool $value): string
    {
        return $value ? 'DA' : 'nu';
    }

    private function remove(): int
    {
        if (! File::exists($this->manifestPath)) {
            $this->warn('Nu există nicio simulare de șters.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $m */
        $m = json_decode((string) File::get($this->manifestPath), true);

        $students = array_merge($m['graduating'] ?? [], $m['continuing'] ?? []);

        DB::transaction(function () use ($m, $students): void {
            DB::table('grades')->whereIn('student_id', $students)->delete();
            DB::table('absences')->whereIn('student_id', $students)->delete();
            DB::table('term_averages')->whereIn('student_id', $students)->delete();
            DB::table('guardian_student')->whereIn('student_id', $students)->delete();
            DB::table('enrollments')->whereIn('student_id', $students)->delete();
            DB::table('students')->whereIn('id', $students)->delete();
            DB::table('school_classes')->whereIn('id', $m['classes'] ?? [])->delete();
            DB::table('terms')->whereIn('id', $m['terms'] ?? [])->delete();
            DB::table('academic_years')->where('id', $m['year'] ?? 0)->delete();
            DB::table('users')->whereIn('id', $m['accounts'] ?? [])->delete();
        });

        File::delete($this->manifestPath);

        $this->info('Simularea a fost ștearsă complet (an, clase, elevi, conturi).');

        return self::SUCCESS;
    }
}
