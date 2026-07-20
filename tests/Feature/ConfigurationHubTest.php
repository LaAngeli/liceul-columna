<?php

/**
 * HUB-ul de configurare (LOT 4 al restructurării „Configurare").
 *
 * Cele 9 secțiuni stăteau într-o listă plată, gardată de patru capabilități diferite — nimic nu
 * arăta relația dintre ele. Hub-ul le grupează pe categorii logice și afișează STAREA configurării,
 * derivând vizibilitatea EXCLUSIV din `Resource::canAccess()`: nicio matrice de roluri copiată,
 * deci nimic care să se desincronizeze tăcut de policies.
 */

use App\Enums\ConfigurationCategory;
use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Filament\Pages\ConfigurationHub;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create(['is_current' => true]);
});

function hubUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    return $user;
}

it('administratorul tehnic nu vede hub-ul deloc (nu configurează școala)', function () {
    hubUser(UserRole::AdministratorTehnic);

    expect(ConfigurationHub::canAccess())->toBeFalse();
});

it('configuratorul vede toate categoriile, cu secțiunile lor', function () {
    hubUser(UserRole::AdministratorOperational);

    expect(ConfigurationHub::canAccess())->toBeTrue();

    $categories = Livewire::test(ConfigurationHub::class)->instance()->categories();
    $keys = collect($categories)->pluck('key')->all();

    // AO: anul, orarele, corigența — și evaluarea, deschisă acum la CITIRE.
    expect($keys)->toContain(
        ConfigurationCategory::An->value,
        ConfigurationCategory::Orar->value,
        ConfigurationCategory::Evaluare->value,
        ConfigurationCategory::Corigenta->value,
    );

    // Categoria „Anul școlar" conține exact secțiunile ei, în ordinea de configurare.
    $an = collect($categories)->firstWhere('key', ConfigurationCategory::An->value);
    expect(collect($an['sections'])->count())->toBe(3);
});

it('prim-vicedirectorul vede anul, dar marcat „Doar citire" (nu e configurator)', function () {
    hubUser(UserRole::PrimVicedirector);

    $categories = Livewire::test(ConfigurationHub::class)->instance()->categories();
    $an = collect($categories)->firstWhere('key', ConfigurationCategory::An->value);

    expect($an)->not->toBeNull();

    $ani = collect($an['sections'])->first();

    expect($ani['badge'])->not->toBeNull()
        ->and($ani['badge']['label'])->toBe(__('panel.config_hub.read_only'))
        ->and($ani['badge']['color'])->toBe('gray');
});

it('profesorul vede orarul și regulile de notare, nu și configurarea propriu-zisă', function () {
    hubUser(UserRole::Profesor);

    // Are ce vedea (orarul, §3.3) → hub-ul îi apare…
    expect(ConfigurationHub::canAccess())->toBeTrue();

    $categories = collect(Livewire::test(ConfigurationHub::class)->instance()->categories());

    // …dar numai secțiuni de CONSULTARE. Regula rescrisă la LOT 8: „Evaluare" apare fiindcă pagina
    // „Reguli de notare" e deschisă personalului pedagogic — profesorul care explică o medie
    // trebuie să aibă unde se uita. Designarea sumativelor din aceeași categorie îi rămâne închisă,
    // iar anul și corigența nu apar deloc.
    expect($categories->pluck('key')->all())
        ->toBe([ConfigurationCategory::Orar->value, ConfigurationCategory::Evaluare->value]);

    $evaluare = $categories->firstWhere('key', ConfigurationCategory::Evaluare->value);

    expect(collect($evaluare['sections'])->pluck('title')->all())
        ->toBe([__('panel.grading_rules.title')]);
});

it('semnalul „de configurat" primează asupra celui de „doar citire" și numără golurile reale', function () {
    hubUser(UserRole::AdministratorOperational);

    // Un singur tip de orar publicat → restul de 8 sunt goluri reale.
    Schedule::factory()->create(['type' => ScheduleType::cases()[0], 'is_public' => true]);

    $categories = Livewire::test(ConfigurationHub::class)->instance()->categories();
    $orar = collect($categories)->firstWhere('key', ConfigurationCategory::Orar->value);
    $orare = collect($orar['sections'])->first();

    $missing = count(ScheduleType::cases()) - 1;

    expect($orare['badge']['color'])->toBe('warning')
        ->and($orare['badge']['label'])->toBe(trans_choice('panel.config_hub.needs_setup', $missing, ['count' => $missing]))
        // Eticheta e RANDATĂ, nu șirul brut de pluralizare (bug prins la verificarea live:
        // `__()` pe un format `trans_choice` afișa „[1]…|[2,*]…" direct în badge).
        ->and($orare['badge']['label'])->not->toContain('|')
        ->and($orare['badge']['label'])->toContain((string) $missing);
});

it('linkurile duc în secțiuni, iar cele pe ani aterizează în anul CURENT', function () {
    hubUser(UserRole::AdministratorOperational);

    $categories = Livewire::test(ConfigurationHub::class)->instance()->categories();
    $an = collect($categories)->firstWhere('key', ConfigurationCategory::An->value);

    // Semestrele au pastile pe ani → linkul poartă anul curent, ca să nu aterizezi în altul.
    $semestre = collect($an['sections'])->firstWhere('title', __('panel.resources.terms.label'));

    expect($semestre['url'])->toContain('an='.$this->year->id);
});

it('numărătoarea de pe card respectă PERIMETRUL utilizatorului, nu totalul școlii', function () {
    $mine = SchoolClass::factory()->for($this->year)->create();
    $theirs = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();

    foreach ([$mine, $theirs] as $class) {
        Lesson::factory()->create([
            'school_class_id' => $class->id,
            'academic_year_id' => $this->year->id,
            'subject_id' => $subject->id,
        ]);
    }

    // Profesor cu O SINGURĂ clasă în perimetru.
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $mine->id,
        'subject_id' => $subject->id,
    ]);
    actingAs($teacherUser);

    $orar = collect(Livewire::test(ConfigurationHub::class)->instance()->categories())
        ->firstWhere('key', ConfigurationCategory::Orar->value);
    $structurat = collect($orar['sections'])->firstWhere('title', __('panel.resources.lessons.label'));

    // 1, nu 2: cardul promitea altfel un total pe care, la click, utilizatorul nu-l vedea.
    expect($structurat['count'])->toBe(1);
});

it('semnalul „de configurat" nu se arată cui nu poate configura — primește starea, nu o sarcină', function () {
    // Un tip de orar publicat → există goluri reale în secțiunea Orare.
    Schedule::factory()->create(['type' => ScheduleType::cases()[0], 'is_public' => true]);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $teacherUser->id]);
    actingAs($teacherUser);

    $orar = collect(Livewire::test(ConfigurationHub::class)->instance()->categories())
        ->firstWhere('key', ConfigurationCategory::Orar->value);

    foreach ($orar['sections'] as $section) {
        expect($section['badge']['label'])->toBe(__('panel.config_hub.read_only'))
            ->and($section['badge']['color'])->toBe('gray');
    }

    // Aceleași goluri, dar pentru cine LE POATE rezolva, rămân o chemare la acțiune.
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $orarAo = collect(Livewire::test(ConfigurationHub::class)->instance()->categories())
        ->firstWhere('key', ConfigurationCategory::Orar->value);
    $orare = collect($orarAo['sections'])->firstWhere('title', __('panel.resources.schedules.label'));

    expect($orare['badge']['color'])->toBe('warning');
});

it('DISCIPLINĂ: fiecare resursă/pagină declară un grup care există în navigationGroups()', function () {
    hubUser(UserRole::Admin);

    $declared = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group): string => is_string($group) ? $group : (string) $group->getLabel())
        ->filter()
        ->values()
        ->all();

    expect($declared)->not->toBeEmpty();

    $orphans = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $group = $resource::getNavigationGroup();

        if ($group !== null && ! in_array((string) $group, $declared, true)) {
            $orphans[] = $resource.' → '.$group;
        }
    }

    foreach (Filament::getPanel('admin')->getPages() as $page) {
        $group = $page::getNavigationGroup();

        if ($group !== null && ! in_array((string) $group, $declared, true)) {
            $orphans[] = $page.' → '.$group;
        }
    }

    // Un grup nedeclarat ajunge la coada sidebar-ului, în afara ordinii gândite — tăcut.
    expect($orphans)->toBe([]);
});
