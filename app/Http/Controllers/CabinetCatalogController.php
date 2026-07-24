<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsStudentCatalogData;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modulele de CATALOG ale cabinetului familiei — Note / Absențe / Orar / Teme — ca destinații
 * DIRECTE în meniul lateral (un click, nu Acasă → card copil → tab). Fiecare modul:
 *   • încarcă DOAR datele lui (și doar SECȚIUNEA activă) — fără interogări pentru celelalte module;
 *   • are comutator de copil (`?copil=`) validat pe server la familia utilizatorului;
 *   • are subsecțiuni adresabile (`?sectiune=`) — aceleași ținte ca sub-linkurile din sidebar.
 *
 * Datele vin din {@see BuildsStudentCatalogData} — ACELEAȘI builder-e ca taburile fișei elevului,
 * deci o schimbare de regulă se reflectă automat în ambele suprafețe. Gardul „doar familie" e pe
 * rută (EnsureFamilyCabinet); apartenența copilului se re-verifică aici la fiecare cerere.
 */
class CabinetCatalogController extends Controller
{
    use BuildsStudentCatalogData;

    /**
     * Modulul „Note": catalogul semestrului (note pe discipline SAU cronologic — o singură
     * încărcare, două vederi comutabile instant) sau evoluția (medii semestriale + dinamica
     * multi-an). `medii` e vechea denumire a secțiunii de evoluție — păstrată ca alias, ca
     * linkurile și semnele de carte de dinainte să nu aterizeze în altă parte.
     */
    public function grades(Request $request): Response
    {
        [$module, $student] = $this->moduleContext($request, ['curente', 'evolutie'], ['medii' => 'evolutie']);

        $props = ['module' => $module, 'gradebook' => null, 'evolution' => null];

        if ($student !== null && $module['section'] === 'curente') {
            $props['gradebook'] = $this->gradeBook($student);
        }

        if ($student !== null && $module['section'] === 'evolutie') {
            $props['evolution'] = $this->gradeEvolution($student);
        }

        return Inertia::render('cabinet/note', $props);
    }

    /** Modulul „Absențe": situația pe semestru (sinteză + discipline + cronologie) sau motivările. */
    public function absences(Request $request): Response
    {
        [$module, $student] = $this->moduleContext($request, ['registru', 'motivari']);

        $props = [
            'module' => $module,
            'overview' => null,
            'motivations' => null,
            'motivationWindow' => null,
            // Pagina e gardată pe familie (EnsureFamilyCabinet) + copilul e validat la familie —
            // deci formularul de motivare e mereu permis aici (POST-ul re-verifică oricum).
            'canRequestMotivation' => $student !== null,
        ];

        if ($student !== null && $module['section'] === 'registru') {
            $props['overview'] = $this->absenceOverview($student);
        }

        if ($student !== null && $module['section'] === 'motivari') {
            $props['motivations'] = $this->motivations($student);
            $props['motivationWindow'] = $this->motivationWindow();
        }

        return Inertia::render('cabinet/absente', $props);
    }

    /** Modulul „Orar": „Ziua mea" (lecțiile + temele zilei) sau orarul săptămânal al clasei. */
    public function schedule(Request $request): Response
    {
        [$module, $student] = $this->moduleContext($request, ['zi', 'saptamana']);

        return Inertia::render('cabinet/orar', [
            'module' => $module,
            // O SINGURĂ formă pentru ambele surse (publicat/structurat) — vezi WeeklySchedule.
            'weekly' => $this->weeklySchedule($student?->currentSchoolClass()),
            // Temele intră DOAR în vederea „Ziua mea" (planificatorul zilei le fuzionează cu lecțiile);
            // săptămânalul rămâne strict orar — modulul Teme e destinația lor dedicată.
            'homework' => $student !== null && $module['section'] === 'zi'
                ? $this->homeworkForStudent($student)
                : null,
        ]);
    }

    /** Modulul „Teme": temele clasei, cronologic (de făcut întâi, istoricul pliat). */
    public function homework(Request $request): Response
    {
        [$module, $student] = $this->moduleContext($request, []);

        return Inertia::render('cabinet/teme', [
            'module' => $module,
            // Tot setul anului (nu doar ultimele 20) — filtrul de calendar navighează la orice zi.
            'homework' => $student !== null ? $this->classHomework($student) : null,
        ]);
    }

    /**
     * Contextul comun al unui modul: copiii familiei (elevul însuși pentru contul de elev),
     * copilul selectat (`?copil=`, validat la familie — un id străin → 403) și secțiunea activă
     * (`?sectiune=`, normalizată la prima secțiune a modulului).
     *
     * @param  list<string>  $sections
     * @param  array<string, string>  $aliases  denumiri vechi de secțiune → cea de azi (linkuri salvate)
     * @return array{0: array{students: array<int, array{id: int, name: string, classLabel: string|null}>, currentId: int|null, section: string}, 1: Student|null}
     */
    private function moduleContext(Request $request, array $sections, array $aliases = []): array
    {
        $user = $request->user('web');

        $students = $user->students()->orderBy('first_name')->get();
        $self = Student::query()->where('user_id', $user->id)->first();
        if ($self !== null) {
            $students->push($self);
        }
        $students = $students->unique('id')->values();

        $selected = null;
        if ($students->isNotEmpty()) {
            $requested = (int) $request->query('copil', 0);
            if ($requested > 0) {
                $selected = $students->firstWhere('id', $requested);
                // Id în afara familiei → refuz explicit (nu „primul copil", care ar masca tentativa).
                abort_if($selected === null, 403);
            } else {
                $selected = $students->first();
            }
        }

        $section = (string) $request->query('sectiune', $sections[0] ?? '');
        $section = $aliases[$section] ?? $section;
        if ($sections !== [] && ! in_array($section, $sections, true)) {
            $section = $sections[0];
        }

        $module = [
            'students' => $students->map(function (Student $student): array {
                $class = $student->currentSchoolClass();

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'classLabel' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
                ];
            })->all(),
            'currentId' => $selected?->id,
            'section' => $section,
        ];

        return [$module, $selected];
    }
}
