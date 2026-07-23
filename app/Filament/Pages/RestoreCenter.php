<?php

namespace App\Filament\Pages;

use App\Actions\InspectRestoreConflicts;
use App\Actions\RestoreArchivedRecord;
use App\Enums\RestorableType;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\RestoreInventory;
use App\Support\SchoolCalendar;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * „Restaurare" — coșul școlii (cerința beneficiarului 2026-07-23).
 *
 * Problema pe care o rezolvă: ștergerile erau reversibile în COD (soft delete pe 22 de modele),
 * dar practic irecuperabile în PANOU — singura cale era filtrul „Șterse" din tabelul fiecărei
 * secțiuni, pe care nimeni nu-l deschide, iar la Profesori acțiunea de restaurare era de-a dreptul
 * inaccesibilă (tabelul n-avea filtrul, deci un profesor șters nu putea fi listat niciodată).
 * Aici există UN singur loc care răspunde la „ce s-a șters" și îl readuce.
 *
 * Ce NU e aici: notele și absențele (au ANULARE cu motiv, nu ștergere — §1) și conturile
 * (nu se mai șterg din panou, se suspendă). Vezi {@see RestorableType}.
 *
 * Drept: `canConfigureSchool()` — super-admin, director, administrator operațional. Ștergerea
 * DEFINITIVĂ (golirea coșului) rămâne doar la super-admin: e singura operațiune din tot fluxul
 * care pierde date fără întoarcere.
 */
class RestoreCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'restaurare';

    protected string $view = 'filament.administration.restore-center';

    /** Tipul deschis (contextul) — validat la citire, ca orice parametru de navigator. */
    #[Url(as: 'tip', except: null)]
    public ?string $activeType = null;

    /** Înregistrarea pentru care se cere confirmarea ștergerii definitive. */
    public ?int $confirmingPurge = null;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.restore.title');
    }

    public function getTitle(): string
    {
        return __('panel.restore.title');
    }

    public function getSubheading(): ?string
    {
        return __('panel.restore.subtitle');
    }

    public static function canAccess(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    /** Badge cu totalul din coș — coșul gol nu-și cere atenția. */
    public static function getNavigationBadge(): ?string
    {
        $total = array_sum(app(RestoreInventory::class)->counts());

        return $total > 0 ? (string) $total : null;
    }

    // ── Navigatorul: carduri pe tip → lista tipului ──────────────────────────────────────

    public function activeType(): ?RestorableType
    {
        return $this->activeType !== null ? RestorableType::tryFrom($this->activeType) : null;
    }

    public function openType(string $key): void
    {
        $this->activeType = RestorableType::tryFrom($key)?->value;
        $this->confirmingPurge = null;
    }

    public function leaveType(): void
    {
        $this->activeType = null;
        $this->confirmingPurge = null;
    }

    /**
     * Cardurile de categorie: câte sunt și de când datează cea mai recentă ștergere.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, count: int, last: string|null}>
     */
    public function typeCards(): array
    {
        $inventory = app(RestoreInventory::class);
        $counts = $inventory->counts();

        $cards = [];

        foreach (RestorableType::cases() as $type) {
            $last = $inventory->lastDeletedAt($type);

            $cards[] = [
                'key' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'count' => $counts[$type->value] ?? 0,
                'last' => $last !== null ? SchoolCalendar::local($last)?->translatedFormat('d.m.Y H:i') : null,
            ];
        }

        return $cards;
    }

    /**
     * Lista tipului deschis: fiecare înregistrare cu contextul ștergerii și cu VERDICTUL —
     * ce blochează restaurarea și ce nu revine odată cu ea.
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, deleted_at: string|null, deleted_by: string|null, blocking: array<int, string>, warnings: array<int, string>, cascade: int}>
     */
    public function records(): array
    {
        $type = $this->activeType();

        if ($type === null) {
            return [];
        }

        $inventory = app(RestoreInventory::class);
        $inspector = app(InspectRestoreConflicts::class);

        $records = $inventory->records($type);
        $context = $inventory->deletionContext($type, $records);

        $rows = [];

        foreach ($records as $record) {
            $conflicts = $inspector->inspect($record);
            $id = (int) $record->getKey();

            $rows[] = [
                'id' => $id,
                'title' => $type->titleFor($record),
                'subtitle' => $type->subtitleFor($record),
                'deleted_at' => SchoolCalendar::local($record->deleted_at)?->translatedFormat('d.m.Y H:i'),
                'deleted_by' => $context[$id]['name'] ?? null,
                'blocking' => $conflicts['blocking'],
                'warnings' => $conflicts['warnings'],
                'cascade' => $conflicts['cascade'],
            ];
        }

        return $rows;
    }

    public function canPurge(): bool
    {
        return auth('web')->user()?->isSuperAdmin() ?? false;
    }

    // ── Acțiuni ─────────────────────────────────────────────────────────────────────────

    public function restore(int $id): void
    {
        $type = $this->activeType();

        if ($type === null || ! $this->authorized()) {
            return;
        }

        $record = $this->findTrashed($type, $id);

        if ($record === null) {
            return;
        }

        try {
            $result = app(RestoreArchivedRecord::class)->restore($record);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('panel.restore.blocked_title'))
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->send();

            return;
        }

        $body = $result['cascaded'] > 0
            ? (string) trans_choice('panel.restore.restored_with_cascade', $result['cascaded'], ['count' => $result['cascaded']])
            : null;

        if ($result['repaired'] !== []) {
            $body = trim(($body ?? '').' '.implode(' ', $result['repaired']));
        }

        Notification::make()
            ->success()
            ->title(__('panel.restore.restored_title'))
            ->body($body !== '' ? $body : null)
            ->send();

        $this->confirmingPurge = null;
    }

    public function askPurge(int $id): void
    {
        $this->confirmingPurge = $this->canPurge() ? $id : null;
    }

    public function cancelPurge(): void
    {
        $this->confirmingPurge = null;
    }

    /**
     * Ștergerea DEFINITIVĂ: singurul loc din flux care pierde date fără întoarcere, deci
     * super-admin + confirmare explicită. Restul rolurilor nici nu văd butonul, iar garda se
     * repetă pe server.
     */
    public function purge(int $id): void
    {
        $type = $this->activeType();

        if ($type === null || ! $this->authorized() || ! $this->canPurge()) {
            return;
        }

        $record = $this->findTrashed($type, $id);

        if ($record === null) {
            return;
        }

        $record->forceDelete();
        $this->confirmingPurge = null;

        Notification::make()
            ->success()
            ->title(__('panel.restore.purged_title'))
            ->send();
    }

    private function authorized(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canConfigureSchool();
    }

    /** @return Student|Teacher|SchoolClass|Enrollment|Subject|null */
    private function findTrashed(RestorableType $type, int $id): ?Model
    {
        return $type->modelClass()::query()->onlyTrashed()->whereKey($id)->first();
    }
}
