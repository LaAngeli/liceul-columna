<?php

namespace App\Filament\Resources\CorigentaExams\Schemas;

use App\Models\CorigentaSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CorigentaExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('corigenta_session_id')
                ->label(__('panel.forms.corigenta_exam.session'))
                ->relationship('session', 'id')
                ->getOptionLabelFromRecordUsing(fn (CorigentaSession $record): string => $record->season->label().' · '.$record->type->label().' ('.$record->starts_on->format('d.m.Y').')')
                ->searchable()
                ->live(),
            Select::make('exam_commission_id')
                ->label(__('panel.forms.corigenta_exam.commission'))
                ->relationship('commission', 'name')
                ->searchable(),
            DatePicker::make('scheduled_on')
                ->label(__('panel.forms.corigenta_exam.scheduled_on_long'))
                // Data trebuie să cadă în fereastra sesiunii: sesiunea E perioada de lichidare,
                // aprobată prin ordin. O dată din afara ei descrie un examen care, formal, nu are
                // loc în sesiunea de care e legat — iar familia îl vede ca programat.
                ->minDate(fn (Get $get): ?string => self::sessionWindow($get('corigenta_session_id'))['start'] ?? null)
                ->maxDate(fn (Get $get): ?string => self::sessionWindow($get('corigenta_session_id'))['end'] ?? null)
                ->helperText(function (Get $get): ?string {
                    $window = self::sessionWindow($get('corigenta_session_id'));

                    return $window === []
                        ? null
                        : (string) __('panel.forms.corigenta_exam.within_session', $window);
                }),
            // SEPARAREA ATRIBUȚIILOR (§3.2/§3.3): programarea (sesiune, comisie, dată) e configurare
            // — o face și administratorul operațional; CONSEMNAREA NOTEI e act de autoritate
            // academică, expres interzis AO („Nu introduce/editează note"). `dehydrated(false)` la
            // lipsa dreptului: câmpul dezactivat nu se trimite, deci nici un POST fabricat nu-l
            // scrie prin formular.
            TextInput::make('mark')
                ->label(__('panel.forms.corigenta_exam.mark'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->step(0.01)
                ->disabled(fn (): bool => ! (auth('web')->user()?->canAdministerCatalog() ?? false))
                ->dehydrated(fn (): bool => auth('web')->user()?->canAdministerCatalog() ?? false)
                ->helperText(fn (): string => (auth('web')->user()?->canAdministerCatalog() ?? false)
                    ? (string) __('panel.forms.corigenta_exam.mark_hint')
                    : (string) __('panel.forms.corigenta_exam.mark_locked'))
                ->placeholder(__('panel.forms.corigenta_exam.result_pending')),
        ]);
    }

    /**
     * Fereastra sesiunii alese, ca text pentru limitele calendarului. Array gol dacă nu e aleasă
     * nicio sesiune — atunci data rămâne liberă, fiindcă nu există o perioadă față de care s-o
     * raportăm.
     *
     * @return array{start: string, end: string}|array{}
     */
    private static function sessionWindow(mixed $sessionId): array
    {
        if (! is_numeric($sessionId)) {
            return [];
        }

        $session = CorigentaSession::query()->whereKey((int) $sessionId)->first();

        if ($session === null) {
            return [];
        }

        return [
            'start' => $session->starts_on->format('Y-m-d'),
            'end' => $session->ends_on->format('Y-m-d'),
        ];
    }
}
