<?php

namespace App\Filament\Widgets;

use App\Enums\CorrectionStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\GradeCorrection;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * „Necesită atenție" — lista de TRIAJ a dashboard-ului (redesign hybrid, varianta V-E): un singur
 * panou pe rânduri (icon + etichetă + număr + link) care adună ce cere intervenție de RUTINĂ, gated
 * pe rol: corigenți, elevi de urmărit, aprobări în așteptare. Înlocuiește cardurile de alertă din
 * overview-uri + fostul PendingApprovalsOverview → o singură sursă, scanabilă. („Clase fără diriginte"
 * e o excepție, nu rutină → widget separat ClassesNeedingHomeroom.)
 */
class NeedsAttention extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    // Polling 30s: coada de aprobări/alerte scade vizibil pe parcursul zilei.
    protected ?string $pollingInterval = '30s';

    /** @var list<array{label: string, icon: string, count: int, url: string}>|null */
    private static ?array $cachedItems = null;

    public static function canView(): bool
    {
        return self::items() !== [];
    }

    /**
     * Resetează cache-ul intra-request al elementelor de triaj. În prod nu e necesar (un request =
     * un utilizator); testele care schimbă utilizatorul curent între aserții trebuie să-l cheme.
     */
    public static function flushCache(): void
    {
        self::$cachedItems = null;
        AbsenceMotivationResource::flushPendingCache();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => self::items()];
    }

    /**
     * Elementele de triaj relevante rolului (fiecare gated de aceleași predicate ca widget-urile
     * originale). Memoizat per request — canView() + getViewData() consumă același rezultat.
     *
     * @return list<array{label: string, icon: string, count: int, url: string}>
     */
    private static function items(): array
    {
        if (self::$cachedItems !== null) {
            return self::$cachedItems;
        }

        $user = auth('web')->user();

        if (! $user instanceof User) {
            return self::$cachedItems = [];
        }

        $items = [];
        $currentTermId = Term::query()->where('is_current', true)->value('id');
        $termId = $currentTermId === null ? null : (int) $currentTermId;

        // Corigenți — conducerea vede toată școala; profesorul/dirigintele doar clasele lui. Sursa
        // predicatului „corigent" e UNICĂ ({@see Student::scopeCorigentInTerm}), împărtășită cu
        // filtrul din tabelul de elevi: prag din constantă + exclude corigențele deja promovate.
        // ⚠️ Fără semestru curent (vacanță/an închis) scope-ul e NEUTRU (nu filtrează) — corect
        // pentru filtru, dar contorul ar număra TOȚI elevii școlii → aici garda dă explicit 0.
        if ($user->isManagement()) {
            $items[] = self::item(
                'panel.widgets.director_overview.corigenti',
                'heroicon-o-exclamation-triangle',
                $termId === null ? 0 : Student::query()->corigentInTerm($termId)->count(),
                StudentResource::getUrl('index').'?corigenti=1',
            );
        } elseif (! $user->isAdministrator() && $user->teacher !== null) {
            $classIds = $user->teacher->visibleSchoolClassIds();
            $items[] = self::item(
                'panel.widgets.teacher_overview.corigenti',
                'heroicon-o-exclamation-triangle',
                $termId === null ? 0 : Student::query()
                    ->whereIn('id', Enrollment::query()->whereIn('school_class_id', $classIds)->select('student_id'))
                    ->corigentInTerm($termId)
                    ->count(),
                StudentResource::getUrl('index').'?corigenti=1',
            );
        }

        // Elevi de urmărit (absențe nemotivate ridicate în SEMESTRUL CURENT) — conducerea. Scoparea pe
        // termenul curent (ca la corigenți) face contorul acționabil; fără ea, aduna absențe pe toate
        // semestrele și devenea nerelevant (audit #36).
        if ($user->isManagement()) {
            $items[] = self::item(
                'panel.widgets.director_overview.students_to_watch',
                'heroicon-o-calendar-date-range',
                $termId === null ? 0 : Student::query()
                    ->whereHas('absences', fn (Builder $q) => $q->where('is_motivated', false)->where('term_id', $termId), '>=', 30)
                    ->count(),
                StudentResource::getUrl('index'),
            );
        }

        // NB: „clase fără diriginte" NU e listat aici. E o EXCEPȚIE (invariant: orice clasă cu elevi
        // are diriginte — impus la creare prin SchoolClassForm), nu un indicator de rutină. Reziduul
        // (import / vacanță) e rezolvat în widget-ul dedicat ClassesNeedingHomeroom, care se
        // auto-ascunde când e curat.

        // Aprobări în așteptare — fiecare gated de dreptul propriu.
        if ($user->canApproveGradeCorrections()) {
            $items[] = self::item(
                'panel.widgets.pending_approvals.grade_corrections.title',
                'heroicon-o-pencil-square',
                GradeCorrection::query()->where('status', CorrectionStatus::Pending)->count(),
                GradeCorrectionResource::getUrl('index', ['tableFilters' => ['status' => ['value' => CorrectionStatus::Pending->value]]]),
            );
        }

        if (AbsenceMotivationResource::canAccess()) {
            $items[] = self::item(
                'panel.widgets.pending_approvals.absence_motivations.title',
                'heroicon-o-check-badge',
                AbsenceMotivationResource::pendingMotivations()->count(),
                AbsenceMotivationResource::getUrl('index', ['tableFilters' => ['status' => ['value' => RequestStatus::Pending->value]]]),
            );
        }

        if (DocumentRequestResource::canAccess()) {
            $items[] = self::item(
                'panel.widgets.pending_approvals.document_requests.title',
                'heroicon-o-document-text',
                // whereHas exclude cererile elevilor ARHIVAȚI (aliniat cu badge-ul resursei).
                DocumentRequest::query()->where('status', RequestStatus::Pending)->whereHas('student')->count(),
                DocumentRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => RequestStatus::Pending->value]]]),
            );
        }

        return self::$cachedItems = $items;
    }

    /**
     * @return array{label: string, icon: string, count: int, url: string}
     */
    private static function item(string $labelKey, string $icon, int $count, string $url): array
    {
        return ['label' => (string) __($labelKey), 'icon' => $icon, 'count' => $count, 'url' => $url];
    }
}
