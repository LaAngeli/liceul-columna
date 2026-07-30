<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\AcademicRecord;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * CALITATEA decide ce se vede din catalog (§3.3): dirigintele vede toată clasa lui — răspunde de
 * ea, indiferent de disciplină — iar profesorul doar perechile (clasă, disciplină) pe care le
 * predă efectiv. Autoritatea academică (super-admin/director/prim-vicedirector/AO) nu e limitată;
 * un cont fără fișă de profesor nu vede nimic.
 *
 * DE CE UN SCOPE DE MODEL, nu clauza copiată în fiecare resursă: clauza trăia identică în
 * `GradeResource::getEloquentQuery()` și `AbsenceResource::getEloquentQuery()`, dar LIPSEA din a
 * treia suprafață — fișa elevului. Acolo relation manager-ul filtrează prin relația `$student->
 * grades`, care restrânge la ELEV, nu la calitatea privitorului; docblock-ul presupunea greșit că
 * „scoping-ul vine din relationship". Efectul măsurat pe producție (2026-07-28): un profesor de
 * limba română vedea, pe fișa elevului său, 17 note din 4 discipline în loc de 5 dintr-una —
 * adică notele la fizică, matematică și istorie ale unui minor, fără drept.
 *
 * Ținută într-un singur loc, regula nu mai poate diverge între suprafețe: orice interogare nouă
 * peste catalog o aplică explicit, iar testul o verifică prin ecranul real, nu prin resursă.
 *
 * Premisa modelelor care îl folosesc: au AMBELE coloane `school_class_id` și `subject_id`
 * (Grade, Absence, TermAverage). Foaia matricolă n-are clasă — vezi scope-ul propriu din
 * {@see AcademicRecord::scopeVisibleToStaff()}.
 */
trait ScopedToTeachingCapacity
{
    /**
     * Formă înlănțuibilă, pentru interogările al căror model e cunoscut static
     * (`TermAverage::query()->visibleToStaff($user)`).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleToStaff(Builder $query, ?User $user): Builder
    {
        return static::applyStaffVisibility($query, $user);
    }

    /**
     * Aceeași regulă, apelabilă pe un builder al cărui parametru generic NU e cunoscut static:
     * `parent::getEloquentQuery()` dintr-o resursă Filament întoarce `Builder<Model>`, iar
     * closure-ul din `modifyQueryUsing()` primește un `Builder` neparametrizat. Pe acestea,
     * apelul de scope „prin magie" nu poate fi verificat, iar alternativa ar fi să-i spun
     * analizorului un `@var` inventat — adică să ascund tipul, nu să-l exprim.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyStaffVisibility(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || $user->isAdministrator()) {
            return $query;
        }

        $teacher = $user->teacher;

        if (! $teacher instanceof Teacher) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        // CONTEXTUL pedagogic (multi-rol F3): în context Diriginte rămâne DOAR ramura de
        // dirigenție (exclusiv clasele lui, doc pct. 5); în context Profesor DOAR ramura de
        // predare (clasa de dirigenție se vede prin ea numai dacă predă acolo — cu drepturi de
        // profesor). Fără context (mono-rol) = ambele ramuri, comportamentul istoric.
        $context = $user->teachingContext();

        return $query->where(function (Builder $scoped) use ($teacher, $table, $context): void {
            $homeroomBranch = $context !== UserRole::Profesor;
            $taughtBranch = $context !== UserRole::Diriginte;

            if ($homeroomBranch) {
                // Ca DIRIGINTE: toată clasa, orice disciplină.
                $scoped->whereIn($table.'.school_class_id', $teacher->homeroomSchoolClassIds());
            }

            if ($taughtBranch) {
                // Ca PROFESOR: doar acolo unde predă chiar acea disciplină, la acea clasă.
                $method = $homeroomBranch ? 'orWhereExists' : 'whereExists';

                $scoped->{$method}(function (QueryBuilder $sub) use ($teacher, $table): void {
                    $sub->selectRaw('1')
                        ->from('teaching_assignments as ta')
                        ->whereColumn('ta.school_class_id', $table.'.school_class_id')
                        ->whereColumn('ta.subject_id', $table.'.subject_id')
                        ->where('ta.teacher_id', $teacher->id)
                        ->whereNull('ta.deleted_at');
                });
            }
        });
    }
}
