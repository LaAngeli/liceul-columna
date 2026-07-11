<?php

namespace App\Enums;

use App\Notifications\CatalogNotification;
use Filament\Support\Contracts\HasLabel;

/**
 * Evenimentele care pot genera o notificare (spec §5). Setul DISPONIBIL diferă pe rol
 * ({@see self::forRole()}): familia vede note/absențe/teme, conducerea vede evenimentele „de
 * nișă" (corecții, cereri). Eticheta + textul vin din `lang/{ro,ru,en}/notifications.php`, deci
 * sunt traduse în limba aleasă de utilizator — vezi {@see CatalogNotification}.
 */
enum NotificationType: string implements HasLabel
{
    // Familie (elev / părinte).
    case NewGrade = 'new_grade';
    case GradeAnnulled = 'grade_annulled';
    case GradeCorrected = 'grade_corrected';
    case NewAbsence = 'new_absence';
    case NewHomework = 'new_homework';
    case StatusChange = 'status_change';

    // Comune (toți).
    case NewMessage = 'new_message';
    case Announcement = 'announcement';

    // Nișă conducere / secretariat.
    case GradeCorrectionRequest = 'grade_correction_request';
    case GradeCorrectionRejected = 'grade_correction_rejected';
    case AbsenceMotivationSubmitted = 'absence_motivation_submitted';
    case DocumentRequestSubmitted = 'document_request_submitted';
    case AdmissionRequestSubmitted = 'admission_request_submitted';

    /**
     * Eticheta tradusă (în limba de interfață a utilizatorului curent).
     */
    public function label(): string
    {
        return (string) trans('notifications.types.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Iconița (Heroicon) afișată în clopoțelul Filament / inboxul cabinetului.
     */
    public function icon(): string
    {
        return match ($this) {
            self::NewGrade => 'heroicon-o-academic-cap',
            self::GradeAnnulled => 'heroicon-o-x-circle',
            self::GradeCorrected => 'heroicon-o-pencil-square',
            self::NewAbsence => 'heroicon-o-calendar-days',
            self::NewHomework => 'heroicon-o-book-open',
            self::StatusChange => 'heroicon-o-flag',
            self::NewMessage => 'heroicon-o-chat-bubble-left-right',
            self::Announcement => 'heroicon-o-megaphone',
            self::GradeCorrectionRequest => 'heroicon-o-pencil-square',
            self::GradeCorrectionRejected => 'heroicon-o-x-mark',
            self::AbsenceMotivationSubmitted => 'heroicon-o-document-check',
            self::DocumentRequestSubmitted => 'heroicon-o-inbox-arrow-down',
            self::AdmissionRequestSubmitted => 'heroicon-o-user-plus',
        };
    }

    /**
     * Tipurile de notificări RELEVANTE pentru un rol (matricea din Setări + filtrul de rutare).
     * „Director pe nișa lui, vicedirector pe a lui" — fără zgomot pentru acțiuni minore.
     *
     * @return list<self>
     */
    public static function forRole(UserRole $role): array
    {
        return match ($role) {
            // Super-admin: tot (break-glass).
            UserRole::Admin => self::cases(),

            // Familia: situația copilului.
            UserRole::Parinte, UserRole::Elev => [
                self::NewGrade,
                self::GradeAnnulled,
                self::GradeCorrected,
                self::NewAbsence,
                self::NewHomework,
                self::StatusChange,
                self::NewMessage,
                self::Announcement,
            ],

            // Diriginte: validează motivările clasei lui + primește verdictul corecțiilor cerute.
            UserRole::Diriginte => [
                self::AbsenceMotivationSubmitted,
                self::GradeCorrectionRejected,
                self::NewMessage,
                self::Announcement,
            ],

            // Profesor: comunicare + verdictul corecțiilor cerute.
            UserRole::Profesor => [
                self::GradeCorrectionRejected,
                self::NewMessage,
                self::Announcement,
            ],

            // Prim-vicedirector: aprobă corecțiile de notă.
            UserRole::PrimVicedirector => [
                self::GradeCorrectionRequest,
                self::NewMessage,
                self::Announcement,
            ],

            // Director: corecții excepționale + admiteri.
            UserRole::Director => [
                self::GradeCorrectionRequest,
                self::AdmissionRequestSubmitted,
                self::NewMessage,
                self::Announcement,
            ],

            // Administrator operațional: secretariat (admiteri + cereri tipice).
            UserRole::AdministratorOperational => [
                self::AdmissionRequestSubmitted,
                self::DocumentRequestSubmitted,
                self::NewMessage,
                self::Announcement,
            ],

            // Administrator tehnic: doar comunicare.
            UserRole::AdministratorTehnic => [
                self::NewMessage,
                self::Announcement,
            ],
        };
    }

    /**
     * Etichetele tipurilor pentru un set dat (matricea din Setări).
     *
     * @param  list<self>  $types
     * @return array<string, string>
     */
    public static function labelsFor(array $types): array
    {
        $options = [];
        foreach ($types as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::labelsFor(self::cases());
    }
}
