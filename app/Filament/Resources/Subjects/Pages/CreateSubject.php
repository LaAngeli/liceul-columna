<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Actions\SyncSubjectTeachers;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Subject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SubjectResource::class;

    /**
     * (1) Poziția în foaia matricolă se aplică DUPĂ creare, prin singura cale de scriere
     * ({@see Subject::placeInReportOrder}) — câmpul din formular nu se dehidratează,
     * deci inserarea pe o poziție ocupată împinge restul tranzacțional, fără duplicate.
     * (2) Profesorii disciplinei (cerința 07.08.2026: echipa se declară DE LA CREARE, nu abia
     * pe fișă) — rândurile repeater-ului, tot nedehidratate, trec prin
     * {@see SyncSubjectTeachers} și devin alocări didactice pe anul curent.
     */
    protected function afterCreate(): void
    {
        $raw = $this->data['report_order'] ?? null;

        /** @var Subject $subject */
        $subject = $this->getRecord();

        Subject::placeInReportOrder($subject, is_numeric($raw) ? (int) $raw : null);

        $teachers = $this->data['teachers'] ?? [];
        app(SyncSubjectTeachers::class)->execute($subject, is_array($teachers) ? $teachers : []);
    }

    /**
     * După creare → FIȘA disciplinei, nu lista: acolo stau alocările (profesori × clase), adică
     * exact pasul următor firesc al configuratorului (cerința 07.08.2026 — „după creare nu mai
     * poți modifica profesorii, clasele"). Îndrumarea o spune explicit.
     */
    protected function getRedirectUrl(): string
    {
        return SubjectResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('panel.forms.subject.created_assignments_title'))
            ->body(__('panel.forms.subject.created_assignments_body'));
    }
}
