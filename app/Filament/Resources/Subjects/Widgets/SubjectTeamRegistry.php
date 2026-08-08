<?php

namespace App\Filament\Resources\Subjects\Widgets;

use App\Actions\BuildSubjectTeamRegistry;
use App\Filament\Resources\Subjects\Pages\EditSubject;
use App\Models\Subject;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * REGISTRUL ECHIPEI disciplinei — widget de subsol pe fișa de editare (v3, 08.08.2026):
 * anii sunt FILE la vedere (un click, nu un filtru ascuns după pâlnie), echipa anului ales
 * e o listă per profesor cu clasele lui ca pastile (activă = verde, retrasă = gri, cu
 * eticheta spusă), plus un rezumat numeric. Implicit: ANUL CURENT.
 *
 * Consultativ prin construcție — n-are nicio acțiune de scriere, deci nu se mai poate călca
 * cu formularul „Profesorii disciplinei" de deasupra (motivul pentru care predecesorul,
 * un relation manager, fusese golit de acțiuni). Datele vin gata-așezate din
 * {@see BuildSubjectTeamRegistry}; widget-ul doar ține anul ales.
 *
 * NU e lazy: lazy-load-ul lăsa în josul fișei o casetă goală până la derulare — exact
 * golul reclamat la restructurarea așezării.
 */
class SubjectTeamRegistry extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.subject-team-registry';

    /** Fișa deschisă — injectată de pagină prin {@see EditSubject::getWidgetData()}. */
    public ?Subject $record = null;

    /** Anul ales din file; null = implicitul acțiunii (anul curent, altfel cel mai nou cu alocări). */
    public ?int $anAles = null;

    public function alegeAnul(int $yearId): void
    {
        $this->anAles = $yearId;
    }

    /**
     * Reîmprospătare la salvarea formularului de deasupra (review 08.08.2026): widget-ul e un
     * copil Livewire cu cheie stabilă, deci re-randarea PAGINII nu-l atinge — fără acest
     * ascultător, o clasă retrasă din „Profesorii disciplinei" rămânea pastilă verde în registru
     * până la refresh, ca și cum salvarea n-ar fi mers. Corpul gol e tot ce trebuie: simpla
     * primire a evenimentului declanșează re-randarea, iar datele se citesc oricum proaspete.
     */
    #[On('subject-team-registry-updated')]
    public function reimprospateaza(): void {}

    /**
     * Gardă-oglindă a fostului `canViewForRecord`: fișa e a configuratorilor, dar consultarea
     * echipei cere context academic — administratorul tehnic nu are ce căuta în ea.
     */
    public static function canView(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    /**
     * @return array{
     *     years: array<int, array{id: int, name: string, is_current: bool}>,
     *     selected: array{id: int, name: string, is_current: bool}|null,
     *     teachers: array<int, array{name: string, archived: bool, group: int|null, classes: array<int, array{label: string, withdrawn: bool}>, active: int, withdrawn: int}>,
     *     stats: array{teachers: int, active: int, withdrawn: int},
     * }
     */
    public function registru(): array
    {
        if (! $this->record instanceof Subject) {
            return ['years' => [], 'selected' => null, 'teachers' => [], 'stats' => ['teachers' => 0, 'active' => 0, 'withdrawn' => 0]];
        }

        return app(BuildSubjectTeamRegistry::class)->execute($this->record, $this->anAles);
    }
}
