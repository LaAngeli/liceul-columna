<?php

namespace App\Filament\Resources\AdmissionRequests;

use App\Actions\ProcessAdmissionRequest;
use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdmissionRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Acțiunile de procesare ale unei cereri de înscriere — O SINGURĂ definiție, montată și pe
 * rândurile tabelului, și în antetul fișei cererii. Fiecare tranziție trece prin
 * {@see ProcessAdmissionRequest} (urmă completă: cine, când, cu ce notă), iar închiderea cu
 * „înmatriculat" întinde puntea către fluxul unificat de onboarding al elevului.
 */
class AdmissionRequestActions
{
    /** Familia a fost contactată — cererea trece „în lucru", cu momentul contactării reținut. */
    public static function markContacted(): Action
    {
        return Action::make('markContacted')
            ->label(__('panel.actions.admission.contacted'))
            ->icon('heroicon-o-phone')
            ->color('info')
            ->visible(fn (AdmissionRequest $record): bool => $record->status === AdmissionStatus::Nou)
            ->action(function (AdmissionRequest $record): void {
                app(ProcessAdmissionRequest::class)->markContacted($record, self::actor());

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.admission.contacted_done'))
                    ->send();
            });
    }

    /**
     * Programează vizita (calendar v3): data + ora stabilite cu familia. Disponibilă pe cererile
     * de tip VIZITĂ încă în lucru; re-apelarea RE-programează. O cerere „Nouă" trece automat în
     * „Contactat" (programarea presupune contact). Vizita apare în calendarul instituțional.
     */
    public static function scheduleVisit(): Action
    {
        return Action::make('scheduleVisit')
            ->label(__('panel.actions.admission.schedule_visit'))
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->visible(fn (AdmissionRequest $record): bool => $record->type === AdmissionRequestType::Visit
                && ! $record->status->isFinal())
            ->schema([
                DateTimePicker::make('scheduled_visit_at')
                    ->label(__('panel.forms.admission.scheduled_visit_at'))
                    ->helperText(__('panel.forms.admission.scheduled_visit_hint'))
                    ->default(fn (AdmissionRequest $record) => $record->scheduled_visit_at)
                    ->seconds(false)
                    ->required(),
            ])
            ->modalHeading(__('panel.actions.admission.schedule_visit_heading'))
            ->modalDescription(fn (AdmissionRequest $record): string => __('panel.actions.admission.schedule_visit_description', ['parent' => $record->parent_name]))
            ->modalSubmitActionLabel(__('panel.actions.admission.schedule_visit'))
            ->action(function (AdmissionRequest $record, array $data): void {
                app(ProcessAdmissionRequest::class)->scheduleVisit(
                    $record,
                    self::actor(),
                    Carbon::parse((string) $data['scheduled_visit_at']),
                );

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.admission.visit_scheduled_done'))
                    ->body($record->refresh()->scheduled_visit_at?->translatedFormat('l, j F Y · H:i') ?? '')
                    ->send();
            });
    }

    /**
     * Închide cererea cu succes. Nota internă e opțională; confirmarea oferă direct
     * butonul „Creează contul elevului" — onboarding-ul pre-completat cu numele copilului.
     */
    public static function enroll(): Action
    {
        return Action::make('enroll')
            ->label(__('panel.actions.admission.enroll'))
            ->icon('heroicon-o-academic-cap')
            ->color('success')
            ->visible(fn (AdmissionRequest $record): bool => ! $record->status->isFinal())
            ->schema([
                Textarea::make('staff_note')
                    ->label(__('panel.forms.admission.staff_note'))
                    ->helperText(__('panel.forms.admission.staff_note_hint'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->modalHeading(__('panel.actions.admission.enroll_heading'))
            ->modalDescription(fn (AdmissionRequest $record): string => __('panel.actions.admission.enroll_description', ['child' => $record->child_name]))
            ->modalSubmitActionLabel(__('panel.actions.admission.enroll'))
            ->action(function (AdmissionRequest $record, array $data): void {
                app(ProcessAdmissionRequest::class)->enroll($record, self::actor(), $data['staff_note'] ?? null);

                Notification::make()
                    ->success()
                    ->persistent()
                    ->title(__('panel.actions.admission.enrolled_done'))
                    ->body(__('panel.actions.admission.enrolled_body', ['child' => $record->child_name]))
                    ->actions([
                        Action::make('onboardStudent')
                            ->label(__('panel.actions.admission.create_student_account'))
                            ->button()
                            ->url(self::onboardingUrl($record)),
                    ])
                    ->send();
            });
    }

    /** Închide cererea cu refuz — motivul intern e OBLIGATORIU (arhiva trebuie să explice decizia). */
    public static function refuse(): Action
    {
        return Action::make('refuse')
            ->label(__('panel.actions.admission.refuse'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (AdmissionRequest $record): bool => ! $record->status->isFinal())
            ->schema([
                Textarea::make('staff_note')
                    ->label(__('panel.forms.admission.refusal_reason'))
                    ->helperText(__('panel.forms.admission.refusal_reason_hint'))
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->modalHeading(__('panel.actions.admission.refuse_heading'))
            ->modalDescription(fn (AdmissionRequest $record): string => __('panel.actions.admission.refuse_description', ['child' => $record->child_name]))
            ->modalSubmitActionLabel(__('panel.actions.admission.refuse'))
            ->action(function (AdmissionRequest $record, array $data): void {
                app(ProcessAdmissionRequest::class)->refuse($record, self::actor(), (string) $data['staff_note']);

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.admission.refused_done'))
                    ->send();
            });
    }

    /** Redeschide o cerere închisă (familia a revenit / decizie greșită) — înapoi în coadă. */
    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label(__('panel.actions.admission.reopen'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (AdmissionRequest $record): bool => $record->status->isFinal())
            ->requiresConfirmation()
            ->modalHeading(__('panel.actions.admission.reopen_heading'))
            ->modalDescription(fn (AdmissionRequest $record): string => __('panel.actions.admission.reopen_description', ['child' => $record->child_name]))
            ->action(function (AdmissionRequest $record): void {
                app(ProcessAdmissionRequest::class)->reopen($record, self::actor());

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.admission.reopened_done'))
                    ->send();
            });
    }

    /**
     * Puntea către onboarding-ul unificat: numele copilului din cerere pre-completează
     * Identitatea (primul cuvânt = numele de familie — convenția catalogului; se corectează
     * în formular dacă familia a scris invers).
     */
    public static function onboardingUrl(AdmissionRequest $record): string
    {
        $parts = preg_split('/\s+/', trim($record->child_name), 2) ?: [];

        return UserResource::getUrl('create', array_filter([
            'rol' => UserRole::Elev->value,
            'nume' => $parts[0] ?? null,
            'prenume' => $parts[1] ?? null,
        ]));
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = auth('web')->user();

        return $user;
    }
}
