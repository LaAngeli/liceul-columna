<?php

namespace App\Filament\Resources\DocumentRequests\Schemas;

use App\Enums\DocumentRequestType;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fișa-DOCUMENT a cererii tipice: ce a depus familia (tip, elev, depunător, perioada învoirii,
 * comentariul cu detaliile) + urma procesării (cine, când, cu ce comentariu — cel pe care
 * familia îl vede în cabinet). Decizia se ia CU cererea în față, nu dintr-un rând de tabel.
 */
class DocumentRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.document_nav.section_request'))
                    ->description(__('panel.document_nav.section_request_hint'))
                    ->columns(3)
                    ->schema([
                        // Text simplu, NU badge: etichetele lungi („Cerere de reexaminare /
                        // contestație a notei") se frâng pe două rânduri în loc să se trunchieze.
                        TextEntry::make('type')
                            ->label(__('panel.fields.type'))
                            ->weight('medium')
                            ->color('primary')
                            ->formatStateUsing(fn (DocumentRequestType $state): string => $state->label()),
                        TextEntry::make('created_at')
                            ->label(__('panel.fields.received_at'))
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('status')
                            ->label(__('panel.fields.status'))
                            ->badge()
                            ->color(fn (RequestStatus $state): string => $state->color()),
                        TextEntry::make('student.full_name')
                            ->label(__('panel.fields.student'))
                            ->weight('bold')
                            ->helperText(fn (DocumentRequest $record): ?string => self::studentClassLabel($record)),
                        TextEntry::make('requestedBy.name')
                            ->label(__('panel.fields.requested_by'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('period')
                            ->label(__('panel.document_nav.period'))
                            ->state(fn (DocumentRequest $record): string => self::periodLabel($record))
                            ->visible(fn (DocumentRequest $record): bool => $record->type->needsPeriod()),
                        // Nota contestată — snapshot-ul din depunere (disciplină, valoare, dată,
                        // profesor): decizia se ia cu contextul în față, nu reconstruindu-l.
                        TextEntry::make('contested_grade')
                            ->label(__('panel.document_nav.contested_grade'))
                            ->state(fn (DocumentRequest $record): string => (string) $record->contestedGradeLabel())
                            ->weight('bold')
                            ->visible(fn (DocumentRequest $record): bool => $record->contestedGradeLabel() !== null)
                            ->columnSpanFull(),
                        TextEntry::make('family_details')
                            ->label(__('panel.document_nav.family_details'))
                            ->state(fn (DocumentRequest $record): string => (string) ($record->payload['details'] ?? ''))
                            ->placeholder(__('panel.document_nav.no_details'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('panel.document_nav.section_processing'))
                    ->description(__('panel.document_nav.section_processing_hint'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reviewedBy.name')
                            ->label(__('panel.forms.admission.processed_by'))
                            ->placeholder(__('panel.common.dash')),
                        TextEntry::make('reviewed_at')
                            ->label(__('panel.forms.admission.processed_at'))
                            ->dateTime('d.m.Y H:i')
                            ->placeholder(__('panel.document_nav.still_open')),
                        TextEntry::make('review_note')
                            ->label(__('panel.document_nav.review_note'))
                            ->placeholder(__('panel.document_nav.no_note'))
                            ->helperText(__('panel.document_nav.review_note_hint'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Clasa curentă a elevului — cererea se judecă știind și unde învață copilul. */
    private static function studentClassLabel(DocumentRequest $record): ?string
    {
        $class = $record->student?->currentSchoolClass();

        return $class instanceof SchoolClass
            ? trim($class->name.' '.($class->section ?? ''))
            : null;
    }

    /** Perioada învoirii, umanizată (o zi sau interval). */
    private static function periodLabel(DocumentRequest $record): string
    {
        $start = $record->payload['period_start'] ?? null;
        $end = $record->payload['period_end'] ?? null;

        if (! is_string($start) || $start === '') {
            return (string) __('panel.common.dash');
        }

        try {
            $startLabel = Carbon::parse($start)->format('d.m.Y');
            $endLabel = is_string($end) && $end !== '' ? Carbon::parse($end)->format('d.m.Y') : $startLabel;
        } catch (\Throwable) {
            return trim($start.' — '.(string) $end);
        }

        return $startLabel === $endLabel ? $startLabel : $startLabel.' — '.$endLabel;
    }
}
