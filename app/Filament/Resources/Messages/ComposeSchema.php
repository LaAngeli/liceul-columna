<?php

namespace App\Filament\Resources\Messages;

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Câmpurile comune de compunere din poșta personalului (mesaj nou + răspuns).
 *
 * Destinatarii NU sunt o listă de convenienţă: se calculează pe SERVER din rolul expeditorului
 * ({@see SendMessage::allowedRecipientsForStaff()}), iar poarta reală rămâne `canSendDirect()`,
 * verificată la fiecare scriere. Atașamentele trec prin ACELEAȘI reguli ca la cabinet.
 */
class ComposeSchema
{
    /**
     * Selectorul de fișiere. `storeFiles(false)` ne dă `TemporaryUploadedFile` (care extinde
     * `UploadedFile`), ca să le putem valida și muta noi pe discul privat — Filament nu le poate
     * atașa singur unei relații polimorfe de mesaj.
     */
    public static function files(): FileUpload
    {
        return FileUpload::make('files')
            ->label(__('panel.mailbox.attachments'))
            ->multiple()
            ->storeFiles(false)
            ->maxFiles((int) config('messaging.attachments.max_files', 5))
            ->maxSize((int) config('messaging.attachments.max_file_kb', 8192))
            ->helperText(__('panel.mailbox.attachments_hint', [
                'files' => (int) config('messaging.attachments.max_files', 5),
                'size' => (int) round(((int) config('messaging.attachments.max_file_kb', 8192)) / 1024),
            ]));
    }

    /**
     * Schema „Mesaj nou": întâi tipul de destinatar (coleg / familie), apoi selecțiile dependente.
     *
     * @return array<int, mixed>
     */
    public static function compose(User $staff): array
    {
        $allowed = app(SendMessage::class)->allowedRecipientsForStaff($staff);

        $colleagues = collect($allowed['colleagues'])->pluck('name', 'id')->all();
        $students = collect($allowed['families'])
            ->mapWithKeys(fn (array $family): array => [
                $family['studentId'] => $family['studentName'].($family['classLabel'] !== null ? ' · '.$family['classLabel'] : ''),
            ])->all();

        return [
            Select::make('kind')
                ->label(__('panel.mailbox.recipient_kind'))
                ->options(array_filter([
                    'colleague' => __('panel.mailbox.kind_colleague'),
                    // Conducerea n-are familii de inițiat (răspunde la audiențe) → opțiunea dispare.
                    'family' => $students === [] ? null : __('panel.mailbox.kind_family'),
                ]))
                ->default('colleague')
                ->required()
                ->live(),

            Select::make('recipient_user_id')
                ->label(__('panel.mailbox.recipient'))
                ->options($colleagues)
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('kind') === 'colleague'),

            Select::make('student_id')
                ->label(__('panel.fields.student'))
                ->options($students)
                ->searchable()
                ->required()
                ->live()
                ->visible(fn (Get $get): bool => $get('kind') === 'family'),

            Select::make('family_user_id')
                ->label(__('panel.mailbox.recipient'))
                ->options(fn (Get $get): array => self::familyOptions($allowed, (int) $get('student_id')))
                ->required()
                ->visible(fn (Get $get): bool => $get('kind') === 'family' && filled($get('student_id'))),

            TextInput::make('subject')
                ->label(__('panel.tables.messages.subject'))
                ->required()
                ->maxLength(120),

            Textarea::make('body')
                ->label(__('panel.actions.reply.body'))
                ->required()
                ->maxLength(2000)
                ->rows(6),

            self::files(),
        ];
    }

    /**
     * Trimite mesajul compus. `SendMessage::direct()` re-verifică regula ierarhică pe server —
     * dacă interfața a fost falsificată, aici se oprește (403).
     *
     * @param  array<string, mixed>  $data
     */
    public static function send(User $staff, array $data): Message
    {
        $isFamily = ($data['kind'] ?? 'colleague') === 'family';

        $recipient = User::query()->findOrFail((int) ($isFamily ? $data['family_user_id'] : $data['recipient_user_id']));
        $student = $isFamily
            ? Student::query()->findOrFail((int) $data['student_id'])
            : null;

        $message = app(SendMessage::class)->direct(
            $staff,
            $recipient,
            (string) $data['body'],
            (string) $data['subject'],
            $student,
        );

        self::storeFiles($message, $data);

        return $message;
    }

    /**
     * Validează și stochează fișierele cu ACELEAȘI reguli ca la cabinet (listă albă de tipuri,
     * mărime, număr) — `acceptedFileTypes` din interfață e doar de curtoazie, poarta e aici.
     *
     * @param  array<string, mixed>  $data
     */
    public static function storeFiles(Message $message, array $data): void
    {
        /** @var array<int, UploadedFile> $files */
        $files = array_values(array_filter(
            (array) ($data['files'] ?? []),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            return;
        }

        Validator::make(['files' => $files], StoreMessageAttachments::validationRules())->validate();

        app(StoreMessageAttachments::class)->handle($message, $files);
    }

    /**
     * @param  array{colleagues: array<int, array{id: int, name: string}>, families: array<int, array{studentId: int, studentName: string, classLabel: string|null, recipients: list<array{id: int, name: string, relation: string}>}>}  $allowed
     * @return array<int, string>
     */
    private static function familyOptions(array $allowed, int $studentId): array
    {
        foreach ($allowed['families'] as $family) {
            if ($family['studentId'] !== $studentId) {
                continue;
            }

            return collect($family['recipients'])
                ->mapWithKeys(fn (array $recipient): array => [
                    $recipient['id'] => $recipient['name'].' · '.__("panel.mailbox.relation_{$recipient['relation']}"),
                ])
                ->all();
        }

        return [];
    }
}
