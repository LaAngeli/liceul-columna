<?php

namespace App\Filament\Resources\AdmissionRequests\Schemas;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Models\AdmissionRequest;
use App\Support\SchoolCalendar;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fișa-DOCUMENT a cererii de înscriere: ce a trimis familia (read-only — datele sursă nu se
 * rescriu de personal) + urma procesării (cine, când, cu ce notă). Contactele sunt acționabile
 * direct (tel:/mailto:) — secretariatul sună dintr-un click.
 */
class AdmissionRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.admission_nav.section_request'))
                    ->description(__('panel.admission_nav.section_request_hint'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('panel.fields.type'))
                            ->badge()
                            ->color(fn (AdmissionRequestType $state): string => $state->color()),
                        TextEntry::make('created_at')
                            ->label(__('panel.fields.received_at'))
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('status')
                            ->label(__('panel.fields.status_label'))
                            ->badge()
                            ->color(fn (AdmissionStatus $state): string => $state->color()),
                        TextEntry::make('child_name')
                            ->label(__('panel.forms.admission.child_name'))
                            ->weight('bold'),
                        TextEntry::make('child_age')
                            ->label(__('panel.forms.admission.child_age'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('desired_class')
                            ->label(__('panel.forms.admission.desired_class'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('parent_name')
                            ->label(__('panel.forms.admission.parent_name')),
                        TextEntry::make('phone')
                            ->label(__('panel.fields.phone'))
                            ->url(fn (AdmissionRequest $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                            ->color('primary'),
                        TextEntry::make('email')
                            ->label(__('panel.fields.email'))
                            ->url(fn (AdmissionRequest $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                            ->color(fn (AdmissionRequest $record): ?string => filled($record->email) ? 'primary' : null)
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('preferred_time')
                            ->label(__('panel.tables.admissions.visit_date'))
                            ->placeholder(__('panel.common.dash'))
                            ->formatStateUsing(fn (?string $state): string => self::formatVisitDate($state))
                            ->visible(fn (AdmissionRequest $record): bool => $record->type === AdmissionRequestType::Visit),
                        // Vizita PROGRAMATĂ de secretariat (calendar v3) — distinctă de preferința
                        // liberă a familiei de mai sus; apare în calendarul instituțional.
                        TextEntry::make('scheduled_visit_at')
                            ->label(__('panel.forms.admission.scheduled_visit_at'))
                            ->dateTime('d.m.Y H:i')
                            ->badge()
                            ->color('info')
                            ->placeholder(__('panel.forms.admission.scheduled_visit_none'))
                            ->visible(fn (AdmissionRequest $record): bool => $record->type === AdmissionRequestType::Visit),
                    ]),

                Section::make(__('panel.admission_nav.section_processing'))
                    ->description(__('panel.admission_nav.section_processing_hint'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('contacted_at')
                            ->label(__('panel.forms.admission.contacted_at'))
                            ->dateTime('d.m.Y H:i')
                            ->placeholder(__('panel.admission_nav.not_contacted')),
                        TextEntry::make('processed_at')
                            ->label(__('panel.forms.admission.processed_at'))
                            ->dateTime('d.m.Y H:i')
                            ->placeholder(__('panel.admission_nav.still_open')),
                        TextEntry::make('processedBy.name')
                            ->label(__('panel.forms.admission.processed_by'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('staff_note')
                            ->label(__('panel.forms.admission.staff_note'))
                            ->placeholder(__('panel.admission_nav.no_note'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Data vizitei, umanizată — string-ul brut rămâne fallback (formatele vechi din intake). */
    public static function formatVisitDate(?string $state): string
    {
        if (! $state) {
            return (string) __('panel.common.dash');
        }

        try {
            return (string) SchoolCalendar::local(Carbon::parse($state))?->translatedFormat('d.m.Y · H:i');
        } catch (\Throwable) {
            return $state;
        }
    }
}
