<?php

namespace App\Support;

use App\Filament\Pages\Calendar;
use App\Filament\Pages\ClassRegister;
use App\Filament\Pages\ConfigurationHub;
use App\Filament\Pages\GradingRules;
use App\Filament\Pages\Mailbox;
use App\Filament\Pages\Reports;
use App\Filament\Pages\RestoreCenter;
use App\Filament\Pages\RoleMatrix;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\AcademicRecords\AcademicRecordResource;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Filament\Resources\CanteenMenus\CanteenMenuResource;
use App\Filament\Resources\ConsentAcknowledgments\ConsentAcknowledgmentResource;
use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use App\Filament\Resources\CorigentaSessions\CorigentaSessionResource;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use App\Filament\Resources\GradeCorrections\GradeCorrectionResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource;
use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use App\Filament\Resources\Terms\TermResource;
use App\Filament\Resources\Users\UserResource;
use Closure;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * Ghidul secțiunilor din panou: iconița „i" de lângă titlu, cu explicația la hover.
 *
 * CE explică, deliberat: nu ce se vede pe ecran (asta se vede), ci **de ce există secțiunea, ce
 * decizie susține și care e regula pe care ai greși-o altfel**. Un panou de catalog școlar e plin de
 * reguli care nu se deduc din interfață — că o notă nu se șterge niciodată, că sumativa cântărește
 * jumătate din medie, că o zi liberă schimbă termene, că absolvirea pornește un termen legal de
 * păstrare. Acelea sunt de scris; „aici vezi notele" nu ajută pe nimeni.
 *
 * CUM se leagă: un SINGUR render hook global ({@see PanelsRenderHook::PAGE_HEADER_HEADING_AFTER}).
 * Filament trimite închiderii `scopes` — pentru paginile de resursă, atât clasa paginii cât și clasa
 * RESURSEI. Cheia pe resursă acoperă deci listarea, fișa și formularele deodată, iar paginile custom
 * se cheamă pe clasa lor. Zero cablare per pagină: o secțiune nouă capătă ghid adăugând o linie aici
 * și textul în `lang/<limbă>/guide.php`.
 *
 * Textele stau în fișier de limbă, nu în cod: sunt conținut, se traduc și se corectează fără atins
 * clasa. O cheie fără traducere nu randează nimic — mai bine lipsă decât o cheie brută pe ecran.
 */
final class PanelGuide
{
    /**
     * Iconița ghidului — SURSĂ UNICĂ pentru toate cele trei niveluri (secțiune, câmp, buton).
     *
     * De aceea e o constantă și nu un SVG copiat: dacă semnul se schimbă, se schimbă peste tot
     * deodată. Trei iconițe ușor diferite pentru același gest ar învăța utilizatorul că înseamnă
     * lucruri diferite.
     */
    public const ICON = 'heroicon-o-information-circle';

    /**
     * Clasă de pagină sau de resursă → cheia din `guide.php`.
     *
     * Ordinea nu contează (căutarea e pe potrivire exactă), dar gruparea urmează meniul, ca lista să
     * se citească alături de panou.
     *
     * @var array<class-string, string>
     */
    private const MAP = [
        // ── Catalog ────────────────────────────────────────────────────────────────────────
        ClassRegister::class => 'class_register',
        GradeResource::class => 'grades',
        AbsenceResource::class => 'absences',
        HomeworkAssignmentResource::class => 'homework',
        AcademicRecordResource::class => 'academic_records',
        StudentResource::class => 'students',
        SubjectResource::class => 'subjects',
        SchoolClassResource::class => 'school_classes',
        CorigentaExamResource::class => 'corigenta_exams',

        // ── Aprobări ───────────────────────────────────────────────────────────────────────
        GradeCorrectionResource::class => 'grade_corrections',
        AbsenceMotivationResource::class => 'absence_motivations',
        HomeworkCorrectionResource::class => 'homework_corrections',

        // ── Configurare ────────────────────────────────────────────────────────────────────
        ConfigurationHub::class => 'configuration',
        AcademicYearResource::class => 'academic_years',
        TermResource::class => 'terms',
        EnrollmentResource::class => 'enrollments',
        HolidayResource::class => 'holidays',
        ScheduleResource::class => 'schedules',
        SummativeDesignationResource::class => 'summative_designations',
        GradingRules::class => 'grading_rules',
        LessonResource::class => 'lessons',
        CorigentaSessionResource::class => 'corigenta_sessions',
        ExamCommissionResource::class => 'exam_commissions',
        CanteenMenuResource::class => 'canteen_menus',

        // ── Comunicare ─────────────────────────────────────────────────────────────────────
        Mailbox::class => 'messages',
        AnnouncementResource::class => 'announcements',
        CalendarEventResource::class => 'calendar_events',
        Calendar::class => 'calendar',

        // ── Secretariat & administrare ─────────────────────────────────────────────────────
        DocumentRequestResource::class => 'document_requests',
        AdmissionRequestResource::class => 'admission_requests',
        DocumentResource::class => 'documents',
        Reports::class => 'reports',
        UserResource::class => 'users',
        RoleMatrix::class => 'role_matrix',
        AuditResource::class => 'audits',
        ConsentAcknowledgmentResource::class => 'consents',
        RestoreCenter::class => 'restore_center',
    ];

    /**
     * Iconița pentru pagina curentă, sau șir gol dacă secțiunea n-are ghid (ori textul lipsește din
     * limba activă — o cheie brută lângă titlu ar fi mai rea decât nimic).
     *
     * @param  array<int, string>  $scopes  clasele raportate de pagină (pagina + resursa ei)
     */
    public static function render(array $scopes): string
    {
        $key = self::keyFor($scopes);

        if ($key === null) {
            return '';
        }

        $text = trans('guide.'.$key);

        if (! is_string($text) || $text === '' || $text === 'guide.'.$key) {
            return '';
        }

        return (string) view('filament.guide.hint', ['text' => $text])->render();
    }

    /**
     * Textul unui ghid de CÂMP sau de BUTON, sau null dacă lipsește.
     *
     * Cheile de câmp trăiesc sub `guide.fields.*`, ca să nu se amestece cu cele de secțiune și ca
     * testul de acoperire să le poată parcurge separat. Null în loc de cheia brută: un `hintIcon`
     * care afișează „guide.fields.x" e mai rău decât un câmp fără iconiță.
     */
    public static function field(string $key): ?string
    {
        $text = trans('guide.fields.'.$key);

        return is_string($text) && $text !== '' && $text !== 'guide.fields.'.$key ? $text : null;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public static function keyFor(array $scopes): ?string
    {
        foreach ($scopes as $scope) {
            if (isset(self::MAP[$scope])) {
                return self::MAP[$scope];
            }
        }

        return null;
    }

    /**
     * Cheile înregistrate — folosite de test ca să dovedească faptul că fiecare are text în TOATE
     * limbile. Un ghid tradus pe jumătate e o secțiune care își pierde explicația exact pentru
     * utilizatorii care au mai multă nevoie de ea.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_values(array_unique(array_values(self::MAP)));
    }

    /** Zahăr pentru înregistrarea hook-ului în provider. */
    public static function hook(): Closure
    {
        return static fn (array $scopes): HtmlString => new HtmlString(self::render($scopes));
    }
}
