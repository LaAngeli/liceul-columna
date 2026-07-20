<?php

namespace App\Filament\Resources\AbsenceMotivations\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\User;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

/**
 * FIȘA cererii de motivare — locul unde se JUDECĂ (spec §2.1): perioada + IMPACTUL (absențele
 * pe care validarea le va marca motivate), motivul integral, justificativul previzualizabil și
 * descărcabil, termenul de validare, contextul elevului (clasa curentă + diriginte) și cronologia
 * cererii — cu Validează / Respinge chiar aici. Dreptul de judecată e PER CERERE
 * ({@see AbsenceMotivation::canBeReviewedBy}): excepțiile tardive țin de vicedirectorul pe
 * educație, nu de diriginte. Respingerea cere obligatoriu motiv (familia îl vede în cabinet).
 *
 * @property AbsenceMotivation $record
 */
class ViewAbsenceMotivation extends ViewRecord
{
    protected static string $resource = AbsenceMotivationResource::class;

    protected string $view = 'filament.approvals.absence-motivation-details';

    public function getTitle(): string
    {
        return __('panel.absence_motivation_view.title', [
            'student' => $this->record->student->full_name,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('panel.actions.validate.label'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->canJudge())
                ->modalHeading(__('panel.actions.validate.label'))
                ->modalDescription(__('panel.actions.validate_bulk.description'))
                ->modalSubmitActionLabel(__('panel.actions.validate.label'))
                ->schema([
                    Textarea::make('review_note')
                        ->label(__('panel.common.review_note'))
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->record->approve((int) auth()->id(), $data['review_note'] ?? null);
                    $this->record->refresh();
                    AbsenceMotivationResource::flushPendingCache();

                    Notification::make()->success()->title(__('panel.actions.validate.success'))->send();
                }),

            Action::make('reject')
                ->label(__('panel.actions.reject.label'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (): bool => $this->canJudge())
                ->modalHeading(__('panel.actions.reject.label'))
                ->modalSubmitActionLabel(__('panel.actions.reject.label'))
                ->schema([
                    // Motiv OBLIGATORIU: familia vede în cabinet DE CE a fost respinsă cererea.
                    Textarea::make('review_note')
                        ->label(__('panel.common.rejection_reason'))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->record->reject((int) auth()->id(), $data['review_note'] ?? null);
                    $this->record->refresh();
                    AbsenceMotivationResource::flushPendingCache();

                    Notification::make()->warning()->title(__('panel.actions.reject.success'))->send();
                }),
        ];
    }

    /**
     * Dreptul de judecată e PER CERERE, nu pe capabilitate globală: dirigintele clasei curente
     * pentru cererile normale, vicedirectorul pe educație pentru excepții (tardive).
     */
    public function canJudge(): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $this->record->canBeReviewedBy($user);
    }

    /**
     * IMPACTUL cererii: absențele elevului din perioada cerută — exact ce va atinge validarea
     * ({@see AbsenceMotivation::absencesInPeriod} e sursa comună cu approve()).
     *
     * @return array{items: list<array{date: string, subject: string, motivated: bool}>, total: int, unmotivated: int}
     */
    public function absenceImpact(): array
    {
        $absences = $this->record->absencesInPeriod()
            ->with('subject')
            ->orderBy('occurred_on')
            ->get();

        $items = $absences
            ->map(fn (Absence $absence): array => [
                'date' => $absence->occurred_on->translatedFormat('d.m.Y'),
                'subject' => $absence->subject !== null
                    ? ContentTranslator::subject($absence->subject->name)
                    : (string) __('panel.absence_motivation_view.full_day'),
                'motivated' => $absence->is_motivated,
            ])
            ->all();

        return [
            'items' => array_values($items),
            'total' => $absences->count(),
            'unmotivated' => $absences->where('is_motivated', false)->count(),
        ];
    }

    /**
     * Justificativul atașat (PII de minor, stocare PRIVATĂ — servit doar prin ruta autentificată
     * cabinet.motivation.document): previzualizare inline pentru imagini/PDF + descărcare.
     *
     * @return array{download_url: string, inline_url: string, is_image: bool, is_pdf: bool, missing: bool}|null
     */
    public function documentMeta(): ?array
    {
        $path = $this->record->document_path;

        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'download_url' => route('cabinet.motivation.document', ['absenceMotivation' => $this->record->id]),
            'inline_url' => route('cabinet.motivation.document', ['absenceMotivation' => $this->record->id, 'inline' => 1]),
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png'], true),
            'is_pdf' => $extension === 'pdf',
            'missing' => ! Storage::disk('local')->exists($path),
        ];
    }

    /**
     * Contextul elevului: fișa lui, clasa CURENTĂ (înmatricularea cea mai recentă — aceeași
     * regulă ca scoping-ul și dreptul de validare) și dirigintele ei.
     *
     * @return array{name: string, url: string|null, archived: bool, class: string|null, homeroom: string|null}|null
     */
    public function studentContext(): ?array
    {
        $student = $this->record->student;

        if ($student === null) {
            return null;
        }

        $enrollment = $student->enrollments()
            ->with('schoolClass.homeroomTeacher')
            ->latest('academic_year_id')
            ->first();

        $class = $enrollment?->schoolClass;

        return [
            'name' => $student->full_name,
            'url' => $student->trashed() ? null : StudentResource::getUrl('view', ['record' => $student->id]),
            'archived' => $student->trashed(),
            'class' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
            'homeroom' => $class?->homeroomTeacher?->full_name,
        ];
    }

    /**
     * Cronologia CERERII: depunerea, apoi verdictul, cu nota integrală a validatorului.
     *
     * @return list<array{label: string, actor: string|null, at: string, note: string|null, color: string}>
     */
    public function timeline(): array
    {
        $entries = [[
            'label' => (string) __('panel.homework_correction_view.submitted'),
            'actor' => $this->record->requestedBy?->name,
            'at' => $this->record->created_at?->translatedFormat('d.m.Y H:i') ?? '—',
            'note' => null,
            'color' => 'bg-primary-500',
        ]];

        if ($this->record->reviewed_at !== null) {
            $entries[] = [
                'label' => $this->record->status->getLabel(),
                'actor' => $this->record->reviewedBy?->name,
                'at' => $this->record->reviewed_at->translatedFormat('d.m.Y H:i'),
                'note' => $this->record->review_note,
                'color' => match ($this->record->status) {
                    RequestStatus::Approved => 'bg-success-500',
                    RequestStatus::Rejected => 'bg-danger-500',
                    default => 'bg-gray-400',
                },
            ];
        }

        return $entries;
    }
}
