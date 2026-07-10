<?php

namespace App\Filament\Resources\Messages;

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Enums\UserRole;
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
use Illuminate\Validation\ValidationException;

/**
 * Compunerea din poșta personalului (mesaj nou + răspuns).
 *
 * Destinatarii sunt organizați pe 4 categorii ABSOLUTE (Administrație / Colegi / Părinți / Elevi),
 * calculate pe SERVER din rolul expeditorului ({@see SendMessage::allowedRecipientsForStaff()}).
 * Pentru părinți/elevi, opțiunea poartă ancora pe elev („studentId:userId") — mesajul către o
 * familie e mereu despre un elev concret, iar poarta reală rămâne `canSendDirect()`, verificată
 * la fiecare scriere (o valoare falsificată ⇒ 403). Atașamentele trec prin ACELEAȘI reguli ca la
 * cabinet și sunt validate ÎNAINTE de crearea mesajului — un fișier neterminat de încărcat sau de
 * tip interzis blochează expedierea, nu dispare tăcut.
 */
class ComposeSchema
{
    /**
     * Selectorul de fișiere. `storeFiles(false)` ne dă `TemporaryUploadedFile` (care extinde
     * `UploadedFile`), ca să le putem valida și muta noi pe discul privat — Filament nu le poate
     * atașa singur unei relații de mesaj.
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
     * Schema „Mesaj nou": categoria de destinatar, apoi selecțiile dependente.
     *
     * @return array<int, mixed>
     */
    public static function compose(User $staff): array
    {
        $allowed = app(SendMessage::class)->allowedRecipientsForStaff($staff);

        // Categoriile fără destinatari dispar (ex. un profesor fără elevi cu cont; conducerea
        // fără elevi predați nu vede deloc Părinți/Elevi — ea răspunde la audiențe, §4.2).
        $kinds = array_filter([
            'administration' => $allowed['administration'] === [] ? null : __('panel.mailbox.kind_administration'),
            'colleague' => $allowed['colleagues'] === [] ? null : __('panel.mailbox.kind_colleague'),
            'parent' => $allowed['parents'] === [] ? null : __('panel.mailbox.kind_parent'),
            'student' => $allowed['students'] === [] ? null : __('panel.mailbox.kind_student'),
        ]);

        $staffOptions = static fn (array $entries): array => collect($entries)
            ->mapWithKeys(fn (array $entry): array => [
                $entry['id'] => $entry['name'].' — '.self::roleLabel($entry['role']),
            ])
            ->all();

        // Ancora pe elev călătorește în valoarea opțiunii: „studentId:userId".
        $parentOptions = collect($allowed['parents'])
            ->mapWithKeys(fn (array $p): array => [
                $p['studentId'].':'.$p['userId'] => $p['name']
                    .' — '.__('panel.fields.student').': '.$p['studentName']
                    .($p['classLabel'] !== null ? ' · '.$p['classLabel'] : ''),
            ])
            ->all();

        $studentOptions = collect($allowed['students'])
            ->mapWithKeys(fn (array $s): array => [
                $s['studentId'].':'.$s['userId'] => $s['name']
                    .($s['classLabel'] !== null ? ' · '.$s['classLabel'] : ''),
            ])
            ->all();

        return [
            Select::make('kind')
                ->label(__('panel.mailbox.recipient_kind'))
                ->options($kinds)
                ->default(array_key_first($kinds))
                ->required()
                ->live(),

            Select::make('recipient_user_id')
                ->label(__('panel.mailbox.recipient'))
                ->options(fn (Get $get): array => $staffOptions(
                    $get('kind') === 'administration' ? $allowed['administration'] : $allowed['colleagues'],
                ))
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => in_array($get('kind'), ['administration', 'colleague'], true)),

            Select::make('parent_target')
                ->label(__('panel.mailbox.recipient'))
                ->options($parentOptions)
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('kind') === 'parent'),

            Select::make('student_target')
                ->label(__('panel.mailbox.recipient'))
                ->options($studentOptions)
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $get('kind') === 'student'),

            TextInput::make('subject')
                ->label(__('panel.tables.messages.subject'))
                ->required()
                ->maxLength(120),

            Textarea::make('body')
                ->label(__('panel.mailbox.body'))
                ->required()
                ->maxLength(2000)
                ->rows(6),

            self::files(),
        ];
    }

    /**
     * Trimite mesajul compus. Atașamentele se validează ÎNTÂI (un eșec nu lasă un mesaj deja
     * expediat fără fișier), apoi `SendMessage::direct()` re-verifică regula ierarhică pe server.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $statePath  prefixul stării formularului (pentru cheile erorilor de validare)
     */
    public static function send(User $staff, array $data, ?string $statePath = null): Message
    {
        $files = self::extractFiles($data, $statePath);

        $kind = (string) ($data['kind'] ?? '');

        if (in_array($kind, ['administration', 'colleague'], true)) {
            $recipient = User::query()->findOrFail((int) ($data['recipient_user_id'] ?? 0));
            $student = null;
        } else {
            [$student, $recipient] = self::resolveFamilyTarget(
                (string) ($data[$kind.'_target'] ?? ''),
            );
        }

        $message = app(SendMessage::class)->direct(
            $staff,
            $recipient,
            (string) $data['body'],
            (string) $data['subject'],
            $student,
        );

        app(StoreMessageAttachments::class)->handle($message, $files);

        return $message;
    }

    /**
     * Extrage și VALIDEAZĂ fișierele din starea formularului, înainte de orice scriere:
     *  • un marker de încărcare neterminată (`livewire-file:*`) blochează expedierea cu o eroare
     *    vizibilă — altfel mesajul ar pleca tăcut FĂRĂ fișierul pe care expeditorul îl vede atașat;
     *  • aceeași listă albă de tipuri + limite ca la cabinet ({@see StoreMessageAttachments}).
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $statePath  prefixul stării formularului — erorile trebuie cheiate pe el
     *                                  (ex. „reply.files") ca să apară SUB câmp, nu pierdute
     * @return array<int, UploadedFile>
     */
    public static function extractFiles(array $data, ?string $statePath = null): array
    {
        $errorKeys = array_values(array_unique([
            $statePath !== null ? "{$statePath}.files" : 'files',
            'files',
        ]));

        $raw = array_values(array_filter(
            (array) ($data['files'] ?? []),
            static fn (mixed $file): bool => $file !== null && $file !== '',
        ));

        $files = array_values(array_filter($raw, static fn (mixed $file): bool => $file instanceof UploadedFile));

        if (count($files) !== count($raw)) {
            $message = (string) __('panel.mailbox.attachments_uploading');

            throw ValidationException::withMessages(array_fill_keys($errorKeys, $message));
        }

        if ($files === []) {
            return [];
        }

        try {
            Validator::make(['files' => $files], StoreMessageAttachments::validationRules())->validate();
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->all();

            throw ValidationException::withMessages(array_fill_keys($errorKeys, $messages));
        }

        return $files;
    }

    /**
     * Desface ancora „studentId:userId" a unei opțiuni de familie. Valorile falsificate nu trec:
     * perechea e re-verificată de `canSendDirect()` (tutorele/contul trebuie să aparțină REAL
     * elevului, iar expeditorul să-l predea).
     *
     * @return array{0: Student, 1: User}
     */
    private static function resolveFamilyTarget(string $target): array
    {
        [$studentId, $userId] = array_pad(explode(':', $target, 2), 2, '');

        abort_unless(ctype_digit($studentId) && ctype_digit($userId), 422, 'Destinatar invalid.');

        return [
            Student::query()->findOrFail((int) $studentId),
            User::query()->findOrFail((int) $userId),
        ];
    }

    private static function roleLabel(string $role): string
    {
        return UserRole::tryFrom($role)?->label() ?? $role;
    }
}
