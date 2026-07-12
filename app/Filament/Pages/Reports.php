<?php

namespace App\Filament\Pages;

use App\Actions\Documents\BuildStaffReportData;
use App\Actions\Documents\RenderPdf;
use App\Actions\LogStudentAccess;
use App\Enums\StaffReportType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\ContentTranslator;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Rapoarte" — generatele de STAFF (spec §2): produse LA CERERE, per-clasă, cu scoping pe rol
 * (`prof_disc_clasa`). Profesorul alege doar clasele/disciplinele lui, dirigintele situația completă
 * a clasei lui, administrația orice. Gardul e RE-verificat pe server la generare (§1), nu doar în UI.
 *
 * @property-read Schema $form
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 20;

    // Slug RO, ca restul panoului (poșta e la /admin/mesaje).
    protected static ?string $slug = 'rapoarte';

    protected string $view = 'filament.pages.reports';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.documents');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.reports.title');
    }

    public function getTitle(): string
    {
        return __('panel.pages.reports.title');
    }

    /**
     * Vizibilă personalului academic (administrație + profesori/diriginți) — NU administratorului tehnic.
     */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('report_type')
                    ->label(__('panel.pages.reports.type'))
                    ->options($this->reportTypeOptions())
                    ->native(false)
                    ->live()
                    ->required(),
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options($this->classOptions())
                    ->searchable()
                    ->native(false)
                    ->required(),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options($this->subjectOptions())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::typeNeedsSubject($get('report_type')))
                    ->required(fn (Get $get): bool => self::typeNeedsSubject($get('report_type'))),
            ])
            ->statePath('data');
    }

    /**
     * Generează raportul ales și îl întoarce ca descărcare PDF (Livewire). Re-verifică pe server că
     * utilizatorul are dreptul la exact (clasa, disciplina) cerute — apărare la un state manipulat.
     */
    public function generate(): ?StreamedResponse
    {
        $data = $this->form->getState();
        $user = auth('web')->user();

        $type = StaffReportType::tryFrom((string) ($data['report_type'] ?? ''));

        if (! $user instanceof User || $type === null) {
            return null;
        }

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;

        if (! $type->canGenerate($user, $classId, $subjectId)) {
            Notification::make()->danger()->title(__('panel.pages.reports.forbidden'))->send();

            return null;
        }

        // Jurnalizarea accesului (L133 §7): raportul conține PII-ul elevilor clasei → fiecare elev
        // intră în jurnal ca „exported" — aliniat cu exportul din tabel și descărcările din cabinet.
        // DOAR elevii care chiar APAR în raport (activi — whereNull left_on, ca
        // BuildStaffReportData::classStudents): un „exported" fals-pozitiv pe un elev plecat ar face
        // jurnalul nefiabil exact la întrebarea L133 „cine mi-a exportat datele?".
        $log = app(LogStudentAccess::class);
        Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q
                ->where('school_class_id', $classId)
                ->whereNull('left_on'))
            ->get()
            ->each(fn (Student $s) => $log->record($s, 'exported', 'Raport staff: '.$type->getLabel()));

        // Document oficial → randare consecventă în RO (denumiri de discipline + antete).
        app()->setLocale('ro');

        $content = app(RenderPdf::class)->fromView(
            $type->blade(),
            app(BuildStaffReportData::class)->build($type, $classId, $subjectId),
        );

        // Fără asta, un browser care blochează descărcarea lasă pagina fără niciun semn că s-a
        // întâmplat ceva (audit staff, raport-generare-rapoarte-staff.md #2).
        Notification::make()->success()->title(__('panel.pages.reports.generated'))->send();

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $type->fileBase().'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array<string, string> */
    private function reportTypeOptions(): array
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return [];
        }

        $options = [];
        foreach (StaffReportType::availableFor($user) as $type) {
            $options[$type->value] = $type->getLabel();
        }

        return $options;
    }

    /** @return array<int, string> */
    private function classOptions(): array
    {
        $user = auth('web')->user();
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');

        if ($user instanceof User && ! $user->isAdministrator() && $user->teacher !== null) {
            $query->whereKey($user->teacher->visibleSchoolClassIds());
        }

        $options = [];
        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /** @return array<int, string> */
    private function subjectOptions(): array
    {
        $user = auth('web')->user();
        $query = Subject::query()->orderBy('name');

        if ($user instanceof User && ! $user->isAdministrator() && $user->teacher !== null) {
            $query->whereKey($user->teacher->taughtSubjectIds());
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    private static function typeNeedsSubject(mixed $value): bool
    {
        return is_string($value) && (StaffReportType::tryFrom($value)?->needsSubject() ?? false);
    }
}
