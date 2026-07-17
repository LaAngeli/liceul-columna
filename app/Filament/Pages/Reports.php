<?php

namespace App\Filament\Pages;

use App\Actions\Documents\BuildStaffReportData;
use App\Actions\Documents\RenderPdf;
use App\Actions\LogStudentAccess;
use App\Enums\ReportCategory;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Generare rapoarte" — RESTRUCTURAT pe limbajul navigatoarelor (cerința beneficiarului):
 * categorii logice (Elevi / Note & evaluare / Absențe / Clase / Profesori / Administrative) →
 * cardurile rapoartelor din categorie → parametrii + generare. Utilizatorul vede DOAR
 * categoriile și rapoartele pe care rolul lui le poate genera; gardul e RE-verificat pe server
 * la generare (§1), nu doar în UI.
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

    /** Categoria deschisă (contextul) — validată la citire. */
    #[Url(as: 'categorie', except: null)]
    public ?string $activeCategory = null;

    /** Raportul ales din categorie — validat la citire (categorie + disponibilitate pe rol). */
    #[Url(as: 'raport', except: null)]
    public ?string $activeReport = null;

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

    // ── Navigatorul: categorii → rapoarte → parametri ────────────────────────────────────

    public function activeCategory(): ?ReportCategory
    {
        if ($this->activeCategory === null) {
            return null;
        }

        foreach ($this->availableCategories() as $category) {
            if ($category->value === $this->activeCategory) {
                return $category;
            }
        }

        return null;
    }

    public function activeReport(): ?StaffReportType
    {
        $category = $this->activeCategory();

        if ($category === null || $this->activeReport === null) {
            return null;
        }

        foreach ($this->availableReports() as $type) {
            if ($type->value === $this->activeReport && $type->category() === $category) {
                return $type;
            }
        }

        return null;
    }

    public function openCategory(string $key): void
    {
        $this->activeCategory = ReportCategory::tryFrom($key)?->value;
        $this->activeReport = null;
    }

    public function leaveCategory(): void
    {
        $this->activeCategory = null;
        $this->activeReport = null;
    }

    public function openReport(string $type): void
    {
        $this->activeReport = StaffReportType::tryFrom($type)?->value;
        $this->form->fill();
    }

    public function leaveReport(): void
    {
        $this->activeReport = null;
    }

    /**
     * Cardurile categoriilor — doar cele în care rolul are cel puțin un raport.
     *
     * @return array<int, array{id: string, title: string, description: string, icon: string, count: int}>
     */
    public function categoryCards(): array
    {
        $reports = $this->availableReports();
        $cards = [];

        foreach ($this->availableCategories() as $category) {
            $count = count(array_filter($reports, fn (StaffReportType $type): bool => $type->category() === $category));

            $cards[] = [
                'id' => $category->value,
                'title' => $category->label(),
                'description' => $category->description(),
                'icon' => $category->icon(),
                'count' => $count,
            ];
        }

        return $cards;
    }

    /**
     * Cardurile rapoartelor din categoria deschisă.
     *
     * @return array<int, array{id: string, title: string, description: string, icon: string, format: string, active: bool}>
     */
    public function reportCards(): array
    {
        $category = $this->activeCategory();

        if ($category === null) {
            return [];
        }

        $cards = [];

        foreach ($this->availableReports() as $type) {
            if ($type->category() !== $category) {
                continue;
            }

            $cards[] = [
                'id' => $type->value,
                'title' => $type->getLabel(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'format' => $type->formatTag(),
                'active' => $this->activeReport()?->value === $type->value,
            ];
        }

        return $cards;
    }

    /** Raportul ales are nevoie de parametri (clasă/disciplină)? Fără — generarea e directă. */
    public function reportNeedsParameters(): bool
    {
        $type = $this->activeReport();

        return $type !== null && ($type->needsClass() || $type->needsSubject());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options($this->classOptions())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (): bool => $this->activeReport()?->needsClass() ?? false)
                    ->required(fn (): bool => $this->activeReport()?->needsClass() ?? false),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options($this->subjectOptions())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (): bool => $this->activeReport()?->needsSubject() ?? false)
                    ->required(fn (): bool => $this->activeReport()?->needsSubject() ?? false),
            ])
            ->statePath('data');
    }

    /**
     * Generează raportul ales și îl întoarce ca descărcare PDF (Livewire). Re-verifică pe server
     * că utilizatorul are dreptul la exact (raport, clasă, disciplină) — apărare la state manipulat.
     */
    public function generate(): ?StreamedResponse
    {
        $data = $this->form->getState();
        $user = auth('web')->user();
        $type = $this->activeReport();

        if (! $user instanceof User || $type === null) {
            return null;
        }

        $classId = $type->needsClass() && isset($data['school_class_id']) && $data['school_class_id'] !== ''
            ? (int) $data['school_class_id']
            : null;
        $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;

        if (! $type->canGenerate($user, $classId, $subjectId)) {
            Notification::make()->danger()->title(__('panel.pages.reports.forbidden'))->send();

            return null;
        }

        // Jurnalizarea accesului (L133 §7) — DOAR pentru rapoartele care numesc elevi individual
        // (lista, clasamentul, situațiile, absențele); agregatele fără nume nu exportă PII.
        if ($type->containsStudentPii() && $classId !== null) {
            $log = app(LogStudentAccess::class);
            Student::query()
                ->whereHas('enrollments', fn (Builder $q) => $q
                    ->where('school_class_id', $classId)
                    ->whereNull('left_on'))
                ->get()
                ->each(fn (Student $s) => $log->record($s, 'exported', 'Raport staff: '.$type->getLabel()));
        }

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

    /** @return list<StaffReportType> */
    private function availableReports(): array
    {
        $user = auth('web')->user();

        return $user instanceof User ? StaffReportType::availableFor($user) : [];
    }

    /** @return list<ReportCategory> */
    private function availableCategories(): array
    {
        $user = auth('web')->user();

        return $user instanceof User ? StaffReportType::categoriesFor($user) : [];
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
}
